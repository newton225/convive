import { useEffect, useRef } from 'react';

/**
 * Dit au formulaire Inertia qu'un champ a change sans que l'utilisateur ait tape dans un champ.
 * Le composant `Form` ne recalcule « modifie ou non » que sur les evenements `input`, `change` et
 * `reset` du formulaire : ajouter ou retirer une ligne, bouger un curseur ou cocher une case qui
 * pilote un champ cache ne declenche rien, et le bouton Enregistrer restait muet. Le hook envoie
 * un `input` depuis l'element designe par la reference, une fois la page a jour. Le premier rendu
 * n'envoie rien : rien n'a change.
 */
export function useNotifyFormChange<T extends HTMLElement>(change: unknown) {
    const element = useRef<T>(null);
    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        element.current?.dispatchEvent(new Event('input', { bubbles: true }));
    }, [change]);

    return element;
}
