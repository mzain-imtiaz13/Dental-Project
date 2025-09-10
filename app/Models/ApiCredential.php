<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;

class ApiCredential extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'api_name',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'token_expiry',
        'base_url',
        'is_active',
        'additional_config'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'token_expiry' => 'datetime',
        'additional_config' => 'array'
    ];

    protected $hidden = [
        'client_secret',
        'access_token',
        'refresh_token'
    ];

    // API name constants
    const MEDIT_LINK = 'medit_link';
    const DS_CORE = 'ds_core';
    const THREESHAPE = '3shape';

    public static function getApiNames()
    {
        return [
            self::MEDIT_LINK => 'Medit Link',
            self::DS_CORE => 'DS Core',
            self::THREESHAPE => '3Shape'
        ];
    }

    // Encrypt client secret when setting
    protected function clientSecret(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    // Encrypt access token when setting
    protected function accessToken(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    // Encrypt refresh token when setting
    protected function refreshToken(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    // Check if token is expired
    public function isTokenExpired()
    {
        if (!$this->token_expiry) {
            return false;
        }
        
        return $this->token_expiry->isPast();
    }

    // Get API display name
    public function getApiDisplayNameAttribute()
    {
        return self::getApiNames()[$this->api_name] ?? $this->api_name;
    }
}
