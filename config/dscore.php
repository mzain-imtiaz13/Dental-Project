<?php

return [
    // Where the browser-based login screen lives
    'base_host' => env('DSCORE_BASE_HOST', 'https://r2.dscore.com'),

    // API host (token + orders endpoints)
    'auth_host' => env('DSCORE_AUTH_HOST', 'https://api.r2.dscore.com'),

    // Browser login URL (user logs in as dentist here)
    'auth_url'  => env('DSCORE_AUTH_URL', 'https://r2.dscore.com/secureLogin'),

    // OAuth token endpoint (IMPORTANT: sandbox R2 endpoint)
    'token_url' => env('DSCORE_TOKEN_URL', 'https://api.r2.dscore.com/v1beta/auth/token'),

    // Orders endpoint (sandbox R2)
    'orders_url' => env('DSCORE_ORDERS_URL', 'https://api.r2.dscore.com/v1beta/orders'),

    'client_id'     => env('DSCORE_CLIENT_ID'),
    'client_secret' => env('DSCORE_CLIENT_SECRET'),

    // Must match what is configured in DS Core Developer Portal
    'redirect_uri'  => env(
        'DSCORE_REDIRECT_URI',
        rtrim(env('APP_URL', 'http://127.0.0.1:8000'), '/') . '/oauth/callback'
    ),

    // If DS Core publishes scopes, configure here; otherwise DS can ignore it
    'scope' => env('DSCORE_SCOPE', 'openid profile email external_api'),
];