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
import { RequiredFieldsNote } from '@/components/required-fields-note';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store as storeInvitation } from '@/routes/tenants/invitations';
import type { ProfileOption, Tenant } from '@/types';
import { useGettingStartedReturn } from '@/hooks/use-getting-started-return';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    tenant: Tenant;
    availableProfiles: ProfileOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function InviteMemberModal({
    tenant,
    availableProfiles,
    open,
    onOpenChange,
}: Props) {
    const { t } = useTranslation();
    const gettingStartedReturn = useGettingStartedReturn();
    const [inviteProfileId, setInviteProfileId] = useState<string>(
        String(
            availableProfiles.find((profile) => !profile.isSystem)?.id ?? '',
        ),
    );

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setInviteProfileId(
                String(
                    availableProfiles.find((profile) => !profile.isSystem)
                        ?.id ?? '',
                ),
            );
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...storeInvitation.form(tenant.slug, gettingStartedReturn)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {t('tenants.modals.invite.title')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('tenants.modals.invite.description')}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <RequiredFieldsNote />
                                <div className="grid gap-2">
                                    <Label htmlFor="email" required>
                                        {t('tenants.modals.invite.email_label')}
                                    </Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        data-test="invite-email"
                                        placeholder={t(
                                            'tenants.modals.invite.email_placeholder',
                                        )}
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="profile_id" required>
                                        {t('tenants.modals.invite.role_label')}
                                    </Label>
                                    <Select
                                        name="profile_id"
                                        data-test="invite-profile"
                                        value={inviteProfileId}
                                        onValueChange={setInviteProfileId}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue
                                                placeholder={t(
                                                    'tenants.modals.invite.role_placeholder',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableProfiles.map(
                                                (profile) => (
                                                    <SelectItem
                                                        key={profile.id}
                                                        value={String(
                                                            profile.id,
                                                        )}
                                                    >
                                                        {profile.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.profile_id} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        {t('common.actions.cancel')}
                                    </Button>
                                </DialogClose>

                                <SubmitButton
                                    data-test="invite-submit"
                                    processing={processing}
                                >
                                    {t('tenants.modals.invite.submit')}
                                </SubmitButton>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
