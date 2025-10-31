<?php

return [
    'client_id'     => env('THREESHAPE_CLIENT_ID', ''),
    'identity_base' => rtrim(env('THREESHAPE_IDENTITY_BASE', 'https://staging-identity.3shape.com'), '/'),
    'resource_base' => rtrim(env('THREESHAPE_RESOURCE_BASE', 'https://staging-eumetadata.3shapecommunicate.com'), '/'),

    // Shared callback (must match one of the three client-allowed URLs)
    'redirect_uri'  => env(
        'THREESHAPE_REDIRECT_URI',
        rtrim(env('APP_URL', 'http://127.0.0.1:8000'), '/').'/oauth/callback'
    ),

    'scope' => env('THREESHAPE_SCOPE',
        'openid api offline_access communicate.connections.manage data.companies.read_only data.users.read_only'
    ),
];
