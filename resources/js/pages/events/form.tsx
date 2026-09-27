import { Form, Head, router } from '@inertiajs/react';
import { AlertTriangle, Megaphone, Send } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import EventVisualField from '@/components/events/event-visual-field';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { translate, useTranslation } from '@/hooks/use-translation';
import {
    announce,
    edit,
    index,
    publish,
    store,
    update,
} from '@/routes/tenants/events';
import { withdraw as withdrawAnnouncement } from '@/routes/tenants/events/announce';
import type {
    EventDetails,
    EventPaymentAccountOption,
    TenantSummary,
    Translations,
} from '@/types';

type Props = {
    tenant: TenantSummary;
    event: EventDetails | null;
    paymentAccounts: EventPaymentAccountOption[];
    defaults: { companionLimit: number; holdDurationMinutes: number };
    tenantColors: { primary: string; secondary: string };
};

export default function EventForm({
    tenant,
    event,
    paymentAccounts,
    defaults,
    tenantColors,
}: Props) {
    const { t } = useTranslation();
    const [overrideColors, setOverrideColors] = useState<boolean>(
        event?.primaryColor !== null && event?.primaryColor !== undefined,
    );

    const action = event
        ? update.form([tenant.slug, event.id])
        : store.form(tenant.slug);

    return (
        <>
            <Head title={event ? event.name : t('events.actions.create')} />

            <h1 className="sr-only">
                {event ? event.name : t('events.actions.create')}
            </h1>

            <div className="flex flex-col space-y-8">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title={event ? event.name : t('events.actions.create')}
                        description={t('events.description')}
                    />

                    {event ? (
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">
                                {event.statusLabel}
                            </Badge>
                            {event.isAnnounced ? (
                                <Badge variant="outline">
                                    {t('events.badges.announced')}
                                </Badge>
                            ) : null}
                            <Button
                                data-test="event-publish"
                                disabled={!event.isReadyToPublish}
                                onClick={() =>
                                    router.post(
                                        publish([tenant.slug, event.id]).url,
                                    )
                                }
                            >
                                <Send /> {t('events.actions.publish')}
                            </Button>
                            {event.isPublished ? (
                                <Button
                                    variant="outline"
                                    data-test={
                                        event.isAnnounced
                                            ? 'event-withdraw-announcement'
                                            : 'event-announce'
                                    }
                                    onClick={() =>
                                        event.isAnnounced
                                            ? router.delete(
                                                  withdrawAnnouncement([
                                                      tenant.slug,
                                                      event.id,
                                                  ]).url,
                                              )
                                            : router.post(
                                                  announce([
                                                      tenant.slug,
                                                      event.id,
                                                  ]).url,
                                              )
                                    }
                                >
                                    <Megaphone />
                                    {t(
                                        event.isAnnounced
                                            ? 'events.actions.withdraw_announcement'
                                            : 'events.actions.announce',
                                    )}
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
                </div>

                {event && !event.isReadyToPublish && !event.isPublished ? (
                    <p
                        className="flex items-start gap-2 rounded-lg border p-3 text-sm"
                        data-test="event-publish-blocked"
                    >
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        {t('events.publishing.blocked')}
                    </p>
                ) : null}

                {event?.isPublished ? (
                    <p className="text-muted-foreground text-sm">
                        {t('events.publishing.frozen_subdomain')}
                    </p>
                ) : null}

                {event?.isPublished ? (
                    <p
                        className="text-muted-foreground text-sm"
                        data-test="event-announcing-description"
                    >
                        {t(
                            event.isAnnounced
                                ? 'events.announcing.announced'
                                : 'events.announcing.description',
                        )}
                    </p>
                ) : null}

                <Form {...action} className="space-y-8">
                    {({ errors, processing }) => (
                        <>
                            <Step
                                index={1}
                                title={t('events.steps.identity')}
                                description={t('events.sections.identity')}
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        name="name"
                                        label={t('events.fields.name')}
                                        placeholder={t(
                                            'events.fields.name_placeholder',
                                        )}
                                        defaultValue={event?.name ?? ''}
                                        error={errors.name}
                                        required
                                    />
                                    <Field
                                        name="subtitle"
                                        label={t('events.fields.subtitle')}
                                        defaultValue={event?.subtitle ?? ''}
                                        error={errors.subtitle}
                                    />
                                    <Field
                                        name="starts_at"
                                        type="datetime-local"
                                        label={t('events.fields.starts_at')}
                                        defaultValue={
                                            event?.startsAtLocal ?? ''
                                        }
                                        error={errors.starts_at}
                                    />
                                    <Field
                                        name="venue"
                                        label={t('events.fields.venue')}
                                        defaultValue={event?.venue ?? ''}
                                        error={errors.venue}
                                    />
                                    <div className="sm:col-span-2">
                                        <Field
                                            name="venue_address"
                                            label={t(
                                                'events.fields.venue_address',
                                            )}
                                            defaultValue={
                                                event?.venueAddress ?? ''
                                            }
                                            error={errors.venue_address}
                                        />
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={overrideColors}
                                            data-test="event-override-colors"
                                            onCheckedChange={(checked) =>
                                                setOverrideColors(
                                                    checked === true,
                                                )
                                            }
                                        />
                                        {t('events.fields.override_colors')}
                                    </label>

                                    {overrideColors ? (
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Field
                                                name="primary_color"
                                                type="color"
                                                label={t(
                                                    'events.fields.primary_color',
                                                )}
                                                defaultValue={
                                                    event?.primaryColor ??
                                                    tenantColors.primary
                                                }
                                                error={errors.primary_color}
                                            />
                                            <Field
                                                name="secondary_color"
                                                type="color"
                                                label={t(
                                                    'events.fields.secondary_color',
                                                )}
                                                defaultValue={
                                                    event?.secondaryColor ??
                                                    tenantColors.secondary
                                                }
                                                error={errors.secondary_color}
                                            />
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground text-xs">
                                            {t(
                                                'events.fields.override_colors_hint',
                                            )}
                                        </p>
                                    )}
                                </div>

                                {event ? (
                                    <EventVisualField
                                        tenantSlug={tenant.slug}
                                        eventId={event.id}
                                        url={event.visualUrl}
                                    />
                                ) : (
                                    <p className="text-muted-foreground text-xs">
                                        {t('events.visual.hint')}
                                    </p>
                                )}
                            </Step>

                            <Step
                                index={2}
                                title={t('events.steps.seating')}
                                description={t('events.sections.seating')}
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        name="table_count"
                                        type="number"
                                        label={t('events.fields.table_count')}
                                        defaultValue={String(
                                            event?.tableCount ?? 0,
                                        )}
                                        error={errors.table_count}
                                    />
                                    <Field
                                        name="seats_per_table"
                                        type="number"
                                        label={t(
                                            'events.fields.seats_per_table',
                                        )}
                                        defaultValue={String(
                                            event?.seatsPerTable ?? 0,
                                        )}
                                        error={errors.seats_per_table}
                                    />
                                    <Field
                                        name="price_per_person"
                                        type="number"
                                        label={t(
                                            'events.fields.price_per_person',
                                        )}
                                        defaultValue={String(
                                            event?.pricePerPerson ?? 0,
                                        )}
                                        error={errors.price_per_person}
                                    />
                                    <Field
                                        name="companion_limit"
                                        type="number"
                                        label={t(
                                            'events.fields.companion_limit',
                                        )}
                                        defaultValue={String(
                                            event?.companionLimit ??
                                                defaults.companionLimit,
                                        )}
                                        error={errors.companion_limit}
                                    />
                                </div>

                                <fieldset className="space-y-2">
                                    <legend className="text-sm font-medium">
                                        {t('events.fields.payment_accounts')}
                                    </legend>

                                    {paymentAccounts.map((account) => (
                                        <label
                                            key={account.id}
                                            className="flex items-start gap-2 text-sm"
                                        >
                                            <Checkbox
                                                name="payment_accounts[]"
                                                value={String(account.id)}
                                                data-test="event-payment-account"
                                                defaultChecked={event?.paymentAccountIds.includes(
                                                    account.id,
                                                )}
                                            />
                                            <span>
                                                {account.label}
                                                {account.accountNumber
                                                    ? ` · ${account.accountNumber}`
                                                    : ''}
                                            </span>
                                        </label>
                                    ))}

                                    <InputError
                                        message={errors.payment_accounts}
                                    />
                                </fieldset>
                            </Step>

                            <Step
                                index={3}
                                title={t('events.steps.deadlines')}
                                description={t('events.sections.deadlines')}
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        name="registration_deadline"
                                        type="datetime-local"
                                        label={t(
                                            'events.fields.registration_deadline',
                                        )}
                                        defaultValue={
                                            event?.registrationDeadline ?? ''
                                        }
                                        error={errors.registration_deadline}
                                    />
                                    <Field
                                        name="purge_at"
                                        type="datetime-local"
                                        label={t('events.fields.purge_at')}
                                        defaultValue={event?.purgeAt ?? ''}
                                        error={errors.purge_at}
                                    />
                                    <Field
                                        name="invitations_send_at"
                                        type="datetime-local"
                                        label={t(
                                            'events.fields.invitations_send_at',
                                        )}
                                        defaultValue={
                                            event?.invitationsSendAt ?? ''
                                        }
                                        error={errors.invitations_send_at}
                                    />
                                    <Field
                                        name="hold_duration_minutes"
                                        type="number"
                                        label={t(
                                            'events.fields.hold_duration_minutes',
                                        )}
                                        defaultValue={String(
                                            event?.holdDurationMinutes ??
                                                defaults.holdDurationMinutes,
                                        )}
                                        error={errors.hold_duration_minutes}
                                    />
                                </div>
                            </Step>

                            <SubmitButton
                                data-test="event-submit"
                                processing={processing}
                            >
                                {t('common.actions.save')}
                            </SubmitButton>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

function Step({
    index: step,
    title,
    description,
    children,
}: {
    index: number;
    title: string;
    description: string;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-4">
            <div className="flex items-baseline gap-2">
                <span className="text-muted-foreground text-sm">{step}.</span>
                <Heading
                    variant="small"
                    title={title}
                    description={description}
                />
            </div>
            {children}
        </section>
    );
}

function Field({
    name,
    label,
    defaultValue,
    error,
    type = 'text',
    placeholder,
    required = false,
}: {
    name: string;
    label: string;
    defaultValue: string;
    error?: string;
    type?: string;
    placeholder?: string;
    required?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                data-test={`event-${name}`}
                defaultValue={defaultValue}
                placeholder={placeholder}
                required={required}
            />
            <InputError message={error} />
        </div>
    );
}

EventForm.layout = (props: {
    tenant: TenantSummary;
    event: EventDetails | null;
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'events.title'),
            href: index(props.tenant.slug),
        },
        ...(props.event
            ? [
                  {
                      title: props.event.name,
                      href: edit([props.tenant.slug, props.event.id]),
                  },
              ]
            : []),
    ],
});
