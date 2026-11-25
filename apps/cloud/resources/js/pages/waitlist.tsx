import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Check, Hourglass, LogOut } from 'lucide-react';
import { useState } from 'react';
import { LogoMark } from '@/components/app-logo';
import { TimeAgo } from '@/components/preflight/bits';
import { AmbientBackground, Reveal } from '@/components/preflight/motion';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { update } from '@/routes/waitlist';

const sizeLabels: Record<string, string> = {
    'just-me': 'Just me',
    '2-10': '2–10',
    '11-50': '11–50',
    '51-200': '51–200',
    '200+': '200+',
};

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

    return (
        <>
            <Head title="You're on the list" />
            <div className="relative isolate flex min-h-svh flex-col items-center justify-center px-4 py-12">
                <AmbientBackground />

                <Reveal className="w-full max-w-lg">
                    <div className="mb-8 flex items-center justify-center gap-2.5">
                        <LogoMark className="size-9" />
                        <span className="text-lg font-semibold tracking-tight">
                            Preflight
                        </span>
                    </div>

                    <div className="rounded-3xl glass-strong p-6 shadow-xl shadow-black/5 sm:p-8">
                        <div className="flex flex-col items-center text-center">
                            <div className="relative mb-5">
                                <span className="absolute inset-0 animate-ping rounded-full bg-brand/30 [animation-duration:2.4s]" />
                                <span className="relative flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-brand to-brand-soft text-white shadow-lg shadow-brand/30">
                                    <Hourglass className="size-6" />
                                </span>
                            </div>
                            <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
                                You're on the list,{' '}
                                {auth.user.name.split(' ')[0]}
                            </h1>
                            <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                                Preflight is in private beta. We're letting
                                teams in a few at a time, and you'll be able to
                                use it as soon as your account is approved.
                            </p>
                            {joinedAt && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Joined <TimeAgo iso={joinedAt} /> as @
                                    {auth.user.login}
                                </p>
                            )}
                        </div>

                        <Form
                            {...update.form()}
                            options={{ preserveScroll: true }}
                            className="mt-8 space-y-5 border-t border-border/60 pt-6"
                        >
                            {({ processing, errors, recentlySuccessful }) => (
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
                                                            ? 'bg-foreground text-background ring-foreground'
                                                            : 'bg-background/60 text-muted-foreground ring-border hover:text-foreground',
                                                    )}
                                                >
                                                    {sizeLabels[value] ?? value}
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
                                        />
                                        {errors.waitlist_note && (
                                            <p className="text-sm text-red-600">
                                                {errors.waitlist_note}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <Button disabled={processing}>
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
                                            <Check className="size-4" /> Saved
                                        </span>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>

                    <div className="mt-6 flex justify-center">
                        <Link
                            href={logout()}
                            as="button"
                            className="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <LogOut className="size-4" />
                            Sign out
                        </Link>
                    </div>
                </Reveal>
            </div>
        </>
    );
}
