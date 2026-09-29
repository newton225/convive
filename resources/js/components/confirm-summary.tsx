import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type ConfirmSummaryItem = {
    label: string;
    value: ReactNode;
    // Ce qui decide de l'action (montant, numero de compte) : en gras.
    emphasis?: boolean;
    // Identifiants a comparer caractere par caractere (reference, numero) : chasse fixe.
    mono?: boolean;
    // Ce qui doit arreter l'oeil avant de confirmer (anomalie, ecart de montant).
    warning?: boolean;
    // Absent : la ligne n'est pas affichee. Evite les conditions a chaque appel.
    hidden?: boolean;
    testId?: string;
};

type Props = {
    items: ConfirmSummaryItem[];
    testId?: string;
};

/**
 * La fiche de la ligne visee, rappelee dans une confirmation : sans elle, deux lignes voisines d'un
 * tableau (meme nom de famille, meme montant) se confondent au moment de valider. Chaque ecran
 * choisit les champs qui designent sa ligne sans ambiguite ; ce composant ne fait que les aligner.
 */
export function ConfirmSummary({ items, testId }: Props) {
    return (
        <dl
            className="bg-muted grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 rounded-lg p-3 text-sm"
            data-test={testId}
        >
            {items
                .filter((item) => !item.hidden)
                .map((item) => (
                    <div key={item.label} className="contents">
                        <dt className="text-muted-foreground">{item.label}</dt>
                        <dd
                            className={cn(
                                'break-words whitespace-pre-line',
                                item.emphasis && 'font-medium',
                                item.mono && 'font-mono break-all',
                                item.warning && 'text-destructive font-medium',
                            )}
                            data-test={item.testId}
                        >
                            {item.value}
                        </dd>
                    </div>
                ))}
        </dl>
    );
}
