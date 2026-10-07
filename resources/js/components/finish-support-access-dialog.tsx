import { useForm } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
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
import { finish } from '@/routes/console/support-access';

type Props = {
    grantId: number;
    organisation: string;
};

/**
 * « J'ai termine » : la personne de l'equipe Convive ferme l'acces de support qu'une organisation
 * lui a ouvert, avec une note de ce qu'elle a constate (README section 3). Le geste ne s'annule
 * pas, il faudrait que l'organisation rouvre un acces : la fenetre le dit.
 */
export function FinishSupportAccessDialog({ grantId, organisation }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({ note: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(finish(grantId).url, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    data-test="support-access-finish"
                >
                    <CircleCheck />
                    {t('support_access.finish.button')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('support_access.finish.title', { organisation })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('support_access.finish.description')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor={`finish-note-${grantId}`} required>
                            {t('support_access.finish.note')}
                        </Label>
                        <Textarea
                            id={`finish-note-${grantId}`}
                            name="note"
                            required
                            rows={4}
                            maxLength={1000}
                            value={form.data.note}
                            onChange={(event) =>
                                form.setData('note', event.target.value)
                            }
                        />
                        <p className="text-muted-foreground text-xs">
                            {t('support_access.finish.note_hint')}
                        </p>
                        <InputError message={form.errors.note} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>
                        <SubmitButton
                            processing={form.processing}
                            data-test="support-access-finish-submit"
                        >
                            {t('support_access.finish.submit')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
