<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use App\Models\ApiCredential;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    /**
     * Initiate OAuth authorization flow
     */
    public function authorize(Request $request)
    {
        $tempCredentials = session('temp_credentials');
        
        if (!$tempCredentials) {
            return redirect()->route('api-credentials.index')
                ->with('error', 'No credentials found. Please add credentials first.');
        }

        // Create a temporary credential object for URL building
        $credential = new ApiCredential($tempCredentials);
        
        // Generate state parameter for security
        $state = Str::random(40);
        session(['oauth_state' => $state]);

        try {
            // Build authorization URL using temporary credentials
            $authUrl = $this->buildAuthorizationUrl($credential, $state);
            return redirect($authUrl);
        } catch (\Exception $e) {
            \Log::error('Authorization URL build failed', [
                'error' => $e->getMessage(),
                'credentials' => $tempCredentials
            ]);
            
            return redirect()->route('api-credentials.index')
                ->with('error', 'Failed to initialize OAuth: ' . $e->getMessage());
        }
    }

    /**
     * Handle OAuth callback
     */
    public function callback(Request $request)
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $tempCredentials = session('temp_credentials');

        \Log::info('OAuth callback received', [
            'has_code' => !empty($code),
            'has_state' => !empty($state),
            'has_temp_credentials' => !empty($tempCredentials)
        ]);

        if (!$code || !$state || !$tempCredentials) {
            return redirect()->route('api-credentials.index')
                ->with('error', 'Invalid callback data');
        }

        try {
            // Create the API credential record
            $apiCredential = ApiCredential::create($tempCredentials);
            
            \Log::info('API Credential created', [
                'id' => $apiCredential->id,
                'api_name' => $apiCredential->api_name
            ]);

            // Exchange code for tokens
            $tokenData = $this->exchangeCodeForToken($apiCredential, $code);
            
            // Update the credential with tokens
            $apiCredential->update([
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expiry' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
            ]);

            // Clear session data
            session()->forget(['temp_credentials', 'oauth_state']);

            return redirect()->route('api-credentials.index')
                ->with('success', 'API credentials saved successfully');

        } catch (\Exception $e) {
            \Log::error('OAuth callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (isset($apiCredential)) {
                $apiCredential->delete();
            }

            return redirect()->route('api-credentials.index')
                ->with('error', 'Failed to complete OAuth process: ' . $e->getMessage());
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
        if (empty($credential->client_id)) {
            throw new \InvalidArgumentException('Client ID is required');
        }

        // Remove trailing slashes and ensure no double slashes
        $baseUrl = rtrim($credential->base_url ?: 'https://dev-openapi-auth.meditlink.com', '/');
        
        $params = http_build_query([
            'client_id' => $credential->client_id,
            'response_type' => 'code',
            'redirect_uri' => route('oauth.callback'),
            'scope' => 'USER GROUP',
            'state' => $state
        ]);

        $finalUrl = "{$baseUrl}/oauth/authorize?{$params}";
        
        // Log the URL for debugging
        \Log::info('Authorization URL built', [
            'baseUrl' => $baseUrl,
            'finalUrl' => $finalUrl,
            'client_id' => $credential->client_id
        ]);

        return $finalUrl;
    }

    /**
     * Exchange authorization code for access token
     */
    private function exchangeCodeForToken(ApiCredential $credential, string $code): array
    {
        $baseUrl = rtrim($credential->base_url ?: 'https://stage-openapi-auth.meditlink.com', '/');
        $tokenUrl = "$baseUrl/oauth/token";

        \Log::info('Token Exchange Request Details', [
            'tokenUrl' => $tokenUrl,
            'client_id' => $credential->client_id,
            'code' => $code,
            'redirect_uri' => route('oauth.callback')
        ]);

        // Add Basic Auth header for client credentials
        $auth = base64_encode($credential->client_id . ':' . $credential->client_secret);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Basic ' . $auth,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ])
                ->withOptions([
                    'verify' => false  // Only for development
                ])
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => route('oauth.callback')
                ]);

            if (!$response->successful()) {
                \Log::error('Token Exchange Response', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                    'headers' => $response->headers()
                ]);
                throw new \Exception('Token exchange failed: ' . $response->body());
            }

            $tokenData = $response->json();
            
            \Log::info('Token Exchange Success', [
                'has_access_token' => isset($tokenData['access_token']),
                'has_refresh_token' => isset($tokenData['refresh_token']),
                'expires_in' => $tokenData['expires_in'] ?? 'not set'
            ]);

            return $tokenData;

        } catch (\Exception $e) {
            \Log::error('Token Exchange Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
