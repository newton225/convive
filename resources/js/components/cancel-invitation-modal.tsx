import { router } from '@inertiajs/react';
import { useState } from 'react';
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
import { destroy as destroyInvitation } from '@/routes/tenants/invitations';
import type { Tenant, TenantInvitation } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    tenant: Tenant;
    invitation: TenantInvitation | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function CancelInvitationModal({
    tenant,
    invitation,
    open,
    onOpenChange,
}: Props) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    const cancelInvitation = () => {
        if (!invitation) {
            return;
        }

        router.visit(destroyInvitation([tenant.slug, invitation.code]), {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('tenants.modals.cancel_invitation.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('tenants.modals.cancel_invitation.description', {
                            email: invitation?.email ?? '',
                        })}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">
                            {t('tenants.modals.cancel_invitation.keep')}
                        </Button>
                    </DialogClose>

                    <SubmitButton
                        type="button"
                        variant="destructive"
                        data-test="cancel-invitation-confirm"
                        processing={processing}
                        onClick={cancelInvitation}
                    >
                        {t('tenants.invitations.cancel')}
                    </SubmitButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
