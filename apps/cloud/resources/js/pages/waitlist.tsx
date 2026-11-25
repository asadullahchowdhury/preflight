import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Check, Hourglass, LogOut } from 'lucide-react';
import { useState } from 'react';
import { TimeAgo } from '@/components/preflight/bits';
import { AmbientBackground, Reveal } from '@/components/preflight/motion';
import { SiteHeader } from '@/components/preflight/site-header';
import type { HeaderLink } from '@/components/preflight/site-header';
import { TiltCard } from '@/components/preflight/tilt-card';
import { Button } from '@/components/ui/button';
import { useSmoothScroll } from '@/hooks/use-cinematic-scroll';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { update } from '@/routes/waitlist';

const links: HeaderLink[] = [
    { label: 'Product', href: '/' },
    { label: 'Pricing', href: '/#pricing' },
    { label: 'Docs', href: '/docs' },
];

const sizeLabels: Record<string, string> = {
    'just-me': 'Just me',
    '2-10': '2–10',
    '11-50': '11–50',
    '51-200': '51–200',
    '200+': '200+',
};

const nextSteps = [
    'We review the waitlist and let teams in a few at a time.',
    "Once you're approved, this page opens your dashboard.",
    'Install the GitHub App and add one workflow, about two minutes.',
];

export default function Waitlist({
    teamSize,
    note,
    teamSizes,
    joinedAt,
}: {
    teamSize: string | null;
    note: string | null;
    teamSizes: string[];
    joinedAt: string | null;
}) {
    const { auth } = usePage().props;
    const [size, setSize] = useState<string | null>(teamSize);

    useSmoothScroll();

    return (
        <>
            <Head title="You're on the list" />
            <SiteHeader links={links} showAction={false} />

            <div className="relative isolate flex min-h-svh flex-col items-center justify-center overflow-x-clip px-4 pt-28 pb-16">
                <AmbientBackground fixed />

                <div className="w-full max-w-lg animate-dolly-in">
                    <TiltCard maxTilt={3}>
                        <div className="p-6 sm:p-8">
                            <div className="flex flex-col items-center text-center">
                                <div className="relative mb-5">
                                    <span className="absolute inset-0 animate-ping rounded-full bg-brand/30 [animation-duration:2.4s]" />
                                    <span className="relative flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-brand to-brand-soft text-white shadow-lg shadow-brand/30">
                                        <Hourglass className="size-6" />
                                    </span>
                                </div>
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    You're on the list,{' '}
                                    <span className="text-gradient">
                                        {auth.user.name.split(' ')[0]}
                                    </span>
                                </h1>
                                <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                                    Preflight is in private beta. You'll be able
                                    to use it as soon as your account is
                                    approved.
                                </p>
                                {joinedAt && (
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Joined <TimeAgo iso={joinedAt} /> as @
                                        {auth.user.login}
                                    </p>
                                )}
                            </div>

                            <ol className="mt-7 space-y-3 rounded-2xl bg-background/40 p-4 ring-1 ring-border/60">
                                {nextSteps.map((step, i) => (
                                    <Reveal
                                        as="li"
                                        key={step}
                                        delay={450 + i * 100}
                                        fast
                                        className="flex gap-3 text-sm"
                                    >
                                        <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand/10 text-xs font-semibold text-brand ring-1 ring-brand/20">
                                            {i + 1}
                                        </span>
                                        <span className="pt-0.5 text-muted-foreground">
                                            {step}
                                        </span>
                                    </Reveal>
                                ))}
                            </ol>

                            <Form
                                {...update.form()}
                                options={{ preserveScroll: true }}
                                className="mt-7 space-y-5 border-t border-border/60 pt-6"
                            >
                                {({
                                    processing,
                                    errors,
                                    recentlySuccessful,
                                }) => (
                                    <>
                                        <p className="text-sm font-medium">
                                            Help us get you in sooner (optional)
                                        </p>

                                        <fieldset className="space-y-2">
                                            <legend className="text-sm text-muted-foreground">
                                                How big is your team?
                                            </legend>
                                            <input
                                                type="hidden"
                                                name="team_size"
                                                value={size ?? ''}
                                            />
                                            <div className="flex flex-wrap gap-2">
                                                {teamSizes.map((value) => (
                                                    <button
                                                        key={value}
                                                        type="button"
                                                        onClick={() =>
                                                            setSize(
                                                                size === value
                                                                    ? null
                                                                    : value,
                                                            )
                                                        }
                                                        aria-pressed={
                                                            size === value
                                                        }
                                                        className={cn(
                                                            'rounded-full px-3.5 py-1.5 text-sm ring-1 transition-all duration-300 ring-inset',
                                                            size === value
                                                                ? 'scale-105 bg-foreground text-background ring-foreground'
                                                                : 'bg-background/60 text-muted-foreground ring-border hover:text-foreground',
                                                        )}
                                                    >
                                                        {sizeLabels[value] ??
                                                            value}
                                                    </button>
                                                ))}
                                            </div>
                                            {errors.team_size && (
                                                <p className="text-sm text-red-600">
                                                    {errors.team_size}
                                                </p>
                                            )}
                                        </fieldset>

                                        <div className="space-y-2">
                                            <label
                                                htmlFor="waitlist_note"
                                                className="text-sm text-muted-foreground"
                                            >
                                                Which migration would you want
                                                checked first?
                                            </label>
                                            <textarea
                                                id="waitlist_note"
                                                name="waitlist_note"
                                                defaultValue={note ?? ''}
                                                rows={3}
                                                maxLength={1000}
                                                placeholder="e.g. a 40M-row orders table we're scared to touch"
                                                className="w-full resize-none rounded-xl border border-input bg-background/60 px-3 py-2 text-sm shadow-xs transition-shadow outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                                                data-lenis-prevent
                                            />
                                            {errors.waitlist_note && (
                                                <p className="text-sm text-red-600">
                                                    {errors.waitlist_note}
                                                </p>
                                            )}
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <Button
                                                disabled={processing}
                                                className="rounded-full px-5"
                                            >
                                                Save
                                            </Button>
                                            <span
                                                className={cn(
                                                    'flex items-center gap-1 text-sm text-emerald-600 transition-all duration-500 dark:text-emerald-400',
                                                    recentlySuccessful
                                                        ? 'blur-0 opacity-100'
                                                        : 'opacity-0 blur-sm',
                                                )}
                                            >
                                                <Check className="size-4" />{' '}
                                                Saved
                                            </span>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </div>
                    </TiltCard>
                </div>

                <Reveal delay={700}>
                    <Link
                        href={logout()}
                        as="button"
                        className="mt-8 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <LogOut className="size-4" />
                        Sign out
                    </Link>
                </Reveal>
            </div>
        </>
    );
}
