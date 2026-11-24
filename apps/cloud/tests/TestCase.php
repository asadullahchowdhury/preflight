<?php

namespace Tests;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /** @var array{private: string, public: \OpenSSLAsymmetricKey}|null */
    private static ?array $keys = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Pretends to be GitHub Actions' OIDC provider: serves a JWKS and signs tokens with the matching key.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function oidcToken(array $claims = []): string
    {
        $keys = self::keys();
        $details = openssl_pkey_get_details($keys['public']);

        Http::fake([
            'token.actions.githubusercontent.com/*' => Http::response(['keys' => [[
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => 'test-key',
                'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
            ]]]),
        ]);

        return JWT::encode($claims + [
            'iss' => 'https://token.actions.githubusercontent.com',
            'aud' => config('preflight.oidc.audience'),
            'iat' => time(),
            'exp' => time() + 300,
        ], $keys['private'], 'RS256', 'test-key');
    }

    protected function appPrivateKey(): string
    {
        return self::keys()['private'];
    }

    /**
     * @return array{private: string, public: \OpenSSLAsymmetricKey}
     */
    private static function keys(): array
    {
        if (self::$keys === null) {
            // A throwaway key committed with the tests (generating one needs an OpenSSL config on Windows).
            $private = (string) file_get_contents(__DIR__.'/Fixtures/test-key.pem');
            $key = openssl_pkey_get_private($private);
            self::$keys = ['private' => $private, 'public' => openssl_pkey_get_public(openssl_pkey_get_details($key)['key'])];
        }

        return self::$keys;
    }
}
