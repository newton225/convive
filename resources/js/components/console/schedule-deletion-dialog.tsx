import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
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
import { schedule } from '@/routes/console/organisations/deletion';

type Props = {
    slug: string;
    name: string;
};

/**
 * Programmer la suppression d'une organisation (README section 3) : uniquement a sa demande
 * ecrite, dont on donne la reference. Rien n'est efface ici, et la suppression reste annulable
 * trente jours ; le nom se retape pour ne pas viser la mauvaise fiche.
 */
export function ScheduleDeletionDialog({ slug, name }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({ request_reference: '', confirmation: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(schedule(slug).url, {
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
                    variant="destructive"
                    size="sm"
                    data-test="console-organisation-schedule-deletion"
                >
                    <Trash2 />
                    {t('console.organisation.action_labels.schedule_deletion')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('console.organisation.dialogs.deletion_title', {
                                organisation: name,
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('console.organisation.dialogs.deletion')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="deletion-request-reference">
                            {t('console.organisation.fields.request_reference')}
                        </Label>
                        <Input
                            id="deletion-request-reference"
                            name="request_reference"
                            required
                            value={form.data.request_reference}
                            onChange={(event) =>
                                form.setData(
                                    'request_reference',
                                    event.target.value,
                                )
                            }
                        />
                        <p className="text-muted-foreground text-xs">
                            {t(
                                'console.organisation.fields.request_reference_hint',
                            )}
                        </p>
                        <InputError message={form.errors.request_reference} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="deletion-confirmation">
                            {t(
                                'console.organisation.fields.confirmation_named',
                                {
                                    organisation: name,
                                },
                            )}
                        </Label>
                        <Input
                            id="deletion-confirmation"
                            name="confirmation"
                            required
                            autoComplete="off"
                            value={form.data.confirmation}
                            onChange={(event) =>
                                form.setData('confirmation', event.target.value)
                            }
                        />
                        <InputError message={form.errors.confirmation} />
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
                            data-test="console-organisation-schedule-deletion-submit"
                        >
                            {t(
                                'console.organisation.action_labels.schedule_deletion',
                            )}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
