import type { ReactNode } from 'react';
import type { ReceiptFact } from '@/components/proofs/receipt-preview-dialog';
import { ReceiptThumbnail } from '@/components/proofs/receipt-thumbnail';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Props = {
    receiptUrl: string | null;
    name: string;
    facts: ReceiptFact[];
    // Statut ou mention, en haut a droite de la carte.
    badge?: ReactNode;
    // La preuve examinee : mise en avant, pour ne jamais la confondre avec un doublon.
    highlighted?: boolean;
    onEnlarge: () => void;
    testId: string;
};

/**
 * Une capture et la fiche de son versement, dans la fenetre « Capture deja vue ». La preuve
 * examinee et ses doublons ont exactement la meme carte, pour que l'oeil compare ligne a ligne.
 */
export function ReceiptComparisonCard({
    receiptUrl,
    name,
    facts,
    badge,
    highlighted = false,
    onEnlarge,
    testId,
}: Props) {
    const { t } = useTranslation();
    const shown = facts.filter((fact) => fact.value !== '');

    return (
        <article
            className={cn(
                'flex gap-4 rounded-lg border p-3',
                highlighted && 'border-primary/60 bg-primary/5',
            )}
            data-test={testId}
        >
            {receiptUrl ? (
                <ReceiptThumbnail
                    url={receiptUrl}
                    name={name}
                    className="h-36 w-28 shrink-0"
                    onEnlarge={onEnlarge}
                />
            ) : (
                <div className="bg-muted text-muted-foreground flex h-36 w-28 shrink-0 items-center justify-center rounded-md p-2 text-center text-xs">
                    {t('proofs.duplicate_image.no_receipt')}
                </div>
            )}

            <div className="min-w-0 flex-1 space-y-2">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <p className="font-medium break-words">{name}</p>
                    {badge}
                </div>
                <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-xs">
                    {shown.map((fact) => (
                        <div key={fact.id} className="contents">
                            <dt className="text-muted-foreground">
                                {fact.label}
                            </dt>
                            <dd
                                className={cn(
                                    'break-words',
                                    fact.mono && 'font-mono break-all',
                                )}
                            >
                                {fact.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </article>
    );
}
