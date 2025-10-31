<?php

namespace App\Services;

class ThreeShapePKCEService
{
    public static function generate(): array
    {
        $verifier  = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        return ['verifier'=>$verifier, 'challenge'=>$challenge];
    }
}
