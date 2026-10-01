import { useForm } from '@inertiajs/react';
import { EyeOff } from 'lucide-react';
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
import { withdraw } from '@/routes/console/showcase';

type Props = {
    id: number;
    eventName: string;
};

/**
 * Retirer une annonce de la vitrine (README section 3 et ecran 32). Le motif est obligatoire et
 * transmis a l'organisation : la fenetre le dit, pour qu'il soit ecrit en consequence.
 */
export function WithdrawAnnouncementDialog({ id, eventName }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(withdraw(id).url, {
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
                    data-test="console-showcase-withdraw"
                >
                    <EyeOff />
                    {t('console.showcase.withdraw')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('console.showcase.withdraw_dialog.title', {
                                event: eventName,
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('console.showcase.withdraw_dialog.description')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor={`withdraw-reason-${id}`}>
                            {t('console.showcase.fields.reason')}
                        </Label>
                        <Textarea
                            id={`withdraw-reason-${id}`}
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
                            data-test="console-showcase-withdraw-submit"
                        >
                            {t('console.showcase.withdraw_dialog.submit')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
