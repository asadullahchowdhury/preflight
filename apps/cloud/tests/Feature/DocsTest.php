<?php

namespace Tests\Feature;

use App\Http\Controllers\DocsController;
use Tests\TestCase;

class DocsTest extends TestCase
{
    public function test_every_docs_page_renders_for_guests(): void
    {
        foreach (array_keys(DocsController::PAGES) as $slug) {
            $this->get(route('docs', $slug))->assertOk()
                ->assertInertia(fn ($page) => $page->component('docs/show')->where('page', $slug));
        }
    }

    public function test_the_docs_start_at_getting_started_and_link_onwards(): void
    {
        $this->get('/docs')->assertOk()->assertInertia(fn ($page) => $page
            ->where('page', 'getting-started')
            ->where('previous', null)
            ->where('next.slug', 'how-it-works')
            ->where('html', fn (string $html) => str_contains($html, '<h1>Getting started</h1>')
                && str_contains($html, '<code class="language-bash">composer require --dev preflight/laravel')));
    }

    public function test_markdown_tables_render(): void
    {
        $this->get(route('docs', 'how-it-works'))->assertInertia(fn ($page) => $page
            ->where('html', fn (string $html) => str_contains($html, '<table>') && str_contains($html, '<code>INSTANT</code>')));
    }

    public function test_unknown_pages_are_not_found(): void
    {
        $this->get('/docs/nope')->assertNotFound();
        $this->get('/docs/..%2F..%2F.env')->assertNotFound();
    }
}
