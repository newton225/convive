let documentNonce: string | null = null;

/**
 * Le nonce CSP du document charge par le navigateur. L'en-tete CSP n'est lu qu'au chargement
 * complet de la page : apres une navigation Inertia, la prop `cspNonce` porte le nonce de la
 * derniere reponse, que le navigateur ne connait pas, et une balise `<style>` qui le porterait
 * serait refusee. On lit donc une fois celui des scripts du document (la propriete `nonce` reste
 * lisible en JavaScript meme quand l'attribut est masque par le navigateur).
 */
export function documentCspNonce(fallback: string): string {
    if (documentNonce === null && typeof document !== 'undefined') {
        documentNonce =
            document.querySelector<HTMLScriptElement>('script[nonce]')?.nonce ||
            null;
    }

    return documentNonce ?? fallback;
}
