import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmSummary } from '@/components/confirm-summary';
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
import { destroy as destroyMember } from '@/routes/tenants/members';
import type { Tenant, TenantMember } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    tenant: Tenant;
    member: TenantMember | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function RemoveMemberModal({
    tenant,
    member,
    open,
    onOpenChange,
}: Props) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    const removeMember = () => {
        if (!member) {
            return;
        }

        router.visit(destroyMember([tenant.slug, member.id]), {
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
                        {t('tenants.modals.remove_member.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('tenants.modals.remove_member.description', {
                            name: member?.name ?? '',
                        })}
                    </DialogDescription>
                </DialogHeader>

                {member ? (
                    <ConfirmSummary
                        testId="remove-member-summary"
                        items={[
                            {
                                label: t('tenants.confirm.name'),
                                value: member.name,
                                emphasis: true,
                            },
                            {
                                label: t('tenants.confirm.email'),
                                value: member.email,
                                mono: true,
                            },
                            {
                                label: t('tenants.confirm.profile'),
                                value: member.profileName,
                                hidden: member.profileName === null,
                            },
                        ]}
                    />
                ) : null}

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">
                            {t('common.actions.cancel')}
                        </Button>
                    </DialogClose>

                    <SubmitButton
                        type="button"
                        variant="destructive"
                        data-test="remove-member-confirm"
                        processing={processing}
                        onClick={removeMember}
                    >
                        {t('tenants.members.remove')}
                    </SubmitButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
