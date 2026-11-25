import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, BookOpen, ListTree } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import {
    AmbientBackground,
    Reveal,
    ScrollProgress,
} from '@/components/preflight/motion';
import { SiteHeader } from '@/components/preflight/site-header';
import type { HeaderLink } from '@/components/preflight/site-header';
import {
    scrollToTarget,
    useScrollFrame,
    useSmoothScroll,
} from '@/hooks/use-cinematic-scroll';
import { cn } from '@/lib/utils';
import { docs } from '@/routes';

const siteLinks: HeaderLink[] = [
    { label: 'Product', href: '/' },
    { label: 'Pricing', href: '/#pricing' },
    { label: 'Docs', href: '/docs' },
];

type PageLink = { slug: string; title: string };
type Section = { id: string; title: string };

const slug = (text: string) =>
    text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');

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

    const article = useRef<HTMLElement>(null);
    const [sections, setSections] = useState<Section[]>([]);
    const [active, setActive] = useState<string | null>(null);

    // Give each section heading an id (for the "On this page" list) and each code block a copy button.
    useEffect(() => {
        const root = article.current;
        if (!root) {
            return;
        }

        const found: Section[] = [];
        root.querySelectorAll('h2').forEach((heading) => {
            const text = heading.textContent ?? '';
            heading.id = heading.id || slug(text);
            found.push({ id: heading.id, title: text });
        });
        setSections(found);

        const buttons: HTMLButtonElement[] = [];
        root.querySelectorAll('pre').forEach((pre) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'docs-copy';
            button.textContent = 'Copy';
            button.addEventListener('click', () => {
                void navigator.clipboard
                    ?.writeText(pre.querySelector('code')?.textContent ?? '')
                    .then(() => {
                        button.textContent = 'Copied';
                        button.dataset.copied = 'true';
                        setTimeout(() => {
                            button.textContent = 'Copy';
                            delete button.dataset.copied;
                        }, 1600);
                    });
            });
            pre.appendChild(button);
            buttons.push(button);
        });

        return () => buttons.forEach((button) => button.remove());
    }, [html]);

    // The section being read: the last heading that has passed 30% of the screen. Short sections at the
    // end can't scroll that high, so at the bottom of the page the last section is the one being read.
    useScrollFrame(() => {
        let current: string | null = sections[0]?.id ?? null;
        for (const section of sections) {
            const heading = document.getElementById(section.id);
            if (
                heading &&
                heading.getBoundingClientRect().top < window.innerHeight * 0.3
            ) {
                current = section.id;
            }
        }
        const atBottom =
            window.innerHeight + window.scrollY >=
            document.documentElement.scrollHeight - 4;
        if (atBottom && sections.length > 0) {
            current = sections[sections.length - 1].id;
        }
        setActive((previous) => (previous === current ? previous : current));
    });

    return (
        <>
            <Head title={`${title} · Docs`} />
            <ScrollProgress />
            <SiteHeader links={siteLinks} badge="Docs" />

            <div className="relative isolate min-h-svh overflow-x-clip">
                <AmbientBackground fixed intensity="subtle" />

                <div className="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 pt-28 pb-16 sm:px-6 lg:grid-cols-[14rem_minmax(0,1fr)] lg:pt-32 xl:grid-cols-[14rem_minmax(0,1fr)_13rem]">
                    <Reveal className="lg:sticky lg:top-28 lg:self-start">
                        <PageNav pages={pages} page={page} />
                    </Reveal>

                    <main key={page} className="min-w-0">
                        <div className="animate-dolly-in">
                            <article
                                ref={article}
                                className="docs-prose relative overflow-hidden rounded-3xl glass p-6 shadow-xl shadow-black/5 sm:p-10"
                                dangerouslySetInnerHTML={{ __html: html }}
                            />
                        </div>

                        <div className="mt-6 grid grid-cols-2 gap-3">
                            {previous ? (
                                <Reveal dolly={0.5}>
                                    <PageCard
                                        link={previous}
                                        direction="previous"
                                    />
                                </Reveal>
                            ) : (
                                <span />
                            )}
                            {next && (
                                <Reveal dolly={0.5} delay={80}>
                                    <PageCard link={next} direction="next" />
                                </Reveal>
                            )}
                        </div>
                    </main>

                    {sections.length > 1 && (
                        <Reveal
                            delay={200}
                            className="hidden xl:sticky xl:top-28 xl:block xl:self-start"
                        >
                            <OnThisPage sections={sections} active={active} />
                        </Reveal>
                    )}
                </div>
            </div>
        </>
    );
}

/**
 * Docs pages, with a pill that glides to the current page or the one you hover.
 * A scrollable row on phones, a list from lg up.
 */
function PageNav({ pages, page }: { pages: PageLink[]; page: string }) {
    const links = useRef<(HTMLAnchorElement | null)[]>([]);
    const [hovered, setHovered] = useState<number | null>(null);
    const [pill, setPill] = useState<{
        left: number;
        top: number;
        width: number;
        height: number;
    } | null>(null);

    const activeIndex = pages.findIndex((link) => link.slug === page);
    const target = hovered ?? (activeIndex >= 0 ? activeIndex : null);

    useLayoutEffect(() => {
        const measure = () => {
            const element = target !== null ? links.current[target] : null;
            setPill(
                element
                    ? {
                          left: element.offsetLeft,
                          top: element.offsetTop,
                          width: element.offsetWidth,
                          height: element.offsetHeight,
                      }
                    : null,
            );
        };
        measure();
        window.addEventListener('resize', measure);

        return () => window.removeEventListener('resize', measure);
    }, [target]);

    return (
        <nav aria-label="Documentation">
            <p className="mb-3 flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                <BookOpen className="size-3.5" />
                Documentation
            </p>
            <ul
                className="relative -mx-4 flex [scrollbar-width:none] gap-1 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 [&::-webkit-scrollbar]:hidden"
                onMouseLeave={() => setHovered(null)}
            >
                <span
                    aria-hidden
                    className={cn(
                        'pointer-events-none absolute rounded-lg transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        hovered !== null && hovered !== activeIndex
                            ? 'bg-accent/60'
                            : 'bg-gradient-to-r from-brand/15 to-brand-soft/5 ring-1 ring-brand/25',
                        pill ? 'opacity-100' : 'opacity-0',
                    )}
                    style={pill ?? undefined}
                />
                {pages.map((link, index) => (
                    <li key={link.slug} className="shrink-0">
                        <Link
                            ref={(element) => {
                                links.current[index] =
                                    element instanceof HTMLAnchorElement
                                        ? element
                                        : null;
                            }}
                            href={docs(link.slug)}
                            onMouseEnter={() => setHovered(index)}
                            className={cn(
                                'relative z-10 block rounded-lg px-3 py-1.5 text-sm whitespace-nowrap transition-colors duration-300',
                                link.slug === page
                                    ? 'font-medium text-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {link.title}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}

/**
 * The current page's sections; a bar slides along to the one you're reading.
 */
function OnThisPage({
    sections,
    active,
}: {
    sections: Section[];
    active: string | null;
}) {
    const items = useRef<Record<string, HTMLAnchorElement | null>>({});
    const [bar, setBar] = useState<{ top: number; height: number } | null>(
        null,
    );

    useLayoutEffect(() => {
        const element = active ? items.current[active] : null;
        setBar(
            element
                ? { top: element.offsetTop, height: element.offsetHeight }
                : null,
        );
    }, [active, sections]);

    return (
        <nav aria-label="On this page" className="text-sm">
            <p className="mb-3 flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                <ListTree className="size-3.5" />
                On this page
            </p>
            <div className="relative border-l border-border/70 pl-4">
                <span
                    aria-hidden
                    className={cn(
                        'absolute -left-px w-0.5 rounded-full bg-gradient-to-b from-brand to-brand-soft transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        bar ? 'opacity-100' : 'opacity-0',
                    )}
                    style={bar ?? undefined}
                />
                <ul className="space-y-1">
                    {sections.map((section) => (
                        <li key={section.id}>
                            <a
                                ref={(element) => {
                                    items.current[section.id] = element;
                                }}
                                href={`#${section.id}`}
                                onClick={(event) => {
                                    event.preventDefault();
                                    scrollToTarget(`#${section.id}`);
                                    history.replaceState(
                                        null,
                                        '',
                                        `#${section.id}`,
                                    );
                                }}
                                className={cn(
                                    'block py-1 leading-snug transition-colors duration-300',
                                    section.id === active
                                        ? 'text-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {section.title}
                            </a>
                        </li>
                    ))}
                </ul>
            </div>
        </nav>
    );
}

function PageCard({
    link,
    direction,
}: {
    link: PageLink;
    direction: 'previous' | 'next';
}) {
    const next = direction === 'next';

    return (
        <Link
            href={docs(link.slug)}
            className={cn(
                'group block h-full rounded-2xl glass p-4 transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:shadow-brand/5',
                next && 'text-right',
            )}
        >
            <span
                className={cn(
                    'flex items-center gap-1 text-xs text-muted-foreground',
                    next && 'justify-end',
                )}
            >
                {!next && (
                    <ArrowLeft className="size-3.5 transition-transform duration-300 group-hover:-translate-x-1" />
                )}
                {next ? 'Next' : 'Previous'}
                {next && (
                    <ArrowRight className="size-3.5 transition-transform duration-300 group-hover:translate-x-1" />
                )}
            </span>
            <span className="mt-1 block text-sm font-medium">{link.title}</span>
        </Link>
    );
}
