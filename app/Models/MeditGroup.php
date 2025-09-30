<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeditGroup extends Model
{
    protected $table = 'medit_groups';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'description',
        'date_created',
        'date_updated',
        'raw',
    ];

    protected $casts = [
        'date_created' => 'datetime',
        'date_updated' => 'datetime',
        'raw'          => 'array',
    ];

    public function profiles()       { return $this->hasMany(MeditProfile::class, 'group_uuid', 'uuid'); }
    public function cases()          { return $this->hasMany(MeditCase::class,    'group_uuid', 'uuid'); }
    public function ordersAsBuyer()  { return $this->hasMany(MeditOrder::class,   'buyer_group_uuid', 'uuid'); }
    public function ordersAsSeller() { return $this->hasMany(MeditOrder::class,   'seller_group_uuid', 'uuid'); }
}
