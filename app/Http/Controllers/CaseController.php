<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaseController extends Controller
{
    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return $this->getCasesData($request);
        }
        return view('cases');
    }

    private function getCasesData(Request $request)
    {
        try {
            $creds = ApiCredential::where('is_active', true)
                ->whereNotNull('access_token')
                ->get();

            if ($creds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active API credentials found'
                ], 404);
            }

            $cases = [];
            $apiStatuses = [];

            foreach ($creds as $c) {
                try {
                    $c = $this->ensureValidToken($c);

                    $apiBase   = $c->resourcesBase();
                    $url       = $apiBase . '/v1/cases/search';
                    $groupUuid = $this->resolveGroupUuid($c); // ← REQUIRED like your Postman

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
                            'page'   => (int) $request->get('page', 0), // 0-based (Postman uses 0)
                            'start'  => 0,
                            'end'    => 253402300799000,
                        ]);

                    if ($res->successful()) {
                        $payload = $res->json();
                        $cases   = array_merge($cases, $payload['content'] ?? []);
                        $apiStatuses[$c->api_name] = ['status' => 'success', 'message' => 'Connected'];
                    } else {
                        $apiStatuses[$c->api_name] = [
                            'status'  => 'error',
                            'message' => 'HTTP '.$res->status().' '.$res->body(),
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::error('Case API fetch failed', [
                        'api'   => $c->api_name,
                        'error' => $e->getMessage(),
                    ]);
                    $apiStatuses[$c->api_name] = ['status' => 'error', 'message' => 'Exception: '.$e->getMessage()];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'cases'       => $cases,
                    'total_count' => count($cases),
                    'api_statuses'=> $apiStatuses,
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Cases fetch failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch cases'
            ], 500);
        }
    }

    private function ensureValidToken(ApiCredential $c): ApiCredential
    {
        if (!$c->token_expiry || Carbon::parse($c->token_expiry)->subMinutes(5)->isPast()) {
            if (!$c->refresh_token) {
                throw new \Exception('No refresh token available');
            }

            $resp = Http::withOptions(['verify' => false])
                ->withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($c->client_id . ':' . $c->client_secret),
                ])
                ->asForm()
                ->post($c->authBase() . '/oauth/token', [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $c->refresh_token,
                ]);

            if (!$resp->successful()) {
                throw new \Exception('Token refresh failed: ' . $resp->body());
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

    /**
     * Get group UUID to mirror your Postman header.
     * Priority:
     *   1) $credential->additional_config['group_uuid']
     *   2) env('MEDIT_GROUP_UUID')
     *   3) GET /v1/groups (first group) — and cache it into additional_config
     */
    private function resolveGroupUuid(ApiCredential $c): ?string
    {
        $fromConfig = $c->additional_config['group_uuid'] ?? null;
        if (!empty($fromConfig)) return $fromConfig;

        $envUuid = env('MEDIT_GROUP_UUID');
        if (!empty($envUuid)) {
            // cache it for next time
            $this->cacheGroupUuid($c, $envUuid);
            return $envUuid;
        }

        // Fallback: query groups once, cache
        try {
            $res = Http::withOptions(['verify' => false])
                ->withHeaders([
                    'Authorization'         => 'Bearer ' . $c->access_token,
                    'Accept'                => 'application/json',
                    'Content-Type'          => 'application/json',
                    'x-meditlink-client-id' => $c->client_id,
                ])->get($c->resourcesBase().'/v1/groups');

            if ($res->successful()) {
                $data = $res->json();
                $uuid = is_array($data) && isset($data[0]['uuid']) ? $data[0]['uuid'] : null;
                if ($uuid) {
                    $this->cacheGroupUuid($c, $uuid);
                    return $uuid;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to auto-resolve group uuid', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function cacheGroupUuid(ApiCredential $c, string $uuid): void
    {
        $cfg = $c->additional_config ?? [];
        $cfg['group_uuid'] = $uuid;
        $c->additional_config = $cfg;
        $c->save();
    }

    public function byCredential(Request $request, ApiCredential $apiCredential)
{
    // JSON request? return data
    if ($request->expectsJson()) {
        try {
            $c = $this->ensureValidToken($apiCredential);

            $apiBase   = $c->resourcesBase();
            $url       = $apiBase . '/v1/cases/search';
            $groupUuid = $this->resolveGroupUuid($c);

            $headers = [
                'Authorization'         => 'Bearer ' . $c->access_token,
                'Accept'                => 'application/json',
                'Content-Type'          => 'application/json',
                'x-meditlink-client-id' => $c->client_id,
            ];
            if ($groupUuid) {
                $headers['x-meditlink-group-uuid'] = $groupUuid;
            }

            $res = \Illuminate\Support\Facades\Http::withOptions(['verify' => false])
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
            $cases   = $payload['content'] ?? [];

            return response()->json([
                'success' => true,
                'data' => [
                    'cases'        => $cases,
                    'total_count'  => count($cases),
                    'api_statuses' => [
                        $c->api_name ?? 'medit_link' => ['status' => 'success', 'message' => 'Connected'],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Cases byCredential failed', [
                'cred_id' => $apiCredential->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch cases: '.$e->getMessage(),
            ], 200);
        }
    }

    // Otherwise show the page
    return view('cases_by_credential', ['credential' => $apiCredential]);
}

}
