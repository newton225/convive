import { router } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { DisabledReason } from '@/components/disabled-reason';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { announce } from '@/routes/tenants/events';
import { withdraw } from '@/routes/tenants/events/announce';
import type { EventDetails } from '@/types';

type Props = {
    tenantSlug: string;
    event: EventDetails;
};

/**
 * Annoncer ou retirer un evenement de la vitrine change ce que voit le public du site produit :
 * les deux sens passent par une confirmation, pas seulement le retrait. Le serveur met a jour la
 * table centrale de la vitrine, ce qui prend un instant : la roue reste visible jusqu'a la reponse.
 */
export function AnnouncementButton({ tenantSlug, event }: Props) {
    const { t } = useTranslation();
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const withdrawing = event.isAnnounced;
    // La vitrine n'accepte qu'un evenement publie, ouvert, a venir et illustre : la fiche dit ce qui
    // manque, le bouton attend. Retirer l'annonce reste toujours possible.
    const blocked = !withdrawing && event.missingBeforeAnnouncing.length > 0;
    const label = t(
        withdrawing
            ? 'events.actions.withdraw_announcement'
            : 'events.actions.announce',
    );
    const options = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => setConfirming(false),
    };

    return (
        <>
            <DisabledReason
                reason={
                    blocked
                        ? t('events.announcing.blocked', {
                              items: event.missingBeforeAnnouncing
                                  .map((key) =>
                                      t(`events.missing_announce.${key}`),
                                  )
                                  .join(', '),
                          })
                        : null
                }
            >
                <Button
                    variant="outline"
                    disabled={processing || blocked}
                    aria-busy={processing}
                    data-test={
                        withdrawing
                            ? 'event-withdraw-announcement'
                            : 'event-announce'
                    }
                    onClick={() => setConfirming(true)}
                >
                    {processing ? <Spinner /> : <Megaphone />}
                    {label}
                </Button>
            </DisabledReason>
            <ConfirmActionDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t(
                    withdrawing
                        ? 'events.confirm_withdraw_announcement.title'
                        : 'events.confirm_announce.title',
                )}
                description={t(
                    withdrawing
                        ? 'events.confirm_withdraw_announcement.description'
                        : 'events.confirm_announce.description',
                    { name: event.name },
                )}
                confirmLabel={label}
                processing={processing}
                testId={
                    withdrawing
                        ? 'event-withdraw-announcement-confirm'
                        : 'event-announce-confirm'
                }
                onConfirm={() =>
                    withdrawing
                        ? router.delete(
                              withdraw([tenantSlug, event.id]).url,
                              options,
                          )
                        : router.post(
                              announce([tenantSlug, event.id]).url,
                              {},
                              options,
                          )
                }
            />
        </>
    );
}
