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
    | Private beta. While on, people who sign in join a waitlist; an admin approves them on /admin/beta.
    | Admins are GitHub usernames (comma-separated) and are always approved.
    */
    'beta' => (bool) env('PREFLIGHT_BETA', true),

    'admins' => array_values(array_filter(array_map(
        static fn (string $login) => strtolower(trim($login)),
        explode(',', (string) env('PREFLIGHT_ADMINS', '')),
    ))),

    /*
    | Local only: sign in as a demo user without GitHub (`/dev/login`), to work on the dashboard.
    | Ignored outside the local environment.
    */
    'dev_login' => (bool) env('PREFLIGHT_DEV_LOGIN', false),

    /*
    | Search and link-preview defaults (resources/views/partials/seo.blade.php). Pages can override the
    | title, description and canonical path with ->withViewData(['meta' => [...]]).
    */
    'seo' => [
        'tagline' => 'Catch table-locking migrations before they ship',
        'description' => 'Preflight reviews every Laravel migration in your pull requests and tells you how MySQL will run it: instantly, in place, or by copying the table while writes wait. With production table sizes, duration estimates and a safer rewrite.',
        'image' => '/og-image.png',
        // Only these routes are indexed; everything else (the dashboard, settings, admin) is noindex.
        'indexable_routes' => ['home', 'docs'],
    ],

];
