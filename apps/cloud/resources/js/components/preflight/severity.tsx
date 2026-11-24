import { CircleAlert, CircleCheck, OctagonAlert } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Severity } from '@/types/preflight';

type Meta = {
    label: string;
    icon: LucideIcon;
    dot: string;
    badge: string;
    text: string;
    ring: string;
    glow: string;
};

export const severityMeta: Record<Severity, Meta> = {
    danger: {
        label: 'Danger',
        icon: OctagonAlert,
        dot: 'bg-red-500',
        badge: 'bg-red-500/10 text-red-700 ring-red-500/25 dark:text-red-300',
        text: 'text-red-600 dark:text-red-400',
        ring: 'ring-red-500/30',
        glow: 'from-red-500/20',
    },
    warn: {
        label: 'Warning',
        icon: CircleAlert,
        dot: 'bg-amber-500',
        badge: 'bg-amber-500/10 text-amber-700 ring-amber-500/25 dark:text-amber-300',
        text: 'text-amber-600 dark:text-amber-400',
        ring: 'ring-amber-500/30',
        glow: 'from-amber-500/20',
    },
    info: {
        label: 'Safe',
        icon: CircleCheck,
        dot: 'bg-emerald-500',
        badge: 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/25 dark:text-emerald-300',
        text: 'text-emerald-600 dark:text-emerald-400',
        ring: 'ring-emerald-500/30',
        glow: 'from-emerald-500/20',
    },
};

export function SeverityBadge({
    severity,
    className,
    label,
}: {
    severity: Severity | null;
    className?: string;
    label?: string;
}) {
    const meta = severityMeta[severity ?? 'info'];

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
                meta.badge,
                className,
            )}
        >
            <span className="relative flex size-1.5">
                {severity === 'danger' && (
                    <span
                        className={cn(
                            'absolute inline-flex size-full animate-ping rounded-full opacity-60',
                            meta.dot,
                        )}
                    />
                )}
                <span
                    className={cn(
                        'relative inline-flex size-1.5 rounded-full',
                        meta.dot,
                    )}
                />
            </span>
            {label ?? meta.label}
        </span>
    );
}

export function SeverityIcon({
    severity,
    className,
}: {
    severity: Severity | null;
    className?: string;
}) {
    const meta = severityMeta[severity ?? 'info'];
    const Icon = meta.icon;

    return <Icon className={cn('size-4', meta.text, className)} />;
}
