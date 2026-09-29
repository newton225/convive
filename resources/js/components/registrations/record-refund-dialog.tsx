import { useForm, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import type { FormEvent } from 'react';
import { formatPhoneNumberIntl } from 'react-phone-number-input';
import { ConfirmSummary } from '@/components/confirm-summary';
import InputError from '@/components/input-error';
import { RefundFields } from '@/components/registrations/refund-fields';
import type { RefundFieldsData } from '@/components/registrations/refund-fields';
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
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { refund } from '@/routes/tenants/events/registrations';
import type { RefundChannelOption, RegistrationCancellation } from '@/types';

type Props = {
    tenantSlug: string;
    eventId: number;
    cancellation: RegistrationCancellation & { amountPaid: number };
    refundChannels: RefundChannelOption[];
    onClose: () => void;
};

/**
 * Marquer comme rembourse une annulation « a rembourser », une fois l'argent parti (README 2.11).
 * L'inscription reste annulee ; l'invite est prevenu du montant recu, frais deduits.
 */
export function RecordRefundDialog({
    tenantSlug,
    eventId,
    cancellation,
    refundChannels,
    onClose,
}: Props) {
    const { t, locale } = useTranslation();
    // Erreur sans champ (paiement deja traite entre-temps) : portee par le sac d'erreurs de la
    // page plutot que par le formulaire, qui ne connait que ses champs.
    const alreadyHandled = usePage().props.errors.refund;
    const form = useForm<RefundFieldsData>({
        channel: '',
        refunded_on: format(new Date(), 'yyyy-MM-dd'),
        fee: '',
        reference: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(refund([tenantSlug, eventId, cancellation.id]).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {t('registrations.refund.mark_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('registrations.refund.mark_description', {
                                name: cancellation.name,
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <ConfirmSummary
                        testId="record-refund-summary"
                        items={[
                            {
                                label: t('registrations.columns.name'),
                                value: cancellation.name,
                                emphasis: true,
                            },
                            {
                                label: t('registrations.confirm.reference'),
                                value: cancellation.reference,
                                mono: true,
                                hidden: cancellation.reference === null,
                            },
                            {
                                label: t('registrations.confirm.phone'),
                                value:
                                    formatPhoneNumberIntl(cancellation.phone) ||
                                    cancellation.phone,
                            },
                            {
                                label: t('registrations.refund.amount_paid'),
                                value: formatAmount(
                                    cancellation.amountPaid,
                                    locale,
                                ),
                                emphasis: true,
                            },
                            {
                                label: t(
                                    'registrations.columns.export.cancellation_reason',
                                ),
                                value: cancellation.reason,
                                hidden: cancellation.reason === null,
                            },
                        ]}
                    />

                    <RefundFields
                        amountPaid={cancellation.amountPaid}
                        channels={refundChannels}
                        data={form.data}
                        onChange={(field, value) => form.setData(field, value)}
                        errors={form.errors}
                        errorPrefix=""
                        idPrefix="record-refund"
                    />

                    {/* Paiement deja traite entre-temps : rien a corriger dans les champs. */}
                    <InputError
                        message={alreadyHandled}
                        data-error-for="refund"
                    />

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>

                        <SubmitButton
                            processing={form.processing}
                            data-test="record-refund-confirm"
                        >
                            {t('registrations.refund.submit')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
