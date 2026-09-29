import InputError from '@/components/input-error';
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
import { formatAmount } from '@/lib/format-currency';
import type { RefundChannelOption } from '@/types';

export type RefundFieldsData = {
    channel: string;
    refunded_on: string;
    fee: string;
    reference: string;
};

type Props = {
    amountPaid: number;
    channels: RefundChannelOption[];
    data: RefundFieldsData;
    onChange: (field: keyof RefundFieldsData, value: string) => void;
    errors: Partial<Record<keyof RefundFieldsData, string>>;
    // Prefixe des cles d'erreur cote serveur (`refund.` dans l'annulation), pour que
    // `useVisitFeedback` sache que l'erreur est deja affichee sous son champ.
    errorPrefix: string;
    idPrefix: string;
};

/**
 * Le remboursement tel qu'il a ete fait (README 2.11) : moyen, date, frais preleves par
 * l'operateur et reference. Rembourser se fait en tout ou rien, frais a la charge de l'invite :
 * le montant recu par l'invite est rappele pour verifier la saisie avant d'enregistrer. Le
 * serveur revalide les frais quoi qu'il arrive.
 */
export function RefundFields({
    amountPaid,
    channels,
    data,
    onChange,
    errors,
    errorPrefix,
    idPrefix,
}: Props) {
    const { t, locale } = useTranslation();
    const fee = Number(data.fee);
    const feeIsValid =
        data.fee !== '' &&
        Number.isInteger(fee) &&
        fee >= 0 &&
        fee < amountPaid;

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-channel`}>
                    {t('registrations.refund.fields.channel')}
                </Label>
                <Select
                    value={data.channel}
                    onValueChange={(value) => onChange('channel', value)}
                >
                    <SelectTrigger
                        id={`${idPrefix}-channel`}
                        className="w-full"
                        data-test="refund-channel"
                    >
                        <SelectValue
                            placeholder={t(
                                'registrations.refund.fields.channel_placeholder',
                            )}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {channels.map((channel) => (
                            <SelectItem
                                key={channel.value}
                                value={channel.value}
                            >
                                {channel.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError
                    message={errors.channel}
                    data-error-for={`${errorPrefix}channel`}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-refunded-on`}>
                    {t('registrations.refund.fields.refunded_on')}
                </Label>
                <Input
                    id={`${idPrefix}-refunded-on`}
                    type="date"
                    value={data.refunded_on}
                    onChange={(event) =>
                        onChange('refunded_on', event.target.value)
                    }
                    data-test="refund-date"
                />
                <InputError
                    message={errors.refunded_on}
                    data-error-for={`${errorPrefix}refunded_on`}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-fee`}>
                    {t('registrations.refund.fields.fee')}
                </Label>
                <Input
                    id={`${idPrefix}-fee`}
                    type="number"
                    inputMode="numeric"
                    min={0}
                    step={1}
                    value={data.fee}
                    onChange={(event) => onChange('fee', event.target.value)}
                    aria-describedby={`${idPrefix}-fee-help`}
                    data-test="refund-fee"
                />
                <p
                    id={`${idPrefix}-fee-help`}
                    className="text-muted-foreground text-xs"
                >
                    {t('registrations.refund.fields.fee_help')}
                </p>
                <InputError
                    message={errors.fee}
                    data-error-for={`${errorPrefix}fee`}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-reference`}>
                    {t('registrations.refund.fields.reference')}
                </Label>
                <Input
                    id={`${idPrefix}-reference`}
                    value={data.reference}
                    maxLength={100}
                    onChange={(event) =>
                        onChange('reference', event.target.value)
                    }
                    data-test="refund-reference"
                />
                <InputError
                    message={errors.reference}
                    data-error-for={`${errorPrefix}reference`}
                />
            </div>

            {feeIsValid ? (
                <p
                    className="bg-muted rounded-lg p-3 text-sm font-medium sm:col-span-2"
                    data-test="refund-net"
                    aria-live="polite"
                >
                    {t('registrations.refund.net', {
                        net: formatAmount(amountPaid - fee, locale),
                        amount: formatAmount(amountPaid, locale),
                        fee: formatAmount(fee, locale),
                    })}
                </p>
            ) : null}
        </div>
    );
}
