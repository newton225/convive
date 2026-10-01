import { useForm } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
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
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/console/organisations/trial';

type Props = {
    slug: string;
    // Vrai quand l'organisation est deja a l'essai : on le prolonge, sinon on l'offre.
    onTrial: boolean;
    // La date de fin actuelle (ISO), ou null pour un essai sans fin.
    endsAt: string | null;
};

/**
 * Offrir ou prolonger l'essai d'une organisation (README section 3). La date de fin est
 * facultative : sans date, l'essai n'a pas de fin.
 */
export function ExtendTrialDialog({ slug, onTrial, endsAt }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({ ends_at: endsAt ? endsAt.slice(0, 10) : '' });
    const label = t(
        onTrial
            ? 'console.organisation.action_labels.extend_trial'
            : 'console.organisation.action_labels.offer_trial',
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        // Vide : pas de date, donc un essai sans fin.
        form.transform((data) => ({
            ends_at: data.ends_at === '' ? null : data.ends_at,
        }));

        form.patch(update(slug).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    data-test="console-organisation-trial"
                >
                    <CalendarPlus />
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{label}</DialogTitle>
                        <DialogDescription>
                            {t('console.organisation.dialogs.trial')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="trial-ends-at">
                            {t('console.organisation.fields.trial_ends_at')}
                        </Label>
                        <Input
                            id="trial-ends-at"
                            name="ends_at"
                            type="date"
                            value={form.data.ends_at}
                            onChange={(event) =>
                                form.setData('ends_at', event.target.value)
                            }
                        />
                        <p className="text-muted-foreground text-xs">
                            {t(
                                'console.organisation.fields.trial_ends_at_hint',
                            )}
                        </p>
                        <InputError message={form.errors.ends_at} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>
                        <SubmitButton
                            processing={form.processing}
                            data-test="console-organisation-trial-submit"
                        >
                            {t('common.actions.save')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
