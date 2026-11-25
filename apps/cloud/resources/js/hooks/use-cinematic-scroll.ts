import Lenis from 'lenis';
import { useEffect, useRef } from 'react';

/** Header height, so anchored sections don't end up underneath it. */
export const HEADER_OFFSET = 88;

let lenis: Lenis | null = null;

export function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

/**
 * Inertial smooth scrolling for the page while it's mounted (skipped for reduced motion).
 */
export function useSmoothScroll(): void {
    useEffect(() => {
        if (prefersReducedMotion()) {
            return;
        }

        const instance = new Lenis({
            duration: 1.15,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            smoothWheel: true,
        });
        lenis = instance;

        let frame = 0;
        const loop = (time: number) => {
            instance.raf(time);
            frame = requestAnimationFrame(loop);
        };
        frame = requestAnimationFrame(loop);

        return () => {
            cancelAnimationFrame(frame);
            instance.destroy();
            lenis = null;
        };
    }, []);
}

/**
 * Glides to a section (`#pricing`) or element, below the fixed header.
 */
export function scrollToTarget(target: string | HTMLElement): void {
    const element =
        typeof target === 'string'
            ? document.querySelector<HTMLElement>(target)
            : target;
    if (!element) {
        return;
    }

    if (lenis) {
        lenis.scrollTo(element, { offset: -HEADER_OFFSET, duration: 1.4 });
    } else {
        const top =
            element.getBoundingClientRect().top +
            window.scrollY -
            HEADER_OFFSET;
        window.scrollTo({
            top,
            behavior: prefersReducedMotion() ? 'auto' : 'smooth',
        });
    }
}

/**
 * Runs `callback` once per animation frame while the page scrolls or resizes (and once on mount).
 * The callback should write styles directly, not set React state on every frame.
 */
export function useScrollFrame(callback: () => void): void {
    const latest = useRef(callback);

    useEffect(() => {
        latest.current = callback;
    });

    useEffect(() => {
        let queued = false;
        const run = () => {
            queued = false;
            latest.current();
        };
        const schedule = () => {
            if (!queued) {
                queued = true;
                requestAnimationFrame(run);
            }
        };

        schedule();
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule);

        return () => {
            window.removeEventListener('scroll', schedule);
            window.removeEventListener('resize', schedule);
        };
    }, []);
}

export const clamp = (value: number, min = 0, max = 1) =>
    Math.min(max, Math.max(min, value));

export const easeOutCubic = (t: number) => 1 - Math.pow(1 - t, 3);
