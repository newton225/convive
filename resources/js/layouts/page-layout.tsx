import type { PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

type Props = PropsWithChildren<{
    // Formulaire simple : une colonne etroite, plus lisible. Pose par la page via ses props de
    // mise en page (`Page.layout`), qu'Inertia transmet a chaque layout de la pile.
    narrow?: boolean;
}>;

/**
 * Les marges d'une page du back-office, communes aux evenements et a l'organisation. Le menu de
 * gauche reste le seul repere de navigation : pas de sous-menu ici.
 */
export default function PageLayout({ children, narrow = false }: Props) {
    return (
        <div className="px-4 py-6 md:px-6">
            <div className={cn('min-w-0', narrow && 'max-w-2xl')}>
                {children}
            </div>
        </div>
    );
}
