<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

class ApiCredential extends Model
{
    protected $fillable = [
        'api_name',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'token_expiry',
        'base_url',             // <— store AUTH base here
        'is_active',
        'additional_config'
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'token_expiry'      => 'datetime',
        'additional_config' => 'array',
    ];

    protected $hidden = [
        'client_secret',
        'access_token',
        'refresh_token',
    ];

    // Encrypt client secret
    protected function clientSecret(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v ? Crypt::decryptString($v) : null,
            set: fn ($v) => $v ? Crypt::encryptString($v) : null,
        );
    }

    // Encrypt access token
    protected function accessToken(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v ? Crypt::decryptString($v) : null,
            set: fn ($v) => $v ? Crypt::encryptString($v) : null,
        );
    }

    // Encrypt refresh token
    protected function refreshToken(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v ? Crypt::decryptString($v) : null,
            set: fn ($v) => $v ? Crypt::encryptString($v) : null,
        );
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expiry ? $this->token_expiry->isPast() : false;
    }

    // Helper: auth base from DB or config
    public function authBase(): string
    {
        return rtrim($this->base_url ?: config('meditlink.auth_base'), '/');
    }

    // Helper: resources base derived from auth base
    public function resourcesBase(): string
    {
        $auth = $this->authBase();
        $res  = str_replace('-auth', '-resources', $auth);
        return rtrim($res, '/');
    }

    // Display name helpers (optional)
    const MEDIT_LINK = 'medit_link';
    const DS_CORE    = 'ds_core';
    const THREESHAPE = '3shape';

    public static function getApiNames()
    {
        return [
            self::MEDIT_LINK => 'Medit Link',
            self::DS_CORE    => 'DS Core',
            self::THREESHAPE => '3Shape',
        ];
    }

    public function getApiDisplayNameAttribute()
    {
        return self::getApiNames()[$this->api_name] ?? $this->api_name;
    }
}
