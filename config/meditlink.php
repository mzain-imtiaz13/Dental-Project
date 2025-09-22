<?php

return [
    // OAuth (AUTH server)
    'auth_base'     => env('MEDIT_AUTH_BASE', 'https://stage-openapi-auth.meditlink.com'),

    // Resource server (used as a fallback only; normally we derive from auth)
    'api_base'      => env('MEDIT_API_BASE', 'https://stage-openapi-resources.meditlink.com'),

    'client_id'     => env('MEDIT_CLIENT_ID'),
    'client_secret' => env('MEDIT_CLIENT_SECRET'),
    'redirect_uri'  => env('MEDIT_REDIRECT_URI', 'http://127.0.0.1:8000/oauth/callback'),
    'scope'         => env('MEDIT_SCOPE', 'USER GROUP'),
];
