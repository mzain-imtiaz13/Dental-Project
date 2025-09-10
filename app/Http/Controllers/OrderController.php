<?php
namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Services\ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        // If requesting JSON, return API data
        if ($request->expectsJson()) {
            return $this->getOrdersData($request);
        }

        // Otherwise return the view
        return view('orders');
    }

    private function getOrdersData(Request $request)
    {
        try {
            $activeCredentials = ApiCredential::where('is_active', true)
                ->whereNotNull('access_token')
                ->get();

            if ($activeCredentials->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active API credentials found'
                ], 404);
            }

            $allOrders = [];
            $apiStatuses = [];

            foreach ($activeCredentials as $credential) {
                try {
                    // Ensure valid token before making API call
                    $credential = $this->ensureValidToken($credential);
                    
                    $apiService = new ApiService($credential);
                    $result = $apiService->getOrders([
                        'page' => $request->get('page', 1),
                        'per_page' => $request->get('per_page', 10)
                    ]);
                    
                    $allOrders = array_merge($allOrders, $result['data'] ?? []);
                    $apiStatuses[$credential->api_name] = [
                        'status' => 'success',
                        'message' => 'Connected'
                    ];
                } catch (\Exception $e) {
                    Log::error('API fetch failed', [
                        'api' => $credential->api_name,
                        'error' => $e->getMessage()
                    ]);
                    
                    $apiStatuses[$credential->api_name] = [
                        'status' => 'info',
                        'message' => 'No data found'
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'orders' => $allOrders,
                    'total_count' => count($allOrders),
                    'api_statuses' => $apiStatuses
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Orders fetch failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch orders at this time'
            ], 500);
        }
    }

    private function ensureValidToken(ApiCredential $credential)
    {
        // Check if token is expired or will expire in next 5 minutes
        if (!$credential->token_expiry || Carbon::parse($credential->token_expiry)->subMinutes(5)->isPast()) {
            if (!$credential->refresh_token) {
                throw new \Exception('No refresh token available');
            }

            try {
                $response = Http::withOptions([
                    'verify' => false
                ])
                ->asForm()
                ->post($credential->base_url . '/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => decrypt($credential->refresh_token),
                    'client_id' => $credential->client_id,
                    'client_secret' => $credential->client_secret
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Token refresh failed: ' . $response->body());
                }

                $tokenData = $response->json();

                // Update credentials in database
                $credential->update([
                    'access_token' => encrypt($tokenData['access_token']),
                    'refresh_token' => isset($tokenData['refresh_token']) ? encrypt($tokenData['refresh_token']) : $credential->refresh_token,
                    'token_expiry' => now()->addSeconds($tokenData['expires_in'] ?? 3600)
                ]);

                Log::info('Token refreshed successfully', [
                    'api' => $credential->api_name
                ]);
            } catch (\Exception $e) {
                Log::error('Token refresh failed', [
                    'api' => $credential->api_name,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        return $credential;
    }
}