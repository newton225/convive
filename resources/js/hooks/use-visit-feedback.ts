import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { translate } from '@/hooks/use-translation';
import type { Translations } from '@/types';

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

// Les traductions de la page servie au premier chargement, si le premier `navigate` est parti avant
// l'abonnement : lues dans le bloc JSON qu'Inertia insere dans le HTML.
function initialTranslations(): Translations {
    try {
        const raw = document.querySelector(
            'script[data-page="app"]',
        )?.textContent;
        const page: unknown = raw ? JSON.parse(raw) : null;

        if (
            isRecord(page) &&
            isRecord(page.props) &&
            isRecord(page.props.translations)
        ) {
            return page.props.translations;
        }
    } catch {
        // Bloc absent ou illisible : les traductions arriveront avec la prochaine navigation.
    }

    return {};
}

/**
 * Retour immediat sur l'echec d'une action (CLAUDE.md, « Retour immediat sur chaque action » et
 * « Messages d'erreur utiles »), pour toute visite Inertia de l'application.
 *
 * Erreurs de validation : une erreur deja affichee sous son champ ne se double pas d'une
 * notification. Est considere comme affiche tout message dont la cle correspond a un champ present
 * et visible a l'ecran (`name`) ou a un emplacement declare (`data-error-for`) : un champ hors de vue,
 * tout en bas d'un long formulaire, donne aussi une notification. Le reste, typiquement une
 * action lancee par un bouton (table pleine, publication refusee, dernier Proprietaire), n'avait
 * aucun endroit ou s'afficher : il devient une notification d'echec.
 *
 * Erreurs HTTP et reseau : un message lisible plutot que la fenetre brute d'Inertia. En
 * developpement, une erreur serveur garde aussi la page de diagnostic de Laravel.
 *
 * Monte avec le `Toaster`, hors de l'arbre des pages : `usePage()` n'y est pas disponible, les
 * traductions sont relevees a chaque navigation (declenchee aussi au premier chargement).
 */
export function useVisitFeedback(): void {
    useEffect(() => {
        let translations = initialTranslations();
        const t = (key: string) => translate(translations, key);

        const offNavigate = router.on('navigate', (event) => {
            translations = event.detail.page.props.translations;
        });

        // Une erreur n'est « affichee » que si son champ est a l'ecran : dans un long formulaire, on
        // valide en haut de page et le champ fautif peut se trouver bien plus bas, hors de vue.
        const isInView = (element: Element) => {
            const target =
                element instanceof HTMLInputElement && element.type === 'hidden'
                    ? element.parentElement
                    : element;

            if (!target) {
                return true;
            }

            const rect = target.getBoundingClientRect();

            return rect.bottom > 0 && rect.top < window.innerHeight;
        };

        const isDisplayed = (key: string) => {
            const base = key.split('.')[0];
            const selectors = [key, base, `${base}[]`]
                .map((name) => `[name="${CSS.escape(name)}"]`)
                .concat(`[data-error-for="${CSS.escape(base)}"]`);

            return Array.from(
                document.querySelectorAll(selectors.join(',')),
            ).some(isInView);
        };

        const offError = router.on('error', (event) => {
            const messages = Object.entries(event.detail.errors)
                .filter(([key]) => !isDisplayed(key))
                .map(([, message]) => message);

            Array.from(new Set(messages)).forEach((message) =>
                toast.error(message),
            );
        });

        const offHttpException = router.on('httpException', (event) => {
            const status = event.detail.response.status;
            const key =
                status === 403
                    ? 'common.feedback.forbidden'
                    : status === 404
                      ? 'common.feedback.not_found'
                      : status === 419
                        ? 'common.feedback.session_expired'
                        : status >= 500
                          ? 'common.feedback.server_error'
                          : 'common.feedback.unexpected';

            toast.error(t(key));

            if (!(import.meta.env.DEV && status >= 500)) {
                event.preventDefault();
            }
        });

        const offNetworkError = router.on('networkError', (event) => {
            toast.error(t('common.feedback.network_error'));
            event.preventDefault();
        });

        return () => {
            offNavigate();
            offError();
            offHttpException();
            offNetworkError();
        };
    }, []);
}
