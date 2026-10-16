import { usePage } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Le numero de version en pied des pages de connexion et d'inscription : c'est lui qu'une personne
 * cite quand elle signale un probleme avant meme d'avoir un compte. Le numero seul, jamais la date
 * de livraison ni la modification (`AppVersion`, reserve aux membres connectes).
 */
export function PublicAppVersion() {
    const { t } = useTranslation();
    const { publicVersion } = usePage().props;

    if (!publicVersion) {
        return null;
    }

    return (
        <footer
            className="text-muted-foreground text-center text-xs"
            data-test="public-app-version"
        >
            {t('navigation.version', { version: publicVersion })}
        </footer>
    );
}
