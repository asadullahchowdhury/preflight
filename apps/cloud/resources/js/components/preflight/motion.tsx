import { useEffect, useRef, useState } from 'react';
import type { CSSProperties, ElementType, ReactNode } from 'react';
import {
    clamp,
    easeOutCubic,
    prefersReducedMotion,
    useScrollFrame,
} from '@/hooks/use-cinematic-scroll';
import { cn } from '@/lib/utils';

/**
 * Fades content in from a soft blur when it scrolls into view (immediately for content already on screen).
 * Stagger siblings with `delay` (ms).
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
    const ref = useRef<HTMLElement>(null);
    const [shown, setShown] = useState(false);

    useEffect(() => {
        const element = ref.current;
        if (!element || typeof IntersectionObserver === 'undefined') {
            setShown(true);

            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    setShown(true);
                    observer.disconnect();
                }
            },
            { rootMargin: '0px 0px -6% 0px', threshold: 0.05 },
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    const style: CSSProperties = { animationDelay: `${delay}ms` };

    return (
        <Component
            ref={ref}
            className={cn(
                shown
                    ? fast
                        ? 'animate-blur-in-fast'
                        : 'animate-blur-in'
                    : 'opacity-0',
                className,
            )}
            style={style}
        >
            {children}
        </Component>
    );
}

/**
 * A camera "dolly" onto the content: it travels in from depth (smaller, tilted back, blurred) and settles
 * as it reaches the middle of the screen. Scrubbed by the scroll position, so it moves with the reader.
 */
export function Dolly({
    children,
    className,
    depth = 1,
}: {
    children: ReactNode;
    className?: string;
    /** 0 = none, 1 = normal, 1.5 = stronger. */
    depth?: number;
}) {
    const ref = useRef<HTMLDivElement>(null);

    useScrollFrame(() => {
        const element = ref.current;
        if (!element) {
            return;
        }
        if (prefersReducedMotion() || depth === 0) {
            element.style.transform = '';
            element.style.opacity = '';
            element.style.filter = '';

            return;
        }

        const rect = element.getBoundingClientRect();
        const viewport = window.innerHeight;
        // 0 when the top edge enters at the bottom of the screen, 1 once it reaches 55% of the way up.
        const progress = easeOutCubic(
            clamp((viewport - rect.top) / (viewport * 0.45)),
        );
        const rest = 1 - progress;

        element.style.transform = `perspective(1400px) translate3d(0, ${rest * 70 * depth}px, ${rest * -220 * depth}px) rotateX(${rest * 9 * depth}deg) scale(${1 - rest * 0.06 * depth})`;
        element.style.opacity = String(0.15 + progress * 0.85);
        element.style.filter = rest > 0.01 ? `blur(${rest * 5}px)` : '';
    });

    return (
        <div
            ref={ref}
            className={cn('will-change-transform', className)}
            style={{ transformOrigin: '50% 100%' }}
        >
            {children}
        </div>
    );
}

/**
 * Slow-moving blurred colour behind the content. With `fixed`, it stays put while the page scrolls and
 * slowly zooms in, like a camera dolly.
 */
export function AmbientBackground({
    className,
    intensity = 'normal',
    fixed = false,
}: {
    className?: string;
    intensity?: 'subtle' | 'normal';
    fixed?: boolean;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const opacity = intensity === 'subtle' ? 'opacity-40' : 'opacity-70';

    useScrollFrame(() => {
        const element = ref.current;
        if (!element || !fixed || prefersReducedMotion()) {
            return;
        }
        const scrollable =
            document.documentElement.scrollHeight - window.innerHeight;
        const progress = scrollable > 0 ? window.scrollY / scrollable : 0;
        element.style.transform = `scale(${1 + progress * 0.35}) translate3d(0, ${progress * -6}%, 0)`;
    });

    return (
        <div
            aria-hidden
            className={cn(
                'pointer-events-none inset-0 -z-10 overflow-hidden',
                fixed ? 'fixed' : 'absolute',
                className,
            )}
        >
            <div ref={ref} className="absolute inset-0 will-change-transform">
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
            </div>
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,color-mix(in_oklab,var(--foreground)_7%,transparent)_1px,transparent_0)] [mask-image:radial-gradient(ellipse_at_top,black_30%,transparent_75%)] bg-[size:24px_24px]" />
        </div>
    );
}

/**
 * A thin gradient line along the top edge showing how far down the page you are.
 */
export function ScrollProgress() {
    const ref = useRef<HTMLDivElement>(null);

    useScrollFrame(() => {
        const element = ref.current;
        if (!element) {
            return;
        }
        const scrollable =
            document.documentElement.scrollHeight - window.innerHeight;
        element.style.transform = `scaleX(${scrollable > 0 ? clamp(window.scrollY / scrollable) : 0})`;
    });

    return (
        <div
            aria-hidden
            className="pointer-events-none fixed inset-x-0 top-0 z-50 h-0.5"
        >
            <div
                ref={ref}
                className="h-full origin-left bg-gradient-to-r from-brand via-brand-soft to-emerald-400"
                style={{ transform: 'scaleX(0)' }}
            />
        </div>
    );
}
