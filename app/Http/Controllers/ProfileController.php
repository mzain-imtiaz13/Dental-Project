<?php

namespace App\Http\Controllers;

use App\Models\MeditProfile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            $profiles = MeditProfile::query()
                ->with(['credential', 'group'])
                ->orderBy('name')
                ->get()
                ->map(function (MeditProfile $p) {
                    return [
                        'id'          => $p->id,
                        'name'        => $p->name,
                        'email'       => $p->email,
                        'group_uuid'  => $p->group_uuid,
                        'group_name'  => $p->group?->name,
                        'group_type'  => $p->group?->type,
                        'created_at'  => optional($p->date_created)->toIso8601String(),
                        'updated_at'  => optional($p->date_updated)->toIso8601String(),
                        'api'         => $p->credential?->api_display_name ?? 'Meditlink',

                        // details for modal
                        'details'     => [
                            'id'            => $p->id,
                            'name'          => $p->name,
                            'email'         => $p->email,
                            'date_created'  => optional($p->date_created)->toIso8601String(),
                            'date_updated'  => optional($p->date_updated)->toIso8601String(),
                            'group'         => [
                                'uuid' => $p->group_uuid,
                                'name' => $p->group?->name,
                                'type' => $p->group?->type,
                            ],
                            'credential'    => [
                                'id'   => $p->credential?->id,
                                'api'  => $p->credential?->api_name,
                                'name' => $p->credential?->api_display_name,
                            ],
                            'profile_image' => $p->profile_image,
                            'raw'           => $p->raw,
                        ],
                    ];
                })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'profiles'    => $profiles,
                    'total_count' => $profiles->count(),
                ],
            ]);
        }

        return view('profiles');
    }
}
