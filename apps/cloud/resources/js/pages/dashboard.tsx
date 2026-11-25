import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowUpRight,
    ChevronRight,
    FolderGit2,
    GitPullRequest,
    Lock,
    Plus,
    RefreshCw,
    ShieldAlert,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import {
    EmptyState,
    GitHubIcon,
    StatCard,
    TimeAgo,
} from '@/components/preflight/bits';
import { Reveal } from '@/components/preflight/motion';
import { SeverityBadge, severityMeta } from '@/components/preflight/severity';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { show as showAnalysis } from '@/routes/analyses';
import { sync } from '@/routes/installations';
import { show as showRepository } from '@/routes/repositories';
import type {
    AnalysisSummary,
    InstallationSummary,
    RepositorySummary,
} from '@/types/preflight';

type Props = {
    installUrl: string;
    installations: InstallationSummary[];
    repositories: RepositorySummary[];
    recentAnalyses: AnalysisSummary[];
    stats: {
        repositories: number;
        analyses: number;
        dangerous: number;
        statements: number;
    };
};

export default function Dashboard({
    installUrl,
    installations,
    repositories,
    recentAnalyses,
    stats,
}: Props) {
    const { auth } = usePage().props;
    const [syncing, setSyncing] = useState(false);
    const firstName = auth.user.name.split(' ')[0];

    const syncNow = () =>
        router.post(
            sync.url(),
            {},
            {
                preserveScroll: true,
                onStart: () => setSyncing(true),
                onFinish: () => setSyncing(false),
            },
        );

    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 px-4 py-6 sm:px-6 lg:py-8">
                <Reveal className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Welcome back, {firstName}
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                            Your migrations,{' '}
                            <span className="text-gradient whitespace-nowrap">
                                pre-flighted
                            </span>
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={syncNow}
                            disabled={syncing}
                            className="glass"
                        >
                            <RefreshCw
                                className={cn(
                                    'size-4',
                                    syncing && 'animate-spin',
                                )}
                            />
                            Sync with GitHub
                        </Button>
                        <Button asChild>
                            <a href={installUrl}>
                                <Plus className="size-4" />
                                Add repositories
                            </a>
                        </Button>
                    </div>
                </Reveal>

                <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <Reveal delay={60}>
                        <StatCard
                            label="Repositories"
                            value={stats.repositories}
                            icon={FolderGit2}
                            hint={`${installations.length} ${installations.length === 1 ? 'account' : 'accounts'}`}
                        />
                    </Reveal>
                    <Reveal delay={120}>
                        <StatCard
                            label="Checks"
                            value={stats.analyses}
                            icon={Activity}
                            hint="last 30 days"
                        />
                    </Reveal>
                    <Reveal delay={180}>
                        <StatCard
                            label="Risky PRs caught"
                            value={stats.dangerous}
                            icon={ShieldAlert}
                            tone="danger"
                            hint="would have locked a table"
                        />
                    </Reveal>
                    <Reveal delay={240}>
                        <StatCard
                            label="Statements checked"
                            value={stats.statements}
                            icon={Sparkles}
                            hint="last 30 days"
                        />
                    </Reveal>
                </div>

                {installations.length === 0 ? (
                    <Reveal delay={300}>
                        <Onboarding installUrl={installUrl} />
                    </Reveal>
                ) : (
                    <div className="grid grid-cols-1 gap-8 lg:grid-cols-5">
                        <section className="space-y-3 lg:col-span-3">
                            <Reveal delay={300}>
                                <SectionTitle
                                    title="Repositories"
                                    count={repositories.length}
                                />
                            </Reveal>
                            {repositories.length === 0 ? (
                                <Reveal delay={340}>
                                    <EmptyState
                                        icon={FolderGit2}
                                        title="No repositories yet"
                                        action={
                                            <Button asChild>
                                                <a href={installUrl}>
                                                    Choose repositories
                                                </a>
                                            </Button>
                                        }
                                    >
                                        The app is installed, but no
                                        repositories are selected. Pick the ones
                                        Preflight should check.
                                    </EmptyState>
                                </Reveal>
                            ) : (
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    {repositories.map((repository, i) => (
                                        <Reveal
                                            key={repository.id}
                                            delay={340 + i * 50}
                                            dolly={0.6}
                                        >
                                            <RepositoryCard
                                                repository={repository}
                                            />
                                        </Reveal>
                                    ))}
                                </div>
                            )}
                        </section>

                        <section className="space-y-3 lg:col-span-2">
                            <Reveal delay={360}>
                                <SectionTitle title="Recent checks" />
                            </Reveal>
                            <Reveal delay={420} dolly={0.5}>
                                <RecentAnalyses analyses={recentAnalyses} />
                            </Reveal>
                            <Reveal delay={480} dolly={0.5}>
                                <Accounts installations={installations} />
                            </Reveal>
                        </section>
                    </div>
                )}
            </div>
        </>
    );
}

function SectionTitle({ title, count }: { title: string; count?: number }) {
    return (
        <div className="flex items-center gap-2">
            <h2 className="text-sm font-semibold tracking-tight">{title}</h2>
            {count !== undefined && (
                <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground tabular-nums">
                    {count}
                </span>
            )}
        </div>
    );
}

function RepositoryCard({ repository }: { repository: RepositorySummary }) {
    const latest = repository.latest;
    const meta = severityMeta[latest?.severity ?? 'info'];

    return (
        <Link
            href={showRepository(repository.id)}
            prefetch
            className="group relative block overflow-hidden rounded-2xl glass p-4 transition-all duration-500 hover:-translate-y-0.5 hover:shadow-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            {latest && (
                <div
                    className={cn(
                        'absolute inset-x-0 top-0 h-16 bg-gradient-to-b to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100',
                        meta.glow,
                    )}
                />
            )}
            <div className="relative flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate text-xs text-muted-foreground">
                        {repository.owner}
                    </p>
                    <p className="flex items-center gap-1.5 truncate font-medium">
                        {repository.name}
                        {repository.private && (
                            <Lock className="size-3 shrink-0 text-muted-foreground" />
                        )}
                    </p>
                </div>
                <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform duration-300 group-hover:translate-x-0.5" />
            </div>
            <div className="relative mt-4 flex items-center justify-between gap-2 text-xs text-muted-foreground">
                {latest ? (
                    <>
                        <SeverityBadge severity={latest.severity} />
                        <span className="truncate">
                            {latest.prNumber ? `#${latest.prNumber} · ` : ''}
                            <TimeAgo iso={latest.createdAt} />
                        </span>
                    </>
                ) : (
                    <span>Waiting for the first pull request</span>
                )}
            </div>
        </Link>
    );
}

function RecentAnalyses({ analyses }: { analyses: AnalysisSummary[] }) {
    if (analyses.length === 0) {
        return (
            <div className="rounded-2xl glass p-6 text-center text-sm text-muted-foreground">
                No checks yet. They appear here when a pull request changes
                migrations.
            </div>
        );
    }

    return (
        <ul className="divide-y divide-border/60 overflow-hidden rounded-2xl glass">
            {analyses.map((analysis) => (
                <li key={analysis.id}>
                    <Link
                        href={showAnalysis(analysis.id)}
                        prefetch
                        className="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-accent/60"
                    >
                        <span
                            className={cn(
                                'size-2 shrink-0 rounded-full',
                                severityMeta[analysis.severity ?? 'info'].dot,
                            )}
                        />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium">
                                {analysis.repository}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {analysis.prNumber ? (
                                    <>
                                        <GitPullRequest className="mr-1 inline size-3" />
                                        #{analysis.prNumber} ·{' '}
                                    </>
                                ) : null}
                                {summaryLine(analysis)}
                            </p>
                        </div>
                        <span className="shrink-0 text-xs text-muted-foreground">
                            <TimeAgo iso={analysis.createdAt} />
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

export function summaryLine(analysis: AnalysisSummary): string {
    if (analysis.statementCount === 0) {
        return 'No migration changes';
    }
    if (analysis.dangerCount > 0) {
        return `${analysis.dangerCount} risky ${analysis.dangerCount === 1 ? 'statement' : 'statements'}`;
    }
    if (analysis.warnCount > 0) {
        return `${analysis.warnCount} ${analysis.warnCount === 1 ? 'warning' : 'warnings'}`;
    }

    return `${analysis.statementCount} safe ${analysis.statementCount === 1 ? 'statement' : 'statements'}`;
}

function Accounts({ installations }: { installations: InstallationSummary[] }) {
    return (
        <div className="space-y-1 rounded-2xl glass p-2">
            <p className="px-2 pt-1 pb-2 text-xs font-medium text-muted-foreground">
                Installed on
            </p>
            {installations.map((installation) => (
                <a
                    key={installation.id}
                    href={installation.settingsUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="group flex items-center gap-3 rounded-xl px-2 py-2 transition-colors hover:bg-accent/60"
                >
                    {installation.avatar ? (
                        <img
                            src={installation.avatar}
                            alt=""
                            className="size-7 rounded-full"
                        />
                    ) : (
                        <div className="flex size-7 items-center justify-center rounded-full bg-muted text-xs font-semibold uppercase">
                            {installation.login.slice(0, 1)}
                        </div>
                    )}
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">
                            {installation.login}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {installation.suspended
                                ? 'Suspended'
                                : `${installation.repositories} ${installation.repositories === 1 ? 'repository' : 'repositories'}`}
                        </p>
                    </div>
                    <ArrowUpRight className="size-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                </a>
            ))}
        </div>
    );
}

function Onboarding({ installUrl }: { installUrl: string }) {
    const steps = [
        {
            title: 'Install the GitHub App',
            body: 'Choose the repositories Preflight should check. It only needs to write checks and PR comments.',
        },
        {
            title: 'Add the workflow',
            body: 'One short GitHub Actions job runs your migrations against a throwaway MySQL. No secrets to copy.',
        },
        {
            title: 'Open a pull request',
            body: 'Preflight comments on every migration that would lock or copy a table, with a safer alternative.',
        },
    ];

    return (
        <div className="relative overflow-hidden rounded-3xl glass p-6 sm:p-10">
            <div className="absolute -top-24 -right-24 size-72 rounded-full bg-brand/20 blur-3xl" />
            <div className="relative max-w-2xl">
                <h2 className="text-xl font-semibold tracking-tight sm:text-2xl">
                    Let's get your first check running
                </h2>
                <p className="mt-2 text-sm text-muted-foreground">
                    It takes about two minutes.
                </p>
                <ol className="mt-8 space-y-6">
                    {steps.map((step, i) => (
                        <li key={step.title} className="flex gap-4">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand to-brand-soft text-sm font-semibold text-white shadow-md shadow-brand/30">
                                {i + 1}
                            </span>
                            <div>
                                <p className="font-medium">{step.title}</p>
                                <p className="mt-0.5 text-sm text-muted-foreground">
                                    {step.body}
                                </p>
                            </div>
                        </li>
                    ))}
                </ol>
                <Button asChild size="lg" className="mt-8">
                    <a href={installUrl}>
                        <GitHubIcon className="size-4" />
                        Install on GitHub
                    </a>
                </Button>
            </div>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
