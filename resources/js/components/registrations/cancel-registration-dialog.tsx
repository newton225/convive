import { useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import type { FormEvent } from 'react';
import { formatPhoneNumberIntl } from 'react-phone-number-input';
import { ConfirmSummary } from '@/components/confirm-summary';
import InputError from '@/components/input-error';
import { RefundFields } from '@/components/registrations/refund-fields';
import { RefundStatusPicker } from '@/components/registrations/refund-status-picker';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import { Duration, EaseOut } from '@/lib/motion';
import { cancel } from '@/routes/tenants/events/registrations';
import type {
    RefundChannelOption,
    RefundStatus,
    RegistrationRow,
} from '@/types';

type Props = {
    tenantSlug: string;
    eventId: number;
    registration: RegistrationRow;
    canRefund: boolean;
    refundChannels: RefundChannelOption[];
    onClose: () => void;
};

type CancelForm = {
    reason: string;
    refund: RefundFieldsData & { status: RefundStatus; kept_reason: string };
};

/**
 * Annuler une inscription (README ecran 20), et fixer le sort de son paiement quand elle etait
 * validee (README 2.11). « A rembourser » est preselectionne : c'est le seul choix qui n'efface
 * rien. Sans la permission de remboursement, le choix n'est pas propose : le serveur note « a
 * rembourser », et un membre autorise tranchera ensuite.
 */
export function CancelRegistrationDialog({
    tenantSlug,
    eventId,
    registration,
    canRefund,
    refundChannels,
    onClose,
}: Props) {
    const { t, locale } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const hasPayment =
        registration.status === 'confirmed' && registration.amountDue > 0;
    const amount = formatAmount(registration.amountDue, locale);

    const form = useForm<CancelForm>({
        reason: '',
        refund: {
            status: 'due',
            channel: '',
            refunded_on: format(new Date(), 'yyyy-MM-dd'),
            fee: '',
            reference: '',
            kept_reason: '',
        },
    });

    const setRefund = (field: keyof CancelForm['refund'], value: string) =>
        form.setData('refund', { ...form.data.refund, [field]: value });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // Seul ce qui concerne le choix part au serveur : un ancien choix ne laisse rien derriere
        // lui, et sans paiement ni permission, le motif suffit.
        form.transform(({ reason, refund }) => {
            if (!hasPayment || !canRefund) {
                return { reason };
            }

            if (refund.status === 'refunded') {
                return {
                    reason,
                    refund: {
                        status: refund.status,
                        channel: refund.channel,
                        refunded_on: refund.refunded_on,
                        fee: refund.fee,
                        reference: refund.reference,
                    },
                };
            }

            return refund.status === 'kept'
                ? {
                      reason,
                      refund: {
                          status: refund.status,
                          kept_reason: refund.kept_reason,
                      },
                  }
                : { reason, refund: { status: refund.status } };
        });

        form.post(cancel([tenantSlug, eventId, registration.id]).url, {
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
                            {t('registrations.modals.cancel.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('registrations.modals.cancel.description', {
                                name: registration.name,
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <ConfirmSummary
                        testId="registration-cancel-summary"
                        items={[
                            {
                                label: t('registrations.columns.name'),
                                value: registration.name,
                                emphasis: true,
                            },
                            {
                                label: t('registrations.confirm.reference'),
                                value: registration.reference,
                                mono: true,
                                hidden: registration.reference === null,
                            },
                            {
                                label: t('registrations.confirm.phone'),
                                value:
                                    formatPhoneNumberIntl(registration.phone) ||
                                    registration.phone,
                            },
                            {
                                label: t('registrations.columns.party_size'),
                                value: `${registration.partySize} (${registration.unit})`,
                            },
                            {
                                label: t('registrations.columns.amount_due'),
                                value: amount,
                                emphasis: true,
                            },
                            {
                                label: t('registrations.columns.status'),
                                value: registration.statusLabel,
                                emphasis: true,
                            },
                            {
                                label: t('registrations.columns.channel'),
                                value: registration.channelLabel,
                                hidden: registration.channelLabel === null,
                            },
                            {
                                label: t('registrations.columns.table'),
                                value: registration.tableNumber,
                                hidden: registration.tableNumber === null,
                            },
                            {
                                // Annuler quelqu'un deja dans la salle est rarement voulu : a voir
                                // avant de confirmer.
                                label: t('registrations.columns.entry'),
                                value: registration.enteredAt
                                    ? t('registrations.entry.entered', {
                                          time: formatDateTime(
                                              registration.enteredAt,
                                              locale,
                                          ),
                                      })
                                    : null,
                                warning: true,
                                hidden: registration.enteredAt === null,
                            },
                        ]}
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="cancel-reason">
                            {t('registrations.modals.cancel.reason_label')}
                        </Label>
                        <Input
                            id="cancel-reason"
                            name="reason"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            data-test="registration-cancel-reason"
                        />
                        <InputError message={form.errors.reason} />
                    </div>

                    {hasPayment ? (
                        <section
                            className="space-y-3 border-t pt-4"
                            data-test="registration-cancel-refund"
                        >
                            <h3 className="font-medium">
                                {t('registrations.refund.title')}
                            </h3>

                            {canRefund ? (
                                <>
                                    <p className="text-muted-foreground text-sm">
                                        {t('registrations.refund.question', {
                                            amount,
                                        })}
                                    </p>
                                    <RefundStatusPicker
                                        value={form.data.refund.status}
                                        onChange={(status) =>
                                            setRefund('status', status)
                                        }
                                    />

                                    {/* Le detail du choix se remplace en fondu : l'ancien sort,
                                        le nouveau entre, sans deux blocs a suivre a la fois. */}
                                    <AnimatePresence
                                        mode="wait"
                                        initial={false}
                                    >
                                        <motion.div
                                            key={form.data.refund.status}
                                            className="space-y-3"
                                            initial={
                                                reduceMotion
                                                    ? false
                                                    : { opacity: 0, y: -6 }
                                            }
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={
                                                reduceMotion
                                                    ? undefined
                                                    : { opacity: 0, y: -6 }
                                            }
                                            transition={{
                                                duration: reduceMotion
                                                    ? 0
                                                    : Duration.quick,
                                                ease: EaseOut,
                                            }}
                                        >
                                            <p className="text-muted-foreground text-sm">
                                                {t(
                                                    `registrations.refund.hints.${form.data.refund.status}`,
                                                )}
                                            </p>

                                            {form.data.refund.status ===
                                            'refunded' ? (
                                                <RefundFields
                                                    amountPaid={
                                                        registration.amountDue
                                                    }
                                                    channels={refundChannels}
                                                    data={form.data.refund}
                                                    onChange={setRefund}
                                                    errors={{
                                                        channel:
                                                            form.errors[
                                                                'refund.channel'
                                                            ],
                                                        refunded_on:
                                                            form.errors[
                                                                'refund.refunded_on'
                                                            ],
                                                        fee: form.errors[
                                                            'refund.fee'
                                                        ],
                                                        reference:
                                                            form.errors[
                                                                'refund.reference'
                                                            ],
                                                    }}
                                                    errorPrefix="refund."
                                                    idPrefix="cancel-refund"
                                                />
                                            ) : null}

                                            {form.data.refund.status ===
                                            'kept' ? (
                                                <div className="grid gap-2">
                                                    <Label htmlFor="cancel-kept-reason">
                                                        {t(
                                                            'registrations.refund.fields.kept_reason',
                                                        )}
                                                    </Label>
                                                    <Textarea
                                                        id="cancel-kept-reason"
                                                        value={
                                                            form.data.refund
                                                                .kept_reason
                                                        }
                                                        maxLength={500}
                                                        placeholder={t(
                                                            'registrations.refund.fields.kept_reason_placeholder',
                                                        )}
                                                        onChange={(event) =>
                                                            setRefund(
                                                                'kept_reason',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        data-test="refund-kept-reason"
                                                    />
                                                    <InputError
                                                        message={
                                                            form.errors[
                                                                'refund.kept_reason'
                                                            ]
                                                        }
                                                        data-error-for="refund.kept_reason"
                                                    />
                                                </div>
                                            ) : null}
                                        </motion.div>
                                    </AnimatePresence>
                                </>
                            ) : (
                                <p className="text-muted-foreground text-sm">
                                    {t('registrations.refund.no_permission', {
                                        amount,
                                    })}
                                </p>
                            )}
                        </section>
                    ) : null}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>

                        <SubmitButton
                            variant="destructive"
                            processing={form.processing}
                            disabled={form.data.reason.trim() === ''}
                            data-test="registration-cancel-confirm"
                        >
                            {t('registrations.modals.cancel.submit')}
                        </SubmitButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
