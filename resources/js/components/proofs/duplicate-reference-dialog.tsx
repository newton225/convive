import { useState } from 'react';
import { ReceiptComparisonCard } from '@/components/proofs/receipt-comparison-card';
import { ReceiptPreviewDialog } from '@/components/proofs/receipt-preview-dialog';
import type { ReceiptPreview } from '@/components/proofs/receipt-preview-dialog';
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
 * Les autres preuves qui portent la meme reference de transaction que celle examinee (signal
 * « Reference deja utilisee », README 2.9), tous evenements confondus. Chaque fiche dit si elle
 * vient du meme dossier (un nouveau depot) ou d'une autre inscription.
 */
export function DuplicateReferenceDialog({ proof, onOpenChange }: Props) {
    const { t, locale } = useTranslation();
    const [preview, setPreview] = useState<ReceiptPreview | null>(null);

    return (
        <Dialog
            open={proof !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setPreview(null);
                }

                onOpenChange(open);
            }}
        >
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('proofs.duplicate_reference.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('proofs.duplicate_reference.description', {
                            name: proof?.name ?? '',
                        })}
                    </DialogDescription>
                </DialogHeader>

                {proof ? (
                    <div className="-mx-1 max-h-[65vh] space-y-5 overflow-y-auto px-1">
                        <section className="space-y-2">
                            <h3 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                {t('proofs.duplicate_reference.current')}
                            </h3>
                            <ReceiptComparisonCard
                                receiptUrl={proof.receiptUrl}
                                name={proof.name}
                                facts={proofReceiptFacts(proof, t, locale)}
                                highlighted
                                testId="duplicate-reference-current"
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
                                {t('proofs.duplicate_reference.others', {
                                    count: proof.duplicateReferenceMatches
                                        .length,
                                })}
                            </h3>
                            {proof.duplicateReferenceMatches.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    {t('proofs.duplicate_reference.empty')}
                                </p>
                            ) : (
                                <div
                                    className="space-y-2"
                                    data-test="duplicate-reference-matches"
                                >
                                    {proof.duplicateReferenceMatches.map(
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
                                                        <div className="flex flex-wrap gap-1">
                                                            {match.sameRegistration ? (
                                                                <Badge variant="outline">
                                                                    {t(
                                                                        'proofs.duplicate_reference.same_registration',
                                                                    )}
                                                                </Badge>
                                                            ) : null}
                                                            <Badge variant="secondary">
                                                                {
                                                                    match.statusLabel
                                                                }
                                                            </Badge>
                                                        </div>
                                                    }
                                                    testId="duplicate-reference-match"
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
