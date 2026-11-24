<?php

namespace App\GitHub;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;
use UnexpectedValueException;

/**
 * Verifies the OIDC token a GitHub Actions job gets from `ACTIONS_ID_TOKEN_REQUEST_URL`: signed by GitHub,
 * issued by GitHub Actions, for our audience, not expired. The claims say which repository the job runs in.
 *
 * @see https://docs.github.com/en/actions/security-for-github-actions/security-hardening-your-deployments/about-security-hardening-with-openid-connect
 */
class OidcVerifier
{
    private const CACHE_KEY = 'github:oidc-jwks';

    /**
     * @return array<string, mixed> the token's claims
     *
     * @throws UnexpectedValueException when the token is not acceptable
     */
    public function verify(string $token): array
    {
        try {
            $claims = $this->decode($token, $this->keys());
        } catch (UnexpectedValueException $e) {
            // GitHub rotates keys: refresh once when the key id is unknown.
            if (! str_contains($e->getMessage(), '"kid"')) {
                throw $e;
            }
            Cache::forget(self::CACHE_KEY);
            $claims = $this->decode($token, $this->keys());
        }

        if (($claims['iss'] ?? null) !== config('preflight.oidc.issuer')) {
            throw new UnexpectedValueException('The token was not issued by GitHub Actions.');
        }

        $audience = (array) ($claims['aud'] ?? []);
        if (! in_array(config('preflight.oidc.audience'), $audience, true)) {
            throw new UnexpectedValueException('The token was issued for a different audience.');
        }

        if (! is_numeric($claims['repository_id'] ?? null)) {
            throw new UnexpectedValueException('The token has no repository_id claim.');
        }

        return $claims;
    }

    /**
     * @param  array<string, Key>  $keys
     * @return array<string, mixed>
     */
    private function decode(string $token, array $keys): array
    {
        try {
            JWT::$leeway = 60;

            return (array) json_decode((string) json_encode(JWT::decode($token, $keys)), true);
        } catch (UnexpectedValueException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new UnexpectedValueException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @return array<string, Key>
     */
    private function keys(): array
    {
        /** @var array<string, mixed> $jwks */
        $jwks = Cache::remember(self::CACHE_KEY, now()->addHour(), fn () => Http::timeout(10)
            ->get((string) config('preflight.oidc.jwks_url'))
            ->throw()
            ->json());

        return JWK::parseKeySet($jwks, 'RS256');
    }
}
