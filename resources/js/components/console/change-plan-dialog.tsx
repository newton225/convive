import { useForm } from '@inertiajs/react';
import { Package } from 'lucide-react';
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
import { update } from '@/routes/console/organisations/plan';
import type { ConsolePlanOption } from '@/types';

type Props = {
    slug: string;
    currentPlan: string;
    plans: ConsolePlanOption[];
};

/**
 * Changer le plan d'une organisation (README section 3). Le serveur decide si le changement est
 * possible (`ManageOrganisation::changePlan`) : une descente est refusee tant que la consommation
 * depasse les quotas vises, et son message dit lesquels.
 */
export function ChangePlanDialog({ slug, currentPlan, plans }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({ plan: currentPlan });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.patch(update(slug).url, {
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
                    data-test="console-organisation-change-plan"
                >
                    <Package />
                    {t('console.organisation.action_labels.change_plan')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                'console.organisation.action_labels.change_plan',
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {t('console.organisation.dialogs.change_plan')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="organisation-plan" required>
                            {t('console.organisation.fields.plan')}
                        </Label>
                        <Select
                            value={form.data.plan}
                            onValueChange={(value) =>
                                form.setData('plan', value)
                            }
                        >
                            <SelectTrigger id="organisation-plan">
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
                            disabled={form.data.plan === currentPlan}
                            data-test="console-organisation-change-plan-submit"
                        >
                            {t(
                                'console.organisation.action_labels.change_plan',
                            )}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
