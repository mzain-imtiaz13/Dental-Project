<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Models\DScoreOrder;
use App\Models\MeditOrder;
use App\Services\MeditPersistenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\DScoreService;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return $this->getOrdersFromDb($request);
        }
        return view('orders');
    }

    private function getOrdersFromDb(Request $request)
    {
        $source = $request->get('source', 'all'); // 'all', 'medit', 'dscore'

        $meditOrders = collect();
        $dscoreOrders = collect();

        // Fetch Medit orders
        if ($source === 'all' || $source === 'medit') {
            $meditQuery = MeditOrder::query()
                ->with(['credential', 'case'])
                ->orderByDesc('date_created')
                ->orderByDesc('created_at');

            if ($request->filled('status')) {
                $meditQuery->where('status', $request->string('status'));
            }

            $meditOrders = $meditQuery->get()->map(function (MeditOrder $o) {
                return [
                    'id'         => (int)$o->order_number,
                    'order_id'   => $o->order_number,
                    'created_at' => optional($o->date_created)->toIso8601String(),
                    'updated_at' => optional($o->date_updated)->toIso8601String(),
                    'status'     => $o->status ?? '-',
                    'patient'    => [
                        'name' => $o->case?->patient_name,
                        'code' => $o->case?->patient_code,
                    ],
                    'case'       => [
                        'uuid'   => $o->case_uuid,
                        'name'   => $o->case?->name,
                        'status' => $o->case?->status,
                    ],
                    'buyer'      => $o->buyer_name,
                    'seller'     => $o->seller_name,
                    'source_api' => 'Meditlink',
                    'details'    => [
                        'status'                => $o->status,
                        'date_created'          => optional($o->date_created)->toIso8601String(),
                        'date_updated'          => optional($o->date_updated)->toIso8601String(),
                        'date_desired_delivery' => optional($o->date_desired_delivery)->toIso8601String(),
                        'buyer' => [
                            'uuid' => $o->buyer_group_uuid,
                            'name' => $o->buyer_name,
                            'type' => $o->buyer_type,
                        ],
                        'seller' => [
                            'uuid' => $o->seller_group_uuid,
                            'name' => $o->seller_name,
                            'type' => $o->seller_type,
                        ],
                        'case' => [
                            'uuid'         => $o->case_uuid,
                            'name'         => $o->case?->name,
                            'status'       => $o->case?->status,
                            'patient_name' => $o->case?->patient_name,
                            'patient_code' => $o->case?->patient_code,
                        ],
                        'credential' => [
                            'id'  => $o->credential?->id,
                            'api' => $o->credential?->api_name,
                            'name'=> $o->credential?->api_display_name,
                        ],
                        'raw' => $o->raw,
                    ],
                ];
            });
        }

        // Fetch DS Core orders
        if ($source === 'all' || $source === 'dscore') {
            $dscoreQuery = DScoreOrder::query()
                ->with(['credential'])
                ->orderByDesc('order_date')
                ->orderByDesc('created_at');

            if ($request->filled('status')) {
                $dscoreQuery->where('status', $request->string('status'));
            }

            $dscoreOrders = $dscoreQuery->get()->map(function (DScoreOrder $o) {
                return [
                    'id'         => $o->id,
                    'order_id'   => $o->order_id,
                    'order_number' => $o->order_number,
                    'created_at' => optional($o->order_date)->toIso8601String(),
                    'updated_at' => optional($o->updated_at)->toIso8601String(),
                    'status'     => $o->status ?? '-',
                    'patient'    => [
                        'name' => $o->patient_name,
                        'id'   => $o->patient_id,
                    ],
                    'buyer'      => $o->practice_name,
                    'seller'     => $o->lab_name,
                    'source_api' => 'DS Core',
                    'order_type' => $o->order_type,
                    'due_date'   => optional($o->due_date)->toIso8601String(),
                    'details'    => [
                        'status'       => $o->status,
                        'order_type'   => $o->order_type,
                        'order_number' => $o->order_number,
                        'order_id'     => $o->order_id,
                        'date_created' => optional($o->order_date)->toIso8601String(),
                        'due_date'     => optional($o->due_date)->toIso8601String(),
                        'shipped_date' => optional($o->shipped_date)->toIso8601String(),
                        'patient' => [
                            'name' => $o->patient_name,
                            'id'   => $o->patient_id,
                        ],
                        'practice' => [
                            'name' => $o->practice_name,
                            'id'   => $o->practice_id,
                        ],
                        'lab' => [
                            'name' => $o->lab_name,
                            'id'   => $o->lab_id,
                        ],
                        'credential' => [
                            'id'  => $o->credential?->id,
                            'api' => $o->credential?->api_name,
                            'name'=> $o->credential?->api_display_name,
                        ],
                        'raw' => $o->raw,
                    ],
                ];
            });
        }

        // Merge and sort by created_at descending
        $allOrders = $meditOrders->concat($dscoreOrders)
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'orders'       => $allOrders,
                'total_count'  => $allOrders->count(),
                'medit_count'  => $meditOrders->count(),
                'dscore_count' => $dscoreOrders->count(),
                'api_statuses' => ['database' => ['status' => 'success', 'message' => 'Loaded from DB']],
            ],
        ]);
    }

    public function byCredential(Request $request, ApiCredential $apiCredential)
    {
       if ($request->expectsJson()) {
    try {
        $c = $apiCredential;

        if ($c->api_name === ApiCredential::DS_CORE) {
            // DS Core: fetch directly from DS Core orders endpoint
            $svc     = new DScoreService();
            $payload = $svc->orders($c, [
                // Adjust query params if DS Core supports pagination/filters
            ]);

            $orders = is_array($payload) ? $payload : [];

            return response()->json([
                'success' => true,
                'data' => [
                    'orders'       => $orders,
                    'total_count'  => is_array($orders) ? count($orders) : 0,
                    'api_statuses' => [
                        $c->api_name => ['status' => 'success', 'message' => 'Connected (DS Core)'],
                    ],
                ],
            ]);
        }

        // Existing Medit flow (unchanged)
        $apiBase   = $c->resourcesBase();
        $url       = $apiBase . '/v1/orders/search';
        $groupUuid = $c->additional_config['group_uuid'] ?? env('MEDIT_GROUP_UUID');

        $headers = [
            'Authorization'         => 'Bearer ' . $c->access_token,
            'Accept'                => 'application/json',
            'Content-Type'          => 'application/json',
            'x-meditlink-client-id' => $c->client_id,
        ];
        if ($groupUuid) {
            $headers['x-meditlink-group-uuid'] = $groupUuid;
        }

        $res = Http::withOptions(['verify' => false])
            ->withHeaders($headers)
            ->get($url, [
                'schema' => 'latest',
                'size'   => (int) $request->get('size', 20),
                'page'   => (int) $request->get('page', 0),
                'start'  => 0,
                'end'    => 253402300799000,
            ]);

        if ($res->status() === 401) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized/expired token. Please re-authorize.',
            ], 200);
        }

        if (!$res->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed: '.$res->status().' '.$res->body(),
            ], 200);
        }

        $payload = $res->json();
        if ($payload === null) {
            $payload = json_decode($res->body(), true);
        }
        if (!is_array($payload)) {
            Log::warning('Orders response not array/JSON', ['body' => $res->body()]);
            $payload = ['content' => []];
        }

        (new MeditPersistenceService())->upsertOrders($payload, $c);

        $content = $payload['content'] ?? (is_array($payload) ? $payload : []);

        return response()->json([
            'success' => true,
            'data' => [
                'orders'       => $content,
                'total_count'  => is_array($content) ? count($content) : 0,
                'api_statuses' => [
                    $c->api_name ?? 'medit_link' => ['status' => 'success', 'message' => 'Connected'],
                ],
            ],
        ]);
    } catch (\Throwable $e) {
        Log::error('Orders byCredential failed', [
            'cred_id' => $apiCredential->id,
            'error'   => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to fetch orders: '.$e->getMessage(),
        ], 200);
    }
}

        return view('orders_by_credential', ['credential' => $apiCredential]);
    }
}
