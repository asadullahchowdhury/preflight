import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import type { MouseEvent } from 'react';
import { LogoMark } from '@/components/app-logo';
import { GitHubIcon } from '@/components/preflight/bits';
import { Reveal } from '@/components/preflight/motion';
import { scrollToTarget } from '@/hooks/use-cinematic-scroll';
import { dashboard, docs, login } from '@/routes';

type FooterLink = { label: string; href: string };

const product: FooterLink[] = [
    { label: 'How it works', href: '/#how' },
    { label: 'Pricing', href: '/#pricing' },
    { label: 'Security', href: '/#security' },
    { label: 'FAQ', href: '/#faq' },
];

const documentation: FooterLink[] = [
    { label: 'Getting started', href: docs('getting-started').url },
    { label: 'The GitHub Action', href: docs('github-action').url },
    { label: 'Table sizes', href: docs('table-sizes').url },
    { label: 'Safe migrations', href: docs('safe-migrations').url },
];

/** On the landing page, its sections glide into view instead of reloading the page. */
function followSectionLink(event: MouseEvent, href: string) {
    if (href.startsWith('/#') && window.location.pathname === '/') {
        event.preventDefault();
        scrollToTarget(href.slice(1));
        history.replaceState(null, '', href.slice(1));
    }
}

/**
 * The public site's footer (landing page and docs): brand, grouped links and the maker's credit,
 * over a faded wordmark.
 */
export function SiteFooter() {
    const { auth, author } = usePage().props;
    const githubUser = author.url?.match(
        /^https:\/\/github\.com\/([\w-]+)\/?$/,
    )?.[1];
    const account: FooterLink[] = auth.user
        ? [{ label: 'Dashboard', href: dashboard().url }]
        : [{ label: 'Sign in with GitHub', href: login().url }];

    return (
        <footer className="relative isolate overflow-hidden border-t border-border/40 bg-background/50 backdrop-blur-xl">
            {/* A brand-coloured hairline and a soft glow along the top edge. */}
            <div
                aria-hidden
                className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-brand/60 to-transparent"
            />
            <div
                aria-hidden
                className="absolute -top-24 left-1/2 -z-10 h-48 w-[36rem] max-w-full -translate-x-1/2 rounded-full bg-brand/15 blur-3xl"
            />

            <div className="mx-auto max-w-6xl px-4 pt-14 sm:px-6 lg:pt-16">
                <Reveal className="grid grid-cols-2 gap-x-6 gap-y-10 md:grid-cols-[minmax(0,1.6fr)_repeat(3,minmax(0,1fr))]">
                    <div className="col-span-2 md:col-span-1">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2.5"
                        >
                            <LogoMark className="size-8" />
                            <span className="text-lg font-semibold tracking-tight">
                                Preflight
                            </span>
                        </Link>
                        <p className="mt-4 max-w-xs text-sm leading-relaxed text-muted-foreground">
                            Catch the migration that locks production before you
                            merge it. MySQL online-DDL checks for Laravel pull
                            requests.
                        </p>
                        <div className="mt-5 flex flex-wrap gap-2 text-xs">
                            <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-1 text-emerald-700 dark:text-emerald-300">
                                <span className="relative flex size-1.5">
                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-60" />
                                    <span className="relative inline-flex size-1.5 rounded-full bg-emerald-500" />
                                </span>
                                Private beta
                            </span>
                            <span className="rounded-full border border-border/70 px-2.5 py-1 text-muted-foreground">
                                MySQL 8.0 · 8.4 LTS
                            </span>
                        </div>
                    </div>

                    <LinkColumn title="Product" links={product} />
                    <LinkColumn title="Docs" links={documentation} />
                    <LinkColumn title="Account" links={account} />
                </Reveal>

                <div className="mt-12 flex flex-col-reverse items-center justify-between gap-4 border-t border-border/40 py-6 text-xs text-muted-foreground sm:flex-row">
                    <p>
                        © {new Date().getFullYear()} Preflight. Your migrations
                        never leave your CI.
                    </p>

                    <a
                        href={author.url ?? undefined}
                        target="_blank"
                        rel="author noopener"
                        className="group inline-flex items-center gap-2 rounded-full border border-border/60 bg-background/60 py-1 pr-3 pl-1 transition-all duration-300 hover:border-brand/40 hover:bg-brand/5 hover:shadow-lg hover:shadow-brand/10"
                    >
                        {githubUser ? (
                            <img
                                src={`https://github.com/${githubUser}.png?size=64`}
                                alt=""
                                width={20}
                                height={20}
                                loading="lazy"
                                className="size-5 rounded-full ring-1 ring-border"
                            />
                        ) : (
                            <GitHubIcon className="size-5 p-0.5" />
                        )}
                        <span>
                            Built by{' '}
                            <span className="font-medium text-foreground">
                                {author.name}
                            </span>
                        </span>
                        {author.url && (
                            <ArrowUpRight className="size-3.5 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                        )}
                    </a>
                </div>
            </div>

            {/* The closing shot: a huge, faded wordmark, cropped by the bottom edge. */}
            <div
                aria-hidden
                className="pointer-events-none -mt-4 flex h-[clamp(3.5rem,13vw,9.5rem)] items-start justify-center overflow-hidden select-none"
            >
                <span className="-translate-y-[8%] bg-gradient-to-b from-foreground/[0.11] via-brand/[0.07] to-transparent bg-clip-text text-[clamp(4.5rem,19vw,14rem)] leading-[0.9] font-semibold tracking-tighter text-transparent">
                    Preflight
                </span>
            </div>
        </footer>
    );
}

function LinkColumn({ title, links }: { title: string; links: FooterLink[] }) {
    return (
        <div>
            <p className="text-xs font-medium tracking-wide text-foreground/80 uppercase">
                {title}
            </p>
            <ul className="mt-4 space-y-2.5 text-sm">
                {links.map((link) => (
                    <li key={link.href}>
                        <Link
                            href={link.href}
                            onClick={(event) =>
                                followSectionLink(event, link.href)
                            }
                            className="relative text-muted-foreground transition-colors duration-300 after:absolute after:inset-x-0 after:-bottom-0.5 after:h-px after:origin-left after:scale-x-0 after:bg-gradient-to-r after:from-brand after:to-brand-soft after:transition-transform after:duration-300 hover:text-foreground hover:after:scale-x-100"
                        >
                            {link.label}
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}
