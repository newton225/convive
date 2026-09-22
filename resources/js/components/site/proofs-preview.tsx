import { Check, TriangleAlert } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

const Rows = [
    {
        name: 'Kofi Diallo',
        reference: 'WV2041',
        amount: '15 000 F CFA',
        signal: 'duplicate',
    },
    {
        name: 'Marie Traore',
        reference: 'OM7788',
        amount: '20 000 F CFA',
        signal: 'mismatch',
    },
    {
        name: 'Aya Kouassi',
        reference: 'WV2107',
        amount: '30 000 F CFA',
        signal: 'clean',
    },
] as const;

/**
 * La file de verification des preuves (README ecran 18), avec ses signaux d'anomalie. Le signal
 * est ecrit en toutes lettres et porte une icone : rien ne repose sur la couleur seule.
 */
export function ProofsPreview() {
    const { t } = useTranslation();

    return (
        <div data-test="site-proofs-preview" className="space-y-2">
            <p className="text-muted-foreground text-sm">
                {t('site.preview.proofs.title')}
            </p>
            <ul className="space-y-2">
                {Rows.map((row) => (
                    <li
                        key={row.reference}
                        className="bg-muted/60 flex flex-wrap items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm"
                    >
                        <span>
                            <span className="font-medium">{row.name}</span>
                            <span className="text-muted-foreground ml-2 font-mono text-xs">
                                {row.reference}
                            </span>
                        </span>
                        <span className="flex items-center gap-2">
                            <span className="tabular-nums">{row.amount}</span>
                            <span
                                className={
                                    row.signal === 'clean'
                                        ? 'text-muted-foreground inline-flex items-center gap-1 text-xs'
                                        : 'text-destructive inline-flex items-center gap-1 text-xs font-medium'
                                }
                            >
                                {row.signal === 'clean' ? (
                                    <Check className="size-3.5" />
                                ) : (
                                    <TriangleAlert className="size-3.5" />
                                )}
                                {t(
                                    row.signal === 'duplicate'
                                        ? 'site.preview.proofs.duplicate'
                                        : row.signal === 'mismatch'
                                          ? 'site.preview.proofs.mismatch'
                                          : 'site.preview.proofs.clean',
                                )}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
