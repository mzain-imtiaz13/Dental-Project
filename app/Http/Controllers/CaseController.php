<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Models\MeditCase;
use App\Services\MeditPersistenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('export') === 'csv') {
            return $this->exportCasesCsv($request);
        }
        if ($request->expectsJson()) {
            return $this->getCasesFromDb($request);
        }
        return view('cases');
    }

    private function getCasesFromDb(Request $request)
    {
        $query = $this->buildBaseQuery($request);

        $cases = $query->with(['credential', 'group'])
            ->orderByDesc('date_created')
            ->orderByDesc('created_at')
            ->get();

        $payload = $cases->map(function (MeditCase $c) {
            return [
                'uuid'        => $c->uuid,
                'name'        => $c->name,
                'status'      => $c->status ?? '-',
                'dateCreated' => optional($c->date_created)->toIso8601String(),
                'dateUpdated' => optional($c->date_updated)->toIso8601String(),
                'dateScanned' => optional($c->date_scanned)->toIso8601String(),
                'patient'     => ['name' => $c->patient_name, 'code' => $c->patient_code],
                'group'       => [
                    'uuid' => $c->group_uuid,
                    'name' => $c->group?->name,
                    'type' => $c->group?->type,
                ],
                'source_api'  => $c->credential?->api_name === ApiCredential::MEDIT_LINK ? 'Meditlink' : ($c->credential?->api_display_name ?? 'Unknown'),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'cases'       => $payload,
                'total_count' => $payload->count(),
                'api_statuses'=> ['database' => ['status' => 'success', 'message' => 'Loaded from DB']],
            ],
        ]);
    }

    private function exportCasesCsv(Request $request)
    {
        $query = $this->buildBaseQuery($request);

        $cases = $query->with(['credential', 'group'])
            ->orderByDesc('date_created')
            ->orderByDesc('created_at')
            ->get();

        $filename = 'cases_export_' . now()->format('Ymd_His') . '.csv';

        $response = new StreamedResponse(function () use ($cases) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'UUID','Name','Patient Name','Patient Code','Status',
                'Group UUID','Group Name','Group Type',
                'Date Created','Date Updated','Date Scanned','Source API',
            ]);

            foreach ($cases as $c) {
                fputcsv($handle, [
                    $c->uuid,
                    $c->name,
                    $c->patient_name,
                    $c->patient_code,
                    $c->status,
                    $c->group_uuid,
                    $c->group?->name,
                    $c->group?->type,
                    optional($c->date_created)->toDateTimeString(),
                    optional($c->date_updated)->toDateTimeString(),
                    optional($c->date_scanned)->toDateTimeString(),
                    $c->credential?->api_name === ApiCredential::MEDIT_LINK ? 'Meditlink' : ($c->credential?->api_display_name ?? 'Unknown'),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-store, no-cache');

        return $response;
    }

    private function buildBaseQuery(Request $request)
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
                if ($payload === null) $payload = json_decode($res->body(), true);
                $cases   = $payload['content'] ?? [];

                (new MeditPersistenceService())->upsertCases($payload, $c, $groupUuid);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'cases'        => $cases,
                        'total_count'  => count($cases),
                        'api_statuses' => [
                            $c->api_name ?? 'medit_link' => ['status' => 'success', 'message' => 'Connected'],
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
