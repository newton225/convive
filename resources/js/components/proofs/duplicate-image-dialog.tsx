import { useState } from 'react';
import { ReceiptPreviewDialog } from '@/components/proofs/receipt-preview-dialog';
import type { ReceiptPreview } from '@/components/proofs/receipt-preview-dialog';
import { ReceiptComparisonCard } from '@/components/proofs/receipt-comparison-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import {
    duplicateMatchReceiptFacts,
    proofReceiptFacts,
} from '@/lib/receipt-facts';
import type { PaymentProofRow } from '@/types';

type Props = {
    proof: PaymentProofRow | null;
    onOpenChange: (open: boolean) => void;
};

/**
 * Les autres versements qui portent la meme capture que la preuve examinee (signal « Capture deja
 * vue », README 2.9), tous evenements confondus. Les captures s'affichent en vignettes, chargees
 * par la meme route journalisee que la file : le tresorier les compare cote a cote avant de
 * trancher, et en agrandit une d'un clic.
 */
export function DuplicateImageDialog({ proof, onOpenChange }: Props) {
    const { t, locale } = useTranslation();
    const [preview, setPreview] = useState<ReceiptPreview | null>(null);

    return (
        <Dialog
            open={proof !== null}
            onOpenChange={(open) => {
                // Un apercu reste ferme d'une ouverture a l'autre de la fenetre.
                if (!open) {
                    setPreview(null);
                }

                onOpenChange(open);
            }}
        >
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('proofs.duplicate_image.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('proofs.duplicate_image.description', {
                            name: proof?.name ?? '',
                        })}
                    </DialogDescription>
                </DialogHeader>

                {proof ? (
                    <div className="-mx-1 max-h-[65vh] space-y-5 overflow-y-auto px-1">
                        <section className="space-y-2">
                            <h3 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                {t('proofs.duplicate_image.current')}
                            </h3>
                            <ReceiptComparisonCard
                                receiptUrl={proof.receiptUrl}
                                name={proof.name}
                                facts={proofReceiptFacts(proof, t, locale)}
                                highlighted
                                testId="duplicate-image-current"
                                onEnlarge={() =>
                                    setPreview({
                                        url: proof.receiptUrl ?? '',
                                        name: proof.name,
                                        facts: proofReceiptFacts(
                                            proof,
                                            t,
                                            locale,
                                        ),
                                    })
                                }
                            />
                        </section>

                        <section className="space-y-2">
                            <h3 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                {t('proofs.duplicate_image.others', {
                                    count: proof.duplicateImageMatches.length,
                                })}
                            </h3>
                            {proof.duplicateImageMatches.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    {t('proofs.duplicate_image.empty')}
                                </p>
                            ) : (
                                <div
                                    className="space-y-2"
                                    data-test="duplicate-image-matches"
                                >
                                    {proof.duplicateImageMatches.map(
                                        (match) => {
                                            const facts =
                                                duplicateMatchReceiptFacts(
                                                    match,
                                                    t,
                                                    locale,
                                                );

                                            return (
                                                <ReceiptComparisonCard
                                                    key={match.proofId}
                                                    receiptUrl={
                                                        match.receiptUrl
                                                    }
                                                    name={match.name}
                                                    // Le statut est deja dans le badge.
                                                    facts={facts.filter(
                                                        (fact) =>
                                                            fact.id !==
                                                            'status',
                                                    )}
                                                    badge={
                                                        <Badge
                                                            variant={
                                                                match.status ===
                                                                'confirmed'
                                                                    ? 'default'
                                                                    : match.status ===
                                                                        'cancelled'
                                                                      ? 'destructive'
                                                                      : 'secondary'
                                                            }
                                                        >
                                                            {match.statusLabel}
                                                        </Badge>
                                                    }
                                                    testId="duplicate-image-match"
                                                    onEnlarge={() =>
                                                        setPreview({
                                                            url:
                                                                match.receiptUrl ??
                                                                '',
                                                            name: match.name,
                                                            facts,
                                                        })
                                                    }
                                                />
                                            );
                                        },
                                    )}
                                </div>
                            )}
                        </section>
                    </div>
                ) : null}

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary">
                            {t('common.actions.close')}
                        </Button>
                    </DialogClose>
                </DialogFooter>

                <ReceiptPreviewDialog
                    receipt={preview}
                    onOpenChange={(open) => !open && setPreview(null)}
                />
            </DialogContent>
        </Dialog>
    );
}
