import { Form, Head, router } from '@inertiajs/react';
import { BrandColorStyle } from '@/components/brand-color-style';
import { GuestClaimCard } from '@/components/public/guest-claim-card';
import { ResumeLinkCard } from '@/components/public/resume-link-card';
import { RegistrationRecap } from '@/components/public/registration-recap';
import { RegistrationSummaryCard } from '@/components/public/registration-summary-card';
import { OfflineBanner } from '@/components/offline-banner';
import { useState } from 'react';
import { CopyButton } from '@/components/copy-button';
import { CompanionTicketPassCard } from '@/components/public/companion-ticket-pass-card';
import { ShareAllTicketsButton } from '@/components/public/share-all-tickets-button';
import { BrandedTicket } from '@/components/ticket-template/branded-ticket';
import { TicketPdfDownload } from '@/components/public/ticket-pdf-download';
import InputError from '@/components/input-error';
import { RequiredFieldsNote } from '@/components/required-fields-note';
import { LabelWithHelp } from '@/components/label-with-help';
import { SubmitButton } from '@/components/submit-button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
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
import { formatDateTime } from '@/lib/format-date';
import { cn } from '@/lib/utils';
import { retry } from '@/routes/public/registrations';
import { store as storeProof } from '@/routes/public/registrations/proof';
import type {
    GuestClaimForm,
    PublicPaymentAccount,
    PublicRegistrationTenant,
    RegistrationShow,
} from '@/types';

type Props = {
    event: {
        name: string;
        startsAt: string | null;
        venue: string | null;
        venueAddress: string | null;
        remainingSeats: number | null;
        hasEnoughSeats: boolean;
    };
    tenant: PublicRegistrationTenant;
    registration: RegistrationShow;
    paymentAccounts: PublicPaymentAccount[];
    token: string;
    claim: GuestClaimForm;
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
    token,
    claim,
    resume,
}: Props) {
    const { t, locale } = useTranslation();
    const { remainingSeconds, hasExpired } = useCountdown(
        registration.status === 'held' ? registration.heldUntil : null,
    );
    // Constante locale : le retrecissement de type tient aussi dans les rappels du rendu.
    const ticket = registration.ticket;
    const isExpired = registration.status === 'expired' || hasExpired;
    const isRejected = registration.status === 'proof_rejected';
    const isCancelled = registration.status === 'cancelled';
    const isUrgent = !isExpired && remainingSeconds <= 120;
    const [idempotencyKey] = useState(() => crypto.randomUUID());
    const [selectedAccountId, setSelectedAccountId] = useState<string>('');
    const [retrying, setRetrying] = useState(false);
    // Le canal de la preuve est celui du compte choisi : un compte en especes n'a pas de reference.
    const accountRequiresReference =
        paymentAccounts.find(
            (account) => String(account.id) === selectedAccountId,
        )?.requiresReference ?? true;

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
                    {registration.reference ? (
                        <p
                            className="text-muted-foreground font-mono text-xs"
                            data-test="registration-reference"
                        >
                            {t('guest.registration.reference', {
                                reference: registration.reference,
                            })}
                        </p>
                    ) : null}
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

                        <RegistrationSummaryCard
                            registration={registration}
                            event={event}
                        />

                        <RegistrationRecap
                            remainingSeats={event.remainingSeats}
                            hasEnoughSeats={event.hasEnoughSeats}
                        />
                    </>
                ) : registration.status === 'proof_submitted' ? (
                    <>
                        <Card>
                            <CardContent className="pt-6 text-center">
                                <p
                                    className="font-medium"
                                    data-test="registration-proof-status"
                                >
                                    {t(
                                        'guest.registration.show.proof_submitted',
                                    )}
                                </p>
                            </CardContent>
                        </Card>

                        <RegistrationSummaryCard
                            registration={registration}
                            event={event}
                        />
                    </>
                ) : registration.status === 'confirmed' ? (
                    ticket ? (
                        // Le billet talon (README ecran 7) : le meme pour l'invite et chacun de
                        // ses accompagnateurs, et le meme que dans le PDF (`App\Support\TicketCard`).
                        <section
                            className="space-y-6"
                            data-test="ticket-card"
                            data-model={ticket.model}
                        >
                            <BrandedTicket
                                model={ticket.model}
                                elements={ticket.elements}
                                brand={ticket.brand}
                                event={ticket.event}
                                ticket={ticket.card}
                            />
                            {ticket.pdfUrl ? (
                                <TicketPdfDownload
                                    url={ticket.pdfUrl}
                                    all={ticket.passes.length > 0}
                                />
                            ) : null}
                            {ticket.scheduledSendAt ? (
                                <p className="text-muted-foreground text-center text-sm">
                                    {t('guest.ticket.scheduled_send', {
                                        date: formatDateTime(
                                            ticket.scheduledSendAt,
                                            locale,
                                        ),
                                    })}
                                </p>
                            ) : null}
                            {ticket.passes.length > 0 ? (
                                <div
                                    className="space-y-4 border-t border-dashed pt-6"
                                    data-test="ticket-passes"
                                >
                                    <div className="space-y-1">
                                        <p className="text-muted-foreground text-xs font-medium uppercase">
                                            {t('guest.ticket.passes_title')}
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            {t(
                                                'guest.ticket.passes_description',
                                            )}
                                        </p>
                                    </div>
                                    <ShareAllTicketsButton
                                        passes={ticket.passes}
                                        eventName={ticket.event.name}
                                    />
                                    {ticket.passes.map((pass) => (
                                        <CompanionTicketPassCard
                                            key={pass.id}
                                            pass={pass}
                                            design={ticket}
                                            event={ticket.event}
                                        />
                                    ))}
                                </div>
                            ) : null}
                        </section>
                    ) : null
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
                                        isUrgent && 'text-[oklch(0.55_0.2_25)]',
                                    )}
                                    data-test="registration-countdown"
                                >
                                    {formatCountdown(remainingSeconds)}
                                </p>
                            </CardContent>
                        </Card>

                        <RegistrationSummaryCard
                            registration={registration}
                            event={event}
                        />

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
                                            <RequiredFieldsNote />
                                            <input
                                                type="hidden"
                                                name="idempotency_key"
                                                value={idempotencyKey}
                                            />

                                            <div className="space-y-2">
                                                <Label
                                                    htmlFor="payment_account_id"
                                                    required
                                                >
                                                    {t(
                                                        'guest.proof.fields.payment_account',
                                                    )}
                                                </Label>
                                                <Select
                                                    name="payment_account_id"
                                                    onValueChange={
                                                        setSelectedAccountId
                                                    }
                                                >
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

                                            {accountRequiresReference ? (
                                                <div className="space-y-2">
                                                    <LabelWithHelp
                                                        htmlFor="reference"
                                                        required
                                                        label={t(
                                                            'guest.proof.fields.reference',
                                                        )}
                                                        help={t(
                                                            'guest.proof.help.reference',
                                                        )}
                                                    />
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
                                                <LabelWithHelp
                                                    htmlFor="guest_note"
                                                    label={t(
                                                        'guest.proof.fields.guest_note',
                                                    )}
                                                    help={t(
                                                        'guest.proof.help.guest_note',
                                                    )}
                                                />
                                                <Textarea
                                                    id="guest_note"
                                                    name="guest_note"
                                                    maxLength={500}
                                                    rows={3}
                                                    placeholder={t(
                                                        'guest.proof.fields.guest_note_placeholder',
                                                    )}
                                                    data-test="proof-guest-note"
                                                />
                                                <InputError
                                                    message={errors.guest_note}
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label
                                                    htmlFor="receipt"
                                                    required
                                                >
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
                <GuestClaimCard token={token} claim={claim} />
            </main>
        </div>
    );
}
