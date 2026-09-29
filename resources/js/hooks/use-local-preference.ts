import { useCallback, useState } from 'react';

const Prefix = 'convive.preference.';

function read(key: string, fallback: boolean): boolean {
    try {
        const stored = window.localStorage.getItem(Prefix + key);

        return stored === null ? fallback : stored === 'true';
    } catch {
        return fallback;
    }
}

/**
 * Une preference d'affichage propre a ce navigateur (une case cochee, un mode de liste) : elle ne
 * gouverne aucune regle metier et n'a pas a suivre l'utilisateur d'un appareil a l'autre. Le
 * stockage peut etre indisponible (navigation privee stricte) : la valeur par defaut s'applique
 * alors, et le choix vaut pour la visite.
 */
export function useLocalPreference(
    key: string,
    fallback: boolean,
): [boolean, (value: boolean) => void] {
    const [value, setValue] = useState(() => read(key, fallback));

    const update = useCallback(
        (next: boolean) => {
            setValue(next);

            try {
                window.localStorage.setItem(Prefix + key, String(next));
            } catch {
                // Stockage indisponible : le choix vaut pour cette visite seulement.
            }
        },
        [key],
    );

    return [value, update];
}
