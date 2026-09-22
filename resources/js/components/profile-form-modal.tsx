import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useTranslation } from '@/hooks/use-translation';
import { store, update } from '@/routes/tenants/profiles';
import type { PermissionDomain, TenantProfile } from '@/types';

type Props = {
    tenantSlug: string;
    profile: TenantProfile | null;
    catalogue: PermissionDomain[];
    heldPermissions: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function ProfileFormModal({
    tenantSlug,
    profile,
    catalogue,
    heldPermissions,
    open,
    onOpenChange,
}: Props) {
    const { t } = useTranslation();

    const action = profile
        ? update.form([tenantSlug, profile.id])
        : store.form(tenantSlug);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <Form
                    key={`${String(open)}-${profile?.id ?? 'new'}`}
                    {...action}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {profile
                                        ? t('profiles.actions.edit')
                                        : t('profiles.actions.create')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('profiles.description')}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="profile-name">
                                        {t('profiles.fields.name')}
                                    </Label>
                                    <Input
                                        id="profile-name"
                                        name="name"
                                        data-test="profile-name"
                                        defaultValue={profile?.name ?? ''}
                                        placeholder={t(
                                            'profiles.fields.name_placeholder',
                                        )}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="profile-description">
                                        {t('profiles.fields.description')}
                                    </Label>
                                    <Input
                                        id="profile-description"
                                        name="description"
                                        data-test="profile-description"
                                        defaultValue={
                                            profile?.description ?? ''
                                        }
                                        placeholder={t(
                                            'profiles.fields.description_placeholder',
                                        )}
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <div className="grid gap-2">
                                    <label className="flex items-start gap-2 text-sm">
                                        <Checkbox
                                            name="requires_two_factor"
                                            value="1"
                                            data-test="profile-requires-two-factor"
                                            defaultChecked={
                                                profile?.requiresTwoFactor
                                            }
                                        />
                                        <span>
                                            {t(
                                                'profiles.fields.requires_two_factor',
                                            )}
                                        </span>
                                    </label>
                                    <p className="text-muted-foreground text-xs">
                                        {t(
                                            'profiles.fields.requires_two_factor_hint',
                                        )}
                                    </p>
                                    <InputError
                                        message={errors.requires_two_factor}
                                    />
                                </div>
                            </div>

                            <fieldset className="space-y-4">
                                <legend className="text-sm font-medium">
                                    {t('profiles.fields.permissions')}
                                </legend>

                                {catalogue.map((domain) => (
                                    <div
                                        key={domain.value}
                                        className="space-y-2"
                                    >
                                        <p className="text-muted-foreground text-xs uppercase">
                                            {domain.label}
                                        </p>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {domain.permissions.map(
                                                (permission) => {
                                                    // Une permission que l'acteur ne detient
                                                    // pas ne peut pas etre accordee : le
                                                    // serveur la refuse, l'interface la grise.
                                                    const held =
                                                        heldPermissions.includes(
                                                            permission.value,
                                                        );

                                                    return (
                                                        <label
                                                            key={
                                                                permission.value
                                                            }
                                                            className="flex items-start gap-2 text-sm"
                                                        >
                                                            <Checkbox
                                                                name="permissions[]"
                                                                value={
                                                                    permission.value
                                                                }
                                                                data-test="profile-permission"
                                                                disabled={!held}
                                                                defaultChecked={profile?.permissions.includes(
                                                                    permission.value,
                                                                )}
                                                            />
                                                            <span
                                                                className={
                                                                    held
                                                                        ? undefined
                                                                        : 'text-muted-foreground'
                                                                }
                                                            >
                                                                {
                                                                    permission.label
                                                                }
                                                            </span>
                                                        </label>
                                                    );
                                                },
                                            )}
                                        </div>
                                    </div>
                                ))}

                                <InputError message={errors.permissions} />
                            </fieldset>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        {t('common.actions.cancel')}
                                    </Button>
                                </DialogClose>

                                <SubmitButton
                                    data-test="profile-submit"
                                    processing={processing}
                                >
                                    {t('common.actions.save')}
                                </SubmitButton>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
