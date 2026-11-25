@php
    $seo = config('preflight.seo');
    $meta = $meta ?? [];
    $appName = config('app.name', 'Preflight');
    $isHome = request()->routeIs('home');

    $pageTitle = $meta['title'] ?? ($isHome ? $seo['tagline'] : null);
    // Same format as the client-side title template in resources/js/app.tsx.
    $fullTitle = $pageTitle ? "{$pageTitle} - {$appName}" : $appName;
    $description = $meta['description'] ?? $seo['description'];
    $canonical = url($meta['path'] ?? request()->path());
    $image = url($seo['image']);
    $indexable = request()->routeIs(...$seo['indexable_routes']);
    $author = config('preflight.author');
    $person = array_filter([
        '@type' => 'Person',
        'name' => $author['name'],
        'url' => $author['url'] ?? null,
        'sameAs' => isset($author['url']) ? [$author['url']] : null,
    ]);

    // Structured data, built here: Blade would read "@context" in the template as a directive.
    $structuredData = [];
    if ($isHome) {
        $structuredData[] = [
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'WebSite', 'name' => $appName, 'url' => url('/')],
                [
                    '@type' => 'SoftwareApplication',
                    'name' => $appName,
                    'url' => url('/'),
                    'description' => $seo['description'],
                    'applicationCategory' => 'DeveloperApplication',
                    'operatingSystem' => 'Any (runs in GitHub Actions)',
                    'image' => $image,
                    'author' => $person,
                    'creator' => $person,
                    'offers' => [
                        ['@type' => 'Offer', 'name' => 'Free', 'price' => '0', 'priceCurrency' => 'USD'],
                        ['@type' => 'Offer', 'name' => 'Pro', 'price' => '29', 'priceCurrency' => 'USD'],
                    ],
                ],
            ],
        ];
    }
    if (! empty($meta['breadcrumbs'])) {
        $structuredData[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($meta['breadcrumbs'])->values()->map(fn (array $crumb, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => url($crumb['path']),
            ])->all(),
        ];
    }
@endphp

{{-- With SSR the page's own <Head> renders the title; this is the fallback without it. Tagged so the client
     replaces it instead of adding a second one. --}}
<x-inertia::head>
    <title data-inertia="">{{ $fullTitle }}</title>
</x-inertia::head>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $indexable ? 'index, follow' : 'noindex, nofollow' }}">
<meta name="application-name" content="{{ $appName }}">
<meta name="author" content="{{ $author['name'] }}">
@if (! empty($author['url']))
    <link rel="author" href="{{ $author['url'] }}">
@endif
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b0b0e" media="(prefers-color-scheme: dark)">
@if ($indexable)
    <link rel="canonical" href="{{ $canonical }}">
@endif

<meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
<meta property="og:site_name" content="{{ $appName }}">
<meta property="og:title" content="{{ $pageTitle ?? $appName }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $appName }}: {{ $seo['tagline'] }}">
<meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle ?? $appName }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">

<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.json">

@foreach ($structuredData as $data)
    <script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
