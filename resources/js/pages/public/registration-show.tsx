import { Form, Head, router } from '@inertiajs/react';
import { BrandColorStyle } from '@/components/brand-color-style';
import { ResumeLinkCard } from '@/components/public/resume-link-card';
import { RegistrationRecap } from '@/components/public/registration-recap';
import { OfflineBanner } from '@/components/offline-banner';
import { useState } from 'react';
import { CopyButton } from '@/components/copy-button';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatCountdown, useCountdown } from '@/hooks/use-countdown';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import { cn } from '@/lib/utils';
import { retry } from '@/routes/public/registrations';
import { store as storeProof } from '@/routes/public/registrations/proof';
import type {
    PublicPaymentAccount,
    PublicPaymentChannel,
    PublicRegistrationTenant,
    RegistrationShow,
} from '@/types';

type Props = {
    event: { name: string; remainingSeats: number };
    tenant: PublicRegistrationTenant;
    registration: RegistrationShow;
    paymentAccounts: PublicPaymentAccount[];
    channels: PublicPaymentChannel[];
    token: string;
    // Absent quand la page est atteinte par le lien signe des envois programmes plutot que par
    // le jeton de reprise de l'invite (voir `RegistrationController::render()`) : relancer une
    // reservation ou deposer une preuve reste alors hors de portee, la page masque ces actions.
    resume: string | null;
};

/**
 * README ecran 5 (comptes de versement, decompte, depot de la preuve) et ecran 6 (preuve
 * recue), etape 6 de « Ordre de construction ».
 *
 * Les couleurs de marque s'appliquent ici comme sur le lien public de l'evenement (CLAUDE.md) :
 * le billet (ecran 7, plus bas dans cette page) en a besoin pour son modele « classique ».
 */
export default function PublicRegistrationShow({
    event,
    tenant,
    registration,
    paymentAccounts,
    channels,
    token,
    resume,
}: Props) {
    const { t, locale } = useTranslation();
    const { remainingSeconds, hasExpired } = useCountdown(
        registration.status === 'held' ? registration.heldUntil : null,
    );
    const isExpired = registration.status === 'expired' || hasExpired;
    const isRejected = registration.status === 'proof_rejected';
    const isCancelled = registration.status === 'cancelled';
    const isUrgent = !isExpired && remainingSeconds <= 120;
    const [idempotencyKey] = useState(() => crypto.randomUUID());
    const [selectedChannel, setSelectedChannel] = useState<string>('');
    const [retrying, setRetrying] = useState(false);
    const channelRequiresReference =
        channels.find((channel) => channel.value === selectedChannel)
            ?.hasAccountNumber ?? true;

    return (
        <div className="bg-background flex min-h-screen flex-col items-center p-4">
            <BrandColorStyle colors={tenant.colors} />
            <Head title={t('guest.registration.title')} />
            <OfflineBanner />

            <main className="w-full max-w-lg space-y-6 pt-12">
                <div className="space-y-1 text-center">
                    <h1 className="text-2xl font-semibold">{event.name}</h1>
                    <p className="text-muted-foreground text-sm">
                        {registration.name}
                    </p>
                </div>

                {isCancelled ? (
                    <Card>
                        <CardContent className="space-y-1 pt-6 text-center">
                            <p
                                className="font-medium"
                                data-test="registration-cancelled"
                            >
                                {t('guest.registration.show.cancelled_title')}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'guest.registration.show.cancelled_description',
                                )}
                            </p>
                        </CardContent>
                    </Card>
                ) : isExpired || isRejected ? (
                    <>
                        <Card>
                            <CardContent className="space-y-4 pt-6 text-center">
                                <p className="font-medium">
                                    {t(
                                        isRejected
                                            ? 'guest.registration.show.proof_rejected'
                                            : 'guest.registration.show.expired',
                                    )}
                                </p>
                                {resume ? (
                                    <SubmitButton
                                        type="button"
                                        processing={retrying}
                                        data-test="registration-retry"
                                        onClick={() => {
                                            setRetrying(true);
                                            router.post(
                                                retry([token, resume]).url,
                                                {},
                                                {
                                                    onFinish: () =>
                                                        setRetrying(false),
                                                },
                                            );
                                        }}
                                    >
                                        {t('guest.registration.show.retry')}
                                    </SubmitButton>
                                ) : null}
                            </CardContent>
                        </Card>

                        <RegistrationRecap
                            registration={registration}
                            remainingSeats={event.remainingSeats}
                        />
                    </>
                ) : registration.status === 'proof_submitted' ? (
                    <Card>
                        <CardContent className="pt-6 text-center">
                            <p
                                className="font-medium"
                                data-test="registration-proof-status"
                            >
                                {t('guest.registration.show.proof_submitted')}
                            </p>
                        </CardContent>
                    </Card>
                ) : registration.status === 'confirmed' ? (
                    <Card
                        data-test="ticket-card"
                        data-model={registration.ticket?.model}
                        className={cn(
                            registration.ticket?.model === 'classic' &&
                                'border-2 border-[color:var(--brand-primary)]',
                            registration.ticket?.model === 'elegant' &&
                                'rounded-2xl',
                        )}
                    >
                        <CardHeader>
                            <div className="flex items-center justify-center gap-2">
                                {registration.ticket?.elements.logo &&
                                registration.ticket.brand.logoUrl ? (
                                    <img
                                        src={registration.ticket.brand.logoUrl}
                                        alt={
                                            registration.ticket.brand
                                                .displayName ?? ''
                                        }
                                        className="size-8 object-contain"
                                        data-test="ticket-logo"
                                    />
                                ) : null}
                                <CardTitle
                                    className={cn(
                                        'text-base',
                                        registration.ticket?.model ===
                                            'elegant' && 'font-serif',
                                    )}
                                >
                                    {t('guest.ticket.title')}
                                </CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 text-center">
                            {registration.ticket ? (
                                <>
                                    <img
                                        src={registration.ticket.qrImage}
                                        alt={t('guest.ticket.title')}
                                        className="mx-auto h-48 w-48"
                                        data-test="ticket-qr"
                                    />
                                    <p className="font-medium">
                                        {registration.ticket.tableNumber !==
                                        null
                                            ? t('guest.ticket.table', {
                                                  number: String(
                                                      registration.ticket
                                                          .tableNumber,
                                                  ),
                                              })
                                            : t('guest.ticket.no_table')}
                                    </p>
                                    <div className="space-y-1 text-left">
                                        <p className="text-muted-foreground text-xs font-medium uppercase">
                                            {t('guest.ticket.guests_title')}
                                        </p>
                                        <p
                                            className="text-sm"
                                            data-test="ticket-guest"
                                        >
                                            {registration.name} ·{' '}
                                            {registration.unit}
                                        </p>
                                        {registration.ticket.elements.companions
                                            ? registration.companions.map(
                                                  (companion, index) => (
                                                      <p
                                                          key={index}
                                                          className="text-sm"
                                                          data-test="ticket-guest"
                                                      >
                                                          {companion.name} ·{' '}
                                                          {companion.unit}
                                                      </p>
                                                  ),
                                              )
                                            : null}
                                    </div>
                                    {registration.ticket.scheduledSendAt ? (
                                        <p className="text-muted-foreground text-sm">
                                            {t('guest.ticket.scheduled_send', {
                                                date: formatDateTime(
                                                    registration.ticket
                                                        .scheduledSendAt,
                                                    locale,
                                                ),
                                            })}
                                        </p>
                                    ) : null}
                                    {(registration.ticket.elements.stamp &&
                                        registration.ticket.brand.stampUrl) ||
                                    (registration.ticket.elements.signature &&
                                        registration.ticket.brand
                                            .signatureUrl) ? (
                                        <div className="flex items-center justify-center gap-3 border-t border-dashed pt-4">
                                            {registration.ticket.elements
                                                .stamp &&
                                            registration.ticket.brand
                                                .stampUrl ? (
                                                <img
                                                    src={
                                                        registration.ticket
                                                            .brand.stampUrl
                                                    }
                                                    alt={t(
                                                        'ticket_template.elements.stamp',
                                                    )}
                                                    className="size-14 object-contain"
                                                    data-test="ticket-stamp"
                                                />
                                            ) : null}
                                            {registration.ticket.elements
                                                .signature &&
                                            registration.ticket.brand
                                                .signatureUrl ? (
                                                <img
                                                    src={
                                                        registration.ticket
                                                            .brand.signatureUrl
                                                    }
                                                    alt={t(
                                                        'ticket_template.elements.signature',
                                                    )}
                                                    className="h-10 w-20 object-contain"
                                                    data-test="ticket-signature"
                                                />
                                            ) : null}
                                        </div>
                                    ) : null}
                                </>
                            ) : null}
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card>
                            <CardContent className="space-y-2 pt-6 text-center">
                                <p className="text-muted-foreground text-sm">
                                    {t(
                                        'guest.registration.show.countdown_label',
                                    )}
                                </p>
                                <p
                                    className={cn(
                                        'text-3xl font-semibold tabular-nums',
                                        isUrgent &&
                                            'text-[oklch(0.55_0.2_25)]',
                                    )}
                                    data-test="registration-countdown"
                                >
                                    {formatCountdown(remainingSeconds)}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="flex items-center justify-between pt-6">
                                <span className="text-sm font-medium">
                                    {t('guest.registration.total.label')}
                                </span>
                                <span className="text-lg font-semibold">
                                    {formatAmount(
                                        registration.amountDue,
                                        locale,
                                    )}
                                </span>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('guest.event.payment_accounts.title')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {paymentAccounts.map((account, index) => (
                                    <div
                                        key={index}
                                        className="space-y-1 rounded-lg border p-3"
                                        data-test="payment-account"
                                    >
                                        <p className="font-medium">
                                            {account.label}
                                        </p>
                                        {account.channelLabel ? (
                                            <p className="text-sm">
                                                {account.channelLabel}
                                            </p>
                                        ) : null}
                                        {account.accountNumber ? (
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-mono text-sm">
                                                    {account.accountNumber}
                                                </p>
                                                <CopyButton
                                                    value={
                                                        account.accountNumber
                                                    }
                                                    variant="ghost"
                                                    testId="payment-account-copy"
                                                />
                                            </div>
                                        ) : null}
                                        {account.holderName ? (
                                            <p className="text-muted-foreground text-sm">
                                                {account.holderName}
                                            </p>
                                        ) : null}
                                        {account.accountNumber ? (
                                            <p className="text-muted-foreground text-sm font-medium">
                                                {t(
                                                    'guest.event.payment_accounts.reference_hint',
                                                    { name: registration.name },
                                                )}
                                            </p>
                                        ) : null}
                                        {account.instructions ? (
                                            <p className="text-muted-foreground text-sm">
                                                {account.instructions}
                                            </p>
                                        ) : null}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        {resume ? (
                            <Form
                                {...storeProof.form([token, resume])}
                                data-test="proof-form"
                            >
                                {({ errors, processing }) => (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-base">
                                                {t('guest.proof.title')}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <input
                                                type="hidden"
                                                name="idempotency_key"
                                                value={idempotencyKey}
                                            />

                                            <div className="space-y-2">
                                                <Label htmlFor="payment_account_id">
                                                    {t(
                                                        'guest.proof.fields.payment_account',
                                                    )}
                                                </Label>
                                                <Select name="payment_account_id">
                                                    <SelectTrigger
                                                        id="payment_account_id"
                                                        className="w-full"
                                                        data-test="proof-account"
                                                    >
                                                        <SelectValue
                                                            placeholder={t(
                                                                'guest.proof.fields.payment_account_placeholder',
                                                            )}
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {paymentAccounts.map(
                                                            (account) => (
                                                                <SelectItem
                                                                    key={
                                                                        account.id
                                                                    }
                                                                    value={String(
                                                                        account.id,
                                                                    )}
                                                                >
                                                                    {
                                                                        account.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={
                                                        errors.payment_account_id
                                                    }
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="channel">
                                                    {t(
                                                        'guest.proof.fields.channel',
                                                    )}
                                                </Label>
                                                <Select
                                                    name="channel"
                                                    onValueChange={
                                                        setSelectedChannel
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id="channel"
                                                        className="w-full"
                                                        data-test="proof-channel"
                                                    >
                                                        <SelectValue
                                                            placeholder={t(
                                                                'guest.proof.fields.channel_placeholder',
                                                            )}
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {channels.map(
                                                            (channel) => (
                                                                <SelectItem
                                                                    key={
                                                                        channel.value
                                                                    }
                                                                    value={
                                                                        channel.value
                                                                    }
                                                                >
                                                                    {
                                                                        channel.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.channel}
                                                />
                                            </div>

                                            {channelRequiresReference ? (
                                                <div className="space-y-2">
                                                    <Label htmlFor="reference">
                                                        {t(
                                                            'guest.proof.fields.reference',
                                                        )}
                                                    </Label>
                                                    <Input
                                                        id="reference"
                                                        name="reference"
                                                        required
                                                        data-test="proof-reference"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.reference
                                                        }
                                                    />
                                                </div>
                                            ) : null}

                                            <div className="space-y-2">
                                                <Label htmlFor="amount_declared">
                                                    {t(
                                                        'guest.proof.fields.amount_declared',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="amount_declared"
                                                    name="amount_declared"
                                                    type="number"
                                                    min={1}
                                                    defaultValue={
                                                        registration.amountDue
                                                    }
                                                    required
                                                    data-test="proof-amount"
                                                />
                                                <InputError
                                                    message={
                                                        errors.amount_declared
                                                    }
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="receipt">
                                                    {t(
                                                        'guest.proof.fields.receipt',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="receipt"
                                                    name="receipt"
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    required
                                                    data-test="proof-receipt"
                                                />
                                                <InputError
                                                    message={errors.receipt}
                                                />
                                            </div>

                                            <SubmitButton
                                                className="w-full"
                                                processing={processing}
                                                data-test="proof-submit"
                                            >
                                                {t('guest.proof.submit')}
                                            </SubmitButton>
                                        </CardContent>
                                    </Card>
                                )}
                            </Form>
                        ) : null}
                    </>
                )}
                {!isCancelled && registration.status !== 'confirmed' ? (
                    // L'echeance n'a de sens que pendant le decompte lui-meme (statut « held ») :
                    // une fois une preuve deposee, rejetee ou le decompte ecoule, il n'y a plus
                    // rien a « reprendre avant » cette date precise, meme si le lien reste valide
                    // (le bouton « relancer » redemarre un nouveau decompte le cas echeant).
                    <ResumeLinkCard
                        deadline={
                            registration.status === 'held'
                                ? registration.heldUntil
                                : null
                        }
                    />
                ) : null}
            </main>
        </div>
    );
}
