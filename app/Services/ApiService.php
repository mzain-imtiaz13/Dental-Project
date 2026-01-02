<?php

namespace App\Services;

use App\Models\ApiCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiService
{
    private const DEFAULT_PAGE_SIZE = 20;

    public function __construct(private ApiCredential $credential) {}

    public function getOrders(array $params = []): array
    {
        if ($this->credential->api_name === 'medit_link') {
            return $this->getMeditLinkOrders($params);
        }
        return ['data' => [], 'meta' => ['page' => 0, 'size' => self::DEFAULT_PAGE_SIZE, 'total' => 0]];
    }

    private function getMeditLinkOrders(array $params = []): array
    {
        $apiBase = $this->credential->resourcesBase();
        $url     = $apiBase.'/v1/orders/search';

        $query = [
            'schema' => 'latest',
            'size'   => min(100, max(1, (int)($params['size'] ?? self::DEFAULT_PAGE_SIZE))),
            'page'   => max(0, (int)($params['page'] ?? 0)), // 0-based
            'start'  => 0,
            'end'    => 253402300799000,
        ];

        $headers = [
            'Authorization'         => 'Bearer '.$this->credential->access_token,
            'Accept'                => 'application/json',
            'Content-Type'          => 'application/json',
            'x-meditlink-client-id' => $this->credential->client_id,
        ];
        // Optionally include group uuid if saved
        $uuid = $this->credential->additional_config['group_uuid'] ?? env('MEDIT_GROUP_UUID');
        if (!empty($uuid)) $headers['x-meditlink-group-uuid'] = $uuid;

        try {
            $res = Http::withOptions(['verify' => false])
                ->withHeaders($headers)
                ->get($url, $query);

            if (!$res->successful()) {
                Log::error('Medit Link orders search failed', ['status' => $res->status(), 'body' => $res->body()]);
                throw new \Exception('Failed to fetch orders: '.$res->body());
            }

            $json = $res->json();
            $items = $json['content'] ?? [];

            return [
                'data' => array_map(function ($o) {
                    return [
                        'id'         => $o['orderNumber'] ?? null,
                        'created_at' => $o['dateCreated'] ?? null,
                        'updated_at' => $o['dateUpdated'] ?? null,
                        'status'     => $o['status'] ?? null,
                        'patient'    => [
                            'name'       => $o['case']['patient']['name'] ?? null,
                            'code'       => $o['case']['patient']['code'] ?? null,
                        ],
                        'case'       => [
                            'uuid'  => $o['case']['uuid'] ?? null,
                            'name'  => $o['case']['name'] ?? null,
                            'status'=> $o['case']['status'] ?? null,
                        ],
                        'buyer'      => $o['buyer']['name'] ?? null,
                        'seller'     => $o['seller']['name'] ?? null,
                        'source_api' => 'Meditlink',
                    ];
                }, $items),
                'meta' => [
                    'page'     => $json['page'] ?? 0,
                    'size'     => $json['size'] ?? (int)$query['size'],
                    'total'    => $json['totalElements'] ?? count($items),
                    'last'     => $json['last'] ?? true,
                    'first'    => $json['first'] ?? true,
                    'pages'    => $json['totalPage'] ?? 1,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Medit Link orders error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
