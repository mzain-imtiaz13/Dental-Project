<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Models\MeditOrder;
use App\Services\MeditPersistenceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        // If the browser asks for JSON, return DB data for the table.
        if ($request->expectsJson()) {
            return $this->getOrdersFromDb($request);
        }
        // Otherwise load the page (JS will call this route again with Accept: JSON)
        return view('orders');
    }

    /**
     * Return orders straight from DB (used by Orders tab).
     */
    private function getOrdersFromDb(Request $request)
    {
        $query = MeditOrder::query()
            ->with(['credential', 'case']) // eager load
            ->orderByDesc('date_created')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('buyer')) {
            $query->where('buyer_name', 'like', '%'.$request->string('buyer').'%');
        }
        if ($request->filled('seller')) {
            $query->where('seller_name', 'like', '%'.$request->string('seller').'%');
        }

        $orders = $query->get();

        $payload = $orders->map(function (MeditOrder $o) {
            $platform = $o->credential?->api_name === ApiCredential::MEDIT_LINK
                ? 'Meditlink'
                : ($o->credential?->api_display_name ?? 'Unknown');

            return [
                // columns for the table
                'id'         => (int)$o->order_number,
                'created_at' => optional($o->date_created)->toIso8601String(),
                'updated_at' => optional($o->date_updated)->toIso8601String(),
                'status'     => $o->status ?? '-',
                'patient'    => [
                    'name' => $o->case?->patient_name,
                    'code' => $o->case?->patient_code,
                ],
                'case'       => [
                    'uuid'   => $o->case_uuid,
                    'name'   => $o->case?->name,
                    'status' => $o->case?->status,
                ],
                'buyer'      => $o->buyer_name,
                'seller'     => $o->seller_name,
                'source_api' => $platform,

                // everything else you may want to show in the modal
                'details'    => [
                    'status'                => $o->status,
                    'date_created'          => optional($o->date_created)->toIso8601String(),
                    'date_updated'          => optional($o->date_updated)->toIso8601String(),
                    'date_desired_delivery' => optional($o->date_desired_delivery)->toIso8601String(),
                    'buyer' => [
                        'uuid' => $o->buyer_group_uuid,
                        'name' => $o->buyer_name,
                        'type' => $o->buyer_type,
                    ],
                    'seller' => [
                        'uuid' => $o->seller_group_uuid,
                        'name' => $o->seller_name,
                        'type' => $o->seller_type,
                    ],
                    'case' => [
                        'uuid'         => $o->case_uuid,
                        'name'         => $o->case?->name,
                        'status'       => $o->case?->status,
                        'patient_name' => $o->case?->patient_name,
                        'patient_code' => $o->case?->patient_code,
                    ],
                    'credential' => [
                        'id'  => $o->credential?->id,
                        'api' => $o->credential?->api_name,
                        'name'=> $o->credential?->api_display_name,
                    ],
                    'raw' => $o->raw,
                ],

                // keep shape your earlier code expected
                'case_info'  => ['files' => []],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'orders'      => $payload,
                'total_count' => $payload->count(),
                'api_statuses'=> ['database' => ['status' => 'success', 'message' => 'Loaded from DB']],
            ],
        ]);
    }

    /* -------------------- existing remote sync endpoints kept as-is -------------------- */

    public function byCredential(Request $request, ApiCredential $apiCredential)
    {
        if ($request->expectsJson()) {
            try {
                $c = $this->ensureValidToken($apiCredential);

                $apiBase   = $c->resourcesBase();
                $url       = $apiBase . '/v1/orders/search';
                $groupUuid = $c->additional_config['group_uuid'] ?? env('MEDIT_GROUP_UUID');

                $headers = [
                    'Authorization'         => 'Bearer ' . $c->access_token,
                    'Accept'                => 'application/json',
                    'Content-Type'          => 'application/json',
                    'x-meditlink-client-id' => $c->client_id,
                ];
                if ($groupUuid) {
                    $headers['x-meditlink-group-uuid'] = $groupUuid;
                }

                $res = Http::withOptions(['verify' => false])
                    ->withHeaders($headers)
                    ->get($url, [
                        'schema' => 'latest',
                        'size'   => (int) $request->get('size', 20),
                        'page'   => (int) $request->get('page', 0),
                        'start'  => 0,
                        'end'    => 253402300799000,
                    ]);

                if (!$res->successful()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed: '.$res->status().' '.$res->body(),
                    ], 200);
                }

                $payload = $res->json();

                // Persist pulled orders
                (new MeditPersistenceService())->upsertOrders($payload, $c);

                $content = $payload['content'] ?? [];

                return response()->json([
                    'success' => true,
                    'data' => [
                        'orders'       => $content,
                        'total_count'  => count($content),
                        'api_statuses' => [
                            $c->api_name ?? 'medit_link' => ['status' => 'success', 'message' => 'Connected'],
                        ],
                    ],
                ]);
            } catch (\Throwable $e) {
                \Log::error('Orders byCredential failed', [
                    'cred_id' => $apiCredential->id,
                    'error'   => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch orders: '.$e->getMessage(),
                ], 200);
            }
        }

        return view('orders_by_credential', ['credential' => $apiCredential]);
    }

    /* ----- helpers copied from your file (unchanged) ----- */

    private function ensureValidToken(ApiCredential $c): ApiCredential
    {
        if (!$c->token_expiry || Carbon::parse($c->token_expiry)->subMinutes(5)->isPast()) {
            if (!$c->refresh_token) {
                throw new \Exception('No refresh token available');
            }

            $resp = Http::withOptions(['verify' => false])
                ->withHeaders([
                    'Authorization' => 'Basic '.base64_encode($c->client_id.':'.$c->client_secret),
                ])->asForm()->post($c->authBase().'/oauth/token', [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $c->refresh_token,
                ]);

            if (!$resp->successful()) {
                throw new \Exception('Token refresh failed: '.$resp->body());
            }

            $tok = $resp->json();
            $c->update([
                'access_token'  => $tok['access_token'],
                'refresh_token' => $tok['refresh_token'] ?? $c->refresh_token,
                'token_expiry'  => now()->addSeconds($tok['expires_in'] ?? 3600),
            ]);
        }

        return $c;
    }
}
