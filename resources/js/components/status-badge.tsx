import { Badge } from '@/components/ui/badge';
import { statusToneClasses } from '@/lib/status-tones';
import type { StatusTone } from '@/lib/status-tones';
import { cn } from '@/lib/utils';

export function StatusBadge({
    tone,
    children,
    ...props
}: {
    tone: StatusTone;
    children: React.ReactNode;
} & Omit<React.ComponentProps<'span'>, 'children'>) {
    return (
        <Badge
            variant="secondary"
            {...props}
            className={cn(
                'border-transparent',
                statusToneClasses[tone],
                props.className,
            )}
        >
            {children}
        </Badge>
    );
}
