<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Models\MeditCase;
use App\Models\ThreeShapeCase;
use App\Services\MeditPersistenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseController extends Controller
{
    /**
     * GET /cases
     * - HTML → render Blade
     * - JSON → return merged list from DB (Medit + 3Shape)
     * - export=csv → stream CSV
     */
    public function index(Request $request)
    {
        // CSV export request
        if ($request->query('export') === 'csv') {
            return $this->exportCasesCsv($request);
        }

        // Frontend table AJAX calls this with Accept: application/json
        if ($request->expectsJson()) {
            return $this->getCasesFromDb($request);
        }

        // Normal page load
        return view('cases');
    }

    /**
     * Pull cases from BOTH sources (Medit + 3Shape),
     * apply filters (status, patient, groupType where possible),
     * then merge, sort, and return JSON in the shape the frontend expects.
     */
    private function getCasesFromDb(Request $request)
    {
        // --- 1) Fetch Medit cases (if table exists/populated) ---
        $meditQuery = $this->buildMeditQuery($request);

        $meditCases = $meditQuery
            ->with(['credential', 'group'])
            ->orderByDesc('date_created')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (MeditCase $c) {
                return [
                    'uuid'         => $c->uuid,
                    'name'         => $c->name,
                    'status'       => $c->status ?? '-',
                    'dateCreated'  => optional($c->date_created)->toIso8601String(),
                    'dateUpdated'  => optional($c->date_updated)->toIso8601String(),
                    'dateScanned'  => optional($c->date_scanned)->toIso8601String(),
                    'patient'      => [
                        'name' => $c->patient_name,
                        'code' => $c->patient_code,
                    ],
                    'group'        => [
                        'uuid' => $c->group_uuid,
                        'name' => optional($c->group)->name,
                        'type' => optional($c->group)->type,
                    ],
                    'source_api'   => $c->credential?->api_name === ApiCredential::MEDIT_LINK
                        ? 'Meditlink'
                        : ($c->credential?->api_display_name ?? 'Meditlink'),
                ];
            });

        // --- 2) Fetch 3Shape cases ---
        $shapeQuery = $this->buildThreeShapeQuery($request);

        $threeShapeCases = $shapeQuery
            ->with(['credential'])
            ->orderByDesc('created_at_3s')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ThreeShapeCase $c) {
                return [
                    'uuid'         => $c->external_id,
                    'name'         => $c->patient_name ?: '(No Name)',
                    'status'       => $c->state ?? '-',
                    'dateCreated'  => optional($c->created_at_3s)->toIso8601String(),
                    'dateUpdated'  => optional($c->updated_at)->toIso8601String(),
                    'dateScanned'  => null, // 3Shape doesn't expose date_scanned the same way
                    'patient'      => [
                        'name' => $c->patient_name,
                        'code' => null,
                    ],
                    // We don't yet have "group" context for 3Shape, so fill with '-'
                    'group'        => [
                        'uuid' => null,
                        'name' => null,
                        'type' => null,
                    ],
                    'source_api'   => '3Shape',
                ];
            });

        // --- 3) Merge + sort newest first by dateCreated ---
        $merged = $meditCases
            ->concat($threeShapeCases)
            ->sortByDesc(function ($item) {
                // prefer dateCreated from source if available
                return $item['dateCreated'] ?? $item['dateUpdated'] ?? null;
            })
            ->values();

        // --- 4) Apply "source" tab filter on merged level if provided ---
        // Frontend uses tab buttons with data-source="Meditlink" / "3Shape"
        if ($request->filled('source')) {
            $sourceFilter = $request->string('source');
            $merged = $merged->filter(function ($row) use ($sourceFilter) {
                return ($row['source_api'] ?? '') === $sourceFilter;
            })->values();
        }

        // Done
        return response()->json([
            'success' => true,
            'data'    => [
                'cases'        => $merged,
                'total_count'  => $merged->count(),
                'api_statuses' => [
                    'database' => [
                        'status'  => 'success',
                        'message' => 'Loaded from DB (Medit + 3Shape)',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Build query for Medit cases only, honoring filters.
     * Filters supported:
     *  - status
     *  - patient
     *  - groupType
     */
    private function buildMeditQuery(Request $request)
    {
        $query = MeditCase::query();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('patient')) {
            $query->where('patient_name', 'like', '%'.$request->string('patient').'%');
        }

        if ($request->filled('groupType')) {
            $gtype = $request->string('groupType');
            $query->whereHas('group', function ($q) use ($gtype) {
                $q->where('type', $gtype);
            });
        }

        return $query;
    }

    /**
     * Build query for 3Shape cases only.
     * Filters supported:
     *  - status  -> maps to 3Shape "state"
     *  - patient -> matches patient_name
     * groupType doesn't apply to 3Shape yet.
     */
    private function buildThreeShapeQuery(Request $request)
    {
        $query = ThreeShapeCase::query();

        if ($request->filled('status')) {
            $query->where('state', $request->string('status'));
        }

        if ($request->filled('patient')) {
            $query->where('patient_name', 'like', '%'.$request->string('patient').'%');
        }

        return $query;
    }

    /**
     * Export combined list as CSV (Medit + 3Shape).
     */
    private function exportCasesCsv(Request $request)
    {
        // re-use the same logic as getCasesFromDb() but don't sort twice in here,
        // we just want the merged map and output rows
        $meditQuery  = $this->buildMeditQuery($request)->with(['credential', 'group'])
                        ->orderByDesc('date_created')->orderByDesc('created_at');
        $shapeQuery  = $this->buildThreeShapeQuery($request)->with(['credential'])
                        ->orderByDesc('created_at_3s')->orderByDesc('created_at');

        $meditRows = $meditQuery->get()->map(function (MeditCase $c) {
            return [
                'uuid'        => $c->uuid,
                'patient'     => $c->patient_name,
                'status'      => $c->status ?? '-',
                'created'     => optional($c->date_created)->toDateTimeString(),
                'updated'     => optional($c->date_updated)->toDateTimeString(),
                'scanned'     => optional($c->date_scanned)->toDateTimeString(),
                'group_uuid'  => $c->group_uuid,
                'group_name'  => optional($c->group)->name,
                'group_type'  => optional($c->group)->type,
                'source'      => 'Meditlink',
            ];
        });

        $shapeRows = $shapeQuery->get()->map(function (ThreeShapeCase $c) {
            return [
                'uuid'        => $c->external_id,
                'patient'     => $c->patient_name,
                'status'      => $c->state ?? '-',
                'created'     => optional($c->created_at_3s)->toDateTimeString(),
                'updated'     => optional($c->updated_at)->toDateTimeString(),
                'scanned'     => null,
                'group_uuid'  => null,
                'group_name'  => null,
                'group_type'  => null,
                'source'      => '3Shape',
            ];
        });

        $allRows = $meditRows->concat($shapeRows)
            ->sortByDesc('created')
            ->values();

        $filename = 'cases_export_' . now()->format('Ymd_His') . '.csv';

        $response = new StreamedResponse(function () use ($allRows) {
            $handle = fopen('php://output', 'w');

            // header
            fputcsv($handle, [
                'UUID',
                'Patient',
                'Status',
                'Created',
                'Updated',
                'Scanned',
                'Group UUID',
                'Group Name',
                'Group Type',
                'Source',
            ]);

            foreach ($allRows as $row) {
                fputcsv($handle, [
                    $row['uuid'],
                    $row['patient'],
                    $row['status'],
                    $row['created'],
                    $row['updated'],
                    $row['scanned'],
                    $row['group_uuid'],
                    $row['group_name'],
                    $row['group_type'],
                    $row['source'],
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-store, no-cache');

        return $response;
    }

    /**
     * /api-credentials/{credential}/cases
     * Medit-only live pull from API + persist.
     * (Your original code, kept mostly as-is.)
     */
    public function byCredential(Request $request, ApiCredential $apiCredential)
    {
        if ($request->expectsJson()) {
            try {
                $c = $apiCredential;

                $apiBase   = $c->resourcesBase();
                $url       = $apiBase . '/v1/cases/search';
                $groupUuid = $this->resolveGroupUuid($c);

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

                $cases = $payload['content'] ?? [];

                (new MeditPersistenceService())->upsertCases($payload, $c, $groupUuid);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'cases'        => $cases,
                        'total_count'  => count($cases),
                        'api_statuses' => [
                            $c->api_name ?? 'medit_link' => [
                                'status'  => 'success',
                                'message' => 'Connected',
                            ],
                        ],
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::error('Cases byCredential failed', [
                    'cred_id' => $apiCredential->id,
                    'error'   => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch cases: '.$e->getMessage(),
                ], 200);
            }
        }

        return view('cases_by_credential', ['credential' => $apiCredential]);
    }

    /**
     * Try to resolve Medit group UUID for a credential.
     */
    private function resolveGroupUuid(ApiCredential $c): ?string
    {
        $fromConfig = $c->additional_config['group_uuid'] ?? null;
        if (!empty($fromConfig)) return $fromConfig;

        $envUuid = env('MEDIT_GROUP_UUID');
        if (!empty($envUuid)) {
            $this->cacheGroupUuid($c, $envUuid);
            return $envUuid;
        }

        try {
            $res = Http::withOptions(['verify' => false])
                ->withHeaders([
                    'Authorization'         => 'Bearer ' . $c->access_token,
                    'Accept'                => 'application/json',
                    'Content-Type'          => 'application/json',
                    'x-meditlink-client-id' => $c->client_id,
                ])->get($c->resourcesBase().'/v1/groups');

            if ($res->successful()) {
                $data = $res->json();
                $uuid = is_array($data) && isset($data[0]['uuid']) ? $data[0]['uuid'] : null;
                if ($uuid) {
                    $this->cacheGroupUuid($c, $uuid);
                    return $uuid;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to auto-resolve group uuid', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function cacheGroupUuid(ApiCredential $c, string $uuid): void
    {
        $cfg = $c->additional_config ?? [];
        $cfg['group_uuid'] = $uuid;
        $c->additional_config = $cfg;
        $c->save();
    }
}
