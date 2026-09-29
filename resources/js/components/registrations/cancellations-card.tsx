import { useState } from 'react';
import { RecordRefundDialog } from '@/components/registrations/record-refund-dialog';
import { RefundSummary } from '@/components/registrations/refund-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import type { RefundChannelOption, RegistrationCancellation } from '@/types';

type Props = {
    tenantSlug: string;
    eventId: number;
    cancellations: RegistrationCancellation[];
    canRefund: boolean;
    refundChannels: RefundChannelOption[];
};

/**
 * Les dernieres annulations de l'evenement (prototype Convive.dc.html, base d'inscrits) : qui a
 * ete annule, pourquoi, par qui et quand, lisible sans filtrer la liste. Avec le sort du paiement
 * (README 2.11) : les sommes encore a rembourser arrivent en tete, et se marquent remboursees ici.
 */
export function CancellationsCard({
    tenantSlug,
    eventId,
    cancellations,
    canRefund,
    refundChannels,
}: Props) {
    const { t, locale } = useTranslation();
    const [recording, setRecording] = useState<
        (RegistrationCancellation & { amountPaid: number }) | null
    >(null);

    return (
        <Card data-test="registration-cancellations">
            <CardHeader>
                <CardTitle className="text-base">
                    {t('registrations.cancellations.title')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {cancellations.length === 0 ? (
                    <div className="space-y-1 text-sm">
                        <p className="font-medium">
                            {t('registrations.cancellations.empty_title')}
                        </p>
                        <p className="text-muted-foreground">
                            {t('registrations.cancellations.empty_description')}
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y text-sm">
                        {cancellations.map((item) => (
                            <li
                                key={item.id}
                                className="space-y-1 py-2"
                                data-test="registration-cancellation"
                            >
                                <p className="font-medium">{item.name}</p>
                                <p className="text-muted-foreground">
                                    {item.reason ??
                                        t(
                                            'registrations.cancellations.no_reason',
                                        )}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {t('registrations.cancellations.by', {
                                        name:
                                            item.cancelledBy ??
                                            t(
                                                'registrations.cancellations.unknown_author',
                                            ),
                                        date: item.cancelledAt
                                            ? formatDateTime(
                                                  item.cancelledAt,
                                                  locale,
                                              )
                                            : '',
                                    })}
                                </p>
                                <RefundSummary cancellation={item} />
                                {canRefund &&
                                item.refundStatus === 'due' &&
                                item.amountPaid !== null ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="mt-1"
                                        data-test="refund-mark"
                                        onClick={() =>
                                            item.amountPaid !== null &&
                                            setRecording({
                                                ...item,
                                                amountPaid: item.amountPaid,
                                            })
                                        }
                                    >
                                        {t(
                                            'registrations.refund.mark_refunded',
                                        )}
                                    </Button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>

            {recording ? (
                <RecordRefundDialog
                    tenantSlug={tenantSlug}
                    eventId={eventId}
                    cancellation={recording}
                    refundChannels={refundChannels}
                    onClose={() => setRecording(null)}
                />
            ) : null}
        </Card>
    );
}
