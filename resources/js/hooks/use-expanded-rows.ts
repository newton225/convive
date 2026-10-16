import type { ExpandedState, Updater } from '@tanstack/react-table';
import { useState } from 'react';
import { useLocalPreference } from '@/hooks/use-local-preference';

/**
 * Les lignes depliees d'un tableau, et le choix « une seule ligne ouverte a la fois » retenu par
 * navigateur (`storageKey`). Partage par la file des preuves et la base d'inscrits : meme comportement,
 * une seule ecriture. Une seule ligne ouverte : ouvrir une ligne referme les autres ; sinon TanStack
 * Table les garde toutes ouvertes.
 */
export function useExpandedRows(storageKey: string): {
    expanded: ExpandedState;
    onExpandedChange: (updater: Updater<ExpandedState>) => void;
    singleExpand: boolean;
    toggleSingleExpand: (checked: boolean) => void;
} {
    const [expanded, setExpanded] = useState<ExpandedState>({});
    const [singleExpand, setSingleExpand] = useLocalPreference(
        storageKey,
        false,
    );

    const onExpandedChange = (updater: Updater<ExpandedState>) =>
        setExpanded((previous) => {
            const next =
                typeof updater === 'function' ? updater(previous) : updater;

            if (!singleExpand || next === true) {
                return next;
            }

            const opened = Object.keys(next).filter(
                (id) => next[id] && !(previous !== true && previous[id]),
            );

            return opened.length > 0
                ? { [opened[opened.length - 1]]: true }
                : next;
        });

    const toggleSingleExpand = (checked: boolean) => {
        setSingleExpand(checked);

        // En passant a une seule ligne, on repart d'un tableau replie plutot que de choisir
        // arbitrairement laquelle des lignes deja ouvertes garder.
        if (checked) {
            setExpanded({});
        }
    };

    return { expanded, onExpandedChange, singleExpand, toggleSingleExpand };
}
