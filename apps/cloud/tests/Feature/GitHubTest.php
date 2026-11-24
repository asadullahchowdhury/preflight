<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\Installation;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GitHubTest extends TestCase
{
    use RefreshDatabase;

    private function webhook(string $event, array $payload, ?string $secret = 'whsec'): TestResponse
    {
        config(['preflight.github_app.webhook_secret' => 'whsec']);
        $body = json_encode($payload);

        return $this->call('POST', '/github/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => $event,
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, (string) $secret),
        ], $body);
    }

    public function test_webhooks_must_be_signed(): void
    {
        $this->webhook('installation', ['action' => 'created'], 'wrong')->assertUnauthorized();
    }

    public function test_installation_webhooks_keep_installations_and_repositories_in_step(): void
    {
        $installation = ['id' => 77, 'account' => ['id' => 5, 'login' => 'acme', 'type' => 'Organization'], 'repository_selection' => 'selected'];

        $this->webhook('installation', ['action' => 'created', 'installation' => $installation, 'repositories' => [
            ['id' => 1, 'full_name' => 'acme/one', 'private' => true],
            ['id' => 2, 'full_name' => 'acme/two', 'private' => false],
        ]])->assertOk();
        $this->assertSame(['acme/one', 'acme/two'], Repository::query()->orderBy('full_name')->pluck('full_name')->all());

        $this->webhook('installation_repositories', ['action' => 'removed', 'installation' => $installation, 'repositories_added' => [], 'repositories_removed' => [['id' => 1]]])->assertOk();
        $this->webhook('installation_repositories', ['action' => 'added', 'installation' => $installation, 'repositories_added' => [['id' => 3, 'full_name' => 'acme/three', 'private' => true]], 'repositories_removed' => []])->assertOk();
        $this->assertSame(['acme/three', 'acme/two'], Repository::query()->orderBy('full_name')->pluck('full_name')->all());

        $this->webhook('installation', ['action' => 'suspend', 'installation' => $installation + ['suspended_at' => now()->toIso8601String()]])->assertOk();
        $this->assertFalse(Installation::query()->sole()->isActive());

        $this->webhook('installation', ['action' => 'deleted', 'installation' => $installation])->assertOk();
        $this->assertDatabaseCount('installations', 0);
        $this->assertDatabaseCount('repositories', 0);
    }

    public function test_signing_in_with_github_creates_the_user_and_syncs_their_installations(): void
    {
        config(['preflight.github_app.id' => '123', 'preflight.github_app.private_key' => $this->appPrivateKey()]);
        $github = new SocialiteUser;
        $github->map(['id' => 9, 'nickname' => 'octocat', 'name' => 'The Octocat', 'email' => null, 'avatar' => 'https://avatars.test/9']);
        $github->token = 'ghu_user';
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($github);
        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

        Http::fake([
            'api.github.com/user/installations*' => Http::response(['installations' => [
                ['id' => 88, 'account' => ['id' => 9, 'login' => 'octocat', 'type' => 'User'], 'repository_selection' => 'all'],
            ]]),
            'api.github.com/app/installations/88/access_tokens' => Http::response(['token' => 'ghs_88']),
            'api.github.com/installation/repositories*' => Http::response(['repositories' => [['id' => 10, 'full_name' => 'octocat/hello', 'private' => false]]]),
        ]);

        $this->get('/auth/github/callback?code=x&state=y')->assertRedirect(route('dashboard'));

        $user = User::query()->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('octocat', $user->github_login);
        $this->assertSame('ghu_user', $user->github_token);
        $this->assertSame(['octocat/hello'], $user->repositories()->pluck('full_name')->all());
        Http::assertSent(fn ($r) => str_contains($r->url(), '/user/installations') && $r->hasHeader('Authorization', 'Bearer ghu_user'));
    }

    public function test_users_only_see_repositories_of_their_installations(): void
    {
        $user = User::factory()->create();
        $mine = Repository::factory()->create();
        $theirs = Repository::factory()->create();
        $user->installations()->attach($mine->installation_id);
        $analysis = Analysis::query()->create([
            'repository_id' => $theirs->id, 'head_sha' => str_repeat('c', 40), 'mysql_version' => '8.0.36', 'migrations' => [],
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('dashboard')->has('repositories', 1)->where('repositories.0.fullName', $mine->full_name));
        $this->actingAs($user)->get(route('repositories.show', $mine))->assertOk();
        $this->actingAs($user)->get(route('repositories.show', $theirs))->assertNotFound();
        $this->actingAs($user)->get(route('analyses.show', $analysis))->assertNotFound();
    }

    public function test_the_login_page_and_guards(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertInertia(fn ($page) => $page->component('auth/login'));
        $this->get(route('home'))->assertOk();
    }
}
