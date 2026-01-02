<?php

namespace App\Services;

use App\Models\ApiCredential;
use App\Models\DScoreOrder;
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

        $verifier = Str::random(96);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        session(['dscore.code_verifier' => $verifier]);

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
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];

        $scope = session('temp_credentials.scope')
            ?: (session('temp_credentials')['scope'] ?? null)
            ?: ($cfg['scope'] ?? '');
        if (!empty($scope)) {
            $params['scope'] = $scope;
        }

        $authUrl = $cfg['auth_url'];

        return rtrim($authUrl, '?') . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCodeForToken(string $code, ApiCredential $cred): ApiCredential
    {
        $cfg = $this->requiredConfig($cred->client_id, $cred->client_secret, true);

        $codeVerifier = session('dscore.code_verifier');
        if (!$codeVerifier) {
            throw new \RuntimeException('DS Core code verifier missing from session. Please restart authorization.');
        }

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
                'code_verifier' => $codeVerifier,
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

    /**
     * Fetch orders from DS Core API and save them to the database.
     * Returns array with 'count' and 'orders' keys.
     *
     * DS Core API response format:
     * {
     *   "orders": [
     *     {
     *       "name": "orders/a0234fe1-a839-4164-90b3-c83b9aeafaf9",
     *       "readableId": "2AA5GRI0",
     *       "type": "nightGuardSplint",
     *       "patient": { "familyName": "...", "givenName": "...", ... },
     *       "account": { "uri": "...", "displayName": "..." },
     *       "performingLab": { "uri": "...", "displayName": "..." },
     *       "state": "REQUESTED",
     *       "createTime": "2025-12-07T15:59:43.701335Z",
     *       "dueTime": "2025-12-14T06:30:00Z",
     *       ...
     *     }
     *   ]
     * }
     */
    public function fetchAndSaveOrders(ApiCredential $cred): array
    {
        $ordersData = $this->orders($cred);

        $saved = 0;
        $orders = [];

        // DS Core API returns {orders: [...]}
        $ordersList = $ordersData['orders'] ?? $ordersData['content'] ?? $ordersData;
        
        if (!is_array($ordersList)) {
            $ordersList = [];
        }

        foreach ($ordersList as $order) {
            // DS Core uses "name" field as ID (e.g., "orders/uuid-here")
            // Extract the UUID from "orders/uuid" format, or use readableId as fallback
            $nameField = $order['name'] ?? null;
            $orderId = null;
            
            if ($nameField && str_contains($nameField, '/')) {
                // Extract UUID from "orders/uuid-here"
                $orderId = substr($nameField, strrpos($nameField, '/') + 1);
            }
            
            // Fallback to other possible ID fields
            if (!$orderId) {
                $orderId = $order['id'] ?? $order['orderId'] ?? $order['readableId'] ?? null;
            }
            
            if (!$orderId) {
                continue;
            }

            // Build patient name from givenName + familyName
            $patientName = null;
            if (isset($order['patient'])) {
                $given = $order['patient']['givenName'] ?? '';
                $family = $order['patient']['familyName'] ?? '';
                $patientName = trim("{$given} {$family}") ?: null;
            }

            // Extract patient ID from URI or cardId
            $patientId = $order['patient']['cardId'] 
                ?? $order['patient']['uri'] 
                ?? null;

            // Practice/Account info
            $practiceName = $order['account']['displayName'] ?? null;
            $practiceId = $order['account']['uri'] ?? null;

            // Lab info (performingLab)
            $labName = $order['performingLab']['displayName'] ?? null;
            $labId = $order['performingLab']['uri'] ?? null;

            $record = DScoreOrder::updateOrCreate(
                ['order_id' => $orderId],
                [
                    'credential_id'  => $cred->id,
                    'order_number'   => $order['readableId'] ?? null,
                    'status'         => $order['state'] ?? $order['status'] ?? null,
                    'order_type'     => $order['type'] ?? null,
                    'patient_name'   => $patientName,
                    'patient_id'     => $patientId,
                    'practice_name'  => $practiceName,
                    'practice_id'    => $practiceId,
                    'lab_name'       => $labName,
                    'lab_id'         => $labId,
                    'order_date'     => $this->parseDate($order['createTime'] ?? $order['createDate'] ?? null),
                    'due_date'       => $this->parseDate($order['dueTime'] ?? $order['dueDate'] ?? null),
                    'shipped_date'   => $this->parseDate($order['shippedTime'] ?? $order['shippedDate'] ?? null),
                    'raw'            => $order,
                ]
            );

            $orders[] = $record;
            $saved++;
        }

        return [
            'count'  => $saved,
            'orders' => $orders,
            'raw'    => $ordersData,
        ];
    }

    private function parseDate(?string $dateStr): ?Carbon
    {
        if (!$dateStr) {
            return null;
        }
        try {
            return Carbon::parse($dateStr);
        } catch (\Throwable) {
            return null;
        }
    }
}
