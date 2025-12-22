<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ThreeShapeOAuthController extends Controller
{
    private function redirectUri(): string
    {
        return rtrim(config('three_shape.redirect_uri'), '/'); // /oauth/callback
    }

    public function start(Request $request)
    {
        $temp = session('three_shape_temp', []);

        session(['oauth_provider' => \App\Models\ApiCredential::THREESHAPE]);
        session()->forget(['temp_credentials', 'oauth_state']);

        $clientId = $request->input('client_id')
            ?: ($temp['client_id'] ?? null)
            ?: config('three_shape.client_id');

        $identityBase = $request->input('identity_base')
            ?: ($temp['base_url'] ?? null)
            ?: config('three_shape.identity_base');
        $identityBase = rtrim($identityBase, '/');

        $resourceBase = $request->input('resource_base')
            ?: ($temp['additional_config']['resource_base'] ?? null)
            ?: config('three_shape.resource_base');
        $resourceBase = rtrim($resourceBase, '/');
        $redirectUri  = $this->redirectUri();
        $scope        = config('three_shape.scope');

        session([
            '3s.client_id'     => $clientId,
            '3s.identity_base' => $identityBase,
            '3s.resource_base' => $resourceBase,
        ]);

        $verifier  = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        session(['3s.code_verifier' => $verifier]);

        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        session(['3s.state' => $state]);

        $params = http_build_query([
            'client_id'             => $clientId,
            'response_type'         => 'code',
            'scope'                 => $scope,
            'redirect_uri'          => $redirectUri,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'response_mode'         => 'query',
            'nonce'                 => $nonce,
            'state'                 => $state,
        ]);

        return redirect()->away($identityBase.'/connect/authorize?'.$params);
    }
}
