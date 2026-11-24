import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Clock,
    FileCode2,
    KeyRound,
    Lightbulb,
    ScanSearch,
    ShieldCheck,
} from 'lucide-react';
import { LogoMark } from '@/components/app-logo';
import { GitHubIcon } from '@/components/preflight/bits';
import { AmbientBackground, Reveal } from '@/components/preflight/motion';
import { SeverityBadge } from '@/components/preflight/severity';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const features = [
    {
        icon: ScanSearch,
        title: 'Knows how MySQL runs it',
        body: 'INSTANT, INPLACE or a full table COPY, per statement and per MySQL version. A deterministic engine, checked against real servers.',
    },
    {
        icon: FileCode2,
        title: 'Right on the line',
        body: 'Risky changes are annotated in the pull request diff, with one comment that stays current on every push.',
    },
    {
        icon: Lightbulb,
        title: 'Tells you the safer way',
        body: 'Expand-and-contract, a maintenance window, or gh-ost: every warning comes with a concrete fix.',
    },
    {
        icon: KeyRound,
        title: 'No secrets, no data',
        body: 'CI proves who it is with GitHub OIDC. Your migrations run in your CI; Preflight only sees the SQL.',
    },
];

const steps = [
    ['Install the GitHub App', 'Pick the repositories to check.'],
    ['Add one workflow', 'A short job that runs on pull requests.'],
    [
        'Merge with confidence',
        'Every migration gets a verdict before it ships.',
    ],
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Catch table-locking migrations before they ship" />
            <div className="relative isolate min-h-svh overflow-x-clip">
                <AmbientBackground />

                <header className="sticky top-0 z-20">
                    <div className="mx-auto mt-3 flex max-w-6xl items-center justify-between rounded-2xl glass px-4 py-2.5 sm:mx-6 lg:mx-auto">
                        <Link href="/" className="flex items-center gap-2.5">
                            <LogoMark />
                            <span className="font-semibold tracking-tight">
                                Preflight
                            </span>
                        </Link>
                        {auth.user ? (
                            <Button asChild size="sm">
                                <Link href={dashboard()}>
                                    Dashboard
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        ) : (
                            <Button asChild size="sm">
                                <Link href={login()}>Sign in</Link>
                            </Button>
                        )}
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-4 sm:px-6">
                    <section className="grid items-center gap-12 pt-16 pb-20 sm:pt-24 lg:grid-cols-2 lg:gap-16">
                        <div>
                            <Reveal>
                                <span className="inline-flex items-center gap-2 rounded-full glass px-3 py-1 text-xs text-muted-foreground">
                                    <ShieldCheck className="size-3.5 text-brand" />
                                    For Laravel and MySQL 8
                                </span>
                            </Reveal>
                            <Reveal delay={80}>
                                <h1 className="mt-5 text-4xl leading-[1.08] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                                    Catch the migration that{' '}
                                    <span className="text-gradient">
                                        locks production
                                    </span>{' '}
                                    before you merge it.
                                </h1>
                            </Reveal>
                            <Reveal delay={160}>
                                <p className="mt-5 max-w-xl text-lg text-pretty text-muted-foreground">
                                    Preflight reviews every pull request that
                                    touches your migrations and tells you
                                    whether MySQL will copy the table and block
                                    writes, and what to do instead.
                                </p>
                            </Reveal>
                            <Reveal
                                delay={240}
                                className="mt-8 flex flex-col gap-3 sm:flex-row"
                            >
                                <Button
                                    asChild
                                    size="lg"
                                    className="h-12 px-6 text-[15px]"
                                >
                                    <Link
                                        href={auth.user ? dashboard() : login()}
                                    >
                                        <GitHubIcon className="size-5" />
                                        {auth.user
                                            ? 'Go to dashboard'
                                            : 'Start with GitHub'}
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    size="lg"
                                    variant="outline"
                                    className="h-12 glass px-6 text-[15px]"
                                >
                                    <a href="#how">How it works</a>
                                </Button>
                            </Reveal>
                        </div>

                        <Reveal delay={320}>
                            <PullRequestPreview />
                        </Reveal>
                    </section>

                    <section className="grid gap-4 pb-24 sm:grid-cols-2 lg:grid-cols-4">
                        {features.map((feature, i) => (
                            <Reveal key={feature.title} delay={100 + i * 80}>
                                <div className="group h-full rounded-2xl glass p-5 transition-all duration-500 hover:-translate-y-1 hover:shadow-xl">
                                    <div className="flex size-10 items-center justify-center rounded-xl bg-brand/10 text-brand ring-1 ring-brand/20 transition-transform duration-500 group-hover:scale-110">
                                        <feature.icon className="size-5" />
                                    </div>
                                    <h3 className="mt-4 font-medium">
                                        {feature.title}
                                    </h3>
                                    <p className="mt-1.5 text-sm text-muted-foreground">
                                        {feature.body}
                                    </p>
                                </div>
                            </Reveal>
                        ))}
                    </section>

                    <section id="how" className="scroll-mt-24 pb-28">
                        <Reveal>
                            <h2 className="text-center text-2xl font-semibold tracking-tight sm:text-3xl">
                                Set up in two minutes
                            </h2>
                        </Reveal>
                        <div className="mx-auto mt-10 grid max-w-4xl gap-4 sm:grid-cols-3">
                            {steps.map(([title, body], i) => (
                                <Reveal key={title} delay={100 + i * 100}>
                                    <div className="h-full rounded-2xl glass p-5 text-center">
                                        <span className="mx-auto flex size-9 items-center justify-center rounded-full bg-gradient-to-br from-brand to-brand-soft font-semibold text-white shadow-lg shadow-brand/30">
                                            {i + 1}
                                        </span>
                                        <p className="mt-4 font-medium">
                                            {title}
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {body}
                                        </p>
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                    </section>
                </main>

                <footer className="border-t border-border/50 py-8 text-center text-xs text-muted-foreground">
                    Preflight · migration safety for Laravel teams
                </footer>
            </div>
        </>
    );
}

function PullRequestPreview() {
    return (
        <div className="relative">
            <div className="absolute -inset-4 rounded-[2rem] bg-gradient-to-br from-brand/30 via-brand-soft/20 to-transparent blur-2xl" />
            <div className="relative overflow-hidden rounded-3xl glass-strong shadow-2xl shadow-black/10">
                <div className="flex items-center gap-2 border-b border-border/60 px-4 py-3 text-xs text-muted-foreground">
                    <span className="size-2.5 rounded-full bg-red-400/80" />
                    <span className="size-2.5 rounded-full bg-amber-400/80" />
                    <span className="size-2.5 rounded-full bg-emerald-400/80" />
                    <span className="ml-2 truncate">
                        acme/storefront · Pull request #412
                    </span>
                </div>
                <div className="space-y-4 p-5">
                    <div className="flex items-center gap-2.5">
                        <LogoMark className="size-7" />
                        <div className="text-sm">
                            <span className="font-medium">preflight</span>{' '}
                            <span className="text-muted-foreground">
                                commented
                            </span>
                        </div>
                    </div>
                    <div className="space-y-3 rounded-2xl border border-border/60 bg-background/60 p-4">
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm font-semibold">
                                1 risky statement
                            </p>
                            <SeverityBadge severity="danger" />
                        </div>
                        <code className="block overflow-x-auto rounded-lg bg-neutral-950 p-3 text-[12px] whitespace-nowrap text-neutral-100">
                            alter table `orders` modify `status` varchar(100)
                        </code>
                        <div className="flex flex-wrap gap-1.5 font-mono text-[11px]">
                            <span className="rounded-md bg-red-500/10 px-1.5 py-0.5 text-red-700 ring-1 ring-red-500/20 dark:text-red-300">
                                COPY
                            </span>
                            <span className="rounded-md bg-red-500/10 px-1.5 py-0.5 text-red-700 ring-1 ring-red-500/20 dark:text-red-300">
                                Blocks writes
                            </span>
                            <span className="inline-flex items-center gap-1 rounded-md bg-muted px-1.5 py-0.5 text-muted-foreground ring-1 ring-border">
                                <Clock className="size-3" /> 8 – 42 min
                            </span>
                        </div>
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            varchar(50) → varchar(100) crosses the 255-byte
                            length boundary, so MySQL copies all 41M rows.
                        </p>
                        <div className="flex gap-2 rounded-xl border border-brand/20 bg-brand/5 p-2.5 text-xs">
                            <Lightbulb className="size-3.5 shrink-0 text-brand" />
                            Add a new column, backfill in batches, then switch.
                        </div>
                    </div>
                    <div className="flex items-center justify-between rounded-xl bg-background/60 px-3 py-2 text-xs ring-1 ring-border/60">
                        <span className="flex items-center gap-2">
                            <span className="size-2 rounded-full bg-red-500" />
                            Preflight: 1 risky statement
                        </span>
                        <span className="text-muted-foreground">Details</span>
                    </div>
                </div>
            </div>
        </div>
    );
}
