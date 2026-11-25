import { Link } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    Code2,
    Database,
    KeyRound,
    Lock,
} from 'lucide-react';
import { Reveal } from '@/components/preflight/motion';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, login } from '@/routes';

const plans = [
    {
        name: 'Free',
        price: '$0',
        period: 'forever',
        blurb: 'The open-source GitHub Action and Laravel package.',
        features: [
            'A verdict for every migration statement',
            'PR comment, annotations and job summary',
            'Runs entirely in your CI',
            'MySQL 8.0 and 8.4',
        ],
        highlight: false,
    },
    {
        name: 'Pro',
        price: '$29',
        period: 'per GitHub account / month',
        blurb: 'Preflight Cloud: the GitHub App, production sizes and history.',
        features: [
            'Everything in Free, posted by the Preflight App',
            'Duration estimates from production table sizes',
            'Size-aware severity: no alarms for tiny tables',
            'Dashboard with check history and table growth',
            'Unlimited repositories',
        ],
        highlight: true,
    },
];

export function Pricing({ signedIn }: { signedIn: boolean }) {
    return (
        <section id="pricing" className="scroll-mt-24 pb-28">
            <Reveal className="text-center">
                <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Simple pricing
                </h2>
                <p className="mt-2 text-muted-foreground">
                    Pro is free while Preflight is in private beta.
                </p>
            </Reveal>
            <div className="mx-auto mt-10 grid max-w-4xl grid-cols-1 gap-4 md:grid-cols-2">
                {plans.map((plan, i) => (
                    <Reveal key={plan.name} delay={100 + i * 100}>
                        <div
                            className={cn(
                                'relative flex h-full flex-col rounded-3xl p-6 sm:p-8',
                                plan.highlight
                                    ? 'glass-strong shadow-xl ring-2 shadow-brand/10 ring-brand/40'
                                    : 'glass',
                            )}
                        >
                            {plan.highlight && (
                                <span className="absolute -top-3 left-6 rounded-full bg-gradient-to-r from-brand to-brand-soft px-3 py-1 text-xs font-medium text-white shadow-md">
                                    Free during the beta
                                </span>
                            )}
                            <p className="font-medium">{plan.name}</p>
                            <p className="mt-3 flex flex-wrap items-baseline gap-x-2">
                                <span className="text-4xl font-semibold tracking-tight">
                                    {plan.price}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {plan.period}
                                </span>
                            </p>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {plan.blurb}
                            </p>
                            <ul className="mt-6 flex-1 space-y-2.5 text-sm">
                                {plan.features.map((feature) => (
                                    <li key={feature} className="flex gap-2.5">
                                        <Check className="mt-0.5 size-4 shrink-0 text-brand" />
                                        {feature}
                                    </li>
                                ))}
                            </ul>
                            {plan.highlight ? (
                                <Button asChild className="mt-8 w-full">
                                    <Link
                                        href={signedIn ? dashboard() : login()}
                                    >
                                        {signedIn
                                            ? 'Go to dashboard'
                                            : 'Join the beta'}
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-8 w-full glass"
                                >
                                    <a href="/docs">Read the setup guide</a>
                                </Button>
                            )}
                        </div>
                    </Reveal>
                ))}
            </div>
        </section>
    );
}

const assurances = [
    {
        icon: Code2,
        title: 'Your code stays in your CI',
        body: 'Migrations run in pretend mode inside your own GitHub Actions job. Preflight Cloud receives the SQL and the schema, never your source code.',
    },
    {
        icon: Database,
        title: 'Numbers, not data',
        body: 'Production sizes are table names, row estimates and byte counts from information_schema. No row is ever read or sent.',
    },
    {
        icon: KeyRound,
        title: 'No secrets to store',
        body: "CI proves which repository it runs in with GitHub's OIDC token, checked against GitHub's keys. Stats pushes use a per-repository token stored only as a hash.",
    },
    {
        icon: Lock,
        title: 'Least privilege',
        body: 'The GitHub App only asks to write checks and pull request comments, plus metadata. It cannot read your repository contents.',
    },
];

export function Security() {
    return (
        <section id="security" className="scroll-mt-24 pb-28">
            <Reveal className="text-center">
                <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Built to be trusted with production
                </h2>
            </Reveal>
            <div className="mx-auto mt-10 grid max-w-5xl grid-cols-1 gap-4 sm:grid-cols-2">
                {assurances.map((item, i) => (
                    <Reveal key={item.title} delay={100 + i * 80}>
                        <div className="flex h-full gap-4 rounded-2xl glass p-5">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand ring-1 ring-brand/20">
                                <item.icon className="size-5" />
                            </div>
                            <div>
                                <h3 className="font-medium">{item.title}</h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {item.body}
                                </p>
                            </div>
                        </div>
                    </Reveal>
                ))}
            </div>
        </section>
    );
}

const questions = [
    {
        q: 'Does Preflight use AI to judge my migrations?',
        a: "No. Verdicts come from a deterministic rules engine that encodes MySQL's online-DDL behaviour for each version, and every rule is checked against real MySQL servers, from 8.0.12 to 8.4, in its test suite.",
    },
    {
        q: 'Which databases and frameworks are supported?',
        a: 'Laravel 11, 12 and 13 with MySQL 8.0 or 8.4 LTS. MariaDB and Postgres are not supported yet.',
    },
    {
        q: 'Does it run my migrations?',
        a: "Only in CI, on a throwaway database: it builds your base branch's schema there, then captures the SQL of the new migrations in Laravel's pretend mode, so they are never executed.",
    },
    {
        q: 'What do I get without Preflight Cloud?',
        a: 'The open-source GitHub Action gives every pull request a verdict, a comment, annotations and a job summary, for free. The cloud adds the GitHub App, production table sizes with duration estimates, and history.',
    },
    {
        q: 'How do I get into the beta?',
        a: 'Sign in with GitHub to join the waitlist. Teams are let in a few at a time, and Pro is free for everyone in the beta.',
    },
];

export function Faq() {
    return (
        <section id="faq" className="scroll-mt-24 pb-28">
            <Reveal className="text-center">
                <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Questions
                </h2>
            </Reveal>
            <div className="mx-auto mt-10 max-w-3xl space-y-3">
                {questions.map((item, i) => (
                    <Reveal key={item.q} delay={80 + i * 60}>
                        <details className="group rounded-2xl glass px-5 py-4 [&_summary::-webkit-details-marker]:hidden">
                            <summary className="flex cursor-pointer items-center justify-between gap-4 font-medium">
                                {item.q}
                                <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform duration-300 group-open:rotate-180" />
                            </summary>
                            <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                                {item.a}
                            </p>
                        </details>
                    </Reveal>
                ))}
            </div>
        </section>
    );
}
