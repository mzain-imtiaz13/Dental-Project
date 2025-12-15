<?php

namespace App\Services;

use App\Models\ApiCredential;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DScoreService
{
    public const API_NAME = 'ds_core';

    private function requiredConfig(?string $clientId = null, ?string $clientSecret = null, bool $requireSecret = true): array
    {
        $cfg = config('dscore');

        $resolvedClientId = $clientId ?: ($cfg['client_id'] ?? null);
        $resolvedClientSecret = $clientSecret ?: ($cfg['client_secret'] ?? null);

        if (!$resolvedClientId || ($requireSecret && !$resolvedClientSecret)) {
            $existing = ApiCredential::where('api_name', self::API_NAME)
                ->orderByDesc('id')
                ->first();

            if (!$resolvedClientId) {
                $resolvedClientId = $existing?->client_id;
            }
            if ($requireSecret && !$resolvedClientSecret) {
                $resolvedClientSecret = $existing?->client_secret;
            }
        }

        if (!$resolvedClientId || ($requireSecret && !$resolvedClientSecret)) {
            throw new \RuntimeException('DS Core credentials not found. Please add them via the API Credentials UI, or set DSCORE_CLIENT_ID and DSCORE_CLIENT_SECRET in your .env file for initial bootstrap.');
        }

        $cfg['client_id'] = $resolvedClientId;
        $cfg['client_secret'] = $resolvedClientSecret;

        return $cfg;
    }

    public function credentials(): ApiCredential
    {
        $cfg = $this->requiredConfig();

        $cred = ApiCredential::firstOrCreate(
            ['api_name' => self::API_NAME],
            [
                'client_id'         => $cfg['client_id'],
                'client_secret'     => $cfg['client_secret'],
                'base_url'          => $cfg['auth_host'],
                'is_active'         => true,
                'additional_config' => [
                    'auth_url'   => $cfg['auth_url'],
                    'token_url'  => $cfg['token_url'],
                    'orders_url' => $cfg['orders_url'],
                    'scope'      => $cfg['scope'],
                ],
            ]
        );

        $dirty = false;
        if ($cred->client_id !== $cfg['client_id'])         { $cred->client_id = $cfg['client_id']; $dirty = true; }
        if ($cred->client_secret !== $cfg['client_secret']) { $cred->client_secret = $cfg['client_secret']; $dirty = true; }
        if (!$cred->base_url)                               { $cred->base_url = $cfg['auth_host']; $dirty = true; }

        $ac = $cred->additional_config ?? [];
        $map = [
            'auth_url'   => $cfg['auth_url'],
            'token_url'  => $cfg['token_url'],
            'orders_url' => $cfg['orders_url'],
            'scope'      => $cfg['scope'],
        ];
        foreach ($map as $k => $v) {
            if (($ac[$k] ?? null) !== $v) {
                $ac[$k] = $v;
                $dirty  = true;
            }
        }
        if ($dirty) {
            $cred->additional_config = $ac;
            $cred->save();
        }

        return $cred;
    }

    public function buildAuthorizeUrl(string $state = null): string
    {
        $cfg   = config('dscore');
        $state = $state ?: 'dscore-' . Str::random(8);

        $tempClientId = session('temp_credentials.client_id')
            ?: (session('temp_credentials')['client_id'] ?? null);

        $clientId = $tempClientId ?: ($cfg['client_id'] ?? null);
        if (!$clientId) {
            $clientId = ApiCredential::where('api_name', self::API_NAME)
                ->orderByDesc('id')
                ->value('client_id');
        }

        if (!$clientId) {
            throw new \RuntimeException('DS Core credentials not found. Please add them via the API Credentials UI, or set DSCORE_CLIENT_ID and DSCORE_CLIENT_SECRET in your .env file for initial bootstrap.');
        }

        $params = [
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => $cfg['redirect_uri'],
            'state'         => $state,
        ];

        if (!empty($cfg['scope'])) {
            $params['scope'] = $cfg['scope'];
        }

        $authUrl = $cfg['auth_url'];

        return rtrim($authUrl, '?') . '?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code, ApiCredential $cred): ApiCredential
    {
        $cfg = $this->requiredConfig($cred->client_id, $cred->client_secret, true);

        $tokenUrl = $cred->additional_config['token_url']
            ?? $cfg['token_url']
            ?? null;

        if (!$tokenUrl) {
            throw new \RuntimeException('DS Core token URL is not configured.');
        }

        $resp = Http::asForm()
            ->timeout(30)
            ->withOptions(['verify' => false])
            ->post($tokenUrl, [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $cfg['redirect_uri'],
                'client_id'     => $cred->client_id,
                'client_secret' => $cred->client_secret,
            ]);

        $resp->throw();

        $json = $resp->json();

        $cred->access_token  = $json['access_token'] ?? null;
        $cred->refresh_token = $json['refresh_token'] ?? null;
        $cred->token_expiry  = Carbon::now()->addSeconds($json['expires_in'] ?? 3600);
        $cred->is_active     = true;
        $cred->save();

        return $cred;
    }

    public function ensureAccessToken(ApiCredential $cred): string
    {
        if ($cred->access_token && !$cred->isTokenExpired()) {
            return $cred->access_token;
        }

        throw new \RuntimeException('DS Core access token missing or expired. Please re-authorize.');
    }

    public function orders(ApiCredential $cred, array $query = []): array
    {
        $token = $this->ensureAccessToken($cred);

        $cfg       = config('dscore');
        $ordersUrl = $cred->additional_config['orders_url'] ?? $cfg['orders_url'];

        $resp = Http::timeout(30)
            ->withOptions(['verify' => false])
            ->withToken($token)
            ->acceptJson()
            ->get($ordersUrl, $query);

        if ($resp->status() === 401) {
            throw new \RuntimeException('DS Core token unauthorized/expired. Please re-authorize.');
        }

        $resp->throw();

        return $resp->json();
    }
}