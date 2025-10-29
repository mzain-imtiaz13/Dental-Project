<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    public function authorize(Request $request)
    {
        $temp = session('temp_credentials');
        if (!$temp) {
            return redirect()->route('api-credentials.index')->with('error', 'No credentials found.');
        }

        // values from “Add API Credentials” form
        $cred = new ApiCredential($temp);

        $state = Str::random(40);
        session(['oauth_state' => $state]);

        // Build authorize URL from AUTH base
        $authBase = rtrim($cred->base_url ?: config('meditlink.auth_base'), '/');
        $callback = route('oauth.callback');

        // Scope without offline_access
        $scope = trim(preg_replace('/\s+/', ' ', config('meditlink.scope', 'USER GROUP ORDER CASE')));

        $params = http_build_query([
            'client_id'     => $cred->client_id,
            'response_type' => 'code',
            'redirect_uri'  => $callback,
            'scope'         => $scope,
            'state'         => $state,
        ]);

        return redirect($authBase . '/oauth/authorize?' . $params);
    }

    public function callback(Request $request)
    {
        $code  = $request->get('code');
        $state = $request->get('state');
        $temp  = session('temp_credentials');

        if (!$code || !$state || !$temp || $state !== session('oauth_state')) {
            return redirect()->route('api-credentials.index')->with('error', 'Invalid callback data');
        }

        // Create the credential row with the values you entered
        $cred = ApiCredential::create($temp);

        $authBase = rtrim($cred->base_url ?: config('meditlink.auth_base'), '/');
        $tokenUrl = $authBase . '/oauth/token';

        // Basic auth with client_id:client_secret
        $basic = base64_encode($cred->client_id . ':' . $cred->client_secret);

        $resp = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Basic ' . $basic,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ])
            ->withOptions(['verify' => false])
            ->asForm()
            ->post($tokenUrl, [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => route('oauth.callback'),
            ]);

        if (!$resp->successful()) {
            $body = $resp->body();
            $cred->delete();
            return redirect()->route('api-credentials.index')
                ->with('error', 'Token exchange failed: ' . $body);
        }

        $tok = $resp->json();
        $cred->update([
            'access_token'  => $tok['access_token'] ?? null,
            // ❌ no refresh token expected without offline_access
            'refresh_token' => $tok['refresh_token'] ?? null,
            'token_expiry'  => now()->addSeconds($tok['expires_in'] ?? 3600),
        ]);

        session()->forget(['temp_credentials', 'oauth_state']);

        return redirect()->route('api-credentials.index')->with('success', 'API credentials saved successfully');
    }


    public function fetchData(Request $request, ApiCredential $cred)
    {
        if (!$cred->access_token) {
            return response()->json(['success' => false, 'message' => 'No access token. Authorize first.'], 400);
        }

        $type    = $request->get('type', 'orders');
        $apiBase = $cred->resourcesBase();

        $endpoints = [
            'orders'   => '/v1/orders',
            'patients' => '/v1/patients',
            'user'     => '/v1/me',
            'groups'   => '/v1/groups',
        ];

        if (!isset($endpoints[$type])) {
            return response()->json(['success' => false, 'message' => 'Invalid type'], 400);
        }

        $url = $apiBase . $endpoints[$type];

        $res = \Illuminate\Support\Facades\Http::timeout(30)
            ->withOptions(['verify' => false])
            ->withHeaders([
                'Authorization'         => 'Bearer ' . $cred->access_token,
                'Accept'                => 'application/json',
                'Content-Type'          => 'application/json',
                'x-meditlink-client-id' => $cred->client_id,
            ])->get($url);

        if ($res->status() === 401) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized/expired token. Please re-authorize.',
            ], 200);
        }

        if ($res->successful()) {
            $json = $res->json();
            if ($json === null) $json = json_decode($res->body(), true);
            return response()->json(['success' => true, 'data' => $json, 'type' => $type]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch data: ' . $res->body(),
        ], 200);
    }
}
