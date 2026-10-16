import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import type { TreasuryTotals } from '@/types';

/**
 * Les totaux de toute l'organisation sur la periode : encaisse, rembourse (avec les frais retenus),
 * a rembourser et net. Les memes chiffres que le rapport d'un evenement, additionnes.
 */
export function TreasuryTotalsCards({ totals }: { totals: TreasuryTotals }) {
    const { t, locale } = useTranslation();

    const cards = [
        {
            key: 'collected',
            label: t('treasury.totals.collected'),
            amount: totals.collected,
            detail: t('treasury.totals.collected_detail'),
        },
        {
            key: 'refunded',
            label: t('treasury.totals.refunded'),
            amount: totals.refunded,
            detail: t('treasury.totals.fees', {
                count: totals.fees,
                amount: formatAmount(totals.fees, locale),
            }),
        },
        {
            key: 'due',
            label: t('treasury.totals.due'),
            amount: totals.due,
            detail: null,
        },
        {
            key: 'net',
            label: t('treasury.totals.net'),
            amount: totals.net,
            detail: t('treasury.totals.net_detail'),
        },
    ];

    return (
        <div
            className="grid grid-cols-[repeat(auto-fit,minmax(11rem,1fr))] gap-4"
            data-test="treasury-totals"
        >
            {cards.map((card) => (
                <Card key={card.key} data-test={`treasury-total-${card.key}`}>
                    <CardContent className="space-y-1 pt-6">
                        <p className="text-muted-foreground text-sm">
                            {card.label}
                        </p>
                        <p className="text-2xl font-semibold">
                            {formatAmount(card.amount, locale)}
                        </p>
                        {card.detail ? (
                            <p className="text-muted-foreground text-xs">
                                {card.detail}
                            </p>
                        ) : null}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
