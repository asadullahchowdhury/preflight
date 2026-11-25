import { useRef, useState } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { clamp, useScrollFrame } from '@/hooks/use-cinematic-scroll';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * The app's top bar. Transparent at the top of a page; once you scroll it turns into a compact frosted bar,
 * with a thin line along its bottom edge showing how far down the page you are.
 */
export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const [scrolled, setScrolled] = useState(false);
    const progress = useRef<HTMLDivElement>(null);

    useScrollFrame(() => {
        const isScrolled = window.scrollY > 8;
        setScrolled((previous) =>
            previous === isScrolled ? previous : isScrolled,
        );

        const scrollable =
            document.documentElement.scrollHeight - window.innerHeight;
        if (progress.current) {
            progress.current.style.transform = `scaleX(${scrollable > 0 ? clamp(window.scrollY / scrollable) : 0})`;
        }
    });

    return (
        <header
            className={cn(
                'sticky top-0 z-20 flex shrink-0 items-center gap-2 border-b px-6 transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] md:rounded-t-xl md:px-4',
                scrolled
                    ? 'h-12 border-border/60 glass-strong shadow-sm'
                    : 'h-16 border-transparent bg-transparent group-has-data-[collapsible=icon]/sidebar-wrapper:h-12',
            )}
        >
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div
                aria-hidden
                className={cn(
                    'pointer-events-none absolute inset-x-0 -bottom-px h-px overflow-hidden transition-opacity duration-500',
                    scrolled ? 'opacity-100' : 'opacity-0',
                )}
            >
                <div
                    ref={progress}
                    className="h-full origin-left bg-gradient-to-r from-brand via-brand-soft to-emerald-400"
                    style={{ transform: 'scaleX(0)' }}
                />
            </div>
        </header>
    );
}
