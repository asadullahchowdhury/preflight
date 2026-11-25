import { useRef } from 'react';
import {
    clamp,
    easeOutCubic,
    prefersReducedMotion,
    useScrollFrame,
} from '@/hooks/use-cinematic-scroll';
import { cn } from '@/lib/utils';

/** Short speed streaks scattered around the edges: [left %, top %, angle °, hidden on phones]. */
const streaks: [number, number, number, boolean][] = [
    [6, 18, -35, false],
    [12, 56, 70, true],
    [3, 74, 20, true],
    [22, 88, -15, true],
    [31, 12, 160, true],
    [47, 92, 0, false],
    [58, 8, -20, true],
    [66, 84, 12, true],
    [79, 66, -60, false],
    [88, 90, 25, true],
    [95, 44, 80, false],
    [72, 30, 145, true],
];

/** Glowing points: [left %, top %, delay s]. */
const sparks: [number, number, number][] = [
    [1.5, 46, 0],
    [97, 58, 1.2],
    [40, 6, 2.1],
];

const line = {
    fill: 'none',
    stroke: 'currentColor',
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
    vectorEffect: 'non-scaling-stroke' as const,
};

/**
 * The footer's backdrop: a wireframe of the Preflight paper plane leaving a dotted trail that ends in
 * the logo's check mark, a wireframe database, speed streaks and soft sparks. The plane
 * flies in as the footer scrolls into view.
 */
export function FooterArt() {
    const root = useRef<HTMLDivElement>(null);
    const plane = useRef<HTMLDivElement>(null);
    const database = useRef<HTMLDivElement>(null);

    useScrollFrame(() => {
        const element = root.current;
        if (!element || !plane.current || !database.current) {
            return;
        }
        if (prefersReducedMotion()) {
            plane.current.style.transform = database.current.style.transform =
                '';

            return;
        }

        const rect = element.getBoundingClientRect();
        const viewport = window.innerHeight;
        // 0 as the footer's top edge enters the screen, 1 once most of it is in view.
        const progress = easeOutCubic(
            clamp(
                (viewport - rect.top) / Math.min(rect.height, viewport * 0.8),
            ),
        );
        const rest = 1 - progress;

        plane.current.style.transform = `translate3d(${rest * -90}px, ${rest * 70}px, 0) rotate(${rest * -8}deg) scale(${1 - rest * 0.12})`;
        plane.current.style.opacity = String(0.25 + progress * 0.75);
        database.current.style.transform = `translate3d(0, ${rest * 40}px, 0)`;
    });

    return (
        <div
            ref={root}
            aria-hidden
            className="pointer-events-none absolute inset-0 -z-10 overflow-hidden select-none"
        >
            {/* The paper plane and its trail, top right (over the sparse Account column). */}
            <div
                ref={plane}
                className="absolute right-2 bottom-56 w-52 text-brand/45 will-change-transform md:top-32 md:right-[1%] md:bottom-auto md:w-[24rem] lg:w-[27rem] xl:right-[4%] xl:w-[30rem] dark:text-brand-soft/45"
                style={{ transformOrigin: '80% 30%' }}
            >
                <svg viewBox="0 0 520 250" className="w-full overflow-visible">
                    <defs>
                        <linearGradient
                            id="footer-plane-fill"
                            x1="0"
                            y1="0"
                            x2="1"
                            y2="1"
                        >
                            <stop
                                offset="0"
                                stopColor="currentColor"
                                stopOpacity="0.22"
                            />
                            <stop
                                offset="1"
                                stopColor="currentColor"
                                stopOpacity="0"
                            />
                        </linearGradient>
                    </defs>

                    {/* The trail: dots streaming back from the tail, curving down to a check mark. */}
                    <path
                        {...line}
                        d="M404 108 C 340 168, 268 112, 206 160 S 112 226, 74 204"
                        strokeWidth={1.6}
                        strokeDasharray="0.5 10.5"
                        className="animate-trail opacity-80"
                    />
                    <path
                        {...line}
                        d="M28 196 l 16 16 l 30 -30"
                        strokeWidth={1.6}
                        className="opacity-90"
                    />

                    {/* The plane: the logo's outline, its fold, and a keel underneath for depth. */}
                    <g
                        className="animate-glide"
                        style={{
                            transformBox: 'fill-box',
                            transformOrigin: 'center',
                        }}
                    >
                        <path
                            d="M490 20 L447 137 L416 90 L369 58 Z"
                            fill="url(#footer-plane-fill)"
                        />
                        <path
                            {...line}
                            d="M490 20 L447 137 L416 90 L369 58 Z"
                            strokeWidth={1.4}
                        />
                        <path {...line} d="M490 20 L416 90" strokeWidth={1.2} />
                        <path
                            {...line}
                            d="M416 90 L428 120 L447 137"
                            strokeWidth={1}
                            className="opacity-70"
                        />
                        <path
                            {...line}
                            d="M416 90 L404 108"
                            strokeWidth={1}
                            className="opacity-70"
                        />
                        {/* Motion lines off the wing. */}
                        <path
                            {...line}
                            d="M356 40 l -22 -4 M350 78 l -26 2 M366 104 l -18 8"
                            strokeWidth={1}
                            className="opacity-60"
                        />
                    </g>
                </svg>
            </div>

            {/* A wireframe database, lower left beside the wordmark. */}
            <div
                ref={database}
                className="absolute bottom-[10%] left-[1.5%] hidden w-16 text-brand/40 will-change-transform md:block lg:w-20 xl:left-[5%] xl:w-24 dark:text-brand-soft/40"
            >
                <svg
                    viewBox="0 0 100 130"
                    className="w-full animate-glide-slow overflow-visible"
                    style={{ transformOrigin: 'center' }}
                >
                    <ellipse
                        {...line}
                        cx="50"
                        cy="20"
                        rx="40"
                        ry="12"
                        strokeWidth={1.3}
                    />
                    <path
                        {...line}
                        d="M10 20 v 84 M90 20 v 84"
                        strokeWidth={1.3}
                    />
                    <path
                        {...line}
                        d="M10 48 a 40 12 0 0 0 80 0 M10 76 a 40 12 0 0 0 80 0 M10 104 a 40 12 0 0 0 80 0"
                        strokeWidth={1.3}
                    />
                    <path
                        {...line}
                        d="M10 48 a 40 12 0 0 1 80 0 M10 76 a 40 12 0 0 1 80 0 M10 104 a 40 12 0 0 1 80 0"
                        strokeWidth={1}
                        strokeDasharray="2 5"
                        className="opacity-60"
                    />
                    {/* Rows of data. */}
                    <path
                        {...line}
                        d="M22 37 h 10 M22 65 h 16 M22 93 h 8"
                        strokeWidth={1.3}
                        className="opacity-70"
                    />
                </svg>
            </div>

            {streaks.map(([left, top, angle, phoneHidden]) => (
                <span
                    key={`${left}-${top}`}
                    className={cn(
                        'absolute h-px w-3.5 rounded-full bg-brand/30 dark:bg-brand-soft/30',
                        phoneHidden && 'hidden md:block',
                    )}
                    style={{
                        left: `${left}%`,
                        top: `${top}%`,
                        transform: `rotate(${angle}deg)`,
                    }}
                />
            ))}

            {sparks.map(([left, top, delay]) => (
                <span
                    key={`${left}-${top}`}
                    className="absolute size-1.5 animate-twinkle rounded-full bg-brand-soft shadow-[0_0_14px_4px] shadow-brand-soft/50"
                    style={{
                        left: `${left}%`,
                        top: `${top}%`,
                        animationDelay: `${delay}s`,
                    }}
                />
            ))}
        </div>
    );
}
