<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThreeShapeCase extends Model
{
    protected $fillable = [
        'api_credential_id',
        'external_id',
        'patient_name',
        'state',
        'created_at_3s',
        'delivery_date',
        'raw',
    ];

    protected $casts = [
        'created_at_3s' => 'datetime',
        'delivery_date' => 'datetime',
        'raw'           => 'array',
    ];

    public function credential()
    {
        return $this->belongsTo(ApiCredential::class, 'api_credential_id');
    }
}
