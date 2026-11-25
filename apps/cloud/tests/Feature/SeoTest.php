<?php

namespace Tests\Feature;

use App\Http\Controllers\DocsController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_has_search_and_link_preview_tags(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<title data-inertia="">Catch table-locking migrations before they ship - Preflight</title>', false)
            ->assertSee('<meta name="description" content="Preflight reviews every Laravel migration', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
            ->assertSee('<meta property="og:image" content="'.url('/og-image.png').'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<link rel="manifest" href="/manifest.json">', false)
            ->assertSee('"@type":"SoftwareApplication","name"', false);
    }

    public function test_docs_pages_describe_themselves(): void
    {
        $this->get('/docs')->assertOk()
            ->assertSee('<title data-inertia="">Getting started · Docs - Preflight</title>', false)
            ->assertSee('<meta name="description" content="Preflight tells you, on every pull request', false)
            // /docs and /docs/getting-started are the same page: one canonical URL.
            ->assertSee('<link rel="canonical" href="'.route('docs', 'getting-started').'">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertDontSee('"@type":"SoftwareApplication","name"', false);
    }

    public function test_every_docs_page_has_a_summary(): void
    {
        foreach (array_keys(DocsController::PAGES) as $slug) {
            $summary = DocsController::summary((string) file_get_contents(resource_path("docs/{$slug}.md")));

            $this->assertGreaterThan(50, mb_strlen($summary), "docs/{$slug}.md needs an opening paragraph");
            $this->assertLessThanOrEqual(161, mb_strlen($summary));
        }
    }

    public function test_signed_in_pages_are_not_indexed(): void
    {
        $this->get(route('login'))->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('rel="canonical"', false);

        $this->actingAs(User::factory()->waitlisted()->create())->get(route('waitlist'))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_the_sitemap_lists_the_public_pages(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $response->assertSee('<loc>'.url('/').'</loc>', false);
        foreach (array_keys(DocsController::PAGES) as $slug) {
            $response->assertSee('<loc>'.route('docs', $slug).'</loc>', false);
        }
        $response->assertDontSee('dashboard');
    }

    public function test_robots_keeps_crawlers_out_of_the_app_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /dashboard')
            ->assertSee('Disallow: /admin/')
            ->assertDontSee("Disallow: /\n", false)
            ->assertSee('Sitemap: '.url('sitemap.xml'));
    }

    public function test_robots_blocks_everything_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false);
    }
}
