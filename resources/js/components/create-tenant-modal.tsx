import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/tenants';
import { useTranslation } from '@/hooks/use-translation';

export default function CreateTenantModal({ children }: PropsWithChildren) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...store.form()}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {t('tenants.modals.create.title')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('tenants.modals.create.description')}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="name" required>
                                    {t('tenants.settings.name_label')}
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    data-test="create-tenant-name"
                                    placeholder={t(
                                        'tenants.settings.name_placeholder',
                                    )}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        {t('common.actions.cancel')}
                                    </Button>
                                </DialogClose>

                                <SubmitButton
                                    data-test="create-tenant-submit"
                                    processing={processing}
                                >
                                    {t('tenants.modals.create.submit')}
                                </SubmitButton>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
