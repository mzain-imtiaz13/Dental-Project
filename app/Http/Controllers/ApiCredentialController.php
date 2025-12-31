<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Services\DScoreService;
use App\Services\MeditPersistenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiCredentialController extends Controller
{
    public function index()
    {
        $credentials = ApiCredential::orderByDesc('id')->get();
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
        $request->validate([
            'api_name'      => 'required|string|in:medit_link,ds_core,3shape',
            'client_id'     => 'required|string',
            'client_secret' => 'nullable|string',
            'base_url'      => 'nullable|url',
            'resource_base' => 'nullable|url',
            'is_active'     => 'boolean',
        ]);

        $api = $request->string('api_name')->toString();

        if ($api === ApiCredential::THREESHAPE) {
            session(['three_shape_temp' => [
                'api_name'  => ApiCredential::THREESHAPE,
                'client_id' => $request->string('client_id')->toString(),
                'base_url'  => $request->input('base_url'),
                'is_active' => $request->boolean('is_active', true),
                'additional_config' => [
                    'resource_base' => $request->input('resource_base'),
                ],
            ]]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success'      => true,
                    'redirect_url' => route('oauth.3shape.start'),
                ]);
            }
            return redirect()->route('oauth.3shape.start');
        }

        // Medit / DS Core must have secret
        $request->validate(['client_secret' => 'required|string']);

        $defaultBase = $api === ApiCredential::DS_CORE
            ? config('dscore.auth_host')
            : config('meditlink.auth_base');

        $payload = [
            'api_name'      => $api,
            'client_id'     => $request->string('client_id')->toString(),
            'client_secret' => $request->string('client_secret')->toString(),
            'base_url'      => $request->input('base_url') ?: $defaultBase,
            'is_active'     => $request->boolean('is_active', true),
        ];

        // Remember which API flow we are about to start (Medit vs DS Core)
        session([
            'temp_credentials' => $payload,
            'oauth_api'        => $api,
        ]);

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
            'client_secret'     => 'nullable|string',
            'base_url'          => 'nullable|url',
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
        return redirect()->route('api-credentials.index')->with('success', 'Deleted.');
    }

    public function toggle(ApiCredential $apiCredential)
    {
        $apiCredential->update(['is_active' => !$apiCredential->is_active]);
        return redirect()->route('api-credentials.index')->with('success', 'Toggled.');
    }

    /**
     * THIS is the important one.
     * After we run connectivity test, if it's Medit and /v1/me succeeded,
     * we persist that profile so /profiles will have data.
     */
    public function test(ApiCredential $apiCredential)
    {
        try {
            $results = $this->performApiTest($apiCredential);

            // If it's Medit link and we got a valid /v1/me payload, upsert profile/group.
            if (
                $apiCredential->api_name === ApiCredential::MEDIT_LINK &&
                isset($results['api_connectivity']['successful']) &&
                $results['api_connectivity']['successful'] === true &&
                !empty($results['api_connectivity']['response']) &&
                is_array($results['api_connectivity']['response'])
            ) {
                try {
                    (new MeditPersistenceService())->upsertConnectivity(
                        $results['api_connectivity']['response'],
                        $apiCredential
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to persist medit profile from test()', [
                        'cred_id' => $apiCredential->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

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
        return [
            'credential_format' => $this->testCredentialFormat($credential),
            'oauth_endpoint'    => $this->testOAuthEndpoint($credential),
            'api_connectivity'  => $this->testApiConnectivity($credential),
            'success'           => true,
        ];
    }

    private function testCredentialFormat(ApiCredential $c): array
    {
        $errors = [];
        if (empty($c->client_id) || strlen($c->client_id) < 3) {
            $errors[] = 'Invalid Client ID';
        }
        if ($c->api_name !== ApiCredential::THREESHAPE) {
            if (empty($c->client_secret) || strlen($c->client_secret) < 3) {
                $errors[] = 'Invalid Client Secret';
            }
        }
        if ($c->base_url && !filter_var($c->base_url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid Base URL';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    private function testOAuthEndpoint(ApiCredential $c): array
    {
        if ($c->api_name === ApiCredential::THREESHAPE) {
            $tokenUrl = rtrim($c->base_url ?: config('three_shape.identity_base'), '/') . '/connect/token';
        } else {
            $authBase = rtrim($c->base_url ?: config('meditlink.auth_base'), '/');
            $tokenUrl = $authBase . '/oauth/token';
        }

        $response = Http::timeout(15)
            ->withOptions(['verify' => false])
            ->post($tokenUrl, []);

        return [
            'accessible' => in_array($response->status(), [200,400,401,403,405]),
            'status'     => $response->status(),
            'response'   => $response->json() ?? ['status' => $response->status()],
            'note'       => null,
        ];
    }

    private function resourceBaseFromAuth(?string $authBase): string
    {
        $auth = rtrim($authBase ?: config('meditlink.auth_base'), '/');
        return str_replace('-auth', '-resources', $auth);
    }

    private function testApiConnectivity(ApiCredential $c): array
    {
        try {
            if ($c->api_name === ApiCredential::THREESHAPE) {
                // For 3Shape, you'd ping a lightweight endpoint. Placeholder.
                $url = $c->threeShapeResourceBase() . '/connect/diagnostics';
            } else {
                // Medit Link: /v1/me gives profile, group, etc.
                $url = $this->resourceBaseFromAuth($c->base_url) . '/v1/me';
            }

            $res = Http::timeout(15)
                ->withOptions(['verify'=>false])
                ->withHeaders([
                    'Authorization'         => $c->access_token ? 'Bearer '.$c->access_token : '',
                    'Accept'                => 'application/json',
                    'Content-Type'          => 'application/json',
                    'x-meditlink-client-id' => $c->client_id,
                ])
                ->get($url);

            return [
                'successful' => $res->successful(),
                'status'     => $res->status(),
                'response'   => $res->json() ?? $res->body(),
                'error'      => $res->successful() ? null : ($res->json() ?? $res->body()),
            ];
        } catch (\Exception $e) {
            return [
                'successful' => false,
                'status'     => 0,
                'response'   => null,
                'error'      => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch DS Core orders and save to database.
     */
    public function fetchDScoreOrders(ApiCredential $apiCredential)
    {
        if ($apiCredential->api_name !== ApiCredential::DS_CORE) {
            return response()->json([
                'success' => false,
                'message' => 'This action is only available for DS Core credentials.',
            ], 400);
        }

        if (!$apiCredential->access_token || $apiCredential->isTokenExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'DS Core token is missing or expired. Please re-authorize.',
            ], 401);
        }

        try {
            $dsService = new DScoreService();
            $result = $dsService->fetchAndSaveOrders($apiCredential);

            return response()->json([
                'success' => true,
                'message' => "Successfully fetched and saved {$result['count']} orders from DS Core.",
                'data'    => [
                    'count'  => $result['count'],
                    'orders' => collect($result['orders'])->map(fn($o) => [
                        'id'           => $o->id,
                        'order_id'     => $o->order_id,
                        'order_number' => $o->order_number,
                        'status'       => $o->status,
                        'patient_name' => $o->patient_name,
                        'practice_name'=> $o->practice_name,
                        'lab_name'     => $o->lab_name,
                        'order_date'   => $o->order_date?->toIso8601String(),
                        'due_date'     => $o->due_date?->toIso8601String(),
                    ])->toArray(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('DS Core fetch orders failed', [
                'credential_id' => $apiCredential->id,
                'error'         => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch DS Core orders: ' . $e->getMessage(),
            ], 500);
        }
    }
}
