import { router } from '@inertiajs/react';
import { useState } from 'react';
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

                    <Button
                        variant="destructive"
                        data-test="profile-delete-confirm"
                        disabled={processing || carried}
                        onClick={deleteProfile}
                    >
                        {t('profiles.actions.delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
