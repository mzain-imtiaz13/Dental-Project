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

        $cred = new ApiCredential($temp);
        $state = Str::random(40);
        session(['oauth_state' => $state]);

        $authBase = rtrim($cred->base_url ?: 'https://stage-openapi-auth.meditlink.com', '/');
        $params = http_build_query([
            'client_id'     => $cred->client_id,
            'response_type' => 'code',
            'redirect_uri'  => route('oauth.callback'),
            'scope'         => 'USER GROUP',
            'state'         => $state,
        ]);
        return redirect($authBase.'/oauth/authorize?'.$params);
    }

    public function callback(Request $request)
    {
        $code  = $request->get('code');
        $state = $request->get('state');
        $temp  = session('temp_credentials');

        if (!$code || !$state || !$temp) {
            return redirect()->route('api-credentials.index')->with('error', 'Invalid callback data');
        }

        $cred = ApiCredential::create($temp);

        $authBase = rtrim($cred->base_url ?: 'https://stage-openapi-auth.meditlink.com', '/');
        $tokenUrl = $authBase.'/oauth/token';

        $auth = base64_encode($cred->client_id.':'.$cred->client_secret);

        $resp = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Basic '.$auth,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ])->withOptions(['verify' => false])
            ->asForm()
            ->post($tokenUrl, [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => route('oauth.callback'),
            ]);

        if (!$resp->successful()) {
            $cred->delete();
            return redirect()->route('api-credentials.index')
                ->with('error', 'Token exchange failed: '.$resp->body());
        }

        $tok = $resp->json();
        $cred->update([
            'access_token'  => $tok['access_token'] ?? null,
            'refresh_token' => $tok['refresh_token'] ?? null,
            'token_expiry'  => now()->addSeconds($tok['expires_in'] ?? 3600),
        ]);

        session()->forget(['temp_credentials', 'oauth_state']);
        return redirect()->route('api-credentials.index')->with('success', 'API credentials saved successfully');
    }

    public function refresh(Request $r, ApiCredential $cred)
    {
        if (!$cred->refresh_token) {
            return response()->json(['success' => false, 'message' => 'No refresh token'], 400);
        }

        $authBase = $cred->authBase();
        $resp = Http::withOptions(['verify' => false])
            ->withHeaders(['Authorization' => 'Basic '.base64_encode($cred->client_id.':'.$cred->client_secret)])
            ->asForm()
            ->post($authBase.'/oauth/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $cred->refresh_token,
            ]);

        if (!$resp->successful()) {
            return response()->json(['success' => false, 'message' => 'Token refresh failed: '.$resp->body()], 400);
        }

        $tok = $resp->json();
        $cred->update([
            'access_token'  => $tok['access_token'] ?? null,
            'refresh_token' => $tok['refresh_token'] ?? $cred->refresh_token,
            'token_expiry'  => now()->addSeconds($tok['expires_in'] ?? 3600),
        ]);

        return response()->json(['success' => true, 'message' => 'Token refreshed']);
    }

    /** Blue "download" — fetch API data from the RESOURCES host. */
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
            'user'     => '/v1/me',        // <— FIXED
            'groups'   => '/v1/groups',
        ];

        if (!isset($endpoints[$type])) {
            return response()->json(['success' => false, 'message' => 'Invalid type'], 400);
        }

        $url = $apiBase.$endpoints[$type];

        $res = Http::timeout(30)
            ->withOptions(['verify' => false])
            ->withHeaders([
                'Authorization'         => 'Bearer '.$cred->access_token,
                'Accept'                => 'application/json',
                'Content-Type'          => 'application/json',
                'x-meditlink-client-id' => $cred->client_id,
            ])->get($url);

        if ($res->successful()) {
            return response()->json(['success' => true, 'data' => $res->json(), 'type' => $type]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch data: '.$res->body(),
        ], 200); // front-end expects 200 + success=false
    }
}
