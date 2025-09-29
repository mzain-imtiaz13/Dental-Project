<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Services\MeditPersistenceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return $this->getOrdersData($request);
        }
        return view('orders');
    }

    private function getOrdersData(Request $request)
    {
        $persist = new MeditPersistenceService();

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

            $orders = [];
            $apiStatuses = [];

            foreach ($creds as $c) {
                try {
                    $c = $this->ensureValidToken($c);

                    $apiBase   = $c->resourcesBase();
                    // Use /v1/orders/search for paging symmetry; /v1/orders also works
                    $url       = $apiBase . '/v1/orders/search';
                    $groupUuid = $this->resolveGroupUuid($c);

                    $headers = [
                        'Authorization'         => 'Bearer ' . $c->access_token,
                        'Accept'                => 'application/json',
                        'Content-Type'          => 'application/json',
                        'x-meditlink-client-id' => $c->client_id,
                    ];
                    if (!empty($groupUuid)) {
                        $headers['x-meditlink-group-uuid'] = $groupUuid;
                    }

                    $query = [
                        'schema' => 'latest',
                        'size'   => (int) $request->get('size', 20),
                        'page'   => (int) $request->get('page', 0),
                        'start'  => 0,
                        'end'    => 253402300799000,
                    ];

                    $res = Http::withOptions(['verify' => false])
                        ->withHeaders($headers)
                        ->get($url, $query);

                    if ($res->successful()) {
                        $payload = $res->json();
                        $content = $payload['content'] ?? [];
                        $orders  = array_merge($orders, $content);
                        $apiStatuses[$c->api_name] = ['status' => 'success', 'message' => 'Connected'];

                        // ⬇️ Persist to DB
                        $persist->upsertOrders($payload, $c);
                    } else {
                        $apiStatuses[$c->api_name] = [
                            'status'  => 'error',
                            'message' => 'HTTP '.$res->status().' '.$res->body(),
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::error('Order API fetch failed', [
                        'api'   => $c->api_name,
                        'error' => $e->getMessage(),
                    ]);
                    $apiStatuses[$c->api_name] = ['status' => 'error', 'message' => 'Exception: '.$e->getMessage()];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'orders'      => $orders,
                    'total_count' => count($orders),
                    'api_statuses'=> $apiStatuses,
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Orders fetch failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch orders'
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

    private function resolveGroupUuid(ApiCredential $c): ?string
    {
        $fromConfig = $c->additional_config['group_uuid'] ?? null;
        if (!empty($fromConfig)) return $fromConfig;

        $envUuid = env('MEDIT_GROUP_UUID');
        if (!empty($envUuid)) {
            $this->cacheGroupUuid($c, $envUuid);
            return $envUuid;
        }

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
            Log::warning('Failed to auto-resolve group uuid (orders)', ['error' => $e->getMessage()]);
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
                $orders  = $payload['content'] ?? [];

                // Persist
                (new MeditPersistenceService())->upsertOrders($payload, $c);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'orders'       => $orders,
                        'total_count'  => count($orders),
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
}
