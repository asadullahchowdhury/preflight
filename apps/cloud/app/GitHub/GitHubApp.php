<?php

namespace App\GitHub;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the GitHub API as the Preflight GitHub App: as the App itself (JWT), or as one installation.
 */
class GitHubApp
{
    public function configured(): bool
    {
        return filled(config('preflight.github_app.id')) && filled(config('preflight.github_app.private_key'));
    }

    public function slug(): string
    {
        return (string) config('preflight.github_app.slug');
    }

    public function installUrl(): string
    {
        return 'https://github.com/apps/'.$this->slug().'/installations/new';
    }

    /**
     * Requests as the App: installation lookups and installation tokens.
     */
    public function asApp(): PendingRequest
    {
        return $this->client()->withToken($this->jwt());
    }

    /**
     * Requests as one installation: check runs, PR comments, repository lists.
     */
    public function asInstallation(int $installationId): PendingRequest
    {
        return $this->client()->withToken($this->installationToken($installationId));
    }

    /**
     * Requests as a signed-in user (their GitHub App user token).
     */
    public function asUser(string $token): PendingRequest
    {
        return $this->client()->withToken($token);
    }

    public function installationToken(int $installationId): string
    {
        // Installation tokens live for an hour; reuse them for 50 minutes.
        return Cache::remember("github:installation-token:{$installationId}", now()->addMinutes(50), function () use ($installationId): string {
            $token = $this->asApp()
                ->post("/app/installations/{$installationId}/access_tokens")
                ->throw()
                ->json('token');

            if (! is_string($token)) {
                throw new RuntimeException("GitHub returned no token for installation {$installationId}.");
            }

            return $token;
        });
    }

    public function forgetInstallationToken(int $installationId): void
    {
        Cache::forget("github:installation-token:{$installationId}");
    }

    public function jwt(): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('The GitHub App is not configured: set GITHUB_APP_ID and GITHUB_APP_PRIVATE_KEY.');
        }

        $now = time();

        return JWT::encode([
            'iat' => $now - 60,      // allow for clock drift
            'exp' => $now + 9 * 60,  // GitHub allows at most 10 minutes
            'iss' => (string) config('preflight.github_app.id'),
        ], $this->privateKey(), 'RS256');
    }

    private function privateKey(): string
    {
        $key = (string) config('preflight.github_app.private_key');
        if (! str_contains($key, '-----BEGIN') && is_file($key)) {
            $key = (string) file_get_contents($key);
        }

        // Keys in .env are often written on one line with literal "\n".
        return str_replace('\n', "\n", $key);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('preflight.github_api'))
            ->acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(15);
    }
}
