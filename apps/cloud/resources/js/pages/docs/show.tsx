import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, BookOpen } from 'lucide-react';
import { AmbientBackground, Reveal } from '@/components/preflight/motion';
import { SiteHeader } from '@/components/preflight/site-header';
import type { HeaderLink } from '@/components/preflight/site-header';
import { useSmoothScroll } from '@/hooks/use-cinematic-scroll';
import { cn } from '@/lib/utils';
import { docs } from '@/routes';

const siteLinks: HeaderLink[] = [
    { label: 'Product', href: '/' },
    { label: 'Pricing', href: '/#pricing' },
    { label: 'Docs', href: '/docs' },
];

type PageLink = { slug: string; title: string };

export default function DocsShow({
    page,
    title,
    html,
    pages,
    previous,
    next,
}: {
    page: string;
    title: string;
    html: string;
    pages: PageLink[];
    previous: PageLink | null;
    next: PageLink | null;
}) {
    useSmoothScroll();

    return (
        <>
            <Head title={`${title} · Docs`} />
            <div className="relative isolate min-h-svh overflow-x-clip">
                <AmbientBackground intensity="subtle" />

                <SiteHeader links={siteLinks} badge="Docs" />

                <div className="mx-auto grid max-w-6xl grid-cols-1 gap-8 px-4 pt-28 pb-12 sm:px-6 lg:grid-cols-[14rem_minmax(0,1fr)] lg:pt-32">
                    <nav
                        aria-label="Documentation"
                        className="lg:sticky lg:top-24 lg:self-start"
                    >
                        <p className="mb-3 flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            <BookOpen className="size-3.5" />
                            Documentation
                        </p>
                        {/* A scrollable row on phones, a list from lg up. */}
                        <ul className="-mx-4 flex gap-1 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0">
                            {pages.map((link) => (
                                <li key={link.slug} className="shrink-0">
                                    <Link
                                        href={docs(link.slug)}
                                        preserveScroll={false}
                                        className={cn(
                                            'block rounded-lg px-3 py-1.5 text-sm whitespace-nowrap transition-colors',
                                            link.slug === page
                                                ? 'bg-background font-medium text-foreground shadow-sm ring-1 ring-border'
                                                : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                                        )}
                                    >
                                        {link.title}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>

                    <main key={page} className="min-w-0">
                        <Reveal fast>
                            <article
                                className="docs-prose rounded-3xl glass p-6 sm:p-10"
                                dangerouslySetInnerHTML={{ __html: html }}
                            />
                        </Reveal>

                        <div className="mt-6 grid grid-cols-2 gap-3">
                            {previous ? (
                                <Link
                                    href={docs(previous.slug)}
                                    className="group rounded-2xl glass p-4 transition-all duration-300 hover:-translate-y-0.5"
                                >
                                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                        <ArrowLeft className="size-3.5 transition-transform group-hover:-translate-x-0.5" />
                                        Previous
                                    </span>
                                    <span className="mt-1 block text-sm font-medium">
                                        {previous.title}
                                    </span>
                                </Link>
                            ) : (
                                <span />
                            )}
                            {next && (
                                <Link
                                    href={docs(next.slug)}
                                    className="group rounded-2xl glass p-4 text-right transition-all duration-300 hover:-translate-y-0.5"
                                >
                                    <span className="flex items-center justify-end gap-1 text-xs text-muted-foreground">
                                        Next
                                        <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
                                    </span>
                                    <span className="mt-1 block text-sm font-medium">
                                        {next.title}
                                    </span>
                                </Link>
                            )}
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
