import { router } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useState } from 'react';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { rotateKey } from '@/routes/tenants/events/scan';

type Props = {
    tenantSlug: string;
    eventId: number;
    keyVersion: number;
};

/**
 * Rotation de la cle des billets (SECURITY.md C2), a faire quand un appareil d'agent est perdu.
 * La confirmation rappelle la consequence exacte : tous les QR deja envoyes cessent d'ouvrir la
 * porte, et chaque invite doit rouvrir son billet pour obtenir le nouveau.
 */
export function RotateTicketKeyCard({
    tenantSlug,
    eventId,
    keyVersion,
}: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const rotate = () => {
        router.post(
            rotateKey([tenantSlug, eventId]).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setOpen(false),
            },
        );
    };

    return (
        <Card data-test="scan-rotate-key">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <KeyRound className="size-4" />
                    {t('scan.rotate_key.title')}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <p className="text-muted-foreground text-sm">
                    {t('scan.rotate_key.body', { version: String(keyVersion) })}
                </p>

                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button variant="outline" size="sm">
                            {t('scan.rotate_key.button')}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {t('scan.rotate_key.confirm_title')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('scan.rotate_key.confirm_body')}
                            </DialogDescription>
                        </DialogHeader>

                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">
                                    {t('common.actions.cancel')}
                                </Button>
                            </DialogClose>

                            <SubmitButton
                                type="button"
                                variant="destructive"
                                data-test="scan-rotate-key-confirm"
                                processing={processing}
                                onClick={rotate}
                            >
                                {t('scan.rotate_key.confirm')}
                            </SubmitButton>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    );
}
