import { Check, Copy } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode, SVGAttributes } from 'react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

export function GitHubIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden {...props}>
            <path d="M12 .5C5.65.5.5 5.65.5 12a11.5 11.5 0 0 0 7.86 10.92c.58.1.79-.25.79-.56v-2c-3.2.7-3.88-1.37-3.88-1.37-.52-1.33-1.28-1.68-1.28-1.68-1.04-.71.08-.7.08-.7 1.15.08 1.76 1.19 1.76 1.19 1.03 1.76 2.69 1.25 3.35.96.1-.75.4-1.25.73-1.54-2.55-.29-5.24-1.28-5.24-5.69 0-1.26.45-2.29 1.19-3.1-.12-.29-.52-1.46.11-3.05 0 0 .97-.31 3.17 1.18a11 11 0 0 1 5.77 0c2.2-1.49 3.17-1.18 3.17-1.18.63 1.59.23 2.76.11 3.05.74.81 1.19 1.84 1.19 3.1 0 4.42-2.7 5.39-5.26 5.68.41.36.78 1.06.78 2.14v3.17c0 .31.21.67.8.56A11.5 11.5 0 0 0 23.5 12C23.5 5.65 18.35.5 12 .5Z" />
        </svg>
    );
}

export function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'default',
}: {
    label: string;
    value: ReactNode;
    hint?: string;
    icon: LucideIcon;
    tone?: 'default' | 'danger';
}) {
    return (
        <div className="group relative overflow-hidden rounded-2xl glass p-4 shadow-sm transition-all duration-500 hover:-translate-y-0.5 hover:shadow-lg sm:p-5">
            <div
                className={cn(
                    'absolute -top-10 -right-10 size-32 rounded-full blur-2xl transition-opacity duration-700 group-hover:opacity-100',
                    tone === 'danger'
                        ? 'bg-red-500/15 opacity-70'
                        : 'bg-brand/15 opacity-60',
                )}
            />
            {/* Icon above the numbers on phones, beside them from sm up. */}
            <div className="relative flex flex-col-reverse gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <p className="text-xs text-muted-foreground sm:text-sm">
                        {label}
                    </p>
                    <p className="text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl">
                        {value}
                    </p>
                    {hint && (
                        <p className="text-xs text-muted-foreground">{hint}</p>
                    )}
                </div>
                <div
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset sm:size-10 sm:rounded-xl',
                        tone === 'danger'
                            ? 'bg-red-500/10 text-red-600 ring-red-500/20 dark:text-red-400'
                            : 'bg-brand/10 text-brand ring-brand/20',
                    )}
                >
                    <Icon className="size-4 sm:size-5" />
                </div>
            </div>
        </div>
    );
}

export function EmptyState({
    icon: Icon,
    title,
    children,
    action,
}: {
    icon: LucideIcon;
    title: string;
    children?: ReactNode;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center rounded-2xl glass px-6 py-12 text-center">
            <div className="relative mb-4">
                <div className="absolute inset-0 rounded-2xl bg-brand/30 blur-xl" />
                <div className="relative flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-soft text-white shadow-lg">
                    <Icon className="size-6" />
                </div>
            </div>
            <h3 className="text-base font-semibold">{title}</h3>
            {children && (
                <div className="mt-1.5 max-w-md text-sm text-muted-foreground">
                    {children}
                </div>
            )}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}

export function CodeBlock({
    code,
    language,
    className,
}: {
    code: string;
    language?: string;
    className?: string;
}) {
    const [copied, setCopied] = useState(false);

    const copy = () => {
        void navigator.clipboard?.writeText(code).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1600);
        });
    };

    return (
        <div
            className={cn(
                'group relative overflow-hidden rounded-xl border bg-neutral-950 text-neutral-100 dark:border-white/10',
                className,
            )}
        >
            <div className="flex items-center justify-between border-b border-white/10 px-4 py-2">
                <span className="font-mono text-[11px] tracking-wide text-neutral-400 uppercase">
                    {language ?? 'code'}
                </span>
                <button
                    type="button"
                    onClick={copy}
                    className="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-neutral-300 transition-colors hover:bg-white/10 hover:text-white"
                    aria-label="Copy to clipboard"
                >
                    {copied ? (
                        <Check className="size-3.5 text-emerald-400" />
                    ) : (
                        <Copy className="size-3.5" />
                    )}
                    {copied ? 'Copied' : 'Copy'}
                </button>
            </div>
            <pre className="overflow-x-auto p-4 text-[13px] leading-relaxed">
                <code>{code}</code>
            </pre>
        </div>
    );
}

const relative = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

export function timeAgo(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const seconds = (new Date(iso).getTime() - Date.now()) / 1000;
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31_536_000],
        ['month', 2_592_000],
        ['week', 604_800],
        ['day', 86_400],
        ['hour', 3_600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relative.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}

export function TimeAgo({ iso }: { iso: string | null }) {
    return (
        <time
            dateTime={iso ?? undefined}
            title={iso ? new Date(iso).toLocaleString() : undefined}
        >
            {timeAgo(iso)}
        </time>
    );
}

/**
 * Text from the engine, where `backticks` mark code (names, SQL, PHP). Renders those spans as code.
 */
export function RichText({ text }: { text: string }) {
    const parts = text.split('`');

    return (
        <>
            {parts.map((part, i) =>
                i % 2 === 1 ? (
                    <code
                        key={i}
                        className="rounded bg-muted px-1 py-0.5 font-mono text-[0.85em] break-words text-foreground"
                    >
                        {part}
                    </code>
                ) : (
                    <span key={i}>{part}</span>
                ),
            )}
        </>
    );
}
