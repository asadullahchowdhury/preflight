import { Link } from '@inertiajs/react';
import { useLayoutEffect, useRef, useState } from 'react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

/**
 * Main navigation. A soft brand-tinted pill glides to the current page (or the item you hover).
 */
export function NavMain({ items }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const menuRef = useRef<HTMLUListElement>(null);
    const itemRefs = useRef<(HTMLLIElement | null)[]>([]);
    const [hovered, setHovered] = useState<number | null>(null);
    const [pill, setPill] = useState<{ top: number; height: number } | null>(
        null,
    );

    const activeIndex = items.findIndex((item) => isCurrentUrl(item.href));
    const target = hovered ?? (activeIndex >= 0 ? activeIndex : null);

    useLayoutEffect(() => {
        const element = target !== null ? itemRefs.current[target] : null;
        setPill(
            element
                ? { top: element.offsetTop, height: element.offsetHeight }
                : null,
        );
    }, [target, items.length]);

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu
                ref={menuRef}
                className="relative"
                onMouseLeave={() => setHovered(null)}
            >
                <span
                    aria-hidden
                    className={cn(
                        'pointer-events-none absolute inset-x-0 rounded-lg transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        hovered !== null && hovered !== activeIndex
                            ? 'bg-sidebar-accent'
                            : 'bg-gradient-to-r from-brand/15 to-brand-soft/5 ring-1 ring-brand/20',
                        pill ? 'opacity-100' : 'opacity-0',
                    )}
                    style={
                        pill
                            ? { top: pill.top, height: pill.height }
                            : undefined
                    }
                />
                {items.map((item, index) => {
                    const active = index === activeIndex;

                    return (
                        <SidebarMenuItem
                            key={item.title}
                            ref={(element) => {
                                itemRefs.current[index] = element;
                            }}
                            onMouseEnter={() => setHovered(index)}
                        >
                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                tooltip={{ children: item.title }}
                                className="relative bg-transparent transition-colors duration-300 hover:bg-transparent data-[active=true]:bg-transparent data-[active=true]:font-medium"
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && (
                                        <item.icon
                                            className={cn(
                                                'transition-colors duration-300',
                                                active && 'text-brand',
                                            )}
                                        />
                                    )}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
