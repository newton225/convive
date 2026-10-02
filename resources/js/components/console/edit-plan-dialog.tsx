import { useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
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
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/console/plans';
import type { ConsolePlan } from '@/types';

type Props = {
    plan: ConsolePlan;
};

type Field =
    | 'monthly_price'
    | 'monthly_price_eur'
    | 'monthly_price_usd'
    | 'max_active_events'
    | 'max_registrations'
    | 'max_members'
    | 'max_messages_per_month';

const PriceFields: { name: Field; step: string }[] = [
    { name: 'monthly_price', step: '1' },
    { name: 'monthly_price_eur', step: '0.01' },
    { name: 'monthly_price_usd', step: '0.01' },
];

const QuotaFields: Field[] = [
    'max_active_events',
    'max_registrations',
    'max_members',
    'max_messages_per_month',
];

// Un nombre stocke redevient ce qui se saisit ; un champ vide veut dire « sur devis » ou
// « illimite », le serveur en decide (`UpdatePlanRequest`).
const asInput = (value: number | null, divisor = 1) =>
    value === null ? '' : String(value / divisor);

/**
 * Modifier les prix et les quotas d'un plan (README ecran 30). L'euro et le dollar se saisissent
 * en unites ; le serveur les range en centimes. Rien n'est calcule ici.
 */
export function EditPlanDialog({ plan }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({
        monthly_price: asInput(plan.monthlyPrice),
        monthly_price_eur: asInput(plan.monthlyPriceEur, 100),
        monthly_price_usd: asInput(plan.monthlyPriceUsd, 100),
        max_active_events: asInput(plan.maxActiveEvents),
        max_registrations: asInput(plan.maxRegistrations),
        max_members: asInput(plan.maxMembers),
        max_messages_per_month: asInput(plan.maxMessagesPerMonth),
        has_reconciliation: plan.hasReconciliation,
        has_reports: plan.hasReports,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            // Vide : aucune valeur, pas une chaine vide que le serveur lirait comme un nombre.
            ...Object.fromEntries(
                [...PriceFields.map((field) => field.name), ...QuotaFields].map(
                    (name) => [name, data[name] === '' ? null : data[name]],
                ),
            ),
        }));

        form.patch(update(plan.code).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    const numberField = (name: Field, step: string, min: string) => (
        <div key={name} className="grid gap-2">
            <Label htmlFor={`plan-${plan.code}-${name}`}>
                {t(`console.plans.fields.${name}`)}
            </Label>
            <Input
                id={`plan-${plan.code}-${name}`}
                name={name}
                type="number"
                inputMode="decimal"
                min={min}
                step={step}
                value={form.data[name]}
                onChange={(event) => form.setData(name, event.target.value)}
            />
            <InputError message={form.errors[name]} />
        </div>
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    data-test={`console-plan-edit-${plan.code}`}
                >
                    <Pencil />
                    {t('console.plans.edit')}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <form onSubmit={submit} className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>
                            {t('console.plans.edit_dialog.title', {
                                plan: plan.name,
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('console.plans.edit_dialog.description')}
                        </DialogDescription>
                    </DialogHeader>

                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">
                            {t('console.plans.edit_dialog.prices')}
                        </legend>
                        <div className="grid grid-cols-[repeat(auto-fit,minmax(9rem,1fr))] gap-3">
                            {PriceFields.map((field) =>
                                numberField(field.name, field.step, '0'),
                            )}
                        </div>
                        <p className="text-muted-foreground text-xs">
                            {t('console.plans.edit_dialog.prices_hint')}
                        </p>
                    </fieldset>

                    <fieldset className="space-y-3">
                        <legend className="text-sm font-medium">
                            {t('console.plans.edit_dialog.quotas')}
                        </legend>
                        <div className="grid grid-cols-[repeat(auto-fit,minmax(9rem,1fr))] gap-3">
                            {QuotaFields.map((name) =>
                                numberField(name, '1', '1'),
                            )}
                        </div>
                        {/* Ce que chaque limite compte, en toutes lettres : les trois mots seuls ne
                            le disent pas. */}
                        <ul className="text-muted-foreground list-disc space-y-1 pl-5 text-xs">
                            {QuotaFields.map((name) => (
                                <li key={name}>
                                    {t(
                                        `console.plans.edit_dialog.quota_help.${name}`,
                                    )}
                                </li>
                            ))}
                        </ul>
                        <p className="text-muted-foreground text-xs">
                            {t('console.plans.edit_dialog.quotas_hint')}
                        </p>
                    </fieldset>

                    <fieldset>
                        <legend className="text-sm font-medium">
                            {t('console.plans.edit_dialog.features')}
                        </legend>
                        <p className="text-muted-foreground text-xs">
                            {t('console.plans.edit_dialog.features_hint')}
                        </p>
                        <CheckboxRow
                            id={`plan-${plan.code}-reconciliation`}
                            label={t('console.plans.features.reconciliation')}
                            checked={form.data.has_reconciliation}
                            onChange={(checked) =>
                                form.setData('has_reconciliation', checked)
                            }
                        />
                        <CheckboxRow
                            id={`plan-${plan.code}-reports`}
                            label={t('console.plans.features.reports')}
                            checked={form.data.has_reports}
                            onChange={(checked) =>
                                form.setData('has_reports', checked)
                            }
                        />
                    </fieldset>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>
                        <SubmitButton
                            processing={form.processing}
                            data-test="console-plan-save"
                        >
                            {t('common.actions.save')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
