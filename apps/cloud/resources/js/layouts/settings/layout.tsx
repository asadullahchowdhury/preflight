import { Link } from '@inertiajs/react';
import { Palette, UserRound } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';
import type { PropsWithChildren } from 'react';
import { Reveal } from '@/components/preflight/motion';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: UserRound,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: Palette,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const navRef = useRef<HTMLElement>(null);
    const linkRefs = useRef<(HTMLAnchorElement | null)[]>([]);
    const [hovered, setHovered] = useState<number | null>(null);
    const [pill, setPill] = useState<{
        left: number;
        top: number;
        width: number;
        height: number;
    } | null>(null);

    const activeIndex = sidebarNavItems.findIndex((item) =>
        isCurrentOrParentUrl(item.href),
    );
    const target = hovered ?? (activeIndex >= 0 ? activeIndex : null);

    useLayoutEffect(() => {
        const measure = () => {
            const element = target !== null ? linkRefs.current[target] : null;
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
        <div className="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:py-8">
            <Reveal>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Settings
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Manage your profile and how Preflight looks.
                </p>
            </Reveal>

            <div className="mt-8 flex flex-col gap-6 lg:flex-row lg:gap-10">
                <Reveal delay={80} className="lg:w-52 lg:shrink-0">
                    <nav
                        ref={navRef}
                        aria-label="Settings"
                        className="relative flex gap-1 rounded-2xl glass p-1 lg:flex-col"
                        onMouseLeave={() => setHovered(null)}
                    >
                        <span
                            aria-hidden
                            className={cn(
                                'pointer-events-none absolute rounded-xl transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]',
                                hovered !== null && hovered !== activeIndex
                                    ? 'bg-accent/70'
                                    : 'bg-background shadow-sm ring-1 ring-border',
                                pill ? 'opacity-100' : 'opacity-0',
                            )}
                            style={pill ?? undefined}
                        />
                        {sidebarNavItems.map((item, index) => {
                            const active = index === activeIndex;

                            return (
                                <Link
                                    key={toUrl(item.href)}
                                    ref={(element) => {
                                        linkRefs.current[index] =
                                            element instanceof HTMLAnchorElement
                                                ? element
                                                : null;
                                    }}
                                    href={item.href}
                                    onMouseEnter={() => setHovered(index)}
                                    className={cn(
                                        'relative z-10 flex flex-1 items-center gap-2.5 rounded-xl px-3 py-2 text-sm transition-colors duration-300 lg:flex-none',
                                        active
                                            ? 'font-medium text-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {item.icon && (
                                        <item.icon
                                            className={cn(
                                                'size-4 transition-colors duration-300',
                                                active && 'text-brand',
                                            )}
                                        />
                                    )}
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>
                </Reveal>

                <Reveal delay={140} className="min-w-0 flex-1">
                    <section className="space-y-10 rounded-3xl glass p-5 sm:p-8">
                        {children}
                    </section>
                </Reveal>
            </div>
        </div>
    );
}
