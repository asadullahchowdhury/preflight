import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/**
 * "Built by …", crediting the maker (config/preflight.php → author).
 */
export function MadeBy({ className }: { className?: string }) {
    const { author } = usePage().props;

    return (
        <p className={cn('text-sm text-muted-foreground', className)}>
            Built by{' '}
            {author.url ? (
                <a
                    href={author.url}
                    target="_blank"
                    rel="author noopener"
                    className="font-medium text-foreground/80 underline-offset-4 transition-colors hover:text-foreground hover:underline"
                >
                    {author.name}
                </a>
            ) : (
                <span className="font-medium text-foreground/80">
                    {author.name}
                </span>
            )}
        </p>
    );
}
