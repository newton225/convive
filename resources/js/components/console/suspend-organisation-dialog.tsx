import { useForm } from '@inertiajs/react';
import { Pause } from 'lucide-react';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { suspend } from '@/routes/console/organisations';

type Props = {
    slug: string;
    name: string;
};

/**
 * Suspendre une organisation a la main (README section 3), avec un motif obligatoire. La fenetre
 * rappelle la consequence : back-office ferme, liens publics fermes aux nouvelles inscriptions,
 * billets deja emis toujours valides.
 */
export function SuspendOrganisationDialog({ slug, name }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(suspend(slug).url, {
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
                <Button
                    variant="outline"
                    size="sm"
                    data-test="console-organisation-suspend"
                >
                    <Pause />
                    {t('console.organisation.action_labels.suspend')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('console.organisation.dialogs.suspend_title', {
                                organisation: name,
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('console.organisation.dialogs.suspend')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="suspension-reason" required>
                            {t('console.organisation.fields.reason')}
                        </Label>
                        <Textarea
                            id="suspension-reason"
                            name="reason"
                            required
                            rows={4}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                        <InputError message={form.errors.reason} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>
                        <SubmitButton
                            variant="destructive"
                            processing={form.processing}
                            data-test="console-organisation-suspend-submit"
                        >
                            {t('console.organisation.action_labels.suspend')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
