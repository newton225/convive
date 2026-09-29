import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDate } from '@/lib/format-date';
import type { RegistrationCancellation } from '@/types';

type Props = {
    cancellation: RegistrationCancellation;
};

/**
 * Le sort du paiement d'une annulation (README 2.11), en une ligne : a rembourser (mis en avant,
 * c'est une somme due), rembourse avec montant recu, date, moyen et frais, ou conserve avec son
 * motif. Rien pour une annulation qui n'avait rien encaisse.
 */
export function RefundSummary({ cancellation }: Props) {
    const { t, locale } = useTranslation();
    const {
        refundStatus,
        refundStatusLabel,
        amountPaid,
        netRefund,
        refundedOn,
        refundChannelLabel,
        refundFee,
        refundKeptReason,
    } = cancellation;

    if (refundStatus === null || amountPaid === null) {
        return null;
    }

    const amount = formatAmount(amountPaid, locale);
    const detail =
        refundStatus === 'refunded'
            ? t('registrations.refund.summary.refunded', {
                  net: formatAmount(netRefund ?? 0, locale),
                  date: refundedOn ? formatDate(refundedOn, locale) : '',
                  channel: refundChannelLabel ?? '',
                  fee: formatAmount(refundFee ?? 0, locale),
              })
            : refundStatus === 'kept'
              ? t('registrations.refund.summary.kept', {
                    amount,
                    reason: refundKeptReason ?? '',
                })
              : t('registrations.refund.summary.due', { amount });

    return (
        <div
            className="flex flex-wrap items-center gap-2"
            data-test="refund-summary"
        >
            <Badge
                variant={refundStatus === 'due' ? 'destructive' : 'secondary'}
            >
                {refundStatusLabel}
            </Badge>
            <span
                className={
                    refundStatus === 'due'
                        ? 'font-medium'
                        : 'text-muted-foreground'
                }
            >
                {detail}
            </span>
        </div>
    );
}
