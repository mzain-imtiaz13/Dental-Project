<?php

namespace App\Http\Controllers;

use App\Models\MeditGroup;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            $groups = MeditGroup::query()
                ->withCount(['profiles', 'cases', 'ordersAsBuyer', 'ordersAsSeller'])
                ->orderBy('name')
                ->get()
                ->map(function (MeditGroup $g) {
                    return [
                        'uuid'        => $g->uuid,
                        'name'        => $g->name,
                        'type'        => $g->type,
                        'description' => $g->description,
                        'created_at'  => optional($g->date_created)->toIso8601String(),
                        'updated_at'  => optional($g->date_updated)->toIso8601String(),
                        'profiles'    => $g->profiles_count,
                        'cases'       => $g->cases_count,
                        'orders_buy'  => $g->orders_as_buyer_count,
                        'orders_sell' => $g->orders_as_seller_count,
                    ];
                })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'groups'      => $groups,
                    'total_count' => $groups->count(),
                ],
            ]);
        }

        return view('groups');
    }
}
