import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Menu, X } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import { LogoMark } from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { scrollToTarget, useScrollFrame } from '@/hooks/use-cinematic-scroll';
import { cn } from '@/lib/utils';
import { dashboard, home, login } from '@/routes';

export type HeaderLink = {
    label: string;
    /** `#section` on the landing page, or a path. */
    href: string;
};

/**
 * The public site's header. Wide and transparent at the top of the page; once you scroll it settles into
 * a compact frosted bar. A soft pill slides under the link you're hovering, or the section you're reading.
 */
export function SiteHeader({
    links,
    badge,
}: {
    links: HeaderLink[];
    badge?: string;
}) {
    const { auth } = usePage().props;
    const [scrolled, setScrolled] = useState(false);
    const [active, setActive] = useState<string | null>(null);
    const [hovered, setHovered] = useState<string | null>(null);
    const [open, setOpen] = useState(false);
    const navRef = useRef<HTMLDivElement>(null);
    const linkRefs = useRef<Record<string, HTMLAnchorElement | null>>({});
    const [pill, setPill] = useState<{ left: number; width: number } | null>(
        null,
    );

    useScrollFrame(() => {
        const isScrolled = window.scrollY > 16;
        setScrolled((previous) =>
            previous === isScrolled ? previous : isScrolled,
        );

        // The section whose top has passed 40% of the screen is the one being read.
        let current: string | null = null;
        for (const link of links) {
            if (!link.href.startsWith('#')) {
                continue;
            }
            const section = document.querySelector(link.href);
            if (
                section &&
                section.getBoundingClientRect().top < window.innerHeight * 0.4
            ) {
                current = link.href;
            }
        }
        setActive((previous) => (previous === current ? previous : current));
    });

    const target = hovered ?? active;

    useLayoutEffect(() => {
        const element = target ? linkRefs.current[target] : null;
        const nav = navRef.current;
        if (!element || !nav) {
            setPill(null);

            return;
        }
        setPill({ left: element.offsetLeft, width: element.offsetWidth });
    }, [target, scrolled]);

    const go = (event: MouseEvent<HTMLAnchorElement>, href: string) => {
        setOpen(false);
        if (href.startsWith('#')) {
            event.preventDefault();
            scrollToTarget(href);
            history.replaceState(null, '', href);
        }
    };

    const action = auth.user ? (
        <Button asChild size="sm" className="rounded-full px-4">
            <Link href={dashboard()}>
                Dashboard
                <ArrowRight className="size-4" />
            </Link>
        </Button>
    ) : (
        <Button asChild size="sm" className="rounded-full px-4">
            <Link href={login()}>Sign in</Link>
        </Button>
    );

    return (
        <header className="pointer-events-none fixed inset-x-0 top-0 z-40 px-3 sm:px-6">
            <div
                className={cn(
                    'pointer-events-auto mx-auto flex items-center justify-between gap-4 border transition-all duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                    scrolled || open
                        ? 'mt-3 max-w-5xl rounded-2xl border-border/60 glass-strong px-3 py-2 shadow-lg shadow-black/10 sm:px-4'
                        : 'mt-0 max-w-6xl rounded-none border-transparent px-1 py-5 sm:px-2',
                )}
            >
                <Link
                    href={home()}
                    className="flex shrink-0 items-center gap-2.5"
                    onClick={() => setOpen(false)}
                >
                    <LogoMark
                        className={cn(
                            'transition-all duration-700',
                            scrolled ? 'size-8' : 'size-9',
                        )}
                    />
                    <span className="text-[15px] font-semibold tracking-tight">
                        Preflight
                    </span>
                    {badge && (
                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                            {badge}
                        </span>
                    )}
                </Link>

                <nav
                    ref={navRef}
                    className="relative hidden items-center md:flex"
                    onMouseLeave={() => setHovered(null)}
                >
                    <span
                        aria-hidden
                        className={cn(
                            'absolute inset-y-0 rounded-full bg-foreground/[0.07] ring-1 ring-foreground/10 transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]',
                            pill ? 'opacity-100' : 'opacity-0',
                        )}
                        style={
                            pill
                                ? { left: pill.left, width: pill.width }
                                : undefined
                        }
                    />
                    {links.map((link) => (
                        <a
                            key={link.href}
                            ref={(element) => {
                                linkRefs.current[link.href] = element;
                            }}
                            href={link.href}
                            onClick={(event) => go(event, link.href)}
                            onMouseEnter={() => setHovered(link.href)}
                            className={cn(
                                'relative rounded-full px-4 py-1.5 text-sm transition-colors duration-300',
                                target === link.href
                                    ? 'text-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {link.label}
                        </a>
                    ))}
                </nav>

                <div className="flex items-center gap-2">
                    <div className="hidden sm:block">{action}</div>
                    <button
                        type="button"
                        onClick={() => setOpen((value) => !value)}
                        className="flex size-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-foreground md:hidden"
                        aria-expanded={open}
                        aria-label={open ? 'Close menu' : 'Open menu'}
                    >
                        {open ? (
                            <X className="size-5" />
                        ) : (
                            <Menu className="size-5" />
                        )}
                    </button>
                </div>
            </div>

            {/* Phones: the links drop down from the bar. */}
            <div
                className={cn(
                    'pointer-events-auto mx-auto mt-2 max-w-5xl origin-top overflow-hidden rounded-2xl border border-border/60 glass-strong shadow-xl transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] md:hidden',
                    open
                        ? 'blur-0 visible translate-y-0 scale-100 opacity-100'
                        : 'invisible -translate-y-2 scale-[0.98] opacity-0 blur-sm',
                )}
            >
                <nav className="flex flex-col p-2">
                    {links.map((link, i) => (
                        <a
                            key={link.href}
                            href={link.href}
                            onClick={(event) => go(event, link.href)}
                            className={cn(
                                'rounded-xl px-4 py-3 text-[15px] transition-all duration-500 hover:bg-accent/60',
                                active === link.href
                                    ? 'font-medium text-foreground'
                                    : 'text-muted-foreground',
                                open
                                    ? 'translate-y-0 opacity-100'
                                    : '-translate-y-1 opacity-0',
                            )}
                            style={{
                                transitionDelay: open
                                    ? `${60 + i * 40}ms`
                                    : '0ms',
                            }}
                        >
                            {link.label}
                        </a>
                    ))}
                    <div className="mt-1 border-t border-border/60 p-2 pt-3 sm:hidden">
                        <div className="[&_a]:w-full [&>*]:w-full">
                            {action}
                        </div>
                    </div>
                </nav>
            </div>
        </header>
    );
}
