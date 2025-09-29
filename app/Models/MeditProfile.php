<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeditProfile extends Model
{
    protected $table = 'medit_profiles';

    protected $fillable = [
        'credential_id',
        'email',
        'name',
        'group_uuid',
        'date_created',
        'date_updated',
        'profile_image',
        'raw',
    ];

    protected $casts = [
        'date_created' => 'datetime',
        'date_updated' => 'datetime',
        'profile_image'=> 'array',
        'raw'          => 'array',
    ];

    public function credential() { return $this->belongsTo(ApiCredential::class, 'credential_id'); }
    public function group()      { return $this->belongsTo(MeditGroup::class, 'group_uuid', 'uuid'); }
}
