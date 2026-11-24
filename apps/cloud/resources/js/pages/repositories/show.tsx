import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    GitPullRequest,
    Lock,
    Rocket,
} from 'lucide-react';
import { useState } from 'react';
import { CodeBlock, GitHubIcon, TimeAgo } from '@/components/preflight/bits';
import { Reveal } from '@/components/preflight/motion';
import { SeverityBadge } from '@/components/preflight/severity';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { summaryLine } from '@/pages/dashboard';
import { dashboard } from '@/routes';
import { show as showAnalysis } from '@/routes/analyses';
import type {
    AnalysisSummary,
    Paginated,
    RepositorySummary,
} from '@/types/preflight';

type Props = {
    repository: RepositorySummary;
    analyses: Paginated<AnalysisSummary>;
    audience: string;
    apiUrl: string;
};

export default function RepositoryShow({
    repository,
    analyses,
    audience,
}: Props) {
    const hasAnalyses = analyses.total > 0;
    const [showSetup, setShowSetup] = useState(!hasAnalyses);

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

                <section className="space-y-3">
                    <Reveal delay={120}>
                        <h2 className="text-sm font-semibold tracking-tight">
                            Checks{' '}
                            <span className="ml-1 rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground tabular-nums">
                                {analyses.total}
                            </span>
                        </h2>
                    </Reveal>

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
                                No checks yet. Add the workflow above, then open
                                a pull request that changes a migration.
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
            </div>
        </>
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
