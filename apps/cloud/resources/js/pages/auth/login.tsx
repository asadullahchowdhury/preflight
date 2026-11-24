import { Head, Link, usePage } from '@inertiajs/react';
import { ShieldCheck, Sparkles } from 'lucide-react';
import { LogoMark } from '@/components/app-logo';
import { GitHubIcon } from '@/components/preflight/bits';
import { AmbientBackground, Reveal } from '@/components/preflight/motion';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { login as devLogin } from '@/routes/dev';
import { redirect } from '@/routes/github';

export default function Login({
    configured,
    devLogin: devLoginEnabled,
}: {
    configured: boolean;
    devLogin: boolean;
}) {
    const { flash } = usePage<{ flash?: { error?: string } }>().props;

    return (
        <>
            <Head title="Sign in" />
            <div className="relative isolate flex min-h-svh flex-col items-center justify-center px-4 py-12">
                <AmbientBackground />

                <Reveal className="w-full max-w-sm">
                    <Link
                        href={home()}
                        className="mx-auto mb-8 flex w-fit items-center gap-2.5"
                    >
                        <LogoMark className="size-9" />
                        <span className="text-lg font-semibold tracking-tight">
                            Preflight
                        </span>
                    </Link>

                    <div className="rounded-3xl glass-strong p-7 shadow-xl shadow-black/5">
                        <h1 className="text-center text-xl font-semibold tracking-tight">
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
                                    className="h-11 w-full text-[15px]"
                                >
                                    <a href={redirect.url()}>
                                        <GitHubIcon className="size-5" />
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
                                    className="h-11 w-full glass"
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
                    </div>

                    <p className="mt-6 flex items-center justify-center gap-1.5 text-center text-xs text-muted-foreground">
                        <ShieldCheck className="size-3.5" />
                        Preflight never reads your code or your data.
                    </p>
                </Reveal>
            </div>
        </>
    );
}
