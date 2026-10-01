import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import BrandFileField from '@/components/brand-file-field';
import Heading from '@/components/heading';
import { TicketBodyGuide } from '@/components/ticket-template/ticket-body-guide';
import { TicketStubGuide } from '@/components/ticket-template/ticket-stub-guide';
import { TicketTemplateEditor } from '@/components/ticket-template/ticket-template-editor';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { translate, useTranslation } from '@/hooks/use-translation';
import { index as eventsIndex } from '@/routes/tenants/events';
import {
    edit as eventTicketTemplateEdit,
    update as eventTicketTemplateUpdate,
} from '@/routes/tenants/events/ticket-template';
import {
    destroy as destroyBackground,
    store as storeBackground,
} from '@/routes/tenants/events/ticket-template/files';
import { edit as organisationTicketTemplateEdit } from '@/routes/tenants/ticket-template';
import type {
    BrandFileOption,
    TenantPermissions,
    TicketBrand,
    TicketCardEvent,
    TicketElements,
    TicketModel,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string; name: string };
    event: TicketCardEvent & { id: number };
    permissions: TenantPermissions;
    // Vrai quand l'evenement applique son propre gabarit plutot que celui de l'organisation.
    enabled: boolean;
    // Ce que l'evenement a regle pour lui, ou a defaut le gabarit de l'organisation.
    model: TicketModel;
    elements: TicketElements;
    organisation: {
        model: TicketModel;
        elements: TicketElements;
        backgroundUrl: string | null;
        bodyBackgroundUrl: string | null;
    };
    // Les fonds y sont ceux propres a l'evenement ; nuls, ceux de l'organisation s'appliquent.
    brand: TicketBrand;
    backgroundFile: BrandFileOption;
    bodyBackgroundFile: BrandFileOption;
};

/**
 * README ecran 15, pour un evenement : son propre gabarit du billet. Active, il l'emporte sur
 * celui de l'organisation ; desactive, l'ecran montre celui de l'organisation, en lecture seule,
 * puisque c'est lui qui s'applique. La regle est appliquee par le serveur
 * (`Event::ticketTemplate()`), l'ecran ne fait que la refleter.
 */
export default function EventTicketTemplate({
    tenant,
    event,
    enabled: savedEnabled,
    model,
    elements,
    organisation,
    brand,
    backgroundFile,
    bodyBackgroundFile,
}: Props) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const [enabled, setEnabled] = useState(savedEnabled);
    const target: [string, number] = [tenant.slug, event.id];

    const fallbackHint = t('ticket_template.event.background_fallback');
    const withFallback = (file: BrandFileOption): BrandFileOption => ({
        ...file,
        hint: `${file.hint} ${fallbackHint}`,
    });
    const urlsOf = (file: BrandFileOption) => ({
        store: storeBackground([...target, file.value]).url,
        destroy: destroyBackground([...target, file.value]).url,
    });

    return (
        <>
            <Head title={t('ticket_template.event.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('ticket_template.event.title')}
                    description={t('ticket_template.event.description', {
                        event: event.name,
                    })}
                />

                <TicketTemplateEditor
                    // Remonte quand la source change : l'editeur repart du gabarit affiche.
                    key={enabled ? 'own' : 'organisation'}
                    tenantSlug={tenant.slug}
                    brand={{
                        ...brand,
                        backgroundUrl: enabled
                            ? (brand.backgroundUrl ??
                              organisation.backgroundUrl)
                            : organisation.backgroundUrl,
                        bodyBackgroundUrl: enabled
                            ? (brand.bodyBackgroundUrl ??
                              organisation.bodyBackgroundUrl)
                            : organisation.bodyBackgroundUrl,
                    }}
                    model={enabled ? model : organisation.model}
                    elements={enabled ? elements : organisation.elements}
                    previewEvent={event}
                    editable={enabled}
                    canSave
                    saveUrl={eventTicketTemplateUpdate(target).url}
                    extraPayload={{ ticket_template_enabled: enabled }}
                    extraDirty={enabled !== savedEnabled}
                    intro={
                        <Card data-test="event-ticket-template-toggle">
                            <CardContent className="space-y-3 pt-6">
                                <div className="flex items-start gap-2">
                                    <Checkbox
                                        id="ticket-template-enabled"
                                        checked={enabled}
                                        onCheckedChange={(checked) =>
                                            setEnabled(checked === true)
                                        }
                                        data-test="event-ticket-template-enabled"
                                    />
                                    <div className="space-y-1">
                                        <Label htmlFor="ticket-template-enabled">
                                            {t('ticket_template.event.enable')}
                                        </Label>
                                        <p className="text-muted-foreground text-xs">
                                            {t(
                                                'ticket_template.event.enable_hint',
                                            )}
                                        </p>
                                    </div>
                                </div>
                                {enabled ? null : (
                                    <div className="space-y-2">
                                        <p className="text-sm">
                                            {t(
                                                'ticket_template.event.organisation_applies',
                                            )}
                                        </p>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={organisationTicketTemplateEdit(
                                                    tenant.slug,
                                                )}
                                            >
                                                {t(
                                                    'ticket_template.event.organisation_link',
                                                )}
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    }
                    backgrounds={
                        !enabled ? undefined : savedEnabled ? (
                            <>
                                <BrandFileField
                                    tenantSlug={tenant.slug}
                                    file={withFallback(backgroundFile)}
                                    url={brand.backgroundUrl}
                                    error={errors.file}
                                    urls={urlsOf(backgroundFile)}
                                    removalDescription={t(
                                        'ticket_template.event.remove_confirm',
                                    )}
                                    cropGuide={<TicketStubGuide />}
                                    cropGuideHint={t(
                                        'organisation.files.crop.stub_guide',
                                    )}
                                />
                                <BrandFileField
                                    tenantSlug={tenant.slug}
                                    file={withFallback(bodyBackgroundFile)}
                                    url={brand.bodyBackgroundUrl}
                                    error={errors.file}
                                    urls={urlsOf(bodyBackgroundFile)}
                                    removalDescription={t(
                                        'ticket_template.event.remove_confirm',
                                    )}
                                    cropGuide={<TicketBodyGuide />}
                                    cropGuideHint={t(
                                        'organisation.files.crop.body_guide',
                                    )}
                                />
                            </>
                        ) : (
                            // Les fonds se deposent sans passer par « Enregistrer » : le gabarit
                            // doit d'abord etre active cote serveur, sinon ils ne s'appliqueraient
                            // a rien.
                            <p className="text-muted-foreground text-sm">
                                {t('ticket_template.event.save_first')}
                            </p>
                        )
                    }
                />
            </div>
        </>
    );
}

EventTicketTemplate.layout = (props: {
    tenant: { slug: string };
    event: { id: number };
    translations: Translations;
}) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'events.title'),
            href: eventsIndex(props.tenant.slug),
        },
        {
            title: translate(props.translations, 'ticket_template.event.title'),
            href: eventTicketTemplateEdit([props.tenant.slug, props.event.id]),
        },
    ],
});
