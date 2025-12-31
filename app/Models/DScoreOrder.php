<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DScoreOrder extends Model
{
    protected $table = 'dscore_orders';

    protected $fillable = [
        'order_id',
        'credential_id',
        'order_number',
        'status',
        'order_type',
        'patient_name',
        'patient_id',
        'practice_name',
        'practice_id',
        'lab_name',
        'lab_id',
        'order_date',
        'due_date',
        'shipped_date',
        'raw',
    ];

    protected $casts = [
        'order_date'   => 'datetime',
        'due_date'     => 'datetime',
        'shipped_date' => 'datetime',
        'raw'          => 'array',
    ];

    public function credential()
    {
        return $this->belongsTo(ApiCredential::class, 'credential_id');
    }
}
