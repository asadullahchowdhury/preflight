import type { CSSProperties, ElementType, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Fades content in from a soft blur. Stagger siblings with `delay` (ms).
 */
export function Reveal({
    children,
    delay = 0,
    className,
    as: Component = 'div',
    fast = false,
}: {
    children: ReactNode;
    delay?: number;
    className?: string;
    as?: ElementType;
    fast?: boolean;
}) {
    const style: CSSProperties = { animationDelay: `${delay}ms` };

    return (
        <Component
            className={cn(
                fast ? 'animate-blur-in-fast' : 'animate-blur-in',
                className,
            )}
            style={style}
        >
            {children}
        </Component>
    );
}

/**
 * Slow-moving blurred colour behind the content. Purely decorative.
 */
export function AmbientBackground({
    className,
    intensity = 'normal',
}: {
    className?: string;
    intensity?: 'subtle' | 'normal';
}) {
    const opacity = intensity === 'subtle' ? 'opacity-40' : 'opacity-70';

    return (
        <div
            aria-hidden
            className={cn(
                'pointer-events-none absolute inset-0 -z-10 overflow-hidden',
                className,
            )}
        >
            <div
                className={cn(
                    'absolute -top-40 left-[10%] size-[34rem] animate-float rounded-full bg-brand/25 blur-3xl dark:bg-brand/30',
                    opacity,
                )}
            />
            <div
                className={cn(
                    'absolute top-20 right-[-8rem] size-[28rem] animate-float-slow rounded-full bg-brand-soft/25 blur-3xl dark:bg-brand-soft/20',
                    opacity,
                )}
            />
            <div
                className={cn(
                    'absolute bottom-[-12rem] left-[35%] size-[30rem] animate-float rounded-full bg-emerald-400/15 blur-3xl [animation-delay:-8s] dark:bg-emerald-400/10',
                    opacity,
                )}
            />
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,color-mix(in_oklab,var(--foreground)_7%,transparent)_1px,transparent_0)] [mask-image:radial-gradient(ellipse_at_top,black_30%,transparent_75%)] bg-[size:24px_24px]" />
        </div>
    );
}
