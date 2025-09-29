<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeditCase extends Model
{
    protected $table = 'medit_cases';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'credential_id',
        'group_uuid',
        'name',
        'status',
        'date_created',
        'date_updated',
        'date_scanned',
        'patient_name',
        'patient_code',
        'tags',
        'raw',
    ];

    protected $casts = [
        'date_created' => 'datetime',
        'date_updated' => 'datetime',
        'date_scanned' => 'datetime',
        'tags'         => 'array',
        'raw'          => 'array',
    ];

    public function credential() { return $this->belongsTo(ApiCredential::class, 'credential_id'); }
    public function group()      { return $this->belongsTo(MeditGroup::class, 'group_uuid', 'uuid'); }
    public function orders()     { return $this->hasMany(MeditOrder::class, 'case_uuid', 'uuid'); }
}
