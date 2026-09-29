import { Head, Link, router } from '@inertiajs/react';
import { useId, useState } from 'react';
import Heading from '@/components/heading';
import { NonceStyle } from '@/components/nonce-style';
import { CheckboxRow } from '@/components/settings/checkbox-row';
import { SettingsSection } from '@/components/settings/settings-section';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { can, Permission } from '@/lib/permissions';
import {
    edit as eventEdit,
    index as eventsIndex,
} from '@/routes/tenants/events';
import {
    edit as settingsEdit,
    update as settingsUpdate,
} from '@/routes/tenants/events/settings';
import { index as seatingIndex } from '@/routes/tenants/events/seating';
import { edit as organisationEdit } from '@/routes/tenants/organisation';
import type {
    EventReminders,
    EventRules,
    EventSettingsEvent,
    TenantPermissions,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string };
    event: EventSettingsEvent;
    colors: { primary: string; secondary: string };
    colorsOverridden: boolean;
    permissions: TenantPermissions;
    reminders: EventReminders;
    rules: EventRules;
    unenforcedRules: (keyof EventRules)[];
};

const ReminderKeys = ['d7', 'd2', 'd1', 'dayOf'] as const;

const RuleKeys = [
    'scheduledSend',
    'autoSeating',
    'allowWithoutProof',
    'proofLegibility',
    'purgeOnExhaustion',
    'temporaryHold',
    'phoneVerification',
] as const;

const ReminderLabels = {
    d7: 'event_settings.reminders.d7',
    d2: 'event_settings.reminders.d2',
    d1: 'event_settings.reminders.d1',
    dayOf: 'event_settings.reminders.day_of',
} as const;

const RuleLabels = {
    scheduledSend: 'event_settings.rules.scheduled_send',
    autoSeating: 'event_settings.rules.auto_seating',
    allowWithoutProof: 'event_settings.rules.allow_without_proof',
    proofLegibility: 'event_settings.rules.proof_legibility',
    purgeOnExhaustion: 'event_settings.rules.purge_on_exhaustion',
    temporaryHold: 'event_settings.rules.temporary_hold',
    phoneVerification: 'event_settings.rules.phone_verification',
} as const;

// Seules les regles appliquees ont une aide : expliquer une regle qui n'agit pas encore, dont le
// sens n'est pas tranche, serait decrire un comportement qui n'existe pas.
const RuleHelp: Partial<Record<keyof typeof RuleLabels, string>> = {
    scheduledSend: 'event_settings.rules_help.scheduled_send',
    autoSeating: 'event_settings.rules_help.auto_seating',
    purgeOnExhaustion: 'event_settings.rules_help.purge_on_exhaustion',
    phoneVerification: 'event_settings.rules_help.phone_verification',
};

const ReminderFields: Record<(typeof ReminderKeys)[number], string> = {
    d7: 'reminder_j7_enabled',
    d2: 'reminder_j2_enabled',
    d1: 'reminder_j1_enabled',
    dayOf: 'reminder_day_of_enabled',
};

const RuleFields: Record<(typeof RuleKeys)[number], string> = {
    scheduledSend: 'rule_scheduled_send',
    autoSeating: 'rule_auto_seating',
    allowWithoutProof: 'rule_allow_without_proof',
    proofLegibility: 'rule_proof_legibility',
    purgeOnExhaustion: 'rule_purge_on_exhaustion',
    temporaryHold: 'rule_temporary_hold',
    phoneVerification: 'rule_phone_verification',
};

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-wrap items-baseline justify-between gap-2 py-1.5 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}

function ColorSwatch({ color }: { color: string }) {
    // Couleur calculee a l'execution : posee via une balise <style> nonce'e et scopee, pas
    // l'attribut `style` (voir NonceStyle).
    const scopeClass = `color-swatch-${useId().replace(/:/g, '')}`;

    return (
        <span
            className={`${scopeClass} size-5 rounded border`}
            aria-hidden="true"
        >
            <NonceStyle
                selector={`.${scopeClass}`}
                declarations={{ background: color }}
            />
        </span>
    );
}

/**
 * README ecran 24 : les reglages d'un evenement. Identite visuelle, places, echeances, rappels,
 * regles. Trois regles s'enregistrent sans encore rien gouverner (`unenforcedRules`, voir
 * `EventSettingsController`) : le sens exact qu'elles devraient avoir n'est pas assez precis dans
 * le README pour deviner sans risquer une regle fausse.
 */
export default function EventSettings({
    tenant,
    event,
    colors,
    colorsOverridden,
    permissions,
    reminders: initialReminders,
    rules: initialRules,
    unenforcedRules,
}: Props) {
    const { t, locale } = useTranslation();
    const ruleHelp = (key: keyof typeof RuleLabels) => {
        const helpKey = RuleHelp[key];

        return helpKey ? t(helpKey) : undefined;
    };
    const [reminders, setReminders] = useState(initialReminders);
    const [rules, setRules] = useState(initialRules);
    const [processing, setProcessing] = useState(false);
    const canEditEvent = can(permissions, Permission.EventsUpdate);
    const dirty =
        JSON.stringify(reminders) !== JSON.stringify(initialReminders) ||
        JSON.stringify(rules) !== JSON.stringify(initialRules);

    const date = (value: string | null) =>
        value
            ? formatDateTime(value, locale)
            : t('event_settings.deadlines.not_set');

    const save = () => {
        const payload: Record<string, boolean> = {};

        for (const key of ReminderKeys) {
            payload[ReminderFields[key]] = reminders[key];
        }

        for (const key of RuleKeys) {
            payload[RuleFields[key]] = rules[key];
        }

        setProcessing(true);
        router.patch(settingsUpdate([tenant.slug, event.id]).url, payload, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <Head title={t('event_settings.title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title={t('event_settings.title')}
                        description={event.name}
                    />
                    {canEditEvent ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={eventEdit([tenant.slug, event.id])}>
                                {t('event_settings.edit_event')}
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <SettingsSection
                    testId="event-settings-identity"
                    title={t('event_settings.identity.title')}
                    description={t('event_settings.identity.description')}
                >
                    <div className="mb-3 flex items-center gap-3">
                        {event.visualUrl ? (
                            <img
                                src={event.visualUrl}
                                alt={t('event_settings.identity.visual_alt', {
                                    name: event.name,
                                })}
                                className="aspect-video w-40 rounded-md object-cover"
                                data-test="event-settings-visual"
                            />
                        ) : (
                            <div className="bg-muted text-muted-foreground flex aspect-video w-40 items-center justify-center rounded-md p-2 text-center text-xs">
                                {t('event_settings.identity.no_visual')}
                            </div>
                        )}
                        <p className="text-muted-foreground text-sm">
                            {colorsOverridden
                                ? t('event_settings.identity.own_colors')
                                : t('event_settings.identity.brand_colors')}
                        </p>
                    </div>
                    <dl>
                        <div className="flex items-center justify-between gap-2 py-1.5 text-sm">
                            <dt className="text-muted-foreground">
                                {t('event_settings.identity.primary')}
                            </dt>
                            <dd className="flex items-center gap-2 font-mono text-xs">
                                <ColorSwatch color={colors.primary} />
                                {colors.primary}
                            </dd>
                        </div>
                        <div className="flex items-center justify-between gap-2 py-1.5 text-sm">
                            <dt className="text-muted-foreground">
                                {t('event_settings.identity.secondary')}
                            </dt>
                            <dd className="flex items-center gap-2 font-mono text-xs">
                                <ColorSwatch color={colors.secondary} />
                                {colors.secondary}
                            </dd>
                        </div>
                    </dl>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {canEditEvent ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={eventEdit([tenant.slug, event.id])}
                                    data-test="event-settings-edit-visual"
                                >
                                    {t(
                                        'event_settings.identity.edit_event_visual',
                                    )}
                                </Link>
                            </Button>
                        ) : null}
                        {!colorsOverridden ? (
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={organisationEdit(tenant.slug)}>
                                    {t('event_settings.identity.edit')}
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </SettingsSection>

                <SettingsSection
                    testId="event-settings-seating"
                    title={t('event_settings.seating.title')}
                    description={t('event_settings.seating.description')}
                >
                    <dl>
                        <Row
                            label={t('event_settings.seating.tables')}
                            value={String(event.tableCount)}
                        />
                        <Row
                            label={t('event_settings.seating.per_table')}
                            value={String(event.seatsPerTable)}
                        />
                        <Row
                            label={t('event_settings.seating.capacity')}
                            value={String(event.capacity)}
                        />
                    </dl>
                    <Button
                        variant="outline"
                        size="sm"
                        className="mt-3"
                        asChild
                    >
                        <Link href={seatingIndex([tenant.slug, event.id])}>
                            {t('event_settings.seating.open')}
                        </Link>
                    </Button>
                </SettingsSection>

                <SettingsSection
                    testId="event-settings-deadlines"
                    title={t('event_settings.deadlines.title')}
                    description={t('event_settings.deadlines.description')}
                >
                    <dl>
                        <Row
                            label={t(
                                'event_settings.deadlines.registration_deadline',
                            )}
                            value={date(event.registrationDeadline)}
                        />
                        <Row
                            label={t('event_settings.deadlines.purge_at')}
                            value={date(event.purgeAt)}
                        />
                        <Row
                            label={t(
                                'event_settings.deadlines.invitations_send_at',
                            )}
                            value={date(event.invitationsSendAt)}
                        />
                        <Row
                            label={t('event_settings.deadlines.hold_duration')}
                            value={t('event_settings.deadlines.minutes', {
                                count: event.holdDurationMinutes,
                            })}
                        />
                    </dl>
                </SettingsSection>

                <SettingsSection
                    testId="event-settings-reminders"
                    title={t('event_settings.reminders.title')}
                    description={t('event_settings.reminders.description')}
                >
                    {ReminderKeys.map((key) => (
                        <CheckboxRow
                            key={key}
                            id={`reminder-${key}`}
                            label={t(ReminderLabels[key])}
                            checked={reminders[key]}
                            disabled={!canEditEvent}
                            onChange={(checked) =>
                                setReminders((current) => ({
                                    ...current,
                                    [key]: checked,
                                }))
                            }
                        />
                    ))}
                </SettingsSection>

                <SettingsSection
                    testId="event-settings-rules"
                    title={t('event_settings.rules.title')}
                >
                    {RuleKeys.map((key) => (
                        <CheckboxRow
                            key={key}
                            id={`rule-${key}`}
                            label={t(RuleLabels[key])}
                            help={ruleHelp(key)}
                            checked={rules[key]}
                            disabled={!canEditEvent}
                            hint={
                                unenforcedRules.includes(key)
                                    ? t('event_settings.rules.not_enforced')
                                    : undefined
                            }
                            onChange={(checked) =>
                                setRules((current) => ({
                                    ...current,
                                    [key]: checked,
                                }))
                            }
                        />
                    ))}
                </SettingsSection>

                {canEditEvent ? (
                    <div>
                        <SubmitButton
                            type="button"
                            processing={processing}
                            dirty={dirty}
                            onClick={save}
                            data-test="event-settings-save"
                        >
                            {t('common.actions.save')}
                        </SubmitButton>
                    </div>
                ) : null}
            </div>
        </>
    );
}

EventSettings.layout = (props: {
    tenant: { slug: string };
    event: { id: number };
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'events.title'),
            href: eventsIndex(props.tenant.slug),
        },
        {
            title: translate(props.translations, 'event_settings.title'),
            href: settingsEdit([props.tenant.slug, props.event.id]),
        },
    ],
});
