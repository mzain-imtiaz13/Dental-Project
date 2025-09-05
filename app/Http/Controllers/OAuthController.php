<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use App\Models\ApiCredential;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    /**
     * Initiate OAuth authorization flow
     */
    public function authorize(Request $request)
    {
        $apiName = $request->get('api', 'medit_link');
        $credential = ApiCredential::where('api_name', $apiName)->first();
        
        if (!$credential) {
            return redirect()->route('api-credentials.index')
                ->with('error', 'No credentials found for ' . $apiName . '. Please add credentials first.');
        }

        // Generate state parameter for security
        $state = Str::random(40);
        session(['oauth_state' => $state, 'oauth_api' => $apiName]);

        // Build authorization URL
        $authUrl = $this->buildAuthorizationUrl($credential, $state);
        
        return redirect($authUrl);
    }

    /**
     * Handle OAuth callback
     */
    public function callback(Request $request)
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $error = $request->get('error');

        // Check for errors
        if ($error) {
            return redirect()->route('api-credentials.index')
                ->with('error', 'OAuth authorization failed: ' . $error);
        }

        // Verify state parameter
        if (!$state || $state !== session('oauth_state')) {
            return redirect()->route('api-credentials.index')
                ->with('error', 'Invalid state parameter. Please try again.');
        }

        $apiName = session('oauth_api', 'medit_link');
        $credential = ApiCredential::where('api_name', $apiName)->first();

        if (!$credential) {
            return redirect()->route('api-credentials.index')
                ->with('error', 'No credentials found for ' . $apiName);
        }

        try {
            // Exchange authorization code for access token
            $tokenData = $this->exchangeCodeForToken($credential, $code);
            
            if ($tokenData) {
                // Update credential with new tokens
                $credential->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'token_expiry' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                ]);

                return redirect()->route('api-credentials.index')
                    ->with('success', 'Successfully authorized ' . $credential->api_display_name . ' API!');
            } else {
                return redirect()->route('api-credentials.index')
                    ->with('error', 'Failed to obtain access token. Please try again.');
            }
        } catch (\Exception $e) {
            return redirect()->route('api-credentials.index')
                ->with('error', 'OAuth callback error: ' . $e->getMessage());
        } finally {
            // Clean up session
            session()->forget(['oauth_state', 'oauth_api']);
        }
    }

    /**
     * Refresh access token
     */
    public function refresh(Request $request, ApiCredential $apiCredential)
    {
        if (!$apiCredential->refresh_token) {
            return response()->json([
                'success' => false,
                'message' => 'No refresh token available'
            ], 400);
        }

        try {
            $tokenData = $this->refreshAccessToken($apiCredential);
            
            if ($tokenData) {
                $apiCredential->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? $apiCredential->refresh_token,
                    'token_expiry' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Token refreshed successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to refresh token'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch data from Medit Link API
     */
    public function fetchData(Request $request, ApiCredential $apiCredential)
    {
        if (!$apiCredential->access_token) {
            return response()->json([
                'success' => false,
                'message' => 'No access token available. Please authorize first.'
            ], 400);
        }

        $dataType = $request->get('type', 'orders');
        
        try {
            $data = $this->fetchApiData($apiCredential, $dataType);
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'type' => $dataType
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build authorization URL
     */
    private function buildAuthorizationUrl(ApiCredential $credential, string $state): string
    {
        $baseUrl = $credential->base_url ?: 'https://dev-openapi-auth.meditlink.com';
        $authUrl = rtrim($baseUrl, '/') . '/oauth/authorize';
        
        $params = [
            'client_id' => $credential->client_id,
            'response_type' => 'code',
            'redirect_uri' => route('oauth.callback'),
            'scope' => 'USER GROUP',
            'state' => $state
        ];

        return $authUrl . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    private function exchangeCodeForToken(ApiCredential $credential, string $code): ?array
    {
        $baseUrl = $credential->base_url ?: 'https://dev-openapi-auth.meditlink.com';
        $tokenUrl = rtrim($baseUrl, '/') . '/oauth/token';
        
        $response = Http::timeout(30)
            ->withOptions([
                'verify' => false, // Disable SSL verification for development
            ])
            ->asForm()
            ->post($tokenUrl, [
                'grant_type' => 'authorization_code',
                'client_id' => $credential->client_id,
                'client_secret' => $credential->client_secret,
                'code' => $code,
                'redirect_uri' => route('oauth.callback'),
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Token exchange failed: ' . $response->body());
    }

    /**
     * Refresh access token
     */
    private function refreshAccessToken(ApiCredential $credential): ?array
    {
        $baseUrl = $credential->base_url ?: 'https://dev-openapi-auth.meditlink.com';
        $tokenUrl = rtrim($baseUrl, '/') . '/oauth/token';
        
        $response = Http::timeout(30)
            ->withOptions([
                'verify' => false, // Disable SSL verification for development
            ])
            ->asForm()
            ->post($tokenUrl, [
                'grant_type' => 'refresh_token',
                'client_id' => $credential->client_id,
                'client_secret' => $credential->client_secret,
                'refresh_token' => $credential->refresh_token,
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Token refresh failed: ' . $response->body());
    }

    /**
     * Fetch data from API
     */
    private function fetchApiData(ApiCredential $credential, string $type): array
    {
        $apiBaseUrl = 'https://api.meditlink.com';
        $endpoints = [
            'orders' => '/v1/orders',
            'patients' => '/v1/patients',
            'user' => '/v1/user/me',
            'groups' => '/v1/groups'
        ];

        if (!isset($endpoints[$type])) {
            throw new \Exception('Invalid data type: ' . $type);
        }

        $url = $apiBaseUrl . $endpoints[$type];
        
        $response = Http::timeout(30)
            ->withOptions([
                'verify' => false, // Disable SSL verification for development
            ])
            ->withHeaders([
                'Authorization' => 'Bearer ' . $credential->access_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])
            ->get($url);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('API request failed: ' . $response->body());
    }
}
