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
import type { TenantProfile } from '@/types';

type Props = {
    profile: TenantProfile | null;
    onOpenChange: (open: boolean) => void;
};

/**
 * Les membres de l'organisation qui portent un profil : de quoi savoir qui sera touche avant de
 * modifier ou de supprimer ce profil.
 */
export function ProfileMembersDialog({ profile, onOpenChange }: Props) {
    const { t } = useTranslation();
    const members = profile?.members ?? [];

    return (
        <Dialog open={profile !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {t('profiles.members_dialog.title', {
                            name: profile?.name ?? '',
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('profiles.badges.members', {
                            count: members.length,
                        })}
                    </DialogDescription>
                </DialogHeader>

                {members.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        {t('profiles.members_dialog.empty')}
                    </p>
                ) : (
                    <ul
                        className="max-h-[60vh] divide-y overflow-y-auto"
                        data-test="profile-members-list"
                    >
                        {members.map((member) => (
                            <li
                                key={member.id}
                                className="flex min-h-11 flex-col justify-center py-2"
                                data-test="profile-member"
                            >
                                <span className="font-medium">
                                    {member.name}
                                </span>
                                <span className="text-muted-foreground text-sm break-all">
                                    {member.email}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary">
                            {t('common.actions.close')}
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
