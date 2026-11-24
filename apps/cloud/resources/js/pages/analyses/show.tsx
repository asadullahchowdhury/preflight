import { Head } from '@inertiajs/react';
import {
    ChevronDown,
    Clock,
    Database,
    FileCode2,
    GitCommitHorizontal,
    GitPullRequest,
    Info,
    Lightbulb,
    PartyPopper,
    PlayCircle,
    TriangleAlert,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { TimeAgo } from '@/components/preflight/bits';
import { Reveal } from '@/components/preflight/motion';
import {
    SeverityBadge,
    SeverityIcon,
    severityMeta,
} from '@/components/preflight/severity';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { summaryLine } from '@/pages/dashboard';
import { dashboard } from '@/routes';
import { show as showRepository } from '@/routes/repositories';
import type {
    Advisory,
    AnalysisSummary,
    RepositorySummary,
    Statement,
} from '@/types/preflight';

type Props = {
    analysis: AnalysisSummary & {
        baseRef: string | null;
        runUrl: string | null;
        actor: string | null;
        sizeAware: boolean;
        mysqlVersion: string;
        status: string;
        error: string | null;
    };
    repository: RepositorySummary;
    statements: Statement[];
    advisories: Advisory[];
    notes: string[];
};

export default function AnalysisShow({
    analysis,
    statements,
    advisories,
    notes,
}: Props) {
    const groups = useMemo(() => groupByMigration(statements), [statements]);
    const severity = analysis.severity ?? 'info';
    const meta = severityMeta[severity];
    const title = analysis.prNumber
        ? `#${analysis.prNumber}`
        : analysis.headSha.slice(0, 7);

    return (
        <>
            <Head title={`${analysis.repository} ${title}`} />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8">
                {/* Verdict */}
                <Reveal>
                    <div className="relative overflow-hidden rounded-3xl glass p-5 sm:p-7">
                        <div
                            className={cn(
                                'absolute inset-0 bg-gradient-to-br to-transparent to-60%',
                                meta.glow,
                            )}
                        />
                        <div className="relative flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex items-start gap-4">
                                <div
                                    className={cn(
                                        'flex size-12 shrink-0 items-center justify-center rounded-2xl bg-background/70 ring-1 backdrop-blur',
                                        meta.ring,
                                    )}
                                >
                                    <SeverityIcon
                                        severity={severity}
                                        className="size-6"
                                    />
                                </div>
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
                                            {headline(analysis)}
                                        </h1>
                                        <SeverityBadge severity={severity} />
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {analysis.repository} · {title} ·{' '}
                                        <TimeAgo iso={analysis.createdAt} />
                                    </p>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {analysis.prUrl && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="glass"
                                        asChild
                                    >
                                        <a
                                            href={analysis.prUrl}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <GitPullRequest className="size-4" />
                                            Pull request
                                        </a>
                                    </Button>
                                )}
                                {analysis.runUrl && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="glass"
                                        asChild
                                    >
                                        <a
                                            href={analysis.runUrl}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <PlayCircle className="size-4" />
                                            CI run
                                        </a>
                                    </Button>
                                )}
                            </div>
                        </div>

                        <dl className="relative mt-6 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <Meta icon={Database} label="MySQL">
                                {analysis.mysqlVersion}
                            </Meta>
                            <Meta icon={FileCode2} label="Migrations">
                                {analysis.migrations.length}
                            </Meta>
                            <Meta icon={GitCommitHorizontal} label="Commit">
                                <span className="font-mono">
                                    {analysis.headSha.slice(0, 7)}
                                </span>
                            </Meta>
                            <Meta icon={Clock} label="Durations">
                                {analysis.sizeAware ? 'Estimated' : 'No sizes'}
                            </Meta>
                        </dl>
                    </div>
                </Reveal>

                {analysis.status === 'failed' && analysis.error && (
                    <Reveal delay={60}>
                        <div className="flex gap-3 rounded-2xl border border-red-500/30 bg-red-500/5 p-4 text-sm">
                            <TriangleAlert className="size-4 shrink-0 text-red-500" />
                            <div>
                                <p className="font-medium">
                                    Could not post to GitHub
                                </p>
                                <p className="mt-0.5 text-muted-foreground">
                                    {analysis.error}
                                </p>
                            </div>
                        </div>
                    </Reveal>
                )}

                {notes.map((note, i) => (
                    <Reveal key={note} delay={80 + i * 40}>
                        <div className="flex gap-3 rounded-2xl glass p-4 text-sm">
                            <Info className="size-4 shrink-0 text-brand" />
                            <p className="text-muted-foreground">{note}</p>
                        </div>
                    </Reveal>
                ))}

                {statements.length === 0 ? (
                    <Reveal delay={120}>
                        <div className="flex flex-col items-center rounded-2xl glass p-10 text-center">
                            <PartyPopper className="size-8 text-emerald-500" />
                            <p className="mt-3 font-medium">
                                No migration changes in this pull request
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Nothing for MySQL to lock.
                            </p>
                        </div>
                    </Reveal>
                ) : (
                    groups.map((group, g) => (
                        <Reveal
                            key={group.migration ?? g}
                            delay={140 + g * 80}
                            as="section"
                            className="space-y-3"
                        >
                            <h2 className="flex items-center gap-2 font-mono text-xs text-muted-foreground">
                                <FileCode2 className="size-3.5" />
                                <span className="truncate">
                                    {group.migration ?? 'Statements'}
                                </span>
                            </h2>
                            <div className="space-y-3">
                                {group.statements.map((statement) => (
                                    <StatementCard
                                        key={statement.index}
                                        statement={statement}
                                    />
                                ))}
                            </div>
                        </Reveal>
                    ))
                )}

                {advisories.length > 0 && (
                    <Reveal delay={200 + groups.length * 80}>
                        <details className="group rounded-2xl glass p-4 [&_summary::-webkit-details-marker]:hidden">
                            <summary className="flex cursor-pointer items-center justify-between gap-2 text-sm font-medium">
                                <span className="flex items-center gap-2">
                                    <Info className="size-4 text-brand" />
                                    Things to know when this runs (
                                    {advisories.length})
                                </span>
                                <ChevronDown className="size-4 transition-transform duration-300 group-open:rotate-180" />
                            </summary>
                            <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                                {advisories.map((advisory, i) => (
                                    <li
                                        key={`${advisory.id}-${i}`}
                                        className="flex gap-2"
                                    >
                                        <span
                                            className={cn(
                                                'mt-1.5 size-1.5 shrink-0 rounded-full',
                                                severityMeta[advisory.severity]
                                                    .dot,
                                            )}
                                        />
                                        <span>{advisory.message}</span>
                                    </li>
                                ))}
                            </ul>
                        </details>
                    </Reveal>
                )}
            </div>
        </>
    );
}

function StatementCard({ statement }: { statement: Statement }) {
    const risky = statement.severity !== 'info';
    const [open, setOpen] = useState(risky);
    const meta = severityMeta[statement.severity];
    const finding = [...statement.findings].sort(
        (a, b) => rank(b.severity) - rank(a.severity),
    )[0];

    return (
        <div
            className={cn(
                'overflow-hidden rounded-2xl glass transition-shadow duration-500',
                risky && 'ring-1',
                risky && meta.ring,
                open && 'shadow-lg',
            )}
        >
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-start gap-3 p-4 text-left transition-colors hover:bg-accent/40"
                aria-expanded={open}
            >
                <SeverityIcon
                    severity={statement.severity}
                    className="mt-0.5 shrink-0"
                />
                <div className="min-w-0 flex-1 space-y-2">
                    <code className="block truncate font-mono text-[13px]">
                        {statement.sql}
                    </code>
                    <div className="flex flex-wrap items-center gap-1.5">
                        {statement.algorithm && (
                            <Chip
                                tone={
                                    statement.algorithm === 'COPY'
                                        ? 'danger'
                                        : 'default'
                                }
                            >
                                {statement.algorithm}
                            </Chip>
                        )}
                        {statement.blocks_writes && (
                            <Chip tone="danger">Blocks writes</Chip>
                        )}
                        {statement.rebuilds_table && (
                            <Chip>Rebuilds table</Chip>
                        )}
                        {statement.on_new_table && <Chip>New table</Chip>}
                        {statement.estimate && (
                            <Chip>
                                <Clock className="size-3" />
                                {statement.estimate.label}
                            </Chip>
                        )}
                        {!open && finding && (
                            <span className="truncate text-xs text-muted-foreground">
                                {finding.rule}
                            </span>
                        )}
                    </div>
                </div>
                <ChevronDown
                    className={cn(
                        'mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform duration-300',
                        open && 'rotate-180',
                    )}
                />
            </button>

            <div
                className={cn(
                    'grid transition-all duration-500 ease-out',
                    open
                        ? 'blur-0 grid-rows-[1fr] opacity-100'
                        : 'grid-rows-[0fr] opacity-0 blur-[2px]',
                )}
            >
                <div className="overflow-hidden">
                    <div className="space-y-3 border-t border-border/60 p-4">
                        <pre className="rounded-xl bg-neutral-950 p-3 font-mono text-[12.5px] leading-relaxed [overflow-wrap:anywhere] whitespace-pre-wrap text-neutral-100">
                            {statement.sql}
                        </pre>
                        {statement.findings.map((f, i) => (
                            <div key={i} className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <SeverityBadge severity={f.severity} />
                                    <span className="font-mono text-xs text-muted-foreground">
                                        {f.rule}
                                    </span>
                                </div>
                                <p className="text-sm leading-relaxed">
                                    {f.message}
                                </p>
                                {f.suggestion && f.severity !== 'info' && (
                                    <div className="flex gap-2.5 rounded-xl border border-brand/20 bg-brand/5 p-3 text-sm">
                                        <Lightbulb className="mt-0.5 size-4 shrink-0 text-brand" />
                                        <p className="leading-relaxed">
                                            {f.suggestion}
                                        </p>
                                    </div>
                                )}
                            </div>
                        ))}
                        {statement.advisories.map((a, i) => (
                            <p
                                key={`a-${i}`}
                                className="flex gap-2 text-sm text-muted-foreground"
                            >
                                <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-500" />
                                {a.message}
                            </p>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

function Chip({
    children,
    tone = 'default',
}: {
    children: React.ReactNode;
    tone?: 'default' | 'danger';
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-mono text-[11px] font-medium ring-1 ring-inset',
                tone === 'danger'
                    ? 'bg-red-500/10 text-red-700 ring-red-500/20 dark:text-red-300'
                    : 'bg-muted text-muted-foreground ring-border',
            )}
        >
            {children}
        </span>
    );
}

function Meta({
    icon: Icon,
    label,
    children,
}: {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-xl bg-background/50 p-3 ring-1 ring-border/60 backdrop-blur">
            <dt className="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Icon className="size-3.5" />
                {label}
            </dt>
            <dd className="mt-1 font-medium">{children}</dd>
        </div>
    );
}

function headline(analysis: AnalysisSummary): string {
    if (analysis.severity === 'danger') {
        return 'This would lock production';
    }
    if (analysis.severity === 'warn') {
        return 'Worth a second look';
    }

    return analysis.statementCount === 0 ? 'Nothing to check' : 'Safe to ship';
}

function rank(severity: string): number {
    return severity === 'danger' ? 2 : severity === 'warn' ? 1 : 0;
}

function groupByMigration(statements: Statement[]) {
    const groups: { migration: string | null; statements: Statement[] }[] = [];
    for (const statement of statements) {
        const last = groups[groups.length - 1];
        if (last && last.migration === statement.migration) {
            last.statements.push(statement);
        } else {
            groups.push({
                migration: statement.migration,
                statements: [statement],
            });
        }
    }

    return groups;
}

AnalysisShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: props.repository.fullName,
            href: showRepository(props.repository.id),
        },
        {
            title: props.analysis.prNumber
                ? `#${props.analysis.prNumber}`
                : summaryLine(props.analysis),
            href: '#',
        },
    ],
});
