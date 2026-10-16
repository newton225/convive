import { router } from '@inertiajs/react';
import { useState } from 'react';
import TenantInvitationController from '@/actions/App/Http/Controllers/Tenants/TenantInvitationController';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { SubmitButton } from '@/components/submit-button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { DashboardInvitation } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    invitations: DashboardInvitation[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function PendingInvitationsModal({
    invitations,
    open,
    onOpenChange,
}: Props) {
    const { t } = useTranslation();
    const [declining, setDeclining] = useState<DashboardInvitation | null>(
        null,
    );
    const [processing, setProcessing] = useState<{
        code: string;
        action: 'accept' | 'decline';
    } | null>(null);

    const acceptInvitation = (invitation: DashboardInvitation) => {
        router.visit(TenantInvitationController.accept(invitation), {
            onStart: () =>
                setProcessing({ code: invitation.code, action: 'accept' }),
            onFinish: () => setProcessing(null),
        });
    };

    const declineInvitation = (invitation: DashboardInvitation) => {
        router.visit(TenantInvitationController.decline(invitation), {
            onStart: () =>
                setProcessing({ code: invitation.code, action: 'decline' }),
            onFinish: () => {
                setProcessing(null);
                setDeclining(null);
            },
            onSuccess: () => {
                if (invitations.length === 1) {
                    onOpenChange(false);
                }
            },
        });
    };

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent data-test="pending-invitations-modal">
                    <DialogHeader>
                        <DialogTitle>
                            {t('tenants.modals.pending.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('tenants.modals.pending.description')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        {invitations.map((invitation) => (
                            <div
                                key={invitation.code}
                                data-test="pending-invitation-row"
                                className="rounded-lg border p-4"
                            >
                                <div className="space-y-1">
                                    <p className="font-medium">
                                        {invitation.tenant.name}
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        {t(
                                            'tenants.modals.pending.invited_by',
                                            {
                                                inviter: invitation.inviterName,
                                            },
                                        )}
                                    </p>
                                </div>

                                <div className="mt-4 flex justify-end gap-2">
                                    <SubmitButton
                                        type="button"
                                        variant="secondary"
                                        data-test="pending-invitation-decline"
                                        processing={
                                            processing?.code ===
                                                invitation.code &&
                                            processing.action === 'decline'
                                        }
                                        disabled={
                                            processing?.code === invitation.code
                                        }
                                        onClick={() => setDeclining(invitation)}
                                    >
                                        {t('common.actions.decline')}
                                    </SubmitButton>

                                    <SubmitButton
                                        type="button"
                                        data-test="pending-invitation-accept"
                                        processing={
                                            processing?.code ===
                                                invitation.code &&
                                            processing.action === 'accept'
                                        }
                                        disabled={
                                            processing?.code === invitation.code
                                        }
                                        onClick={() =>
                                            acceptInvitation(invitation)
                                        }
                                    >
                                        {t('common.actions.accept')}
                                    </SubmitButton>
                                </div>
                            </div>
                        ))}
                    </div>
                </DialogContent>
            </Dialog>

            <ConfirmActionDialog
                open={declining !== null}
                onOpenChange={(next) => !next && setDeclining(null)}
                title={t('tenants.invitations_home.confirm_decline.title')}
                description={t(
                    'tenants.invitations_home.confirm_decline.description',
                    { tenant: declining?.tenant.name ?? '' },
                )}
                confirmLabel={t('tenants.invitations_home.decline')}
                destructive
                testId="pending-invitation-decline-confirm"
                processing={processing?.action === 'decline'}
                onConfirm={() => declining && declineInvitation(declining)}
            />
        </>
    );
}
