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
import { leave as leaveTenantAction } from '@/routes/tenants';
import type { Tenant } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    tenant: Tenant | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function LeaveTenantModal({
    tenant,
    open,
    onOpenChange,
}: Props) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    const leaveTenant = () => {
        if (!tenant) {
            return;
        }

        router.visit(leaveTenantAction(tenant.slug), {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('tenants.modals.leave.title')}</DialogTitle>
                    <DialogDescription>
                        {t('tenants.modals.leave.description', {
                            name: tenant?.name ?? '',
                        })}
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
                        data-test="leave-tenant-confirm"
                        processing={processing}
                        onClick={leaveTenant}
                    >
                        {t('tenants.actions.leave')}
                    </SubmitButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
