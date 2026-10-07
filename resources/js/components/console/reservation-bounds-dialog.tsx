import { useForm } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { RequiredFieldsNote } from '@/components/required-fields-note';
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
import { update } from '@/routes/console/security/reservation-bounds';

type Props = {
    min: number;
    max: number;
};

/**
 * Regler les bornes de la duree de reservation proposee aux organisateurs (decision du 2026-10-07),
 * en minutes. Le serveur verifie que le maximum ne passe pas sous le minimum.
 */
export function ReservationBoundsDialog({ min, max }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({ min: String(min), max: String(max) });

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
                    data-test="console-reservation-bounds-edit"
                >
                    <Settings2 />
                    {t('console.plans.edit')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('console.security.reservation_bounds.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('console.security.reservation_bounds.dialog')}
                        </DialogDescription>
                    </DialogHeader>

                    <RequiredFieldsNote />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="reservation-min" required>
                                {t('console.security.reservation_bounds.min')}
                            </Label>
                            <Input
                                id="reservation-min"
                                type="number"
                                min={1}
                                max={1440}
                                required
                                value={form.data.min}
                                onChange={(event) =>
                                    form.setData('min', event.target.value)
                                }
                            />
                            <InputError message={form.errors.min} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reservation-max" required>
                                {t('console.security.reservation_bounds.max')}
                            </Label>
                            <Input
                                id="reservation-max"
                                type="number"
                                min={1}
                                max={1440}
                                required
                                value={form.data.max}
                                onChange={(event) =>
                                    form.setData('max', event.target.value)
                                }
                            />
                            <InputError message={form.errors.max} />
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>
                        <SubmitButton
                            processing={form.processing}
                            data-test="console-reservation-bounds-save"
                        >
                            {t('common.actions.save')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
