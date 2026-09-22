import { CloudOff } from 'lucide-react';
import { useOnlineStatus } from '@/hooks/use-online-status';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Le bandeau « hors ligne » des pages invite : il previent que les envois attendent le retour du
 * reseau, sans bloquer la lecture (le billet reste consultable). Un message ecrit et une icone,
 * annonces aux lecteurs d'ecran (`role="status"`) : rien ne repose sur la couleur seule.
 */
export function OfflineBanner() {
    const online = useOnlineStatus();
    const { t } = useTranslation();

    if (online) {
        return null;
    }

    return (
        <div
            className="bg-muted text-foreground flex items-start gap-3 px-4 py-3 text-sm"
            role="status"
            data-test="offline-banner"
        >
            <CloudOff className="mt-0.5 size-4 shrink-0" />
            <div>
                <p className="font-medium">{t('offline.banner.title')}</p>
                <p className="text-muted-foreground">
                    {t('offline.banner.body')}
                </p>
            </div>
        </div>
    );
}
