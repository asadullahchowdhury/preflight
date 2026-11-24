<?php

return [

    /*
    | The GitHub App that posts check runs and PR comments. Create it at
    | https://github.com/settings/apps/new (see docs in apps/cloud/README.md). Its OAuth client
    | (GITHUB_CLIENT_ID / GITHUB_CLIENT_SECRET in config/services.php) is also used to sign people in.
    */
    'github_app' => [
        'id' => env('GITHUB_APP_ID'),
        'slug' => env('GITHUB_APP_SLUG', 'preflight'),
        // PEM contents, or a path to the .pem file.
        'private_key' => env('GITHUB_APP_PRIVATE_KEY'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],

    'github_api' => env('GITHUB_API_URL', 'https://api.github.com'),

    /*
    | CI authenticates with GitHub Actions' OIDC token: no secrets to store. The action requests a token
    | for this audience; the API accepts nothing else.
    */
    'oidc' => [
        'issuer' => 'https://token.actions.githubusercontent.com',
        'audience' => env('PREFLIGHT_OIDC_AUDIENCE', env('APP_URL')),
        // Only APP_ENV=local may point this elsewhere (a fake provider for local testing); anywhere else,
        // trusting another key set would let anyone mint tokens.
        'jwks_url' => env('APP_ENV') === 'local'
            ? env('PREFLIGHT_OIDC_JWKS_URL', 'https://token.actions.githubusercontent.com/.well-known/jwks')
            : 'https://token.actions.githubusercontent.com/.well-known/jwks',
    ],

    /*
    | Local only: sign in as a demo user without GitHub (`/dev/login`), to work on the dashboard.
    | Ignored outside the local environment.
    */
    'dev_login' => (bool) env('PREFLIGHT_DEV_LOGIN', false),

];
