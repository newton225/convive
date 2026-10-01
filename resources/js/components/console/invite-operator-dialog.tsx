import { useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { store } from '@/routes/console/team';
import type { ConsoleOperatorProfile } from '@/types';

type Props = {
    profiles: ConsoleOperatorProfile[];
};

/**
 * Inviter une personne dans l'equipe editeur (README ecran 34) : son adresse et son profil. Le
 * profil propose par defaut est le plus etroit ; le choisir plus large est un geste voulu.
 */
export function InviteOperatorDialog({ profiles }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm<{ email: string; profile: ConsoleOperatorProfile }>({
        email: '',
        profile: 'support',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" data-test="console-team-invite">
                    <UserPlus />
                    {t('console.team.invite')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('console.team.invite_dialog.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('console.team.invite_dialog.description')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="operator-email">
                            {t('console.team.invite_dialog.email')}
                        </Label>
                        <Input
                            id="operator-email"
                            name="email"
                            type="email"
                            autoComplete="off"
                            required
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="operator-profile">
                            {t('console.team.invite_dialog.profile')}
                        </Label>
                        <Select
                            value={form.data.profile}
                            onValueChange={(value) => {
                                const profile = profiles.find(
                                    (item) => item === value,
                                );

                                if (profile) {
                                    form.setData('profile', profile);
                                }
                            }}
                        >
                            <SelectTrigger id="operator-profile">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {profiles.map((profile) => (
                                    <SelectItem key={profile} value={profile}>
                                        {t(`console.team.profiles.${profile}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-xs">
                            {t(
                                `console.team.profile_descriptions.${form.data.profile}`,
                            )}
                        </p>
                        <InputError message={form.errors.profile} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>
                        <SubmitButton
                            processing={form.processing}
                            data-test="console-team-invite-submit"
                        >
                            {t('console.team.invite_dialog.submit')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
