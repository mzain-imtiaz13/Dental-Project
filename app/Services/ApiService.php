<?php
namespace App\Services;

use App\Models\ApiCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiService
{
    private const MEDIT_API_VERSION = 'v1';
    private const DEFAULT_PAGE_SIZE = 20;

    private $credential;

    public function __construct(ApiCredential $credential)
    {
        $this->credential = $credential;
    }

    public function getOrders(array $params = []): array
    {
        if ($this->credential->api_name === 'medit_link') {
            return $this->getMeditLinkOrders($params);
        }
        // Add other API handlers here
        return ['data' => [], 'meta' => ['current_page' => 1, 'total' => 0, 'per_page' => self::DEFAULT_PAGE_SIZE]];
    }

    private function getMeditLinkOrders(array $params = []): array
    {
        // Convert stored auth host to resources host (what Postman uses)
        $authBase = rtrim($this->credential->base_url ?? 'https://stage-openapi-auth.meditlink.com', '/');
        $baseUrl  = str_replace('-auth', '-resources', $authBase);

        $endpoint = '/' . self::MEDIT_API_VERSION . '/orders/search';

        // Format query parameters according to Medit Link specs
        $queryParams = [
            'page' => max(1, intval($params['page'] ?? 1)),
            'per_page' => min(100, max(1, intval($params['per_page'] ?? self::DEFAULT_PAGE_SIZE))),
            'sort' => $params['sort'] ?? '-created_at', // Default sort by newest first
        ];

        // Add optional filters if provided
        if (!empty($params['created_from'])) {
            $queryParams['created_from'] = date('Y-m-d', strtotime($params['created_from']));
        }
        if (!empty($params['created_to'])) {
            $queryParams['created_to'] = date('Y-m-d', strtotime($params['created_to']));
        }
        if (!empty($params['status'])) {
            $queryParams['status'] = $params['status'];
        }

        Log::info('Fetching Medit Link orders', [
            'endpoint' => $endpoint,
            'params' => $queryParams
        ]);

        try {
            $response = Http::withOptions([
                'verify' => false // Only for development
            ])
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->credential->access_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                // REQUIRED header (matches Postman)
                'x-meditlink-client-id' => $this->credential->client_id,
            ])
            ->get($baseUrl . $endpoint, $queryParams);

            if (!$response->successful()) {
                Log::error('Medit Link API error', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body()
                ]);
                throw new \Exception('Failed to fetch orders: ' . ($response->json()['message'] ?? $response->body()));
            }

            $responseData = $response->json();

            // Transform response to standardized format
            return [
                'data' => array_map(function($order) {
                    return [
                        'id' => $order['id'],
                        'created_at' => $order['created_at'],
                        'updated_at' => $order['updated_at'],
                        'status' => $order['status'],
                        'patient' => [
                            'name' => $order['patient']['name'] ?? null,
                            'birth_date' => $order['patient']['birth_date'] ?? null,
                            'gender' => $order['patient']['gender'] ?? null
                        ],
                        'case_info' => $order['case_info'] ?? [],
                        'source_api' => 'Meditlink'
                    ];
                }, $responseData['data'] ?? []),
                'meta' => [
                    'current_page' => $responseData['meta']['current_page'] ?? 1,
                    'total' => $responseData['meta']['total'] ?? 0,
                    'per_page' => $responseData['meta']['per_page'] ?? self::DEFAULT_PAGE_SIZE
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to fetch Medit Link orders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
