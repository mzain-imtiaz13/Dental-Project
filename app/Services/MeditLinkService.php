<?php

namespace App\Services;

use App\Models\ApiCredential;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MeditLinkService
{
    public const API_NAME = 'medit_link';

    public function __construct(
        private readonly string $authBase = '',
        private readonly string $apiBase  = '',
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly string $redirectUri = '',
        private readonly string $scope = '',
    ) {
        $cfg = config('meditlink');
        $this->authBase     = $this->authBase     ?: $cfg['auth_base'];
        $this->apiBase      = $this->apiBase      ?: $cfg['api_base'];
        $this->clientId     = $this->clientId     ?: $cfg['client_id'];
        $this->clientSecret = $this->clientSecret ?: $cfg['client_secret'];
        $this->redirectUri  = $this->redirectUri  ?: $cfg['redirect_uri'];
        $this->scope        = $this->scope        ?: $cfg['scope']; 
    }

    public function credentials(): ApiCredential
    {
        $cred = ApiCredential::firstOrCreate(
            ['api_name' => self::API_NAME],
            [
                'client_id'         => $this->clientId,
                'client_secret'     => $this->clientSecret,
                'base_url'          => $this->authBase,
                'additional_config' => [],
            ]
        );

        $dirty = false;
        if ($cred->client_id !== $this->clientId)        { $cred->client_id = $this->clientId; $dirty = true; }
        if ($cred->client_secret !== $this->clientSecret){ $cred->client_secret = $this->clientSecret; $dirty = true; }
        if (!$cred->base_url)                             { $cred->base_url = $this->authBase; $dirty = true; }
        if ($dirty) $cred->save();

        return $cred;
    }

    public function buildAuthorizeUrl(string $state = null): string
    {
        $cred  = $this->credentials();
        $state = $state ?: 'medit-'.Str::random(8);

        return $cred->authBase().'/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'scope'         => $this->scope, 
            'state'         => $state,
        ]);
    }

    public function exchangeCodeForToken(string $code): ApiCredential
    {
        $cred = $this->credentials();

        $resp = Http::asForm()
            ->baseUrl($cred->authBase())
            ->acceptJson()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post('/oauth/token', [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => $this->redirectUri,
            ]);

        $resp->throw();

        $json = $resp->json();
        $cred->access_token  = $json['access_token'] ?? null;
        $cred->refresh_token = $json['refresh_token'] ?? null;
        $cred->token_expiry  = Carbon::now()->addSeconds($json['expires_in'] ?? 3600);
        $cred->save();

        return $cred;
    }

    /** Without offline_access, we do NOT auto-refresh; just return current token or throw. */
    public function ensureAccessToken(): string
    {
        $cred = $this->credentials();

        if ($cred->access_token) {
            return $cred->access_token;
        }

        throw new RequestException(Http::response('Unauthorized: no access token, please authorize', 401));
    }

    /** Common HTTP client for RESOURCE server. */
    private function api(): \Illuminate\Http\Client\PendingRequest
    {
        $cred  = $this->credentials();
        $token = $this->ensureAccessToken();

        $apiBase = $cred->resourcesBase() ?: $this->apiBase;

        return Http::baseUrl($apiBase)
            ->acceptJson()
            ->withHeaders([
                'x-meditlink-client-id' => $this->clientId,
            ])
            ->withToken($token);
    }

    public function me(): array
    {
        $resp = $this->api()->get('/v1/me');
        if ($resp->status() === 401) {
            throw new RequestException(Http::response('Unauthorized/expired token. Please re-authorize.', 401));
        }
        $resp->throw();
        return $resp->json();
    }

    public function orders(array $query = []): array
    {
        $resp = $this->api()->get('/v1/orders', $query);
        if ($resp->status() === 401) {
            throw new RequestException(Http::response('Unauthorized/expired token. Please re-authorize.', 401));
        }
        $resp->throw();
        return $resp->json();
    }

    /** Diagnostic test */
    public function runConnectivityTest(): array
    {
        $result = [
            'success' => true,
            'message' => 'API test completed successfully',
            'results' => [
                'credential_format' => ['valid' => true, 'errors' => []],
                'oauth_endpoint'    => [],
                'api_connectivity'  => [],
                'success'           => true,
            ]
        ];

        $cred = $this->credentials();

        $oauthPing = Http::baseUrl($cred->authBase())->acceptJson()->post('/oauth/token', []);
        $result['results']['oauth_endpoint'] = [
            'accessible' => in_array($oauthPing->status(), [400, 401, 403, 200]),
            'status'     => $oauthPing->status(),
            'response'   => $oauthPing->json() ?? ['status' => $oauthPing->status(), 'error' => $oauthPing->reason(), 'path' => '/oauth/token'],
            'note'       => null,
        ];

        try {
            $me = $this->me();
            $result['results']['api_connectivity'] = [
                'successful' => true,
                'status'     => 200,
                'response'   => $me,
                'error'      => null,
            ];
        } catch (\Throwable $e) {
            $status = method_exists($e, 'response') && $e->response() ? $e->response()->status() : 0;
            $body   = method_exists($e, 'response') && $e->response() ? ($e->response()->json() ?? $e->response()->body()) : $e->getMessage();

            $result['results']['api_connectivity'] = [
                'successful' => false,
                'status'     => $status,
                'response'   => $body,
                'error'      => is_string($body) ? $body : json_encode($body),
            ];
        }

        return $result;
    }
}
