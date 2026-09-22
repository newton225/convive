import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { read, readAll } from '@/routes/notifications';

/**
 * La cloche (README section 5) : compteur de non-lus, les dix dernieres alertes, clic vers
 * l'ecran concerne (le serveur marque l'alerte lue puis redirige), et « tout marquer comme lu ».
 * L'etat non lu ne repose pas sur la couleur seule : un texte pour les lecteurs d'ecran et une
 * graisse differente.
 */
export function NotificationBell() {
    const { t, locale } = useTranslation();
    const { notifications } = usePage().props;

    if (!notifications) {
        return null;
    }

    const { unreadCount, latest } = notifications;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={t('notifications.bell.label', {
                        count: unreadCount,
                    })}
                    data-test="notification-bell"
                >
                    <Bell className="size-5" />
                    {unreadCount > 0 ? (
                        <Badge
                            className="absolute -top-1 -right-1 h-5 min-w-5 justify-center px-1"
                            data-test="notification-count"
                        >
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </Badge>
                    ) : null}
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-80">
                <div className="flex items-center justify-between gap-2 px-2 py-1.5">
                    <DropdownMenuLabel className="p-0">
                        {t('notifications.bell.title')}
                    </DropdownMenuLabel>
                    {unreadCount > 0 ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            data-test="notification-read-all"
                            onClick={() =>
                                router.post(
                                    readAll().url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('notifications.bell.read_all')}
                        </Button>
                    ) : null}
                </div>

                <DropdownMenuSeparator />

                {latest.length === 0 ? (
                    <p className="text-muted-foreground px-2 py-4 text-sm">
                        {t('notifications.bell.empty')}
                    </p>
                ) : (
                    latest.map((alert) => (
                        <DropdownMenuItem
                            key={alert.id}
                            className="flex flex-col items-start gap-0.5"
                            data-test="notification-item"
                            onSelect={() => router.post(read(alert.id).url)}
                        >
                            <span
                                className={
                                    alert.read
                                        ? 'text-sm'
                                        : 'text-sm font-semibold'
                                }
                            >
                                {alert.read ? null : (
                                    <span className="sr-only">
                                        {t('notifications.bell.unread')}
                                        {' : '}
                                    </span>
                                )}
                                {alert.title}
                            </span>
                            <span className="text-muted-foreground text-xs">
                                {[
                                    alert.tenantName,
                                    alert.createdAt
                                        ? formatDateTime(
                                              alert.createdAt,
                                              locale,
                                          )
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
