<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class OAuthController extends Controller
{
    /* ----------- Medit: kick off ----------- */
    public function authorize(Request $request)
    {
        $temp = session('temp_credentials');
        if (!$temp) {
            return redirect()->route('api-credentials.index')->with('error', 'No credentials found.');
        }

        $cred  = new ApiCredential($temp);
        $state = Str::random(40);
        session(['oauth_state' => $state]);

        $authBase = rtrim($cred->base_url ?: config('meditlink.auth_base'), '/');
        $callback = route('oauth.callback');

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

    /* ===== unified callback entry point ===== */
    public function sharedCallback(Request $request)
    {
        $iss = (string) $request->query('iss', '');

        if (str_contains($iss, '3shape.com') || session()->has('3s.state')) {
            return $this->callback3Shape($request);
        }

        return $this->callbackMedit($request);
    }

    /* ------------- Medit callback ------------- */
    private function callbackMedit(Request $request)
    {
        $code  = $request->get('code');
        $state = $request->get('state');
        $temp  = session('temp_credentials');

        if (!$code || !$state || !$temp || $state !== session('oauth_state')) {
            return redirect()->route('api-credentials.index')->with('error', 'Invalid callback data');
        }

        $cred = ApiCredential::create($temp);

        $authBase = rtrim($cred->base_url ?: config('meditlink.auth_base'), '/');
        $tokenUrl = $authBase . '/oauth/token';

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
            'refresh_token' => $tok['refresh_token'] ?? null,
            'token_expiry'  => now()->addSeconds($tok['expires_in'] ?? 3600),
        ]);

        session()->forget(['temp_credentials', 'oauth_state']);

        return redirect()->route('api-credentials.index')->with('success', 'API credentials saved successfully');
    }

    /* ------------- 3Shape callback ------------- */
    private function callback3Shape(Request $request)
    {
        if ($request->query('error')) {
            return redirect()->route('api-credentials.index')
                ->with('error', '3Shape authorization failed: '.$request->query('error'));
        }

        $state = $request->query('state');
        if (!$state || $state !== session('3s.state')) {
            return redirect()->route('api-credentials.index')
                ->with('error', 'Invalid OAuth state for 3Shape.');
        }

        $code = $request->query('code');
        if (!$code) {
            return redirect()->route('api-credentials.index')
                ->with('error', 'Missing authorization code from 3Shape.');
        }

        $codeVerifier = session('3s.code_verifier');
        $identityBase = session('3s.identity_base') ?: config('three_shape.identity_base');
        $clientId     = session('3s.client_id')     ?: config('three_shape.client_id');
        $redirectUri  = rtrim(config('three_shape.redirect_uri'), '/');

        $tokenUrl = rtrim($identityBase, '/').'/connect/token';

        $resp = Http::asForm()
            ->withOptions(['verify' => false])
            ->post($tokenUrl, [
                'grant_type'    => 'authorization_code',
                'client_id'     => $clientId,
                'code'          => $code,
                'redirect_uri'  => $redirectUri,
                'code_verifier' => $codeVerifier,
            ]);

        if (!$resp->successful()) {
            Log::error('3Shape token exchange failed', [
                'status'=>$resp->status(),
                'body'=>$resp->body()
            ]);
            return redirect()->route('api-credentials.index')
                ->with('error', '3Shape token exchange failed. Please verify redirect URI and app settings.');
        }

        $tok = $resp->json();

        ApiCredential::create([
            'api_name'          => ApiCredential::THREESHAPE,
            'client_id'         => $clientId,
            'client_secret'     => null, // 3Shape doesn't give client_secret in PKCE
            'base_url'          => $identityBase, // identity base
            'is_active'         => true,
            'access_token'      => $tok['access_token'] ?? null,
            'refresh_token'     => $tok['refresh_token'] ?? null,
            'token_expiry'      => now()->addSeconds($tok['expires_in'] ?? 3600),
            'additional_config' => [
                'resource_base' => session('3s.resource_base') ?: config('three_shape.resource_base'),
            ],
        ]);

        session()->forget([
            '3s.code_verifier',
            '3s.state',
            '3s.identity_base',
            '3s.resource_base',
            '3s.client_id',
        ]);

        return redirect()->route('api-credentials.index')
            ->with('success', '3Shape connected successfully.');
    }
}
