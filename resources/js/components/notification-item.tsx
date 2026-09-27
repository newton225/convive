import { router } from '@inertiajs/react';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { formatRelative } from '@/lib/format-date';
import { notificationIcon } from '@/lib/notification-icons';
import { cn } from '@/lib/utils';
import { read } from '@/routes/notifications';
import type { NotificationAlert } from '@/types';

type Props = {
    alert: NotificationAlert;
    // Le nom de l'organisation n'aide que le membre qui en a plusieurs.
    showTenant: boolean;
};

/**
 * Une alerte de la cloche : icone du type, texte, date relative. Non lue : un point d'accent et
 * une mention pour les lecteurs d'ecran, jamais la couleur seule.
 */
export function NotificationItem({ alert, showTenant }: Props) {
    const { t, locale } = useTranslation();
    const Icon = notificationIcon(alert.type);

    return (
        <DropdownMenuItem
            className="items-start gap-3 rounded-md px-2 py-2.5"
            data-test="notification-item"
            onSelect={() => router.post(read(alert.id).url)}
        >
            <span
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-full',
                    alert.read
                        ? 'bg-muted text-muted-foreground'
                        : 'bg-primary/10 text-primary',
                )}
            >
                <Icon className="size-4" />
            </span>

            <span className="min-w-0 flex-1 space-y-0.5">
                <span
                    className={cn(
                        'block text-sm leading-snug',
                        alert.read ? 'text-muted-foreground' : 'font-medium',
                    )}
                >
                    {alert.read ? null : (
                        <span className="sr-only">
                            {t('notifications.bell.unread')}
                            {' : '}
                        </span>
                    )}
                    {alert.title}
                </span>
                <span className="text-muted-foreground block text-xs">
                    {[
                        alert.createdAt
                            ? formatRelative(alert.createdAt, locale)
                            : null,
                        showTenant ? alert.tenantName : null,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </span>
            </span>

            {alert.read ? null : (
                <span
                    className="bg-primary mt-1.5 size-2 shrink-0 rounded-full"
                    aria-hidden="true"
                />
            )}
        </DropdownMenuItem>
    );
}
