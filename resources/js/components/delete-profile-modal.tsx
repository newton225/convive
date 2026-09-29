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
import { useTranslation } from '@/hooks/use-translation';
import { destroy } from '@/routes/tenants/profiles';
import type { TenantProfile } from '@/types';

type Props = {
    tenantSlug: string;
    profile: TenantProfile | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteProfileModal({
    tenantSlug,
    profile,
    open,
    onOpenChange,
}: Props) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    const deleteProfile = () => {
        if (!profile) {
            return;
        }

        router.delete(destroy([tenantSlug, profile.id]).url, {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    // Un profil encore porte ne se supprime pas : la confirmation le dit avant l'action,
    // plutot que de laisser le serveur repondre par une erreur.
    const carried = (profile?.memberCount ?? 0) > 0;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('profiles.confirm_delete.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('profiles.confirm_delete.description', {
                            name: profile?.name ?? '',
                        })}
                    </DialogDescription>
                </DialogHeader>

                {profile ? (
                    <ConfirmSummary
                        testId="profile-delete-summary"
                        items={[
                            {
                                label: t('profiles.confirm.name'),
                                value: profile.name,
                                emphasis: true,
                            },
                            {
                                label: t('profiles.confirm.description'),
                                value: profile.description,
                                hidden: !profile.description,
                            },
                            {
                                label: t('profiles.confirm.permissions'),
                                value: t('profiles.confirm.permissions_count', {
                                    count: profile.permissions.length,
                                }),
                            },
                            {
                                label: t('profiles.confirm.members'),
                                value: profile.memberCount,
                                warning: carried,
                            },
                        ]}
                    />
                ) : null}

                <p className="text-muted-foreground text-sm">
                    {t('profiles.confirm_delete.members', {
                        count: profile?.memberCount ?? 0,
                    })}
                </p>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">
                            {t('common.actions.cancel')}
                        </Button>
                    </DialogClose>

                    <SubmitButton
                        type="button"
                        variant="destructive"
                        data-test="profile-delete-confirm"
                        processing={processing}
                        disabled={carried}
                        onClick={deleteProfile}
                    >
                        {t('profiles.actions.delete')}
                    </SubmitButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
