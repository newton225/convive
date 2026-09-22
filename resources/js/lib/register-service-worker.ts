/**
 * Enregistre le service worker de l'application (PWA). Apres le chargement de la page, pour ne
 * jamais ralentir le premier affichage. Sans effet hors contexte securise (HTTPS ou localhost) ou
 * sur un navigateur qui ne connait pas les service workers : l'application reste alors utilisable
 * comme un site ordinaire.
 */
export function registerServiceWorker(): void {
    if (
        typeof window === 'undefined' ||
        !('serviceWorker' in navigator) ||
        !window.isSecureContext
    ) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Un echec d'enregistrement ne doit jamais casser la page.
        });
    });
}
