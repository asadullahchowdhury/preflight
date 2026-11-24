import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    Gauge,
    GitPullRequest,
    KeyRound,
    Lock,
    Rocket,
} from 'lucide-react';
import { useState } from 'react';
import { CodeBlock, GitHubIcon, TimeAgo } from '@/components/preflight/bits';
import {
    CheckHistory,
    formatBytes,
    formatCount,
    Sparkline,
} from '@/components/preflight/charts';
import { Reveal } from '@/components/preflight/motion';
import { SeverityBadge } from '@/components/preflight/severity';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { summaryLine } from '@/pages/dashboard';
import { dashboard } from '@/routes';
import { show as showAnalysis } from '@/routes/analyses';
import {
    destroy as destroyToken,
    store as storeToken,
} from '@/routes/repositories/stats-token';
import type {
    AnalysisSummary,
    Paginated,
    RepositoryStats,
    RepositorySummary,
    Severity,
} from '@/types/preflight';

type Props = {
    repository: RepositorySummary;
    analyses: Paginated<AnalysisSummary>;
    history: {
        id: number;
        severity: Severity | null;
        prNumber: number | null;
        createdAt: string | null;
    }[];
    stats: RepositoryStats;
    newStatsToken: string | null;
    audience: string;
    apiUrl: string;
    cloudUrl: string;
};

type Tab = 'checks' | 'tables';

export default function RepositoryShow({
    repository,
    analyses,
    history,
    stats,
    newStatsToken,
    audience,
    cloudUrl,
}: Props) {
    const hasAnalyses = analyses.total > 0;
    const [showSetup, setShowSetup] = useState(!hasAnalyses);
    const [tab, setTab] = useState<Tab>(newStatsToken ? 'tables' : 'checks');

    return (
        <>
            <Head title={repository.fullName} />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8">
                <Reveal className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <p className="text-sm text-muted-foreground">
                            {repository.owner}
                        </p>
                        <h1 className="flex items-center gap-2 truncate text-2xl font-semibold tracking-tight">
                            {repository.name}
                            {repository.private && (
                                <Lock className="size-4 text-muted-foreground" />
                            )}
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            className="glass"
                            onClick={() => setShowSetup((v) => !v)}
                        >
                            <Rocket className="size-4" />
                            {showSetup ? 'Hide setup' : 'Setup'}
                        </Button>
                        <Button variant="outline" className="glass" asChild>
                            <a
                                href={repository.url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <GitHubIcon className="size-4" />
                                Open on GitHub
                            </a>
                        </Button>
                    </div>
                </Reveal>

                <div
                    className={cn(
                        'grid transition-all duration-500 ease-out',
                        showSetup
                            ? 'blur-0 grid-rows-[1fr] opacity-100'
                            : 'grid-rows-[0fr] opacity-0 blur-sm',
                    )}
                >
                    <div className="overflow-hidden">
                        <Setup cloudUrl={audience} />
                    </div>
                </div>

                <Reveal delay={100}>
                    <Tabs
                        value={tab}
                        onChange={setTab}
                        tabs={[
                            {
                                value: 'checks',
                                label: 'Checks',
                                count: analyses.total,
                            },
                            {
                                value: 'tables',
                                label: 'Table sizes',
                                count: stats.tableCount,
                            },
                        ]}
                    />
                </Reveal>

                {tab === 'tables' ? (
                    <div key="tables" className="animate-blur-in-fast">
                        <StatsPanel
                            repository={repository}
                            stats={stats}
                            newToken={newStatsToken}
                            cloudUrl={cloudUrl}
                        />
                    </div>
                ) : (
                    <section
                        key="checks"
                        className="animate-blur-in-fast space-y-3"
                    >
                        {history.length > 1 && (
                            <div className="rounded-2xl glass p-4 sm:p-5">
                                <div className="mb-4 flex items-baseline justify-between gap-2">
                                    <h2 className="text-sm font-semibold tracking-tight">
                                        Recent checks
                                    </h2>
                                    <span className="text-xs text-muted-foreground">
                                        oldest → newest
                                    </span>
                                </div>
                                <CheckHistory checks={history} />
                            </div>
                        )}

                        {hasAnalyses ? (
                            <Reveal delay={180}>
                                <ul className="divide-y divide-border/60 overflow-hidden rounded-2xl glass">
                                    {analyses.data.map((analysis) => (
                                        <AnalysisRow
                                            key={analysis.id}
                                            analysis={analysis}
                                        />
                                    ))}
                                </ul>
                            </Reveal>
                        ) : (
                            <Reveal delay={180}>
                                <div className="rounded-2xl glass p-8 text-center text-sm text-muted-foreground">
                                    No checks yet. Add the workflow above, then
                                    open a pull request that changes a
                                    migration.
                                </div>
                            </Reveal>
                        )}

                        {analyses.last_page > 1 && (
                            <div className="flex items-center justify-between pt-2 text-sm">
                                <Pager
                                    href={analyses.prev_page_url}
                                    label="Newer"
                                    direction="prev"
                                />
                                <span className="text-muted-foreground">
                                    Page {analyses.current_page} of{' '}
                                    {analyses.last_page}
                                </span>
                                <Pager
                                    href={analyses.next_page_url}
                                    label="Older"
                                    direction="next"
                                />
                            </div>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

function Tabs<T extends string>({
    value,
    onChange,
    tabs,
}: {
    value: T;
    onChange: (value: T) => void;
    tabs: { value: T; label: string; count: number }[];
}) {
    const index = Math.max(
        0,
        tabs.findIndex((t) => t.value === value),
    );

    return (
        <div
            role="tablist"
            className="relative inline-grid w-full auto-cols-fr grid-flow-col rounded-xl glass p-1 sm:w-auto"
        >
            <span
                aria-hidden
                className="absolute inset-y-1 left-1 rounded-lg bg-background shadow-sm ring-1 ring-border transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                style={{
                    width: `calc((100% - 0.5rem) / ${tabs.length})`,
                    transform: `translateX(${index * 100}%)`,
                }}
            />
            {tabs.map((tab) => (
                <button
                    key={tab.value}
                    type="button"
                    role="tab"
                    aria-selected={tab.value === value}
                    onClick={() => onChange(tab.value)}
                    className={cn(
                        'relative z-10 flex items-center justify-center gap-2 rounded-lg px-3 py-1.5 text-sm whitespace-nowrap transition-colors sm:px-4',
                        tab.value === value
                            ? 'font-medium text-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.label}
                    <span className="rounded-full bg-muted px-1.5 text-xs text-muted-foreground tabular-nums">
                        {tab.count}
                    </span>
                </button>
            ))}
        </div>
    );
}

function StatsPanel({
    repository,
    stats,
    newToken,
    cloudUrl,
}: {
    repository: RepositorySummary;
    stats: RepositoryStats;
    newToken: string | null;
    cloudUrl: string;
}) {
    const [confirmRevoke, setConfirmRevoke] = useState(false);
    const largest = stats.tables[0]?.totalBytes ?? 0;

    const issue = () =>
        router.post(
            storeToken.url(repository.id),
            {},
            { preserveScroll: true },
        );
    const revoke = () =>
        router.delete(destroyToken.url(repository.id), {
            preserveScroll: true,
            onFinish: () => setConfirmRevoke(false),
        });

    return (
        <div className="space-y-4">
            <div className="rounded-2xl glass p-5 sm:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex gap-3">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand ring-1 ring-brand/20">
                            <Gauge className="size-5" />
                        </div>
                        <div>
                            <h2 className="font-semibold tracking-tight">
                                Size-aware checks
                            </h2>
                            <p className="mt-1 max-w-xl text-sm text-muted-foreground">
                                With production table sizes, every check
                                estimates how long a change takes, and a table
                                copy on a 12-row table stops being flagged as
                                dangerous. Only names and numbers are sent,
                                never row data.
                            </p>
                        </div>
                    </div>
                    <div className="flex shrink-0 flex-wrap gap-2">
                        {stats.hasToken && !confirmRevoke && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setConfirmRevoke(true)}
                            >
                                Revoke token
                            </Button>
                        )}
                        {confirmRevoke && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={revoke}
                            >
                                Revoke: pushes stop working
                            </Button>
                        )}
                        <Button size="sm" onClick={issue}>
                            <KeyRound className="size-4" />
                            {stats.hasToken ? 'New token' : 'Create token'}
                        </Button>
                    </div>
                </div>

                {newToken && (
                    <div className="mt-5 animate-blur-in-fast space-y-3 rounded-xl border border-brand/30 bg-brand/5 p-4">
                        <p className="text-sm font-medium">
                            Add these to production's environment. The token is
                            shown only once.
                        </p>
                        <CodeBlock
                            language="env"
                            code={`PREFLIGHT_CLOUD_URL=${cloudUrl}\nPREFLIGHT_STATS_TOKEN=${newToken}`}
                        />
                        <p className="text-sm font-medium">
                            Then schedule the push, e.g. in{' '}
                            <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                routes/console.php
                            </code>
                            :
                        </p>
                        <CodeBlock
                            language="php"
                            code="Schedule::command('preflight:stats --push')->daily();"
                        />
                    </div>
                )}

                <dl className="mt-5 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <StatsMeta label="Last push">
                        {stats.pushedAt ? (
                            <TimeAgo iso={stats.pushedAt} />
                        ) : (
                            'Never'
                        )}
                    </StatsMeta>
                    <StatsMeta label="Tables">{stats.tableCount}</StatsMeta>
                    <StatsMeta label="MySQL">
                        {stats.serverVersion ?? '–'}
                    </StatsMeta>
                    <StatsMeta label="Token">
                        {stats.hasToken ? (
                            <span className="text-emerald-600 dark:text-emerald-400">
                                Active
                            </span>
                        ) : (
                            'None'
                        )}
                    </StatsMeta>
                </dl>
            </div>

            {stats.tables.length === 0 ? (
                <div className="rounded-2xl glass p-8 text-center text-sm text-muted-foreground">
                    No sizes yet. Create a token, then run{' '}
                    <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                        php artisan preflight:stats --push
                    </code>{' '}
                    in production.
                </div>
            ) : (
                <div className="overflow-hidden rounded-2xl glass">
                    <table className="w-full text-sm">
                        <caption className="sr-only">
                            Production tables, largest first
                        </caption>
                        <thead>
                            <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                <th className="px-4 py-2.5 font-medium">
                                    Table
                                </th>
                                <th className="hidden px-4 py-2.5 text-right font-medium sm:table-cell">
                                    Rows
                                </th>
                                <th className="px-4 py-2.5 text-right font-medium">
                                    Size
                                </th>
                                <th className="hidden px-4 py-2.5 font-medium md:table-cell">
                                    Last 30 days
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border/60">
                            {stats.tables.map((table) => (
                                <tr
                                    key={table.name}
                                    className="transition-colors hover:bg-accent/40"
                                >
                                    <td className="px-4 py-3">
                                        <p className="font-mono text-[13px]">
                                            {table.name}
                                        </p>
                                        <div className="mt-1.5 h-1 w-full max-w-48 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-brand/70"
                                                style={{
                                                    width: `${largest ? Math.max(2, (table.totalBytes / largest) * 100) : 0}%`,
                                                }}
                                            />
                                        </div>
                                    </td>
                                    <td className="hidden px-4 py-3 text-right tabular-nums sm:table-cell">
                                        {formatCount(table.rows)}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        <p>{formatBytes(table.totalBytes)}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatBytes(table.indexBytes)}{' '}
                                            index
                                        </p>
                                    </td>
                                    <td className="hidden px-4 py-3 md:table-cell">
                                        <Sparkline points={table.history} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function StatsMeta({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-xl bg-background/50 p-3 ring-1 ring-border/60">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-medium">{children}</dd>
        </div>
    );
}

function AnalysisRow({ analysis }: { analysis: AnalysisSummary }) {
    return (
        <li>
            <Link
                href={showAnalysis(analysis.id)}
                prefetch
                className="group flex flex-col gap-2 px-4 py-3.5 transition-colors hover:bg-accent/60 sm:flex-row sm:items-center sm:gap-4"
            >
                <SeverityBadge severity={analysis.severity} />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">
                        {analysis.prNumber ? (
                            <span className="inline-flex items-center gap-1">
                                <GitPullRequest className="size-3.5 text-muted-foreground" />
                                #{analysis.prNumber}
                            </span>
                        ) : (
                            <span className="font-mono text-xs">
                                {analysis.headSha.slice(0, 7)}
                            </span>
                        )}
                        <span className="ml-2 font-normal text-muted-foreground">
                            {summaryLine(analysis)}
                        </span>
                    </p>
                    {analysis.migrations.length > 0 && (
                        <p className="truncate font-mono text-xs text-muted-foreground">
                            {analysis.migrations.join(', ')}
                        </p>
                    )}
                </div>
                <span className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                    <TimeAgo iso={analysis.createdAt} />
                    <ChevronRight className="hidden size-4 transition-transform group-hover:translate-x-0.5 sm:block" />
                </span>
            </Link>
        </li>
    );
}

function Pager({
    href,
    label,
    direction,
}: {
    href: string | null;
    label: string;
    direction: 'prev' | 'next';
}) {
    if (!href) {
        return <span className="w-20" />;
    }

    return (
        <Link
            href={href}
            preserveScroll
            className="inline-flex w-20 items-center gap-1 text-muted-foreground transition-colors hover:text-foreground data-[dir=next]:justify-end"
            data-dir={direction}
        >
            {direction === 'prev' && <ChevronLeft className="size-4" />}
            {label}
            {direction === 'next' && <ChevronRight className="size-4" />}
        </Link>
    );
}

function Setup({ cloudUrl }: { cloudUrl: string }) {
    const workflow = `name: preflight

on:
  pull_request:
    paths: ['database/migrations/**']

permissions:
  contents: read
  id-token: write        # lets Preflight verify this job without secrets

jobs:
  preflight:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - uses: asadullahchowdhury/preflight/action@main
        with:
          php-version: '8.4'
          mysql-version: '8.0.36'   # what production runs
          cloud-url: ${cloudUrl}`;

    return (
        <div className="space-y-5 rounded-2xl glass p-5 sm:p-6">
            <div>
                <h2 className="font-semibold tracking-tight">
                    Add Preflight to this repository
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Two steps. Nothing to store in your repository's secrets.
                </p>
            </div>
            <ol className="space-y-5">
                <li className="space-y-2">
                    <p className="text-sm font-medium">
                        1. Require the Laravel package
                    </p>
                    <CodeBlock
                        language="shell"
                        code="composer require --dev preflight/laravel"
                    />
                </li>
                <li className="space-y-2">
                    <p className="text-sm font-medium">
                        2. Add{' '}
                        <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                            .github/workflows/preflight.yml
                        </code>
                    </p>
                    <CodeBlock language="yaml" code={workflow} />
                </li>
            </ol>
            <a
                href="https://github.com/asadullahchowdhury/preflight/tree/main/action#readme"
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1 text-sm text-brand hover:underline"
            >
                All action options <ArrowUpRight className="size-3.5" />
            </a>
        </div>
    );
}

RepositoryShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: props.repository.fullName, href: '#' },
    ],
});
