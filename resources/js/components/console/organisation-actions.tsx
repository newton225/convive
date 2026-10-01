import { router } from '@inertiajs/react';
import { Play, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { ChangePlanDialog } from '@/components/console/change-plan-dialog';
import { ExtendTrialDialog } from '@/components/console/extend-trial-dialog';
import { ScheduleDeletionDialog } from '@/components/console/schedule-deletion-dialog';
import { SuspendOrganisationDialog } from '@/components/console/suspend-organisation-dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { reactivate } from '@/routes/console/organisations';
import { cancel as cancelDeletion } from '@/routes/console/organisations/deletion';
import type { ConsoleOrganisationDetails, ConsolePlanOption } from '@/types';

type Props = {
    organisation: ConsoleOrganisationDetails;
    plans: ConsolePlanOption[];
};

type Pending = 'reactivate' | 'cancel_deletion';

/**
 * Les actions de l'editeur sur une organisation (README section 3 et ecran 28) : changer de plan,
 * offrir ou prolonger l'essai, suspendre ou reactiver, programmer ou annuler la suppression.
 * Chaque bouton n'apparait que dans l'etat ou l'action a un sens ; le serveur revalide de toute
 * facon.
 */
export function OrganisationActions({ organisation, plans }: Props) {
    const { t } = useTranslation();
    const [pending, setPending] = useState<Pending | null>(null);
    const [processing, setProcessing] = useState(false);

    const run = () => {
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        };

        if (pending === 'reactivate') {
            router.post(reactivate(organisation.slug).url, {}, options);
        }

        if (pending === 'cancel_deletion') {
            router.delete(cancelDeletion(organisation.slug).url, options);
        }
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap gap-2">
                <ChangePlanDialog
                    slug={organisation.slug}
                    currentPlan={organisation.plan}
                    plans={plans}
                />

                {/* Un abonnement l'emporte sur l'essai : rien a offrir a une organisation abonnee. */}
                {organisation.hasSubscription ? null : (
                    <ExtendTrialDialog
                        slug={organisation.slug}
                        onTrial={organisation.onTrial}
                        endsAt={organisation.trialEndsAt}
                    />
                )}

                {organisation.suspendedByEditor ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setPending('reactivate')}
                        data-test="console-organisation-reactivate"
                    >
                        <Play />
                        {t('console.organisation.action_labels.reactivate')}
                    </Button>
                ) : (
                    <SuspendOrganisationDialog
                        slug={organisation.slug}
                        name={organisation.name}
                    />
                )}

                {organisation.deletionAt ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setPending('cancel_deletion')}
                        data-test="console-organisation-cancel-deletion"
                    >
                        <Undo2 />
                        {t(
                            'console.organisation.action_labels.cancel_deletion',
                        )}
                    </Button>
                ) : (
                    <ScheduleDeletionDialog
                        slug={organisation.slug}
                        name={organisation.name}
                    />
                )}
            </div>

            {organisation.suspensionReason ? (
                <p className="text-sm">
                    <span className="text-muted-foreground">
                        {t('console.organisation.suspension_reason')} :
                    </span>{' '}
                    {organisation.suspensionReason}
                </p>
            ) : null}

            {/* Une suspension pour impaye ne se leve pas d'ici : elle se leve par le paiement. */}
            {organisation.status === 'suspended' &&
            !organisation.suspendedByEditor ? (
                <p className="text-muted-foreground text-sm">
                    {t('console.organisation.suspended_for_non_payment')}
                </p>
            ) : null}

            <ConfirmActionDialog
                open={pending !== null}
                onOpenChange={(open) => !open && setPending(null)}
                title={t(
                    `console.organisation.dialogs.${pending ?? 'reactivate'}_title`,
                    { organisation: organisation.name },
                )}
                description={t(
                    `console.organisation.dialogs.${pending ?? 'reactivate'}`,
                )}
                confirmLabel={t(
                    `console.organisation.action_labels.${pending ?? 'reactivate'}`,
                )}
                onConfirm={run}
                processing={processing}
                testId="console-organisation-confirm"
            />
        </div>
    );
}
