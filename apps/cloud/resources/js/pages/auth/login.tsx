import { Head, usePage } from '@inertiajs/react';
import { Check, ShieldCheck, Sparkles } from 'lucide-react';
import { LogoMark } from '@/components/app-logo';
import { GitHubIcon } from '@/components/preflight/bits';
import { AmbientBackground, Reveal } from '@/components/preflight/motion';
import { SiteHeader } from '@/components/preflight/site-header';
import type { HeaderLink } from '@/components/preflight/site-header';
import { TiltCard } from '@/components/preflight/tilt-card';
import { Button } from '@/components/ui/button';
import { useSmoothScroll } from '@/hooks/use-cinematic-scroll';
import { login as devLogin } from '@/routes/dev';
import { redirect } from '@/routes/github';

const links: HeaderLink[] = [
    { label: 'Product', href: '/' },
    { label: 'Pricing', href: '/#pricing' },
    { label: 'Docs', href: '/docs' },
];

const promises = [
    'A verdict on every migration in your pull requests',
    'No secrets to store: CI proves who it is',
    'Your code and data never leave your CI',
];

export default function Login({
    configured,
    devLogin: devLoginEnabled,
}: {
    configured: boolean;
    devLogin: boolean;
}) {
    const { flash } = usePage<{ flash?: { error?: string } }>().props;

    useSmoothScroll();

    return (
        <>
            <Head title="Sign in" />
            <SiteHeader links={links} showAction={false} />

            <div className="relative isolate flex min-h-svh flex-col items-center justify-center overflow-x-clip px-4 pt-28 pb-16">
                <AmbientBackground fixed />

                <div className="w-full max-w-sm animate-dolly-in">
                    <TiltCard>
                        <div className="p-7 sm:p-8">
                            <div className="flex justify-center">
                                <div className="relative">
                                    <span className="absolute inset-0 rounded-2xl bg-brand/40 blur-xl" />
                                    <LogoMark className="relative size-12 rounded-2xl" />
                                </div>
                            </div>
                            <h1 className="mt-5 text-center text-2xl font-semibold tracking-tight">
                                Welcome back
                            </h1>
                            <p className="mt-1.5 text-center text-sm text-muted-foreground">
                                Sign in with the GitHub account that owns your
                                repositories.
                            </p>

                            <div className="mt-7 space-y-3">
                                {configured ? (
                                    <Button
                                        asChild
                                        size="lg"
                                        className="group h-11 w-full rounded-full text-[15px] shadow-lg shadow-brand/20"
                                    >
                                        <a href={redirect.url()}>
                                            <GitHubIcon className="size-5 transition-transform duration-500 group-hover:rotate-[-8deg]" />
                                            Continue with GitHub
                                        </a>
                                    </Button>
                                ) : (
                                    <div className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-3 text-center text-sm text-muted-foreground">
                                        GitHub sign-in isn't configured on this
                                        server yet.
                                    </div>
                                )}

                                {devLoginEnabled && (
                                    <Button
                                        asChild
                                        variant="outline"
                                        size="lg"
                                        className="h-11 w-full rounded-full glass"
                                    >
                                        <a href={devLogin.url()}>
                                            <Sparkles className="size-4" />
                                            Explore the demo
                                        </a>
                                    </Button>
                                )}

                                {flash?.error && (
                                    <p className="text-center text-sm text-red-600 dark:text-red-400">
                                        {flash.error}
                                    </p>
                                )}
                            </div>

                            <ul className="mt-7 space-y-2.5 border-t border-border/60 pt-6">
                                {promises.map((promise, i) => (
                                    <Reveal
                                        as="li"
                                        key={promise}
                                        delay={500 + i * 90}
                                        fast
                                        className="flex gap-2.5 text-sm text-muted-foreground"
                                    >
                                        <Check className="mt-0.5 size-4 shrink-0 text-brand" />
                                        {promise}
                                    </Reveal>
                                ))}
                            </ul>
                        </div>
                    </TiltCard>
                </div>

                <Reveal delay={700}>
                    <p className="mt-8 flex items-center justify-center gap-1.5 text-center text-xs text-muted-foreground">
                        <ShieldCheck className="size-3.5" />
                        Preflight never reads your code or your data.
                    </p>
                </Reveal>
            </div>
        </>
    );
}
