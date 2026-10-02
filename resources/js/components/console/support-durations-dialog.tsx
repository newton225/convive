import { useForm } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
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
import { update } from '@/routes/console/security/support-durations';

type Props = {
    durations: number[];
};

/**
 * Regler les durees qu'une organisation peut choisir pour un acces de support (README ecran 25) :
 * des heures, separees par des virgules. Le serveur les valide et les range.
 */
export function SupportDurationsDialog({ durations }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({ durations: durations.join(', ') });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.put(update().url, {
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
                    data-test="console-support-durations-edit"
                >
                    <Settings2 />
                    {t('console.plans.edit')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('console.security.support_durations.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('console.security.support_durations.dialog')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="support-durations">
                            {t('console.security.support_durations.field')}
                        </Label>
                        <Input
                            id="support-durations"
                            name="durations"
                            inputMode="numeric"
                            required
                            value={form.data.durations}
                            onChange={(event) =>
                                form.setData('durations', event.target.value)
                            }
                            data-test="console-support-durations-input"
                        />
                        <p className="text-muted-foreground text-xs">
                            {t('console.security.support_durations.field_hint')}
                        </p>
                        <InputError message={form.errors.durations} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>
                        <SubmitButton
                            processing={form.processing}
                            data-test="console-support-durations-save"
                        >
                            {t('common.actions.save')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
