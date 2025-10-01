<?php

namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Models\MeditCase;
use App\Services\MeditPersistenceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return $this->getCasesFromDb($request);
        }
        return view('cases');
    }

    /**
     * Return cases from DB (used by Cases tab).
     */
    private function getCasesFromDb(Request $request)
    {
        $query = MeditCase::query()
            ->with(['credential', 'group'])
            ->orderByDesc('date_created')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('patient')) {
            $query->where('patient_name', 'like', '%'.$request->string('patient').'%');
        }

        $cases = $query->get();

        $payload = $cases->map(function (MeditCase $c) {
            $sourceApi = $c->credential?->api_name === ApiCredential::MEDIT_LINK
                ? 'Meditlink'
                : ($c->credential?->api_display_name ?? 'Unknown');

            return [
                // table fields
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
                'source_api'  => $sourceApi,

                // modal details
                'details' => [
                    'status'        => $c->status,
                    'date_created'  => optional($c->date_created)->toIso8601String(),
                    'date_updated'  => optional($c->date_updated)->toIso8601String(),
                    'date_scanned'  => optional($c->date_scanned)->toIso8601String(),
                    'patient'       => ['name' => $c->patient_name, 'code' => $c->patient_code],
                    'group'         => [
                        'uuid' => $c->group_uuid,
                        'name' => $c->group?->name,
                        'type' => $c->group?->type,
                    ],
                    'credential'    => [
                        'id'   => $c->credential?->id,
                        'api'  => $c->credential?->api_name,
                        'name' => $c->credential?->api_display_name,
                    ],
                    'source_api'    => $sourceApi,
                    'tags'          => $c->tags,
                    'raw'           => $c->raw,
                ],
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

    /* ------- your existing “remote fetch + persist” endpoints + helpers remain unchanged ------- */
}
