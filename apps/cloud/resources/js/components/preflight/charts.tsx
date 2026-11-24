import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { severityMeta, SeverityIcon } from '@/components/preflight/severity';
import { cn } from '@/lib/utils';
import { show as showAnalysis } from '@/routes/analyses';
import type { Severity } from '@/types/preflight';

export function formatBytes(bytes: number): string {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${value >= 10 || unit === 0 ? Math.round(value) : value.toFixed(1)} ${units[unit]}`;
}

export function formatCount(n: number): string {
    return new Intl.NumberFormat(undefined, {
        notation: n >= 100_000 ? 'compact' : 'standard',
        maximumFractionDigits: 1,
    }).format(n);
}

/**
 * One table's total size over time. Single series, so no legend; hovering shows the day's value.
 */
export function Sparkline({
    points,
    className,
}: {
    points: { date: string; bytes: number }[];
    className?: string;
}) {
    const [hover, setHover] = useState<number | null>(null);
    const width = 120;
    const height = 32;
    const pad = 3;

    if (points.length < 2) {
        return (
            <span className={cn('text-xs text-muted-foreground', className)}>
                {points.length === 1 ? 'one day so far' : ''}
            </span>
        );
    }

    const values = points.map((p) => p.bytes);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const span = max - min || 1;
    const x = (i: number) =>
        pad + (i / (points.length - 1)) * (width - pad * 2);
    const y = (v: number) =>
        height - pad - ((v - min) / span) * (height - pad * 2);
    const line = points
        .map(
            (p, i) =>
                `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(p.bytes).toFixed(1)}`,
        )
        .join(' ');
    const area = `${line} L${x(points.length - 1).toFixed(1)},${height} L${x(0).toFixed(1)},${height} Z`;
    const growth =
        values[0] > 0 ? (values[values.length - 1] - values[0]) / values[0] : 0;
    const active = hover !== null ? points[hover] : null;

    return (
        <div className={cn('relative', className)}>
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="h-8 w-[7.5rem] overflow-visible"
                role="img"
                aria-label={`Size over ${points.length} days: ${formatBytes(values[0])} to ${formatBytes(values[values.length - 1])}`}
                onMouseLeave={() => setHover(null)}
            >
                <defs>
                    <linearGradient id="spark-fill" x1="0" x2="0" y1="0" y2="1">
                        <stop
                            offset="0%"
                            stopColor="var(--color-brand)"
                            stopOpacity={0.25}
                        />
                        <stop
                            offset="100%"
                            stopColor="var(--color-brand)"
                            stopOpacity={0}
                        />
                    </linearGradient>
                </defs>
                <path d={area} fill="url(#spark-fill)" />
                <path
                    d={line}
                    fill="none"
                    stroke="var(--color-brand)"
                    strokeWidth={2}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    vectorEffect="non-scaling-stroke"
                />
                {hover !== null && (
                    <>
                        <line
                            x1={x(hover)}
                            x2={x(hover)}
                            y1={0}
                            y2={height}
                            stroke="currentColor"
                            className="text-muted-foreground/40"
                            strokeWidth={1}
                            vectorEffect="non-scaling-stroke"
                        />
                        <circle
                            cx={x(hover)}
                            cy={y(points[hover].bytes)}
                            r={3}
                            fill="var(--color-brand)"
                            stroke="var(--background)"
                            strokeWidth={1.5}
                        />
                    </>
                )}
                {points.map((p, i) => (
                    <rect
                        key={p.date}
                        x={i === 0 ? 0 : (x(i - 1) + x(i)) / 2}
                        width={
                            (i === points.length - 1
                                ? width
                                : (x(i) + x(i + 1)) / 2) -
                            (i === 0 ? 0 : (x(i - 1) + x(i)) / 2)
                        }
                        y={0}
                        height={height}
                        fill="transparent"
                        onMouseEnter={() => setHover(i)}
                    />
                ))}
            </svg>
            <span
                className={cn(
                    'pointer-events-none absolute -top-7 right-0 rounded-md bg-popover px-2 py-0.5 text-[11px] whitespace-nowrap shadow-md ring-1 ring-border transition-all duration-200',
                    active ? 'blur-0 opacity-100' : 'opacity-0 blur-[2px]',
                )}
            >
                {active
                    ? `${formatBytes(active.bytes)} · ${new Date(active.date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`
                    : ''}
            </span>
            {growth !== 0 && (
                <span className="sr-only">
                    {growth > 0 ? 'grew' : 'shrank'}{' '}
                    {Math.abs(Math.round(growth * 100))}%
                </span>
            )}
        </div>
    );
}

/**
 * The repository's recent checks, oldest to newest, one bar each in its severity's status colour.
 * Every bar links to its check; the legend names the colours (with icons), so colour is never the only signal.
 */
export function CheckHistory({
    checks,
}: {
    checks: {
        id: number;
        severity: Severity | null;
        prNumber: number | null;
        createdAt: string | null;
    }[];
}) {
    const [hover, setHover] = useState<number | null>(null);
    const active = hover !== null ? checks[hover] : null;
    const counts = { danger: 0, warn: 0, info: 0 } as Record<Severity, number>;
    checks.forEach((c) => counts[c.severity ?? 'info']++);

    return (
        <div className="space-y-3">
            <div className="relative">
                <div
                    className="flex h-12 items-end gap-[2px]"
                    onMouseLeave={() => setHover(null)}
                >
                    {checks.map((check, i) => {
                        const severity = check.severity ?? 'info';
                        const height =
                            severity === 'danger'
                                ? 'h-full'
                                : severity === 'warn'
                                  ? 'h-2/3'
                                  : 'h-1/3';

                        return (
                            <Link
                                key={check.id}
                                href={showAnalysis(check.id)}
                                aria-label={`${check.prNumber ? `#${check.prNumber}` : 'Check'}: ${severityMeta[severity].label}`}
                                onMouseEnter={() => setHover(i)}
                                onFocus={() => setHover(i)}
                                className="group flex h-full max-w-5 min-w-1 flex-1 items-end"
                            >
                                <span
                                    className={cn(
                                        'w-full rounded-t-[4px] transition-all duration-300 group-hover:opacity-100 group-focus-visible:ring-2 group-focus-visible:ring-ring',
                                        height,
                                        severityMeta[severity].dot,
                                        hover !== null && hover !== i
                                            ? 'opacity-40'
                                            : 'opacity-90',
                                    )}
                                />
                            </Link>
                        );
                    })}
                </div>
                <div
                    className={cn(
                        'pointer-events-none absolute -top-8 left-0 rounded-md bg-popover px-2 py-1 text-xs whitespace-nowrap shadow-md ring-1 ring-border transition-all duration-200',
                        active ? 'blur-0 opacity-100' : 'opacity-0 blur-[2px]',
                    )}
                >
                    {active && (
                        <span className="flex items-center gap-1.5">
                            <SeverityIcon
                                severity={active.severity}
                                className="size-3.5"
                            />
                            {active.prNumber ? `#${active.prNumber}` : 'Check'}{' '}
                            · {severityMeta[active.severity ?? 'info'].label} ·{' '}
                            {active.createdAt
                                ? new Date(active.createdAt).toLocaleDateString(
                                      undefined,
                                      { month: 'short', day: 'numeric' },
                                  )
                                : ''}
                        </span>
                    )}
                </div>
            </div>
            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                {(['danger', 'warn', 'info'] as Severity[]).map((s) => (
                    <span key={s} className="flex items-center gap-1.5">
                        <SeverityIcon severity={s} className="size-3.5" />
                        {severityMeta[s].label}
                        <span className="text-foreground tabular-nums">
                            {counts[s]}
                        </span>
                    </span>
                ))}
            </div>
        </div>
    );
}
