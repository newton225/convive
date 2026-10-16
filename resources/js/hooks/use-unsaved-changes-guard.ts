import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Previent avant de quitter une page dont des changements ne sont pas enregistres : actualiser ou
 * fermer l'onglet (le navigateur pose sa propre question), ou aller sur une autre page de
 * l'application (confirmation). L'enregistrement lui-meme n'est pas une navigation (PATCH, POST) :
 * seules les visites en GET demandent confirmation. Rien n'est enregistre a la place de l'utilisateur.
 */
export function useUnsavedChangesGuard(dirty: boolean) {
    const { t } = useTranslation();

    useEffect(() => {
        if (!dirty) {
            return;
        }

        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };

        window.addEventListener('beforeunload', onBeforeUnload);

        const offBefore = router.on('before', (event) => {
            if (event.detail.visit.method !== 'get') {
                return;
            }

            return window.confirm(t('common.leave_unsaved'));
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            offBefore();
        };
    }, [dirty, t]);
}
