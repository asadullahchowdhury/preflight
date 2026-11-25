import { Head, router } from '@inertiajs/react';
import { Check, ShieldCheck, Undo2, Users } from 'lucide-react';
import { useState } from 'react';
import { EmptyState, TimeAgo } from '@/components/preflight/bits';
import { Reveal } from '@/components/preflight/motion';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { approve, revoke } from '@/routes/admin/beta';
import { beta as betaPage } from '@/routes/admin';

type Person = {
    id: number;
    login: string;
    name: string;
    avatar: string | null;
    teamSize: string | null;
    note: string | null;
    joinedAt: string | null;
    approvedAt: string | null;
    admin: boolean;
};

type Filter = 'waiting' | 'approved';

export default function BetaAdmin({
    people,
    betaEnabled,
}: {
    people: Person[];
    betaEnabled: boolean;
}) {
    const waiting = people.filter((p) => !p.approvedAt && !p.admin);
    const approved = people.filter((p) => p.approvedAt || p.admin);
    const [filter, setFilter] = useState<Filter>(
        waiting.length > 0 ? 'waiting' : 'approved',
    );
    const [busy, setBusy] = useState<number | null>(null);
    const shown = filter === 'waiting' ? waiting : approved;

    const act = (person: Person, action: 'approve' | 'revoke') =>
        router.post(
            (action === 'approve' ? approve : revoke).url(person.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(person.id),
                onFinish: () => setBusy(null),
            },
        );

    return (
        <>
            <Head title="Beta access" />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8">
                <Reveal>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Beta access
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {betaEnabled
                            ? 'People who sign in wait here until you let them in. Approved accounts can install the App and get checks.'
                            : 'The private beta is off (PREFLIGHT_BETA=false): everyone who signs in has access.'}
                    </p>
                </Reveal>

                <Reveal delay={80}>
                    <div
                        role="tablist"
                        className="inline-flex rounded-xl glass p-1"
                    >
                        {(
                            [
                                ['waiting', 'Waiting', waiting.length],
                                ['approved', 'Approved', approved.length],
                            ] as const
                        ).map(([value, label, count]) => (
                            <button
                                key={value}
                                type="button"
                                role="tab"
                                aria-selected={filter === value}
                                onClick={() => setFilter(value)}
                                className={cn(
                                    'flex items-center gap-2 rounded-lg px-4 py-1.5 text-sm transition-all duration-300',
                                    filter === value
                                        ? 'bg-background font-medium shadow-sm ring-1 ring-border'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {label}
                                <span className="rounded-full bg-muted px-1.5 text-xs tabular-nums">
                                    {count}
                                </span>
                            </button>
                        ))}
                    </div>
                </Reveal>

                <div key={filter} className="animate-blur-in-fast">
                    {shown.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title={
                                filter === 'waiting'
                                    ? 'Nobody is waiting'
                                    : 'Nobody approved yet'
                            }
                        >
                            {filter === 'waiting'
                                ? 'New sign-ins will show up here.'
                                : 'Approve people from the Waiting tab.'}
                        </EmptyState>
                    ) : (
                        <ul className="divide-y divide-border/60 overflow-hidden rounded-2xl glass">
                            {shown.map((person) => (
                                <li
                                    key={person.id}
                                    className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start"
                                >
                                    <div className="flex min-w-0 flex-1 gap-3">
                                        {person.avatar ? (
                                            <img
                                                src={person.avatar}
                                                alt=""
                                                className="size-10 shrink-0 rounded-full"
                                            />
                                        ) : (
                                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted font-semibold uppercase">
                                                {person.login.slice(0, 1)}
                                            </div>
                                        )}
                                        <div className="min-w-0 space-y-1">
                                            <p className="flex flex-wrap items-center gap-x-2 text-sm">
                                                <span className="font-medium">
                                                    {person.name}
                                                </span>
                                                <a
                                                    href={`https://github.com/${person.login}`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-muted-foreground hover:underline"
                                                >
                                                    @{person.login}
                                                </a>
                                                {person.admin && (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-brand/10 px-2 py-0.5 text-xs text-brand ring-1 ring-brand/20">
                                                        <ShieldCheck className="size-3" />
                                                        Admin
                                                    </span>
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Joined{' '}
                                                <TimeAgo
                                                    iso={person.joinedAt}
                                                />
                                                {person.teamSize &&
                                                    ` · team ${person.teamSize}`}
                                                {person.approvedAt && (
                                                    <>
                                                        {' · approved '}
                                                        <TimeAgo
                                                            iso={
                                                                person.approvedAt
                                                            }
                                                        />
                                                    </>
                                                )}
                                            </p>
                                            {person.note && (
                                                <p className="rounded-lg bg-muted/60 px-3 py-2 text-sm">
                                                    {person.note}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    {!person.admin && (
                                        <div className="shrink-0 sm:pt-1">
                                            {person.approvedAt ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={
                                                        busy === person.id
                                                    }
                                                    onClick={() =>
                                                        act(person, 'revoke')
                                                    }
                                                >
                                                    <Undo2 className="size-4" />
                                                    Move back to waitlist
                                                </Button>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    disabled={
                                                        busy === person.id
                                                    }
                                                    onClick={() =>
                                                        act(person, 'approve')
                                                    }
                                                >
                                                    <Check className="size-4" />
                                                    Approve
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}

BetaAdmin.layout = {
    breadcrumbs: [{ title: 'Beta access', href: betaPage() }],
};
