<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public documentation, written in Markdown under resources/docs.
 */
class DocsController extends Controller
{
    /** Pages in reading order: slug => title. */
    public const PAGES = [
        'getting-started' => 'Getting started',
        'how-it-works' => 'How it works',
        'github-action' => 'The GitHub Action',
        'table-sizes' => 'Production table sizes',
        'safe-migrations' => 'Writing safe migrations',
    ];

    public function show(?string $page = null): Response
    {
        $page ??= array_key_first(self::PAGES);
        abort_unless(isset(self::PAGES[$page]), 404);

        $markdown = (string) file_get_contents(resource_path("docs/{$page}.md"));
        // Our own files, but escape any HTML anyway: the page renders this as HTML.
        $html = Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);

        $slugs = array_keys(self::PAGES);
        $index = (int) array_search($page, $slugs, true);

        return Inertia::render('docs/show', [
            'page' => $page,
            'title' => self::PAGES[$page],
            'html' => $html,
            'pages' => collect(self::PAGES)->map(fn (string $title, string $slug) => ['slug' => $slug, 'title' => $title])->values(),
            'previous' => $index > 0 ? ['slug' => $slugs[$index - 1], 'title' => self::PAGES[$slugs[$index - 1]]] : null,
            'next' => $index < count($slugs) - 1 ? ['slug' => $slugs[$index + 1], 'title' => self::PAGES[$slugs[$index + 1]]] : null,
        ]);
    }
}
