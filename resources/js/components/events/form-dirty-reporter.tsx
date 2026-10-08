import { useEffect } from 'react';

/**
 * Remonte l'etat « modifie ou non » du formulaire Inertia, que seule sa fonction de rendu connait,
 * jusqu'aux elements qui vivent hors du formulaire (le bouton Publier de l'en-tete).
 */
export function FormDirtyReporter({
    dirty,
    onChange,
}: {
    dirty: boolean;
    onChange: (dirty: boolean) => void;
}) {
    useEffect(() => {
        onChange(dirty);
    }, [dirty, onChange]);

    return null;
}
