<?php

return [
    'auth_base'     => env('MEDIT_AUTH_BASE', 'https://stage-openapi-auth.meditlink.com'),
    'api_base'      => env('MEDIT_API_BASE',  'https://stage-openapi-resources.meditlink.com'),

    'client_id'     => env('MEDIT_CLIENT_ID'),
    'client_secret' => env('MEDIT_CLIENT_SECRET'),

    // Must match what you registered and what Postman uses
    'redirect_uri'  => env('MEDIT_REDIRECT_URI', 'http://127.0.0.1:8000/oauth/callback'),

    // MUST include offline_access to receive refresh_token
    'scope'         => env('MEDIT_SCOPE', 'USER GROUP ORDER CASE offline_access'),
];
