import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Clock,
    FileCode2,
    KeyRound,
    Lightbulb,
    ScanSearch,
} from 'lucide-react';
import { useRef } from 'react';
import { LogoMark } from '@/components/app-logo';
import { GitHubIcon } from '@/components/preflight/bits';
import {
    Faq,
    Pricing,
    Security,
} from '@/components/preflight/landing-sections';
import {
    AmbientBackground,
    Dolly,
    Reveal,
    ScrollProgress,
} from '@/components/preflight/motion';
import { SeverityBadge } from '@/components/preflight/severity';
import { SiteFooter } from '@/components/preflight/site-footer';
import { SiteHeader } from '@/components/preflight/site-header';
import type { HeaderLink } from '@/components/preflight/site-header';
import { Button } from '@/components/ui/button';
import {
    clamp,
    prefersReducedMotion,
    scrollToTarget,
    useScrollFrame,
    useSmoothScroll,
} from '@/hooks/use-cinematic-scroll';
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

const headerLinks: HeaderLink[] = [
    { label: 'How it works', href: '#how' },
    { label: 'Pricing', href: '#pricing' },
    { label: 'Security', href: '#security' },
    { label: 'FAQ', href: '#faq' },
    { label: 'Docs', href: '/docs' },
];

export default function Welcome() {
    const { auth } = usePage().props;
    const heroText = useRef<HTMLDivElement>(null);
    const heroCard = useRef<HTMLDivElement>(null);

    useSmoothScroll();

    // The opening shot: as you scroll, the headline drifts back into the distance while the PR card
    // swings flat and comes towards you (a camera dolly), and the background zooms behind them.
    useScrollFrame(() => {
        const text = heroText.current;
        const card = heroCard.current;
        if (!text || !card) {
            return;
        }
        if (prefersReducedMotion()) {
            text.style.transform = card.style.transform = '';

            return;
        }

        const p = clamp(window.scrollY / (window.innerHeight * 0.85));
        const wide = window.innerWidth >= 1024;

        text.style.transform = `translate3d(0, ${p * -50}px, 0) scale(${1 - p * 0.07})`;
        text.style.opacity = String(1 - p * 0.75);
        text.style.filter = p > 0.01 ? `blur(${p * 6}px)` : '';

        card.style.transform = wide
            ? `perspective(1600px) translate3d(0, ${p * -30}px, ${p * 90}px) rotateX(${(1 - p) * 8}deg) rotateY(${(1 - p) * -14}deg) rotateZ(${(1 - p) * 1.5}deg)`
            : `translate3d(0, ${p * -20}px, 0) scale(${1 + p * 0.04})`;
    });

    return (
        <>
            <Head title="Catch table-locking migrations before they ship" />
            <ScrollProgress />
            <SiteHeader links={headerLinks} />

            <div className="relative isolate min-h-svh overflow-x-clip">
                <AmbientBackground fixed />
                <main className="mx-auto max-w-6xl px-4 sm:px-6">
                    <section className="grid min-h-[92svh] grid-cols-1 items-center gap-12 pt-32 pb-16 sm:pt-36 lg:grid-cols-2 lg:gap-16">
                        <div ref={heroText} className="will-change-transform">
                            <Reveal>
                                <span className="inline-flex items-center gap-2 rounded-full glass px-3 py-1 text-xs text-muted-foreground">
                                    <span className="relative flex size-2">
                                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-60" />
                                        <span className="relative inline-flex size-2 rounded-full bg-emerald-400" />
                                    </span>
                                    Private beta · For Laravel and MySQL 8
                                </span>
                            </Reveal>
                            <Reveal delay={80}>
                                <h1 className="mt-6 text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl lg:text-[4rem]">
                                    Catch the migration that{' '}
                                    <span className="text-gradient">
                                        locks production
                                    </span>{' '}
                                    before you merge it.
                                </h1>
                            </Reveal>
                            <Reveal delay={160}>
                                <p className="mt-6 max-w-xl text-lg text-pretty text-muted-foreground">
                                    Preflight reviews every pull request that
                                    touches your migrations and tells you
                                    whether MySQL will copy the table and block
                                    writes, and what to do instead.
                                </p>
                            </Reveal>
                            <Reveal
                                delay={240}
                                className="mt-9 flex flex-col gap-3 sm:flex-row"
                            >
                                <Button
                                    asChild
                                    size="lg"
                                    className="group h-12 rounded-full px-6 text-[15px] shadow-lg shadow-brand/20"
                                >
                                    <Link
                                        href={auth.user ? dashboard() : login()}
                                    >
                                        <GitHubIcon className="size-5" />
                                        {auth.user
                                            ? 'Go to dashboard'
                                            : 'Start with GitHub'}
                                        <ArrowRight className="size-4 transition-transform duration-300 group-hover:translate-x-0.5" />
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    size="lg"
                                    variant="outline"
                                    className="h-12 rounded-full glass px-6 text-[15px]"
                                >
                                    <a
                                        href="#how"
                                        onClick={(event) => {
                                            event.preventDefault();
                                            scrollToTarget('#how');
                                        }}
                                    >
                                        How it works
                                    </a>
                                </Button>
                            </Reveal>
                        </div>

                        <Reveal delay={320}>
                            <div
                                ref={heroCard}
                                className="will-change-transform [transform-style:preserve-3d]"
                            >
                                <PullRequestPreview />
                            </div>
                        </Reveal>
                    </section>

                    <a
                        href="#features"
                        onClick={(event) => {
                            event.preventDefault();
                            scrollToTarget('#features');
                        }}
                        aria-label="Scroll down"
                        className="mx-auto -mt-4 mb-16 hidden w-fit flex-col items-center gap-2 text-xs text-muted-foreground transition-colors hover:text-foreground lg:flex"
                    >
                        <span className="flex h-9 w-5.5 justify-center rounded-full border border-current/40 pt-1.5">
                            <span className="h-2 w-0.5 animate-bounce rounded-full bg-current" />
                        </span>
                        Scroll
                    </a>

                    <section id="features" className="scroll-mt-24 pb-28">
                        <Dolly>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {features.map((feature, i) => (
                                    <Reveal key={feature.title} delay={i * 80}>
                                        <div className="group h-full rounded-2xl glass p-5 transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:shadow-brand/5">
                                            <div className="flex size-10 items-center justify-center rounded-xl bg-brand/10 text-brand ring-1 ring-brand/20 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6">
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
                            </div>
                        </Dolly>
                    </section>

                    <section id="how" className="scroll-mt-24 pb-28">
                        <Reveal>
                            <p className="text-center text-sm font-medium text-brand">
                                How it works
                            </p>
                            <h2 className="mt-2 text-center text-2xl font-semibold tracking-tight sm:text-4xl">
                                Set up in two minutes
                            </h2>
                        </Reveal>
                        <Dolly className="mx-auto mt-12 max-w-4xl">
                            <div className="relative grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div
                                    aria-hidden
                                    className="absolute top-[2.35rem] right-[16%] left-[16%] hidden h-px bg-gradient-to-r from-transparent via-brand/40 to-transparent sm:block"
                                />
                                {steps.map(([title, body], i) => (
                                    <Reveal key={title} delay={i * 120}>
                                        <div className="relative h-full rounded-2xl glass p-5 text-center">
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
                        </Dolly>
                    </section>

                    <Pricing signedIn={Boolean(auth.user)} />
                    <Security />
                    <Faq />
                </main>

                <SiteFooter />
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
