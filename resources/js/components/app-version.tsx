import { usePage } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format-date';

/**
 * La version de l'application, en pied de menu du back-office : c'est elle qu'un membre cite
 * quand il signale un probleme. Le numero est choisi par l'editeur ; la date de livraison le suit
 * des qu'une livraison a ete estampillee, et l'identifiant de la modification se lit au survol.
 * Masquee quand le menu est replie en icones.
 */
export function AppVersion() {
    const { t, locale } = useTranslation();
    const { appVersion, appRelease } = usePage().props;

    if (!appVersion) {
        return null;
    }

    return (
        <p
            className="text-muted-foreground px-2 text-xs group-data-[collapsible=icon]:hidden"
            data-test="app-version"
            title={
                appRelease?.commit
                    ? t('navigation.version_commit', {
                          commit: appRelease.commit,
                      })
                    : undefined
            }
        >
            {appRelease
                ? t('navigation.version_released', {
                      version: appVersion,
                      date: formatDate(appRelease.releasedAt, locale),
                  })
                : t('navigation.version', { version: appVersion })}
        </p>
    );
}
