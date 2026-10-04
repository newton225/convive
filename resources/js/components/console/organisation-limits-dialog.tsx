import { useForm } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
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
import { update } from '@/routes/console/organisations/limits';
import type { ConsoleOrganisationLimits, ConsoleQuotaName } from '@/types';

type Props = {
    slug: string;
    limits: ConsoleOrganisationLimits;
};

const Quotas: ConsoleQuotaName[] = [
    'max_active_events',
    'max_registrations',
    'max_members',
    'max_messages_per_month',
];

const asInput = (value: number | null) => (value === null ? '' : String(value));

/**
 * Regler les limites propres a une organisation (README section 3) : ce qu'un devis a negocie pour
 * elle seule. Un champ vide rend la main au plan, dont la limite est rappelee sous le champ.
 */
export function OrganisationLimitsDialog({ slug, limits }: Props) {
    const { t, locale } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({
        max_active_events: asInput(limits.max_active_events.own),
        max_registrations: asInput(limits.max_registrations.own),
        max_members: asInput(limits.max_members.own),
        max_messages_per_month: asInput(limits.max_messages_per_month.own),
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        // Vide : aucune valeur, pas une chaine vide que le serveur lirait comme un nombre.
        form.transform((data) => ({
            max_active_events: data.max_active_events || null,
            max_registrations: data.max_registrations || null,
            max_members: data.max_members || null,
            max_messages_per_month: data.max_messages_per_month || null,
        }));

        form.put(update(slug).url, {
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
                    data-test="console-organisation-limits"
                >
                    <SlidersHorizontal />
                    {t('console.organisation.action_labels.limits')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('console.organisation.action_labels.limits')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('console.organisation.dialogs.limits')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid items-start gap-4 sm:grid-cols-2">
                        {Quotas.map((quota) => {
                            const planLimit = limits[quota].plan;

                            return (
                                <div key={quota} className="grid gap-2">
                                    <Label htmlFor={`limit-${quota}`}>
                                        {t(`console.plans.fields.${quota}`)}
                                    </Label>
                                    <Input
                                        id={`limit-${quota}`}
                                        name={quota}
                                        type="number"
                                        inputMode="numeric"
                                        min={1}
                                        step={1}
                                        value={form.data[quota]}
                                        onChange={(event) =>
                                            form.setData(
                                                quota,
                                                event.target.value,
                                            )
                                        }
                                        data-test={`console-organisation-limit-${quota}`}
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        {planLimit === null
                                            ? t(
                                                  'console.organisation.limits.plan_unlimited',
                                              )
                                            : t(
                                                  'console.organisation.limits.plan_value',
                                                  {
                                                      max: new Intl.NumberFormat(
                                                          locale,
                                                      ).format(planLimit),
                                                  },
                                              )}
                                    </p>
                                    <InputError message={form.errors[quota]} />
                                </div>
                            );
                        })}
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>
                        <SubmitButton
                            processing={form.processing}
                            data-test="console-organisation-limits-submit"
                        >
                            {t('common.actions.save')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
