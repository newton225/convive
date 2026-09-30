import { Download } from 'lucide-react';
import { ReceiptImage } from '@/components/proofs/receipt-image';
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
import { cn } from '@/lib/utils';

export type ReceiptFact = {
    // Stable d'une langue a l'autre : cle de rendu, et de quoi en ecarter un selon l'ecran.
    id: string;
    label: string;
    value: string;
    // Reference ou numero : police a chasse fixe, pour lire caractere par caractere.
    mono?: boolean;
};

export type ReceiptPreview = {
    url: string;
    name: string;
    // Ce qui designe la ligne cliquee sans ambiguite : dossier, telephone, montant, reference...
    // Deux inscrits voisins se ressemblent (meme tarif, meme compte), le nom seul ne suffit pas.
    facts: ReceiptFact[];
};

type Props = {
    receipt: ReceiptPreview | null;
    onOpenChange: (open: boolean) => void;
};

/**
 * Apercu d'un recu dans l'application, sans le telecharger, a cote de la fiche de la preuve pour
 * que le tresorier sache quelle ligne il regarde et compare le recu a ce qui est attendu. Le
 * telechargement reste propose pour qui veut garder le fichier ou le zoomer ailleurs.
 */
export function ReceiptPreviewDialog({ receipt, onOpenChange }: Props) {
    const { t } = useTranslation();
    const facts = receipt?.facts.filter((fact) => fact.value !== '') ?? [];

    return (
        <Dialog open={receipt !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('proofs.preview.title', {
                            name: receipt?.name ?? '',
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('proofs.preview.description')}
                    </DialogDescription>
                </DialogHeader>

                {receipt ? (
                    <div
                        className={cn(
                            'grid gap-5',
                            facts.length > 0 &&
                                'sm:grid-cols-[minmax(0,1fr)_minmax(0,16rem)]',
                        )}
                    >
                        <ReceiptImage
                            url={receipt.url}
                            alt={t('proofs.preview.alt', {
                                name: receipt.name,
                            })}
                            className="max-h-[65vh]"
                        />

                        {facts.length > 0 ? (
                            <dl
                                className="grid content-start gap-3 text-sm"
                                data-test="receipt-preview-facts"
                            >
                                {facts.map((fact) => (
                                    <div key={fact.id}>
                                        <dt className="text-muted-foreground text-xs">
                                            {fact.label}
                                        </dt>
                                        <dd
                                            className={cn(
                                                'font-medium break-words',
                                                fact.mono &&
                                                    'font-mono break-all',
                                            )}
                                        >
                                            {fact.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        ) : null}
                    </div>
                ) : null}

                <DialogFooter className="gap-2">
                    {receipt ? (
                        <Button variant="outline" asChild>
                            <a
                                href={receipt.url}
                                rel="noreferrer"
                                data-test="receipt-preview-download"
                            >
                                <Download />
                                {t('proofs.preview.download')}
                            </a>
                        </Button>
                    ) : null}
                    <DialogClose asChild>
                        <Button variant="secondary">
                            {t('common.actions.close')}
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
