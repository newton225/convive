import { Form, Head } from '@inertiajs/react';
import { AlertTriangle, Send } from 'lucide-react';
import { useState } from 'react';
import { CopyButton } from '@/components/copy-button';
import { AnnouncementButton } from '@/components/events/announcement-button';
import { TemplatePicker } from '@/components/events/template-picker';
import Heading from '@/components/heading';
import { TableGroupsField } from '@/components/events/table-groups-field';
import { HelpTip } from '@/components/help-tip';
import { LabelWithHelp } from '@/components/label-with-help';
import { RequiredFieldsNote } from '@/components/required-fields-note';
import { ProductTourButton } from '@/components/product-tour-button';
import EventVisualField from '@/components/events/event-visual-field';
import { PublishEventDialog } from '@/components/events/publish-event-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useGettingStartedReturn } from '@/hooks/use-getting-started-return';
import { translate, useTranslation } from '@/hooks/use-translation';
import { edit, index, store, update } from '@/routes/tenants/events';
import type {
    EventDetails,
    EventPaymentAccountOption,
    EventTemplate,
    EventTemplateOption,
    TenantSummary,
    Translations,
} from '@/types';

type Props = {
    tenant: TenantSummary;
    event: EventDetails | null;
    paymentAccounts: EventPaymentAccountOption[];
    defaults: { companionLimit: number; holdDurationMinutes: number };
    tenantColors: { primary: string; secondary: string };
    // Creation seulement : les modeles proposes et celui applique.
    templates?: EventTemplateOption[];
    template?: EventTemplate | null;
};

export default function EventForm({
    tenant,
    event,
    paymentAccounts,
    defaults,
    tenantColors,
    templates = [],
    template = null,
}: Props) {
    const { t } = useTranslation();
    const gettingStartedReturn = useGettingStartedReturn();
    const [confirmingPublish, setConfirmingPublish] = useState(false);
    // Valeurs de depart des champs : l'evenement edite, sinon le modele choisi a la creation.
    const prefill = event ?? template;
    const [overrideColors, setOverrideColors] = useState<boolean>(
        prefill?.primaryColor !== null && prefill?.primaryColor !== undefined,
    );

    const action = event
        ? update.form([tenant.slug, event.id])
        : store.form(tenant.slug, gettingStartedReturn);

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

                    <ProductTourButton tour="first_event" autoStart={!event} />

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
                            {/* Une fois publie, il n'y a plus rien a publier : le bouton laisse
                                place au lien lui-meme, seul geste encore utile. */}
                            {event.isPublished ? (
                                <>
                                    <Badge
                                        data-test="event-published"
                                        data-tour="event-publish"
                                    >
                                        {t('events.badges.published')}
                                    </Badge>
                                    {event.publicUrl ? (
                                        <CopyButton
                                            value={event.publicUrl}
                                            label={t(
                                                'events.actions.copy_link',
                                            )}
                                            size="default"
                                            testId="event-copy-link"
                                        />
                                    ) : null}
                                </>
                            ) : (
                                <>
                                    <Button
                                        data-test="event-publish"
                                        data-tour="event-publish"
                                        disabled={!event.isReadyToPublish}
                                        onClick={() =>
                                            setConfirmingPublish(true)
                                        }
                                    >
                                        <Send /> {t('events.actions.publish')}
                                    </Button>
                                    <PublishEventDialog
                                        open={confirmingPublish}
                                        onOpenChange={setConfirmingPublish}
                                        tenantSlug={tenant.slug}
                                        event={event}
                                        paymentAccounts={paymentAccounts}
                                        firstPublication={
                                            !tenant.paymentDelayActive
                                        }
                                    />
                                </>
                            )}
                            {event.isPublished ? (
                                <AnnouncementButton
                                    tenantSlug={tenant.slug}
                                    event={event}
                                />
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

                {event === null ? (
                    <TemplatePicker
                        tenantSlug={tenant.slug}
                        templates={templates}
                        activeId={template?.sourceId ?? null}
                    />
                ) : null}

                {/* Cle par modele : les champs ne sont pas controles, ils doivent repartir des
                    valeurs du modele choisi plutot que garder celles du precedent. */}
                <Form
                    key={
                        event?.id ?? `template-${template?.sourceId ?? 'blank'}`
                    }
                    {...action}
                    setDefaultsOnSuccess
                    className="space-y-8"
                >
                    {({ errors, processing, isDirty }) => (
                        <>
                            <RequiredFieldsNote />
                            <Step
                                index={1}
                                title={t('events.steps.identity')}
                                description={t('events.sections.identity')}
                            >
                                <div className="grid items-start gap-4 sm:grid-cols-2">
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
                                        defaultValue={prefill?.subtitle ?? ''}
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
                                        defaultValue={prefill?.venue ?? ''}
                                        error={errors.venue}
                                    />
                                    <div className="sm:col-span-2">
                                        <Field
                                            name="venue_address"
                                            label={t(
                                                'events.fields.venue_address',
                                            )}
                                            defaultValue={
                                                prefill?.venueAddress ?? ''
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
                                        <div className="grid items-start gap-4 sm:grid-cols-2">
                                            <Field
                                                name="primary_color"
                                                type="color"
                                                label={t(
                                                    'events.fields.primary_color',
                                                )}
                                                defaultValue={
                                                    prefill?.primaryColor ??
                                                    tenantColors.primary
                                                }
                                                error={errors.primary_color}
                                                help={t(
                                                    'events.help.primary_color',
                                                )}
                                            />
                                            <Field
                                                name="secondary_color"
                                                type="color"
                                                label={t(
                                                    'events.fields.secondary_color',
                                                )}
                                                defaultValue={
                                                    prefill?.secondaryColor ??
                                                    tenantColors.secondary
                                                }
                                                error={errors.secondary_color}
                                                help={t(
                                                    'events.help.secondary_color',
                                                )}
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
                                <TableGroupsField
                                    defaultGroups={prefill?.tableGroups ?? []}
                                    errors={errors}
                                />

                                <div className="grid items-start gap-4 sm:grid-cols-2">
                                    <Field
                                        name="price_per_person"
                                        type="number"
                                        label={t(
                                            'events.fields.price_per_person',
                                        )}
                                        defaultValue={String(
                                            prefill?.pricePerPerson ?? 0,
                                        )}
                                        error={errors.price_per_person}
                                        help={t('events.help.price_per_person')}
                                    />
                                    <Field
                                        name="companion_limit"
                                        type="number"
                                        label={t(
                                            'events.fields.companion_limit',
                                        )}
                                        defaultValue={String(
                                            prefill?.companionLimit ??
                                                defaults.companionLimit,
                                        )}
                                        error={errors.companion_limit}
                                        help={t('events.help.companion_limit')}
                                    />
                                </div>

                                <fieldset
                                    className="space-y-2"
                                    data-tour="event-payment-accounts"
                                >
                                    <legend className="flex items-center gap-1.5 text-sm font-medium">
                                        {t('events.fields.payment_accounts')}
                                        <HelpTip
                                            subject={t(
                                                'events.fields.payment_accounts',
                                            )}
                                        >
                                            {t('events.help.payment_accounts')}
                                        </HelpTip>
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
                                                defaultChecked={prefill?.paymentAccountIds.includes(
                                                    account.id,
                                                )}
                                            />
                                            {account.channelLabel && (
                                                <Badge variant="secondary">
                                                    {account.channelLabel}
                                                </Badge>
                                            )}
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
                                <div className="grid items-start gap-4 sm:grid-cols-2">
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
                                        help={t(
                                            'events.help.registration_deadline',
                                        )}
                                    />
                                    <Field
                                        name="purge_at"
                                        type="datetime-local"
                                        label={t('events.fields.purge_at')}
                                        defaultValue={event?.purgeAt ?? ''}
                                        error={errors.purge_at}
                                        help={t('events.help.purge_at')}
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
                                        help={t(
                                            'events.help.invitations_send_at',
                                        )}
                                    />
                                    <Field
                                        name="hold_duration_minutes"
                                        type="number"
                                        label={t(
                                            'events.fields.hold_duration_minutes',
                                        )}
                                        defaultValue={String(
                                            prefill?.holdDurationMinutes ??
                                                defaults.holdDurationMinutes,
                                        )}
                                        error={errors.hold_duration_minutes}
                                        help={t(
                                            'events.help.hold_duration_minutes',
                                        )}
                                    />
                                </div>
                            </Step>

                            <SubmitButton
                                data-test="event-submit"
                                data-tour="event-submit"
                                processing={processing}
                                dirty={isDirty}
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
        <section className="space-y-4" data-tour={`event-step-${step}`}>
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
    help,
}: {
    name: string;
    label: string;
    defaultValue: string;
    error?: string;
    type?: string;
    placeholder?: string;
    required?: boolean;
    help?: string;
}) {
    return (
        <div className="grid gap-2">
            <LabelWithHelp
                htmlFor={name}
                label={label}
                help={help}
                required={required}
            />
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
