import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { AmbientBackground } from '@/components/preflight/motion';
import { useSmoothScroll } from '@/hooks/use-cinematic-scroll';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    // Keying on the path replays the blur-in when navigating between pages, not on partial reloads.
    const path = usePage().url.split('?')[0];

    useSmoothScroll();

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="relative isolate min-w-0 overflow-x-clip"
            >
                <AmbientBackground intensity="subtle" />
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div
                    key={path}
                    className="flex flex-1 animate-blur-in flex-col"
                >
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
