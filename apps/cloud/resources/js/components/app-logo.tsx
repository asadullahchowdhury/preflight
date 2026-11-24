import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

export function LogoMark({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                'relative flex aspect-square size-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand to-brand-soft text-white shadow-md shadow-brand/25',
                className,
            )}
        >
            <AppLogoIcon className="size-[60%]" />
        </div>
    );
}

export default function AppLogo() {
    return (
        <>
            <LogoMark />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold tracking-tight">
                    Preflight
                </span>
                <span className="truncate text-[11px] leading-none text-muted-foreground">
                    Migration safety
                </span>
            </div>
        </>
    );
}
