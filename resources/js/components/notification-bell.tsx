import { router, usePage } from '@inertiajs/react';
import { Bell, BellOff } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { NotificationItem } from '@/components/notification-item';
import { useTranslation } from '@/hooks/use-translation';
import { readAll } from '@/routes/notifications';

/**
 * La cloche (README section 5) : compteur de non-lus, les dix dernieres alertes, clic vers
 * l'ecran concerne (le serveur marque l'alerte lue puis redirige), et « tout marquer comme lu ».
 * L'etat non lu ne repose pas sur la couleur seule : un texte pour les lecteurs d'ecran et une
 * graisse differente.
 */
export function NotificationBell() {
    const { t } = useTranslation();
    const { notifications, tenants } = usePage().props;

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

            <DropdownMenuContent align="end" className="w-[22rem] p-0">
                <div className="flex items-center justify-between gap-2 px-4 py-3">
                    <DropdownMenuLabel className="p-0 text-sm">
                        {t('notifications.bell.title')}
                    </DropdownMenuLabel>
                    {unreadCount > 0 ? (
                        <button
                            type="button"
                            className="text-primary text-xs font-medium hover:underline"
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
                        </button>
                    ) : null}
                </div>

                <DropdownMenuSeparator className="m-0" />

                {latest.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 px-4 py-8 text-center">
                        <BellOff className="text-muted-foreground size-6" />
                        <p className="text-muted-foreground text-sm">
                            {t('notifications.bell.empty')}
                        </p>
                    </div>
                ) : (
                    <div className="max-h-96 overflow-y-auto p-1.5">
                        {latest.map((alert) => (
                            <NotificationItem
                                key={alert.id}
                                alert={alert}
                                showTenant={tenants.length > 1}
                            />
                        ))}
                    </div>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
