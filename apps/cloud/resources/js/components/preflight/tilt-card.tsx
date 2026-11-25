import { useRef } from 'react';
import type { PointerEvent, ReactNode } from 'react';
import { prefersReducedMotion } from '@/hooks/use-cinematic-scroll';
import { cn } from '@/lib/utils';

/**
 * A card that tilts gently towards the pointer, with a soft spotlight following it and a slowly
 * turning gradient edge. Mouse and pen only (no tilt on touch); off for reduced motion.
 */
export function TiltCard({
    children,
    className,
    maxTilt = 5,
}: {
    children: ReactNode;
    className?: string;
    maxTilt?: number;
}) {
    const card = useRef<HTMLDivElement>(null);
    const frame = useRef(0);

    const move = (event: PointerEvent<HTMLDivElement>) => {
        const element = card.current;
        if (
            !element ||
            event.pointerType === 'touch' ||
            prefersReducedMotion()
        ) {
            return;
        }

        const rect = element.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width;
        const y = (event.clientY - rect.top) / rect.height;

        cancelAnimationFrame(frame.current);
        frame.current = requestAnimationFrame(() => {
            element.style.transform = `perspective(1200px) rotateX(${(0.5 - y) * maxTilt}deg) rotateY(${(x - 0.5) * maxTilt}deg)`;
            element.style.setProperty('--spot-x', `${x * 100}%`);
            element.style.setProperty('--spot-y', `${y * 100}%`);
            element.style.setProperty('--spot-opacity', '1');
        });
    };

    const leave = () => {
        const element = card.current;
        if (!element) {
            return;
        }
        cancelAnimationFrame(frame.current);
        element.style.transform = '';
        element.style.setProperty('--spot-opacity', '0');
    };

    return (
        <div className="group/tilt relative">
            {/* Soft glow behind the card. */}
            <div
                aria-hidden
                className="pointer-events-none absolute -inset-6 rounded-[2.5rem] bg-gradient-to-br from-brand/25 via-brand-soft/10 to-emerald-400/10 opacity-70 blur-2xl transition-opacity duration-700 group-hover/tilt:opacity-100"
            />
            <div
                ref={card}
                onPointerMove={move}
                onPointerLeave={leave}
                className={cn(
                    'relative overflow-hidden rounded-3xl glass-strong shadow-2xl shadow-black/10 transition-transform duration-500 ease-out will-change-transform',
                    className,
                )}
                style={{ transformStyle: 'preserve-3d' }}
            >
                {/* Spotlight that follows the pointer. */}
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 transition-opacity duration-500"
                    style={{
                        opacity: 'var(--spot-opacity, 0)',
                        background:
                            'radial-gradient(420px circle at var(--spot-x, 50%) var(--spot-y, 0%), color-mix(in oklab, var(--color-brand) 14%, transparent), transparent 60%)',
                    }}
                />
                {/* A thin gradient edge along the top. */}
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-x-6 top-0 h-px bg-gradient-to-r from-transparent via-brand-soft/70 to-transparent"
                />
                <div className="relative">{children}</div>
            </div>
        </div>
    );
}
