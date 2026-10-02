import { useForm } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { CheckboxRow } from '@/components/settings/checkbox-row';
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
import { update } from '@/routes/console/trial';
import type { ConsolePlan, ConsoleTrialSettings } from '@/types';

type Props = {
    trial: ConsoleTrialSettings;
    plans: ConsolePlan[];
};

/**
 * Regler la periode d'essai offerte aux organisations neuves (README section 3) : ouverte ou non,
 * sa duree, le plan dont elles profitent. Une duree vide veut dire un essai sans date de fin.
 */
export function TrialSettingsDialog({ trial, plans }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({
        enabled: trial.enabled,
        days: trial.days === null ? '' : String(trial.days),
        plan: trial.plan,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        // Vide : pas de duree, donc un essai sans fin.
        form.transform((data) => ({
            ...data,
            days: data.days === '' ? null : data.days,
        }));

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
                    data-test="console-trial-edit"
                >
                    <Settings2 />
                    {t('console.plans.edit')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{t('console.trial.title')}</DialogTitle>
                        <DialogDescription>
                            {t('console.trial.dialog')}
                        </DialogDescription>
                    </DialogHeader>

                    <CheckboxRow
                        id="trial-enabled"
                        label={t('console.trial.fields.enabled')}
                        checked={form.data.enabled}
                        onChange={(checked) => form.setData('enabled', checked)}
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="trial-days">
                            {t('console.trial.fields.days')}
                        </Label>
                        <Input
                            id="trial-days"
                            name="days"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            step={1}
                            value={form.data.days}
                            disabled={!form.data.enabled}
                            onChange={(event) =>
                                form.setData('days', event.target.value)
                            }
                            data-test="console-trial-days"
                        />
                        <p className="text-muted-foreground text-xs">
                            {t('console.trial.fields.days_hint')}
                        </p>
                        <InputError message={form.errors.days} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="trial-plan">
                            {t('console.trial.fields.plan')}
                        </Label>
                        <Select
                            value={form.data.plan}
                            disabled={!form.data.enabled}
                            onValueChange={(value) =>
                                form.setData('plan', value)
                            }
                        >
                            <SelectTrigger id="trial-plan">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {plans.map((plan) => (
                                    <SelectItem
                                        key={plan.code}
                                        value={plan.code}
                                    >
                                        {plan.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.plan} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>
                        <SubmitButton
                            processing={form.processing}
                            data-test="console-trial-save"
                        >
                            {t('common.actions.save')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
