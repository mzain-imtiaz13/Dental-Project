<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Models\ThreeShapeCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ThreeShapeCaseController extends Controller
{
    /**
     * Page with the standalone 3Shape table (debug view)
     */
    public function index()
    {
        return view('three_shape.cases');
    }

    /**
     * Return minimal 3Shape cases from local DB (used by /threeshape/cases view)
     */
    public function list(Request $request)
    {
        $q = ThreeShapeCase::query();

        if ($p = $request->get('patient')) {
            $q->where('patient_name', 'like', "%{$p}%");
        }
        if ($s = $request->get('state')) {
            $q->where('state', 'like', "%{$s}%");
        }

        $cases = $q->orderByDesc('created_at_3s')
            ->take(500)
            ->get()
            ->map(function ($c) {
                return [
                    'id'            => $c->external_id,
                    'patient_name'  => $c->patient_name,
                    'state'         => $c->state,
                    'created_at_3s' => optional($c->created_at_3s)->toIso8601String(),
                    'delivery_date' => optional($c->delivery_date)->toIso8601String(),
                ];
            });

        return response()->json(['cases' => $cases]);
    }

    /**
     * Sync from 3Shape API -> upsert into local DB
     */
    public function sync()
    {
        // grab latest active 3shape credential with token
        $cred = ApiCredential::where('api_name', ApiCredential::THREESHAPE)
            ->whereNotNull('access_token')
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$cred) {
            return response()->json([
                'success' => false,
                'message' => 'No active 3Shape credential with access token.',
            ], 200);
        }

        // hit /api/cases?page=0 (the same endpoint you tested in Postman)
        $endpoint = rtrim($cred->threeShapeResourceBase(), '/') . '/api/cases?page=0';

        $res = Http::timeout(30)
            ->withOptions(['verify' => false])
            ->withHeaders([
                'Authorization' => 'Bearer ' . $cred->access_token,
                'Accept'        => 'application/json',
            ])
            ->get($endpoint);

        if (!$res->successful()) {
            return response()->json([
                'success' => false,
                'message' => '3Shape fetch failed: '.$res->status().' '.$res->body(),
            ], 200);
        }

        $json = $res->json();

        // Some 3Shape responses wrap in "Cases": [...],
        // some wrap in "items": [...]. We'll support both.
        $remoteCases = $json['Cases'] ?? $json['cases'] ?? $json['items'] ?? [];

        foreach ($remoteCases as $rc) {
            // Map 3Shape -> DB columns for list view
            $externalId     = $rc['Id']               ?? $rc['id']               ?? null;
            $patientName    = $rc['PatientName']      ?? ($rc['Patient']['Name'] ?? null ?? $rc['patient']['name'] ?? null);
            $state          = $rc['State']            ?? $rc['state']            ?? null;
            $createdIso     = $rc['Created']          ?? $rc['created']          ?? null;
            $deliveryIso    = $rc['DeliveryDate']     ?? $rc['deliveryDate']     ?? null;

            $createdAt3s = $createdIso    ? Carbon::parse($createdIso)    : null;
            $deliveryAt  = $deliveryIso   ? Carbon::parse($deliveryIso)   : null;

            // upsert to local table
            ThreeShapeCase::updateOrCreate(
                [
                    'api_credential_id' => $cred->id,
                    'external_id'       => $externalId,
                ],
                [
                    'patient_name'   => $patientName,
                    'state'          => $state,
                    'created_at_3s'  => $createdAt3s,
                    'delivery_date'  => $deliveryAt,
                    'raw'            => $rc, // store full blob for debug
                ]
            );
        }

        return response()->json([
            'success' => true,
            'count'   => count($remoteCases),
        ]);
    }

    /**
     * NEW: return full rich case detail for a given UUID.
     * Used by "View" button in /cases.
     */
    public function detail(string $uuid)
    {
        // 1. Try DB first (maybe we already synced and stored raw JSON)
        $stored = ThreeShapeCase::where('external_id', $uuid)->latest()->first();

        // 2. Also try live fetch from 3Shape (fresh data)
        $cred = ApiCredential::where('api_name', ApiCredential::THREESHAPE)
            ->whereNotNull('access_token')
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$cred) {
            // No 3Shape token available. If we have DB raw, return that, else fail.
            if ($stored && $stored->raw) {
                return response()->json([
                    'success' => true,
                    'source'  => 'db',
                    'case'    => $stored->raw,
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'No 3Shape credential with token found.',
            ], 200);
        }

        // call 3Shape detail endpoint: /api/cases/{uuid}
        $endpoint = rtrim($cred->threeShapeResourceBase(), '/') . '/api/cases/' . $uuid;

        $res = Http::timeout(30)
            ->withOptions(['verify' => false])
            ->withHeaders([
                'Authorization' => 'Bearer ' . $cred->access_token,
                'Accept'        => 'application/json',
            ])
            ->get($endpoint);

        if ($res->status() === 401) {
            // token expired
            // fallback to DB raw if we have it
            if ($stored && $stored->raw) {
                return response()->json([
                    'success' => true,
                    'source'  => 'db-expired-token',
                    'case'    => $stored->raw,
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized/expired token from 3Shape',
            ], 200);
        }

        if (!$res->successful()) {
            // again fallback to DB raw if we have it
            if ($stored && $stored->raw) {
                return response()->json([
                    'success' => true,
                    'source'  => 'db-fallback',
                    'case'    => $stored->raw,
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => '3Shape case fetch failed: '.$res->status().' '.$res->body(),
            ], 200);
        }

        $body = $res->json();

        // Some envs return a single object,
        // Some return { "Cases": [ { ... } ] }
        if (isset($body['Cases']) && is_array($body['Cases']) && count($body['Cases']) > 0) {
            $body = $body['Cases'][0];
        }

        // Update DB raw blob so we persist the full copy
        if ($stored) {
            $stored->raw = $body;
            $stored->save();
        } else {
            ThreeShapeCase::create([
                'api_credential_id' => $cred->id,
                'external_id'       => $uuid,
                'patient_name'      => $body['PatientName'] ?? ($body['Patient']['FirstName'] ?? '').' '.($body['Patient']['LastName'] ?? ''),
                'state'             => $body['State'] ?? null,
                'created_at_3s'     => isset($body['Created']) ? Carbon::parse($body['Created']) : null,
                'delivery_date'     => isset($body['DeliveryDate']) ? Carbon::parse($body['DeliveryDate']) : null,
                'raw'               => $body,
            ]);
        }

        return response()->json([
            'success' => true,
            'source'  => 'live',
            'case'    => $body,
        ], 200);
    }

     public function proxyFile(Request $request)
    {
        $href = $request->query('href');

        if (!$href) {
            abort(400, 'Missing href parameter');
        }

        // Get latest active 3Shape credential with a valid access token
        $cred = ApiCredential::where('api_name', ApiCredential::THREESHAPE)
            ->whereNotNull('access_token')
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$cred) {
            abort(500, 'No active 3Shape credential with access token found.');
        }

        try {
            $res = Http::timeout(60)
                ->withOptions(['verify' => false])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $cred->access_token,
                    'Accept'        => '*/*',
                ])
                ->get($href);

            // If token is bad/expired, pass the status/body back (user will see error)
            if (!$res->successful()) {
                return response($res->body(), $res->status())
                    ->header('Content-Type', $res->header('Content-Type') ?? 'application/json');
            }

            $contentType   = $res->header('Content-Type', 'application/octet-stream');
            $disposition   = $res->header('Content-Disposition');
            $contentLength = $res->header('Content-Length');

            $response = response($res->body(), 200)
                ->header('Content-Type', $contentType);

            if ($disposition) {
                $response->header('Content-Disposition', $disposition);
            }

            if ($contentLength) {
                $response->header('Content-Length', $contentLength);
            }

            return $response;
        } catch (\Throwable $e) {
            Log::error('3Shape file proxy failed', [
                'href'  => $href,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Unable to download 3Shape file.');
        }
    }

    /**
     * OPTIONAL: refresh route (already referenced in routes)
     * If you already implemented refresh() elsewhere in ThreeShapeOAuthController
     * you can remove this here.
     */
}
