<?php

namespace App\Services;

use App\Models\ApiCredential;
use App\Models\MeditCase;
use App\Models\MeditGroup;
use App\Models\MeditOrder;
use App\Models\MeditProfile;
use Carbon\Carbon;

class MeditPersistenceService
{
    /** Upsert /v1/me (connectivity) and its group. */
    public function upsertConnectivity(array $me, ApiCredential $cred): void
    {
        // Group
        if (!empty($me['group']) && is_array($me['group'])) {
            $g = $me['group'];

            MeditGroup::updateOrCreate(
                ['uuid' => $g['uuid'] ?? null],
                [
                    'name'         => $g['name']         ?? null,
                    'type'         => $g['type']         ?? null,
                    'description'  => $g['description']  ?? null,
                    'date_created' => $this->ts($g['dateCreated'] ?? null),
                    'date_updated' => $this->ts($g['dateUpdated'] ?? null),
                    'raw'          => $g,
                ]
            );
        }

        // Profile
        MeditProfile::updateOrCreate(
            ['email' => $me['email'] ?? null],
            [
                'credential_id'=> $cred->id,
                'name'         => $me['name'] ?? null,
                'group_uuid'   => $me['group']['uuid'] ?? null,
                'date_created' => $this->ts($me['dateCreated'] ?? null),
                'date_updated' => $this->ts($me['dateUpdated'] ?? null),
                'profile_image'=> $me['profileImage'] ?? null,
                'raw'          => $me,
            ]
        );
    }

    /** Upsert cases (from /v1/cases/search). */
    public function upsertCases(array $payload, ApiCredential $cred, ?string $groupUuid): void
    {
        $list = $payload['content'] ?? [];
        if (!is_array($list)) return;

        // Ensure group row exists (at least with UUID)
        if ($groupUuid) {
            MeditGroup::firstOrCreate(['uuid' => $groupUuid], ['raw' => null]);
        }

        foreach ($list as $c) {
            MeditCase::updateOrCreate(
                ['uuid' => $c['uuid']],
                [
                    'credential_id' => $cred->id,
                    'group_uuid'    => $groupUuid,
                    'name'          => $c['name']   ?? null,
                    'status'        => $c['status'] ?? null,
                    'date_created'  => $this->ts($c['dateCreated'] ?? null),
                    'date_updated'  => $this->ts($c['dateUpdated'] ?? null),
                    'date_scanned'  => $this->ts($c['dateScanned'] ?? null),
                    'patient_name'  => $c['patient']['name'] ?? null,
                    'patient_code'  => $c['patient']['code'] ?? null,
                    'tags'          => $c['tags'] ?? null,
                    'raw'           => $c,
                ]
            );
        }
    }

    /** Upsert orders (from /v1/orders or /v1/orders/search). */
    public function upsertOrders(array $payload, ApiCredential $cred): void
    {
        $list = $payload['content'] ?? $payload; // /v1/orders returns {content:[]} or an array
        if (!is_array($list)) return;

        foreach ($list as $o) {
            // Groups
            $buyer  = $o['buyer']  ?? [];
            $seller = $o['seller'] ?? [];

            if (!empty($buyer['uuid'])) {
                MeditGroup::updateOrCreate(
                    ['uuid' => $buyer['uuid']],
                    [
                        'name'         => $buyer['name'] ?? null,
                        'type'         => $buyer['type'] ?? null,
                        'raw'          => null, // keep light; orders payload already stored in order.raw
                    ]
                );
            }
            if (!empty($seller['uuid'])) {
                MeditGroup::updateOrCreate(
                    ['uuid' => $seller['uuid']],
                    [
                        'name'         => $seller['name'] ?? null,
                        'type'         => $seller['type'] ?? null,
                        'raw'          => null,
                    ]
                );
            }

            // Case (orders embed a light case object)
            $case = $o['case'] ?? null;
            if (is_array($case) && !empty($case['uuid'])) {
                MeditCase::updateOrCreate(
                    ['uuid' => $case['uuid']],
                    [
                        'credential_id' => $cred->id,
                        // If we can deduce seller is LAB, store it as group on the case (best effort)
                        'group_uuid'    => $seller['uuid'] ?? null,
                        'name'          => $case['name']   ?? null,
                        'status'        => $case['status'] ?? null,
                        'patient_name'  => $case['patient']['name'] ?? null,
                        'patient_code'  => $case['patient']['code'] ?? null,
                        'tags'          => $case['tags'] ?? null,
                        'raw'           => null, // raw persisted on first full cases sync
                    ]
                );
            }

            // Order
            if (!isset($o['orderNumber'])) continue;

            MeditOrder::updateOrCreate(
                ['order_number' => (int)$o['orderNumber']],
                [
                    'credential_id'        => $cred->id,
                    'case_uuid'            => $case['uuid'] ?? null,
                    'buyer_group_uuid'     => $buyer['uuid'] ?? null,
                    'seller_group_uuid'    => $seller['uuid'] ?? null,
                    'buyer_name'           => $buyer['name'] ?? null,
                    'buyer_type'           => $buyer['type'] ?? null,
                    'seller_name'          => $seller['name'] ?? null,
                    'seller_type'          => $seller['type'] ?? null,
                    'status'               => $o['status'] ?? null,
                    'date_created'         => $this->ts($o['dateCreated'] ?? null),
                    'date_updated'         => $this->ts($o['dateUpdated'] ?? null),
                    'date_desired_delivery'=> $this->ts($o['dateDesiredDelivery'] ?? null),
                    'raw'                  => $o,
                ]
            );
        }
    }

    private function ts(?string $iso): ?Carbon
    {
        if (!$iso) return null;
        try { return Carbon::parse($iso); } catch (\Throwable) { return null; }
    }
}
