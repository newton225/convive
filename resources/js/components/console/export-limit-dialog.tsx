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
import { update } from '@/routes/console/security/export-limit';

type Props = {
    exportsPerHour: number;
};

/**
 * Regler le nombre d'exports qu'une meme personne peut lancer par heure (SECURITY.md M3). Le
 * serveur refuse une valeur vide : la limite se regle, elle ne se retire pas.
 */
export function ExportLimitDialog({ exportsPerHour }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({ exports_per_hour: String(exportsPerHour) });

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
                    data-test="console-export-limit-edit"
                >
                    <Settings2 />
                    {t('console.plans.edit')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('console.security.export_limit.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('console.security.export_limit.dialog')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="exports-per-hour" required>
                            {t('console.security.export_limit.field')}
                        </Label>
                        <Input
                            id="exports-per-hour"
                            name="exports_per_hour"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            max={1000}
                            step={1}
                            required
                            value={form.data.exports_per_hour}
                            onChange={(event) =>
                                form.setData(
                                    'exports_per_hour',
                                    event.target.value,
                                )
                            }
                            data-test="console-export-limit-input"
                        />
                        <InputError message={form.errors.exports_per_hour} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>
                        <SubmitButton
                            processing={form.processing}
                            data-test="console-export-limit-save"
                        >
                            {t('common.actions.save')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
