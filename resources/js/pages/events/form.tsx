import { Form, Head } from '@inertiajs/react';
import { AlertTriangle, Info, Send } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ComponentRef } from 'react';
import { CopyButton } from '@/components/copy-button';
import { FormDirtyReporter } from '@/components/events/form-dirty-reporter';
import { DisabledReason } from '@/components/disabled-reason';
import { AnnouncementButton } from '@/components/events/announcement-button';
import { TemplatePicker } from '@/components/events/template-picker';
import Heading from '@/components/heading';
import { CompanionLimitField } from '@/components/events/companion-limit-field';
import { TableGroupsField } from '@/components/events/table-groups-field';
import { VenueMapField } from '@/components/events/venue-map-field';
import { HelpTip } from '@/components/help-tip';
import { LabelWithHelp } from '@/components/label-with-help';
import { RequiredFieldsNote } from '@/components/required-fields-note';
import { ProductTourButton } from '@/components/product-tour-button';
import EventVisualField from '@/components/events/event-visual-field';
import { EventShowcasePreview } from '@/components/events/event-showcase-preview';
import { HoldDurationField } from '@/components/events/hold-duration-field';
import { PriceCategoriesField } from '@/components/events/price-categories-field';
import { PublishEventDialog } from '@/components/events/publish-event-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { StickySaveBar } from '@/components/sticky-save-bar';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { validatedFieldName } from '@/lib/validated-field';
import { useGettingStartedReturn } from '@/hooks/use-getting-started-return';
import { useNotifyFormChange } from '@/hooks/use-notify-form-change';
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
    defaults: {
        companionLimit: number;
        holdDurationMinutes: number;
        // Bornes de la duree de reservation, reglees depuis la console.
        holdDurationMin: number;
        holdDurationMax: number;
    };
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
    const formRef =
        useRef<ComponentRef<typeof Form<Record<string, string>>>>(null);
    const gettingStartedReturn = useGettingStartedReturn();
    const [confirmingPublish, setConfirmingPublish] = useState(false);
    // Les invites sont assis a des tables, sauf pour un rassemblement sans table (plein air...).
    const [seatsAtTables, setSeatsAtTables] = useState(
        (event ?? template)?.seatsAtTables ?? true,
    );
    const [freeSeats, setFreeSeats] = useState(
        String((event ?? template)?.freeSeats ?? ''),
    );
    const [tableSeats, setTableSeats] = useState(0);
    const roomSeats = seatsAtTables ? tableSeats : Number(freeSeats) || 0;
    // La case pilote un champ cache : le formulaire n'entend pas son changement sans ce signal.
    const seatingMode = useNotifyFormChange<HTMLDivElement>(seatsAtTables);
    const [allFree, setAllFree] = useState(false);
    // Des modifications pas encore enregistrees : publier publierait l'ancienne version.
    const [unsaved, setUnsaved] = useState(false);
    // Pourquoi « Publier » est grise : d'abord des modifications a enregistrer, puis ce qui manque.
    const publishBlockedReason = !event
        ? null
        : unsaved
          ? t('events.publishing.unsaved')
          : event.isReadyToPublish
            ? null
            : t('events.publishing.blocked', {
                  items: event.missingBeforePublishing
                      .map((key) => t(`events.missing_publish.${key}`))
                      .join(', '),
              });
    // L'apercu de la carte de vitrine suit la saisie : les champs ne sont pas controles, on relit
    // le formulaire a chaque changement.
    const [previewName, setPreviewName] = useState(event?.name ?? '');
    const [previewStartsAt, setPreviewStartsAt] = useState<string | null>(
        event?.startsAtLocal ?? null,
    );
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
                                    <DisabledReason
                                        reason={publishBlockedReason}
                                    >
                                        <Button
                                            data-test="event-publish"
                                            data-tour="event-publish"
                                            disabled={
                                                !event.isReadyToPublish ||
                                                unsaved
                                            }
                                            onClick={() =>
                                                setConfirmingPublish(true)
                                            }
                                        >
                                            <Send />{' '}
                                            {t('events.actions.publish')}
                                        </Button>
                                    </DisabledReason>
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
                        role="status"
                        className="flex items-start gap-3 rounded-lg border border-amber-500/60 bg-amber-500/10 p-4 text-sm font-medium text-amber-700 dark:text-amber-300"
                        data-test="event-publish-blocked"
                    >
                        <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
                        {t('events.publishing.blocked', {
                            items: event.missingBeforePublishing
                                .map((key) =>
                                    t(`events.missing_publish.${key}`),
                                )
                                .join(', '),
                        })}
                    </p>
                ) : null}

                {event?.isPublished ? (
                    <div
                        className="bg-muted/40 text-muted-foreground flex items-start gap-3 rounded-lg border p-4 text-sm"
                        data-test="event-published-notes"
                    >
                        <Info className="mt-0.5 size-4 shrink-0" />
                        <ul className="space-y-1.5">
                            <li>{t('events.publishing.frozen_subdomain')}</li>
                            <li data-test="event-changes-not-notified">
                                {t('events.publishing.changes_not_notified')}
                            </li>
                            <li data-test="event-announcing-description">
                                {t(
                                    event.isAnnounced
                                        ? 'events.announcing.announced'
                                        : 'events.announcing.description',
                                )}
                            </li>
                            {!event.isAnnounced &&
                            event.missingBeforeAnnouncing.length > 0 ? (
                                <li
                                    role="status"
                                    className="font-medium text-amber-600 dark:text-amber-400"
                                    data-test="event-announce-blocked"
                                >
                                    {t('events.announcing.blocked', {
                                        items: event.missingBeforeAnnouncing
                                            .map((key) =>
                                                t(
                                                    `events.missing_announce.${key}`,
                                                ),
                                            )
                                            .join(', '),
                                    })}
                                </li>
                            ) : null}
                        </ul>
                    </div>
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
                <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    <Form
                        ref={formRef}
                        // Validation en temps reel : quand on quitte un champ, le serveur rejoue les
                        // regles de la Form Request sur lui seul (route `precognitive`).
                        validationTimeout={400}
                        onBlur={(left) => {
                            const field = validatedFieldName(left.target);

                            if (field !== null) {
                                formRef.current?.validate(field);
                            }
                        }}
                        key={
                            event?.id ??
                            `template-${template?.sourceId ?? 'blank'}`
                        }
                        {...action}
                        setDefaultsOnSuccess
                        className="space-y-8"
                        onChange={(changed) => {
                            const data = new FormData(changed.currentTarget);
                            const text = (field: string) => {
                                const value = data.get(field);

                                return typeof value === 'string' ? value : '';
                            };
                            setPreviewName(text('name'));
                            setPreviewStartsAt(text('starts_at') || null);
                        }}
                    >
                        {({ errors, processing, isDirty }) => (
                            <>
                                <FormDirtyReporter
                                    dirty={isDirty}
                                    onChange={setUnsaved}
                                />
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
                                            defaultValue={
                                                prefill?.subtitle ?? ''
                                            }
                                            error={errors.subtitle}
                                        />
                                        <Field
                                            name="starts_at"
                                            forPublishing
                                            type="datetime-local"
                                            label={t('events.fields.starts_at')}
                                            defaultValue={
                                                event?.startsAtLocal ?? ''
                                            }
                                            error={errors.starts_at}
                                        />
                                        <Field
                                            name="ends_at"
                                            type="datetime-local"
                                            label={t('events.fields.ends_at')}
                                            help={t('events.help.ends_at')}
                                            defaultValue={
                                                event?.endsAtLocal ?? ''
                                            }
                                            error={errors.ends_at}
                                        />
                                        <Field
                                            name="entry_opens_minutes_before"
                                            type="number"
                                            label={t(
                                                'events.fields.entry_opens_minutes_before',
                                            )}
                                            help={t(
                                                'events.help.entry_opens_minutes_before',
                                            )}
                                            defaultValue={
                                                event?.entryOpensMinutesBefore !=
                                                null
                                                    ? String(
                                                          event.entryOpensMinutesBefore,
                                                      )
                                                    : ''
                                            }
                                            error={
                                                errors.entry_opens_minutes_before
                                            }
                                        />
                                        <Field
                                            name="entry_grace_minutes"
                                            type="number"
                                            label={t(
                                                'events.fields.entry_grace_minutes',
                                            )}
                                            help={t(
                                                'events.help.entry_grace_minutes',
                                            )}
                                            defaultValue={String(
                                                event?.entryGraceMinutes ?? 30,
                                            )}
                                            error={errors.entry_grace_minutes}
                                        />
                                        <Field
                                            name="venue"
                                            forPublishing
                                            label={t('events.fields.venue')}
                                            help={t('events.help.venue')}
                                            defaultValue={prefill?.venue ?? ''}
                                            error={errors.venue}
                                        />
                                        <div className="sm:col-span-2">
                                            <Field
                                                name="venue_address"
                                                label={t(
                                                    'events.fields.venue_address',
                                                )}
                                                help={t(
                                                    'events.help.venue_address',
                                                )}
                                                defaultValue={
                                                    prefill?.venueAddress ?? ''
                                                }
                                                error={errors.venue_address}
                                            />
                                        </div>
                                        <div className="sm:col-span-2">
                                            <VenueMapField
                                                defaultValue={
                                                    prefill?.venueMapUrl ?? ''
                                                }
                                                error={errors.venue_map_url}
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
                                                    error={
                                                        errors.secondary_color
                                                    }
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
                                    <div
                                        ref={seatingMode}
                                        className="space-y-2"
                                    >
                                        <label className="flex items-center gap-2 text-sm font-medium">
                                            <DisabledReason
                                                reason={
                                                    event?.seatingModeLocked
                                                        ? t(
                                                              'events.errors.seating_mode_locked',
                                                          )
                                                        : null
                                                }
                                            >
                                                <Checkbox
                                                    checked={seatsAtTables}
                                                    disabled={
                                                        event?.seatingModeLocked ===
                                                        true
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setSeatsAtTables(
                                                            checked === true,
                                                        )
                                                    }
                                                    data-test="event-seats-at-tables"
                                                />
                                            </DisabledReason>
                                            {t('events.fields.seats_at_tables')}
                                            <HelpTip
                                                subject={t(
                                                    'events.fields.seats_at_tables',
                                                )}
                                            >
                                                {t(
                                                    'events.help.seats_at_tables',
                                                )}
                                            </HelpTip>
                                        </label>
                                        {/* La case n'est pas un champ de formulaire fiable : la valeur part par ce champ cache. */}
                                        <input
                                            type="hidden"
                                            name="seats_at_tables"
                                            value={seatsAtTables ? '1' : '0'}
                                        />
                                        <InputError
                                            message={errors.seats_at_tables}
                                        />
                                    </div>

                                    {seatsAtTables ? (
                                        <TableGroupsField
                                            onSeatsChange={setTableSeats}
                                            defaultGroups={
                                                prefill?.tableGroups ?? []
                                            }
                                            errors={errors}
                                        />
                                    ) : (
                                        <div className="max-w-xs">
                                            <Label
                                                htmlFor="free_seats"
                                                required
                                            >
                                                {t('events.fields.free_seats')}
                                            </Label>
                                            <Input
                                                id="free_seats"
                                                name="free_seats"
                                                type="number"
                                                min={1}
                                                max={100000}
                                                required
                                                value={freeSeats}
                                                onChange={(event) =>
                                                    setFreeSeats(
                                                        event.target.value.replace(
                                                            /^0+(?=\d)/,
                                                            '',
                                                        ),
                                                    )
                                                }
                                                data-test="event-free-seats"
                                                className="mt-2"
                                            />
                                            <InputError
                                                message={errors.free_seats}
                                            />
                                        </div>
                                    )}

                                    {event?.isPublished &&
                                    roomSeats > 0 &&
                                    roomSeats < event.capacity ? (
                                        <p
                                            className="text-sm font-medium text-amber-600 dark:text-amber-400"
                                            role="status"
                                            data-test="event-capacity-reduced"
                                        >
                                            {t(
                                                'events.publishing.capacity_reduced',
                                                {
                                                    from: event.capacity,
                                                    to: roomSeats,
                                                    taken: event.occupiedSeats,
                                                },
                                            )}
                                        </p>
                                    ) : null}

                                    <div className="grid items-start gap-4 sm:grid-cols-2">
                                        <div className="space-y-2 sm:col-span-2">
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {t(
                                                        'events.fields.price_categories',
                                                    )}
                                                </p>
                                                <p className="text-muted-foreground text-sm">
                                                    {t(
                                                        'events.help.price_categories',
                                                    )}
                                                </p>
                                            </div>
                                            {/* Apres un enregistrement, les tarifs reviennent du serveur avec leur identifiant :
                                                la cle recharge la liste, sinon un second envoi recreerait les memes lignes. */}
                                            <PriceCategoriesField
                                                key={(
                                                    event?.priceCategories ?? []
                                                )
                                                    .map(({ id }) => id)
                                                    .join('-')}
                                                defaultCategories={
                                                    prefill?.priceCategories ??
                                                    []
                                                }
                                                defaultPrice={
                                                    prefill?.pricePerPerson ?? 0
                                                }
                                                capacity={roomSeats}
                                                onFreeChange={setAllFree}
                                                errors={errors}
                                            />
                                        </div>
                                        <CompanionLimitField
                                            defaultValue={
                                                prefill?.companionLimit ??
                                                defaults.companionLimit
                                            }
                                            max={defaults.companionLimit}
                                            error={errors.companion_limit}
                                        />
                                    </div>

                                    <fieldset
                                        className="space-y-2"
                                        data-tour="event-payment-accounts"
                                    >
                                        <legend className="flex items-center gap-1.5 text-sm font-medium">
                                            {t(
                                                'events.fields.payment_accounts',
                                            )}
                                            <HelpTip
                                                subject={t(
                                                    'events.fields.payment_accounts',
                                                )}
                                            >
                                                {t(
                                                    'events.help.payment_accounts',
                                                )}
                                            </HelpTip>
                                        </legend>

                                        {allFree ? (
                                            <p
                                                className="text-muted-foreground text-sm"
                                                data-test="event-payment-accounts-optional"
                                            >
                                                {t(
                                                    'events.payment_accounts_not_needed',
                                                )}
                                            </p>
                                        ) : null}

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
                                                event?.registrationDeadline ??
                                                ''
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
                                        <HoldDurationField
                                            defaultValue={
                                                prefill?.holdDurationMinutes ??
                                                defaults.holdDurationMinutes
                                            }
                                            min={defaults.holdDurationMin}
                                            max={defaults.holdDurationMax}
                                            error={errors.hold_duration_minutes}
                                        />
                                    </div>
                                </Step>

                                <StickySaveBar testId="event-submit-bar">
                                    <SubmitButton
                                        data-test="event-submit"
                                        data-tour="event-submit"
                                        processing={processing}
                                        dirty={isDirty}
                                    >
                                        {t('common.actions.save')}
                                    </SubmitButton>
                                </StickySaveBar>
                            </>
                        )}
                    </Form>
                    <aside className="lg:sticky lg:top-4">
                        <EventShowcasePreview
                            name={previewName}
                            organisationName={tenant.displayName}
                            startsAt={previewStartsAt}
                            visualUrl={event?.visualUrl ?? null}
                        />
                    </aside>
                </div>
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
    forPublishing = false,
    help,
}: {
    name: string;
    label: string;
    defaultValue: string;
    error?: string;
    type?: string;
    placeholder?: string;
    required?: boolean;
    // Exige pour publier, pas pour enregistrer.
    forPublishing?: boolean;
    help?: string;
}) {
    return (
        <div className="grid gap-2">
            <LabelWithHelp
                htmlFor={name}
                label={label}
                help={help}
                required={required}
                forPublishing={forPublishing}
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
