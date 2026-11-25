<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['preflight.beta' => true, 'preflight.admins' => ['octo-admin'], 'preflight.oidc.audience' => 'https://preflight.test']);
    }

    public function test_waitlisted_people_only_see_the_waitlist(): void
    {
        $user = User::factory()->waitlisted()->create();
        $repository = Repository::factory()->create();
        $user->installations()->attach($repository->installation_id);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('waitlist'));
        $this->actingAs($user)->get(route('repositories.show', $repository))->assertRedirect(route('waitlist'));
        $this->actingAs($user)->get(route('waitlist'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('waitlist')->where('teamSizes.0', 'just-me'));

        // Settings stay reachable (e.g. to delete the account).
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_people_can_tell_us_about_their_team(): void
    {
        $user = User::factory()->waitlisted()->create();

        $this->actingAs($user)->patch(route('waitlist.update'), ['team_size' => '11-50', 'waitlist_note' => 'Laravel + MySQL, 40M-row orders table'])
            ->assertRedirect(route('waitlist'));
        $this->assertSame('11-50', $user->fresh()?->team_size);

        $this->actingAs($user)->patch(route('waitlist.update'), ['team_size' => 'huge'])->assertSessionHasErrors('team_size');
    }

    public function test_approved_people_skip_the_waitlist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('waitlist'))->assertRedirect(route('dashboard'));
    }

    public function test_admins_approve_and_revoke(): void
    {
        $admin = User::factory()->waitlisted()->create(['github_login' => 'Octo-Admin']);
        $person = User::factory()->waitlisted()->create(['github_login' => 'newcomer', 'waitlist_note' => 'hi']);

        $this->assertTrue($admin->hasAccess(), 'admins are always in');
        $this->actingAs($person)->get(route('admin.beta'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.beta'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/beta')->where('people.0.login', 'Octo-Admin')->has('people', 2));

        $this->actingAs($admin)->post(route('admin.beta.approve', $person))->assertRedirect();
        $this->assertTrue($person->fresh()?->hasAccess());

        $this->actingAs($admin)->post(route('admin.beta.revoke', $person))->assertRedirect();
        $this->assertFalse($person->fresh()?->hasAccess());

        // Admins can't lock each other (or themselves) out.
        $this->actingAs($admin)->post(route('admin.beta.revoke', $admin))->assertRedirect();
        $this->assertTrue($admin->fresh()?->hasAccess());
    }

    public function test_ci_reports_need_an_approved_user_on_the_installation(): void
    {
        Queue::fake();
        $repository = Repository::factory()->create(['github_id' => 4242]);
        $waitlisted = User::factory()->waitlisted()->create();
        $waitlisted->installations()->attach($repository->installation_id);
        $payload = [
            'server_version' => '8.0.36',
            'migrations' => [],
            'input' => ['statements' => [], 'schema_sql' => ''],
        ];

        $this->withToken($this->oidcToken(['repository_id' => '4242']))->postJson('/api/analyses', $payload)
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'Preflight is in private beta and this account is still on the waitlist. The action falls back to its own comment until it is approved.']);

        User::factory()->create()->installations()->attach($repository->installation_id);

        $this->withToken($this->oidcToken(['repository_id' => '4242']))->postJson('/api/analyses', $payload)->assertCreated();
    }

    public function test_turning_the_beta_off_opens_everything(): void
    {
        config(['preflight.beta' => false]);

        $this->actingAs(User::factory()->waitlisted()->create())->get(route('dashboard'))->assertOk();
    }
}
