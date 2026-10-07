import { Form, Head } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { BrandColorStyle } from '@/components/brand-color-style';
import type { Country } from 'react-phone-number-input';
import { OfflineBanner } from '@/components/offline-banner';
import { AdditionalRegistrationNotice } from '@/components/public/additional-registration-notice';
import { BotCheck } from '@/components/public/bot-check';
import { OngoingReservationNotice } from '@/components/public/ongoing-reservation-notice';
import { PhoneField } from '@/components/phone/phone-field';
import { useRef, useState } from 'react';
import {
    CompanionFields,
    Field,
    UnitSelect,
} from '@/components/registration-fields';
import { RequiredFieldsNote } from '@/components/required-fields-note';
import LocaleSwitcher from '@/components/locale-switcher';
import { SubmitButton } from '@/components/submit-button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { store } from '@/routes/public/registrations';
import type {
    PublicRegistrationEvent,
    PublicRegistrationTenant,
    PublicUnitOption,
} from '@/types';

type Props = {
    event: PublicRegistrationEvent;
    tenant: PublicRegistrationTenant;
    units: PublicUnitOption[];
    token: string;
    defaultCountry: Country;
    // La politique de confidentialite, sur le site de l'editeur.
    privacyUrl: string;
    // La cle publique du widget anti-robot, quand l'evenement le demande.
    botCheckSiteKey: string | null;
    // La reservation encore en cours de ce navigateur sur cet evenement, a reprendre.
    ongoingReservationUrl: string | null;
};

/**
 * README ecran 4 : nom, telephone, email (facultatif), unite, compteur d'accompagnateurs (0-10)
 * avec nom et unite chacun, total recalcule en direct. Le paiement (ecran 5, decompte de reservation) est l'etape
 * 5 de « Ordre de construction » : ce formulaire enregistre l'inscription en `Draft`, sans
 * consommer de place.
 *
 * Le montant ne depend que du nombre d'accompagnateurs, jamais de leur unite (voir
 * `Event::amountFor()`) : le total se recalcule donc a partir du seul compte de lignes.
 */
export default function PublicRegistration({
    event,
    tenant,
    units,
    token,
    defaultCountry,
    privacyUrl,
    botCheckSiteKey,
    ongoingReservationUrl,
}: Props) {
    const { t, locale } = useTranslation();
    const [companionIds, setCompanionIds] = useState<number[]>([]);
    const nextId = useRef(0);
    const [botCheckReset, setBotCheckReset] = useState(0);

    const total = event.pricePerPerson * (1 + companionIds.length);

    return (
        <div className="bg-background flex min-h-screen flex-col">
            <BrandColorStyle colors={tenant.colors} />
            <Head title={t('guest.registration.title')} />
            <OfflineBanner />

            <header className="flex items-center justify-between p-4">
                <div className="flex items-center gap-2">
                    {tenant.logoUrl ? (
                        <img
                            src={tenant.logoUrl}
                            alt={tenant.displayName}
                            className="h-8 w-8 rounded object-contain"
                        />
                    ) : null}
                    <span className="text-sm font-medium">
                        {tenant.displayName}
                    </span>
                </div>
                <LocaleSwitcher />
            </header>

            <main className="mx-auto w-full max-w-lg flex-1 space-y-6 p-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-[color:var(--brand-primary)]">
                        {t('guest.registration.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {event.name}
                    </p>
                </div>

                {ongoingReservationUrl ? (
                    <OngoingReservationNotice href={ongoingReservationUrl} />
                ) : null}

                <Form
                    {...store.form(token)}
                    className="space-y-6"
                    data-test="registration-form"
                    onError={() => setBotCheckReset((count) => count + 1)}
                >
                    {({ errors, processing }) => (
                        <>
                            {errors.registration ? (
                                <Alert
                                    variant="destructive"
                                    data-test="registration-closed"
                                    data-error-for="registration"
                                >
                                    <AlertCircle />
                                    <AlertDescription>
                                        {errors.registration}
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            <Card>
                                <CardContent className="space-y-4 pt-6">
                                    <RequiredFieldsNote />
                                    <Field
                                        id="name"
                                        required
                                        label={t(
                                            'guest.registration.fields.name',
                                        )}
                                        error={errors.name}
                                    >
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            data-test="registration-name"
                                        />
                                    </Field>

                                    <Field
                                        id="phone"
                                        required
                                        label={t(
                                            'guest.registration.fields.phone',
                                        )}
                                        error={errors.phone}
                                        help={t(
                                            'guest.registration.help.phone',
                                        )}
                                    >
                                        <PhoneField
                                            id="phone"
                                            name="phone"
                                            defaultCountry={defaultCountry}
                                            required
                                            testId="registration-phone"
                                        />
                                    </Field>

                                    <Field
                                        id="email"
                                        label={t(
                                            'guest.registration.fields.email',
                                        )}
                                        error={errors.email}
                                    >
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            data-test="registration-email"
                                        />
                                    </Field>

                                    <Field
                                        id="unit_id"
                                        required
                                        label={t(
                                            'guest.registration.fields.unit',
                                        )}
                                        error={errors.unit_id}
                                        help={t('guest.registration.help.unit')}
                                    >
                                        <UnitSelect
                                            name="unit_id"
                                            units={units}
                                            testId="registration-unit"
                                        />
                                    </Field>
                                </CardContent>
                            </Card>

                            <CompanionFields
                                units={units}
                                companionLimit={event.companionLimit}
                                companionIds={companionIds}
                                onAdd={() =>
                                    setCompanionIds((ids) => [
                                        ...ids,
                                        nextId.current++,
                                    ])
                                }
                                onRemove={(id) =>
                                    setCompanionIds((ids) =>
                                        ids.filter(
                                            (existing) => existing !== id,
                                        ),
                                    )
                                }
                                errors={errors}
                            />

                            <Card>
                                <CardContent className="flex items-center justify-between pt-6">
                                    <span className="text-sm font-medium">
                                        {t('guest.registration.total.label')}
                                    </span>
                                    <span
                                        className="text-2xl font-semibold text-[color:var(--brand-primary)]"
                                        data-test="registration-total"
                                    >
                                        {formatAmount(total, locale)}
                                    </span>
                                </CardContent>
                            </Card>

                            {botCheckSiteKey ? (
                                <BotCheck
                                    siteKey={botCheckSiteKey}
                                    resetKey={botCheckReset}
                                    error={errors['cf-turnstile-response']}
                                />
                            ) : null}

                            {errors.additional_registration ? (
                                <AdditionalRegistrationNotice
                                    message={errors.additional_registration}
                                    processing={processing}
                                />
                            ) : null}

                            <SubmitButton
                                className="w-full"
                                processing={processing}
                                data-test="registration-submit"
                            >
                                {t('guest.registration.submit')}
                            </SubmitButton>

                            <p
                                className="text-muted-foreground text-center text-xs"
                                data-test="registration-privacy"
                            >
                                {t('guest.registration.privacy_notice', {
                                    organisation: tenant.displayName,
                                })}{' '}
                                <a
                                    href={privacyUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="underline underline-offset-2"
                                >
                                    {t('guest.registration.privacy_link')}
                                </a>
                            </p>
                        </>
                    )}
                </Form>
            </main>
        </div>
    );
}
