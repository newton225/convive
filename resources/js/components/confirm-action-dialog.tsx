import type { ReactNode } from 'react';
import { SubmitButton } from '@/components/submit-button';
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

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel: string;
    // Le bouton qui renonce, « Annuler » par defaut : a renommer quand l'action confirmee est elle-meme
    // un abandon, ou « Annuler » ne dirait plus ce qu'il annule.
    cancelLabel?: string;
    onConfirm: () => void;
    processing?: boolean;
    destructive?: boolean;
    // Garde le bouton d'action inactif tant qu'une condition n'est pas remplie (case cochee).
    confirmDisabled?: boolean;
    testId?: string;
    children?: ReactNode;
};

/**
 * Confirmation d'une action qui ne s'annule pas (CLAUDE.md, « Rien de destructif sans
 * confirmation », etendu aux validations irreversibles). La description rappelle la consequence
 * exacte ; `children` porte un recapitulatif (nom, montant) pour verifier qu'on agit sur la bonne
 * ligne. Le focus s'ouvre sur « Annuler » (premier bouton), jamais sur l'action : un double clic ou
 * un Entree trop rapide ne confirme rien.
 */
export function ConfirmActionDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    cancelLabel,
    onConfirm,
    processing = false,
    destructive = false,
    confirmDisabled = false,
    testId,
    children,
}: Props) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                {children}

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">
                            {cancelLabel ?? t('common.actions.cancel')}
                        </Button>
                    </DialogClose>

                    <SubmitButton
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        processing={processing}
                        disabled={confirmDisabled}
                        data-test={testId}
                        onClick={onConfirm}
                    >
                        {confirmLabel}
                    </SubmitButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
