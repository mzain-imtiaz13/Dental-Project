<?php

namespace App\Services;

use App\Models\ApiCredential;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DScoreService
{
    public const API_NAME = 'ds_core';

    /**
     * Get or create DS Core credentials.
     * Uses database as primary source, env variables only for initial bootstrap.
     */
    public function credentials(): ApiCredential
    {
        $cfg = config('dscore');

        // Try to get existing credentials from database
        $cred = ApiCredential::where('api_name', self::API_NAME)->first();

        // If credentials exist in database, use them (database is source of truth)
        if ($cred) {
            // Ensure additional_config has the URLs (in case they were added after creation)
            $ac = $cred->additional_config ?? [];
            $dirty = false;
            
            if (empty($ac['auth_url'])) {
                $ac['auth_url'] = $cfg['auth_url'];
                $dirty = true;
            }
            if (empty($ac['token_url'])) {
                $ac['token_url'] = $cfg['token_url'];
                $dirty = true;
            }
            if (empty($ac['orders_url'])) {
                $ac['orders_url'] = $cfg['orders_url'];
                $dirty = true;
            }
            if (empty($ac['scope'])) {
                $ac['scope'] = $cfg['scope'];
                $dirty = true;
            }
            
            if ($dirty) {
                $cred->additional_config = $ac;
                $cred->save();
            }

            return $cred;
        }

        // No credentials in database - try to bootstrap from env
        $clientId = $cfg['client_id'] ?? null;
        $clientSecret = $cfg['client_secret'] ?? null;

        if (!$clientId || !$clientSecret) {
            throw new \RuntimeException(
                'DS Core credentials not found. Please add them via the API Credentials UI, ' .
                'or set DSCORE_CLIENT_ID and DSCORE_CLIENT_SECRET in your .env file for initial bootstrap.'
            );
        }

        // Create initial credentials from env
        $cred = ApiCredential::create([
            'api_name'          => self::API_NAME,
            'client_id'         => $clientId,
            'client_secret'     => $clientSecret,
            'base_url'          => $cfg['auth_host'],
            'is_active'         => true,
            'additional_config' => [
                'auth_url'   => $cfg['auth_url'],
                'token_url'  => $cfg['token_url'],
                'orders_url' => $cfg['orders_url'],
                'scope'      => $cfg['scope'],
            ],
        ]);

        return $cred;
    }

    public function buildAuthorizeUrl(string $state = null): string
    {
        $cred  = $this->credentials();
        $cfg   = config('dscore');
        $state = $state ?: 'dscore-' . Str::random(8);

        $params = [
            'response_type' => 'code',
            'client_id'     => $cred->client_id,
            'redirect_uri'  => $cfg['redirect_uri'],
            'state'         => $state,
        ];

        $scope = $cred->additional_config['scope'] ?? $cfg['scope'];
        if (!empty($scope)) {
            $params['scope'] = $scope;
        }

        $authUrl = $cred->additional_config['auth_url'] ?? $cfg['auth_url'];

        return rtrim($authUrl, '?') . '?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code, ApiCredential $cred): ApiCredential
    {
        $cfg      = config('dscore');
        $tokenUrl = $cred->additional_config['token_url'] ?? $cfg['token_url'];

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