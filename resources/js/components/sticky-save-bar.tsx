import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
    className?: string;
    testId?: string;
};

/**
 * La barre qui porte le bouton d'enregistrement d'un long formulaire : collee en bas de l'ecran, pour
 * qu'enregistrer n'oblige jamais a le parcourir jusqu'au bout (decision du proprietaire du projet,
 * 2026-10-11). Le fond est opaque : les champs qui defilent dessous ne doivent pas se lire a travers.
 * A reserver aux formulaires longs d'une seule action d'enregistrement ; une page qui en porte
 * plusieurs, chacun avec son bouton (la page de l'organisation), garde ses boutons en bas de section.
 */
export function StickySaveBar({ children, className, testId }: Props) {
    return (
        <div
            className={cn(
                'bg-background sticky bottom-0 z-20 -mx-1 flex flex-wrap gap-2 px-1 py-3',
                className,
            )}
            data-test={testId}
        >
            {children}
        </div>
    );
}
