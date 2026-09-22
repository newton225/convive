import { Form, Head } from '@inertiajs/react';
import { OfflineBanner } from '@/components/offline-banner';
import { useRef, useState } from 'react';
import LocaleSwitcher from '@/components/locale-switcher';
import {
    CompanionFields,
    Field,
    UnitSelect,
} from '@/components/registration-fields';
import { SubmitButton } from '@/components/submit-button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { store } from '@/routes/public/waitlist';
import type { PublicRegistrationTenant, PublicUnitOption } from '@/types';

type Props = {
    event: { name: string; companionLimit: number };
    tenant: PublicRegistrationTenant;
    units: PublicUnitOption[];
    token: string;
};

/**
 * README ecran 10 : rejoindre la liste d'attente quand l'evenement est complet (2.3). Mêmes
 * champs que le formulaire d'inscription (voir `resources/js/components/registration-fields.tsx`) :
 * l'invite n'aura rien a ressaisir le jour ou une place se libere.
 */
export default function PublicWaitlistJoin({
    event,
    tenant,
    units,
    token,
}: Props) {
    const { t } = useTranslation();
    const [companionIds, setCompanionIds] = useState<number[]>([]);
    const nextId = useRef(0);

    const brandStyle = {
        '--brand-primary': tenant.colors.primary,
        '--brand-secondary': tenant.colors.secondary,
    } as React.CSSProperties;

    return (
        <div
            style={brandStyle}
            className="bg-background flex min-h-screen flex-col"
        >
            <Head title={t('guest.waitlist.title')} />
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
                    <h1
                        className="text-2xl font-semibold"
                        style={{ color: 'var(--brand-primary)' }}
                    >
                        {t('guest.waitlist.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {event.name}
                    </p>
                    <p className="text-sm">{t('guest.waitlist.description')}</p>
                </div>

                <Form
                    {...store.form(token)}
                    className="space-y-6"
                    data-test="waitlist-form"
                >
                    {({ errors, processing }) => (
                        <>
                            <Card>
                                <CardContent className="space-y-4 pt-6">
                                    <Field
                                        id="name"
                                        label={t(
                                            'guest.registration.fields.name',
                                        )}
                                        error={errors.name}
                                    >
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            data-test="waitlist-name"
                                        />
                                    </Field>

                                    <Field
                                        id="phone"
                                        label={t(
                                            'guest.registration.fields.phone',
                                        )}
                                        error={errors.phone}
                                    >
                                        <Input
                                            id="phone"
                                            name="phone"
                                            type="tel"
                                            required
                                            data-test="waitlist-phone"
                                        />
                                    </Field>

                                    <Field
                                        id="unit_id"
                                        label={t(
                                            'guest.registration.fields.unit',
                                        )}
                                        error={errors.unit_id}
                                    >
                                        <UnitSelect
                                            name="unit_id"
                                            units={units}
                                            testId="waitlist-unit"
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

                            <SubmitButton
                                className="w-full"
                                processing={processing}
                                data-test="waitlist-submit"
                            >
                                {t('guest.waitlist.submit')}
                            </SubmitButton>
                        </>
                    )}
                </Form>
            </main>
        </div>
    );
}
