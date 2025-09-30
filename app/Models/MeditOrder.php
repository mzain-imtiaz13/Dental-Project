<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeditOrder extends Model
{
    protected $table = 'medit_orders';
    protected $primaryKey = 'order_number';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'order_number',
        'credential_id',
        'case_uuid',
        'buyer_group_uuid',
        'seller_group_uuid',
        'buyer_name',
        'buyer_type',
        'seller_name',
        'seller_type',
        'status',
        'date_created',
        'date_updated',
        'date_desired_delivery',
        'raw',
    ];

    protected $casts = [
        'date_created'          => 'datetime',
        'date_updated'          => 'datetime',
        'date_desired_delivery' => 'datetime',
        'raw'                   => 'array',
    ];

    public function credential() { return $this->belongsTo(ApiCredential::class, 'credential_id'); }
    public function case()       { return $this->belongsTo(MeditCase::class, 'case_uuid', 'uuid'); }
    public function buyerGroup() { return $this->belongsTo(MeditGroup::class, 'buyer_group_uuid', 'uuid'); }
    public function sellerGroup(){ return $this->belongsTo(MeditGroup::class, 'seller_group_uuid', 'uuid'); }
}
