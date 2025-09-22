<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class ApiCredentialController extends Controller
{
    public function index()
    {
        $credentials = ApiCredential::all();
        return view('api-credentials.index', ['credentials' => $credentials]);
    }

    public function create(Request $request)
    {
        $apiName  = $request->get('api', 'medit_link');
        $apiNames = ApiCredential::getApiNames();
        return view('api-credentials.create', compact('apiName', 'apiNames'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'api_name'      => 'required|string',
            'client_id'     => 'required|string',
            'client_secret' => 'required|string',
            'base_url'      => 'nullable|url', // AUTH base
            'is_active'     => 'boolean',
        ]);

        // default AUTH base (stage)
        $validated['base_url'] = $validated['base_url'] ?: 'https://stage-openapi-auth.meditlink.com';
        $validated['is_active'] = $validated['is_active'] ?? true;

        session(['temp_credentials' => $validated]);

        if ($request->expectsJson()) {
            return response()->json([
                'success'      => true,
                'redirect_url' => route('oauth.authorize'),
            ]);
        }

        return redirect()->route('oauth.authorize');
    }

    public function show(ApiCredential $apiCredential)
    {
        return view('api-credentials.show', compact('apiCredential'));
    }

    public function edit(ApiCredential $apiCredential)
    {
        $apiNames = ApiCredential::getApiNames();
        return view('api-credentials.edit', compact('apiCredential', 'apiNames'));
    }

    public function update(Request $request, ApiCredential $apiCredential)
    {
        $validator = Validator::make($request->all(), [
            'api_name'          => 'required|string|in:medit_link,ds_core,3shape',
            'client_id'         => 'required|string|max:255',
            'client_secret'     => 'required|string',
            'base_url'          => 'nullable|url', // AUTH base
            'additional_config' => 'nullable|json',
            'is_active'         => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();
        $data['is_active'] = $request->has('is_active');

        $apiCredential->update($data);

        return redirect()->route('api-credentials.index')
            ->with('success', 'API credentials updated successfully.');
    }

    public function destroy(ApiCredential $apiCredential)
    {
        $apiCredential->delete();
        return redirect()->route('api-credentials.index')
            ->with('success', 'API credentials deleted successfully.');
    }

    public function toggle(ApiCredential $apiCredential)
    {
        $apiCredential->update(['is_active' => !$apiCredential->is_active]);
        $status = $apiCredential->is_active ? 'activated' : 'deactivated';
        return redirect()->route('api-credentials.index')
            ->with('success', "API credentials {$status} successfully.");
    }

    public function test(ApiCredential $apiCredential)
    {
        try {
            $results = $this->performApiTest($apiCredential);

            return response()->json([
                'success' => true,
                'message' => 'API test completed successfully',
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'API test error: '.$e->getMessage(),
                'results' => [],
            ], 500);
        }
    }

    private function performApiTest(ApiCredential $credential): array
    {
        $results = [
            'credential_format' => $this->testCredentialFormat($credential),
            'oauth_endpoint'    => $this->testOAuthEndpoint($credential),
            'api_connectivity'  => $this->testApiConnectivity($credential),
            'success'           => true,
        ];

        $results['success'] = $results['credential_format']['valid'] && $results['oauth_endpoint']['accessible'];
        return $results;
    }

    private function testCredentialFormat(ApiCredential $c): array
    {
        $errors = [];
        if (empty($c->client_id)     || strlen($c->client_id) < 10)     $errors[] = 'Invalid Client ID';
        if (empty($c->client_secret) || strlen($c->client_secret) < 10) $errors[] = 'Invalid Client Secret';
        if ($c->base_url && !filter_var($c->base_url, FILTER_VALIDATE_URL)) $errors[] = 'Invalid Base URL';
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    private function testOAuthEndpoint(ApiCredential $c): array
    {
        $authBase = rtrim($c->base_url ?: config('meditlink.auth_base'), '/');
        $tokenUrl = $authBase.'/oauth/token';

        $response = Http::timeout(30)->withOptions(['verify' => false])->post($tokenUrl, []);
        return [
            'accessible' => in_array($response->status(), [200, 400, 401, 403]),
            'status'     => $response->status(),
            'response'   => $response->json() ?? ['status' => $response->status(), 'error' => $response->reason(), 'path' => '/oauth/token'],
            'note'       => null,
        ];
    }

    /** Convert AUTH base → RESOURCES base */
    private function resourceBaseFromAuth(?string $authBase): string
    {
        $auth = rtrim($authBase ?: config('meditlink.auth_base'), '/');
        return str_replace('-auth', '-resources', $auth);
    }

    private function testApiConnectivity(ApiCredential $c): array
    {
        $apiBase = $this->resourceBaseFromAuth($c->base_url);
        $url     = $apiBase.'/v1/me';    // <— FIXED

        try {
            $res = Http::timeout(30)
                ->withOptions(['verify' => false])
                ->withHeaders([
                    'Authorization'          => 'Bearer '.$c->access_token,
                    'Accept'                 => 'application/json',
                    'Content-Type'           => 'application/json',
                    'x-meditlink-client-id'  => $c->client_id,
                ])->get($url);

            return [
                'successful' => $res->successful(),
                'status'     => $res->status(),
                'response'   => $res->json() ?? $res->body(),
                'error'      => $res->successful() ? null : ($res->json() ?? $res->body()),
            ];
        } catch (\Exception $e) {
            return ['successful' => false, 'status' => 0, 'response' => null, 'error' => $e->getMessage()];
        }
    }
}
