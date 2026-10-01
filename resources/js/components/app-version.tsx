import { usePage } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';

/**
 * La version de l'application, en pied de menu du back-office : c'est elle qu'un membre cite
 * quand il signale un probleme. Masquee quand le menu est replie en icones.
 */
export function AppVersion() {
    const { t } = useTranslation();
    const { appVersion } = usePage().props;

    if (!appVersion) {
        return null;
    }

    return (
        <p
            className="text-muted-foreground px-2 text-xs group-data-[collapsible=icon]:hidden"
            data-test="app-version"
        >
            {t('navigation.version', { version: appVersion })}
        </p>
    );
}
