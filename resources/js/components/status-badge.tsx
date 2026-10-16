import { Badge } from '@/components/ui/badge';
import { statusToneClasses } from '@/lib/status-tones';
import type { StatusTone } from '@/lib/status-tones';
import { cn } from '@/lib/utils';

export function StatusBadge({
    tone,
    className,
    ...props
}: { tone: StatusTone } & Omit<React.ComponentProps<typeof Badge>, 'variant'>) {
    return (
        <Badge
            variant="secondary"
            className={cn(
                'border-transparent',
                statusToneClasses[tone],
                className,
            )}
            {...props}
        />
    );
}
