import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy } from '@/routes/tenants';
import type { Tenant } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    tenant: Tenant;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteTenantModal({
    tenant,
    open,
    onOpenChange,
}: Props) {
    const { t } = useTranslation();
    const [confirmationName, setConfirmationName] = useState('');

    const canDeleteTenant = confirmationName === tenant.name;

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setConfirmationName('');
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(tenant.slug)}
                    className="space-y-6"
                    onSuccess={() => handleOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {t('tenants.modals.delete.title')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('tenants.modals.delete.description', {
                                        name: tenant.name,
                                    })}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-4 py-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="confirmation-name">
                                        {t(
                                            'tenants.modals.delete.confirmation_label',
                                            { name: tenant.name },
                                        )}
                                    </Label>
                                    <Input
                                        id="confirmation-name"
                                        name="name"
                                        data-test="delete-tenant-name"
                                        value={confirmationName}
                                        onChange={(event) =>
                                            setConfirmationName(
                                                event.target.value,
                                            )
                                        }
                                        placeholder={t(
                                            'tenants.settings.name_label',
                                        )}
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        {t('common.actions.cancel')}
                                    </Button>
                                </DialogClose>

                                <SubmitButton
                                    variant="destructive"
                                    data-test="delete-tenant-confirm"
                                    processing={processing}
                                    disabled={!canDeleteTenant}
                                >
                                    {t('tenants.actions.delete')}
                                </SubmitButton>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
