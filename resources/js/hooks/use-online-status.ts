import { useSyncExternalStore } from 'react';

const subscribe = (callback: () => void) => {
    window.addEventListener('online', callback);
    window.addEventListener('offline', callback);

    return () => {
        window.removeEventListener('online', callback);
        window.removeEventListener('offline', callback);
    };
};

/**
 * Le reseau est-il disponible ? Reactif : le composant se met a jour au retour comme a la perte de
 * la connexion. `navigator.onLine` dit seulement qu'une interface reseau existe, pas qu'Internet
 * repond : c'est un signal, pas une garantie, d'ou les echecs de synchronisation gardes en file.
 */
export function useOnlineStatus(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => navigator.onLine,
        () => true,
    );
}
