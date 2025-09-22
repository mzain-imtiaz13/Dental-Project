<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

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
        try {
            $creds = ApiCredential::where('is_active', true)->whereNotNull('access_token')->get();
            if ($creds->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No active API credentials found'], 404);
            }

            $orders = [];
            $apiStatuses = [];

            foreach ($creds as $c) {
                try {
                    $c = $this->ensureValidToken($c);

                    $apiBase = $c->resourcesBase();
                    $res = Http::withOptions(['verify' => false])
                        ->withHeaders([
                            'Authorization'         => 'Bearer '.$c->access_token,
                            'Accept'                => 'application/json',
                            'x-meditlink-client-id' => $c->client_id,
                        ])->get($apiBase.'/v1/orders', [
                            'page' => $request->get('page', 1),
                            'size' => $request->get('per_page', 10),
                        ]);

                    if ($res->successful()) {
                        $payload = $res->json();
                        $orders = array_merge($orders, is_array($payload) ? $payload : ($payload['data'] ?? []));
                        $apiStatuses[$c->api_name] = ['status' => 'success', 'message' => 'Connected'];
                    } else {
                        throw new \Exception($res->body());
                    }
                } catch (\Exception $e) {
                    Log::error('API fetch failed', ['api' => $c->api_name, 'error' => $e->getMessage()]);
                    $apiStatuses[$c->api_name] = ['status' => 'info', 'message' => 'No data found'];
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
        } catch (\Exception $e) {
            Log::error('Orders fetch failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Unable to fetch orders at this time'], 500);
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
}
