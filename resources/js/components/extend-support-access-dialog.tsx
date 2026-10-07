import { useForm } from '@inertiajs/react';
import { TimerReset } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/tenants/support-access';

type Props = {
    tenantSlug: string;
    grantId: number;
    operator: string;
    durations: number[];
    maxHours: number;
};

/**
 * Prolonger l'acces de support en cours (README ecran 25), sans le fermer ni le rouvrir. Le serveur
 * plafonne : l'acces n'a jamais plus que la duree la plus longue devant lui.
 */
export function ExtendSupportAccessDialog({
    tenantSlug,
    grantId,
    operator,
    durations,
    maxHours,
}: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({ duration: String(durations[0]) });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.patch(update([tenantSlug, grantId]).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" data-test="support-access-extend">
                    <TimerReset />
                    {t('support_access.extend.button')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('support_access.extend.title', { operator })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('support_access.extend.description', {
                                count: maxHours,
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="support-extension" required>
                            {t('support_access.extend.duration')}
                        </Label>
                        <Select
                            value={form.data.duration}
                            onValueChange={(value) =>
                                form.setData('duration', value)
                            }
                        >
                            <SelectTrigger id="support-extension">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {durations.map((hours) => (
                                    <SelectItem
                                        key={hours}
                                        value={String(hours)}
                                    >
                                        {t('support_access.grant.hours', {
                                            count: hours,
                                        })}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.duration} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>
                        <SubmitButton
                            processing={form.processing}
                            data-test="support-access-extend-submit"
                        >
                            {t('support_access.extend.button')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
