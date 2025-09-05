<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiCredentialController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $credentials = ApiCredential::orderBy('api_name')->orderBy('created_at', 'desc')->get();
        $apiNames = ApiCredential::getApiNames();
        
        return view('api-credentials.index', compact('credentials', 'apiNames'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $apiName = $request->get('api', 'medit_link');
        $apiNames = ApiCredential::getApiNames();
        
        return view('api-credentials.create', compact('apiName', 'apiNames'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_name' => 'required|string|in:medit_link,ds_core,3shape',
            'client_id' => 'required|string|max:255',
            'client_secret' => 'required|string',
            'base_url' => 'nullable|url',
            'additional_config' => 'nullable|json',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $data = $request->all();
            $data['is_active'] = $request->has('is_active');
            
            // Parse additional_config if it's a string
            if (isset($data['additional_config']) && is_string($data['additional_config'])) {
                $data['additional_config'] = json_decode($data['additional_config'], true);
            }

            $credential = ApiCredential::create($data);

            return redirect()->route('api-credentials.index')
                ->with('success', 'API credentials created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to save credentials: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ApiCredential $apiCredential)
    {
        return view('api-credentials.show', compact('apiCredential'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ApiCredential $apiCredential)
    {
        $apiNames = ApiCredential::getApiNames();
        
        return view('api-credentials.edit', compact('apiCredential', 'apiNames'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ApiCredential $apiCredential)
    {
        $validator = Validator::make($request->all(), [
            'api_name' => 'required|string|in:medit_link,ds_core,3shape',
            'client_id' => 'required|string|max:255',
            'client_secret' => 'required|string',
            'base_url' => 'nullable|url',
            'additional_config' => 'nullable|json',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->all();
        $data['is_active'] = $request->has('is_active');

        $apiCredential->update($data);

        return redirect()->route('api-credentials.index')
            ->with('success', 'API credentials updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ApiCredential $apiCredential)
    {
        $apiCredential->delete();

        return redirect()->route('api-credentials.index')
            ->with('success', 'API credentials deleted successfully.');
    }

    /**
     * Toggle active status
     */
    public function toggle(ApiCredential $apiCredential)
    {
        $apiCredential->update(['is_active' => !$apiCredential->is_active]);
        
        $status = $apiCredential->is_active ? 'activated' : 'deactivated';
        
        return redirect()->route('api-credentials.index')
            ->with('success', "API credentials {$status} successfully.");
    }

    /**
     * Test API credentials connectivity
     */
    public function test(ApiCredential $apiCredential)
    {
        try {
            $results = $this->performApiTest($apiCredential);
            
            if ($results['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'API test completed successfully',
                    'results' => $results
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'API test failed',
                    'results' => $results
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'API test error: ' . $e->getMessage(),
                'results' => []
            ], 500);
        }
    }

    /**
     * Perform the actual API test
     */
    private function performApiTest($credential)
    {
        $results = [
            'credential_format' => $this->testCredentialFormat($credential),
            'oauth_endpoint' => $this->testOAuthEndpoint($credential),
            'api_connectivity' => null
        ];

        // Test API connectivity if we have an access token
        if ($credential->access_token) {
            $results['api_connectivity'] = $this->testApiConnectivity($credential);
        }

        // Determine overall success
        $results['success'] = $results['credential_format']['valid'] && 
                             $results['oauth_endpoint']['accessible'];

        return $results;
    }

    /**
     * Test credential format
     */
    private function testCredentialFormat($credential)
    {
        $errors = [];
        
        if (empty($credential->client_id)) {
            $errors[] = 'Client ID is empty';
        } elseif (strlen($credential->client_id) < 10) {
            $errors[] = 'Client ID seems too short';
        }

        if (empty($credential->client_secret)) {
            $errors[] = 'Client Secret is empty';
        } elseif (strlen($credential->client_secret) < 10) {
            $errors[] = 'Client Secret seems too short';
        }

        if ($credential->base_url && !filter_var($credential->base_url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Base URL format is invalid';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Test OAuth endpoint
     */
    private function testOAuthEndpoint($credential)
    {
        $baseUrl = $credential->base_url ?: 'https://openapi-auth.meditlink.com';
        $tokenUrl = rtrim($baseUrl, '/') . '/oauth/token';
        
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for development
                ])
                ->post($tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $credential->client_id,
                    'client_secret' => $credential->client_secret,
                    'scope' => 'read'
                ]);

            return [
                'accessible' => true,
                'status' => $response->status(),
                'response' => $response->json(),
                'note' => $response->status() === 400 ? 'Expected - Medit Link uses Authorization Code flow' : null
            ];
        } catch (\Exception $e) {
            return [
                'accessible' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test API connectivity
     */
    private function testApiConnectivity($credential)
    {
        $apiBaseUrl = 'https://api.meditlink.com';
        $userEndpoint = $apiBaseUrl . '/v1/user/me';
        
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for development
                ])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $credential->access_token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])
                ->get($userEndpoint);

            return [
                'successful' => $response->successful(),
                'status' => $response->status(),
                'response' => $response->json(),
                'error' => $response->successful() ? null : $response->body()
            ];
        } catch (\Exception $e) {
            return [
                'successful' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
