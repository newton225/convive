import { Head, usePage } from '@inertiajs/react';
import BrandFileField from '@/components/brand-file-field';
import { DownloadButton } from '@/components/download-button';
import Heading from '@/components/heading';
import { TicketBodyGuide } from '@/components/ticket-template/ticket-body-guide';
import { TicketStubGuide } from '@/components/ticket-template/ticket-stub-guide';
import { TicketTemplateEditor } from '@/components/ticket-template/ticket-template-editor';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format-date';
import { can, Permission } from '@/lib/permissions';
import {
    edit as ticketTemplateEdit,
    update as ticketTemplateUpdate,
} from '@/routes/tenants/ticket-template';
import { checklists } from '@/routes/tenants/events/registrations/export';
import type {
    BrandFileOption,
    TenantPermissions,
    TicketBrand,
    TicketElements,
    TicketModel,
    TicketTemplateEvent,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string; name: string };
    permissions: TenantPermissions;
    brand: TicketBrand;
    model: TicketModel;
    elements: TicketElements;
    events: TicketTemplateEvent[];
    backgroundFile: BrandFileOption;
    bodyBackgroundFile: BrandFileOption;
};

/**
 * README ecran 15 : le gabarit du billet. Modeles, elements activables (logo, cachet, signature,
 * liste des accompagnateurs), apercu en direct, impression des listes de controle par table.
 * Modele et elements s'enregistrent sur la marque de l'organisation (`tenant_brandings`) et
 * s'appliquent au vrai billet de l'invite (`resources/js/pages/public/registration-show.tsx`).
 */
export default function TicketTemplate({
    tenant,
    permissions,
    brand,
    model,
    elements,
    events,
    backgroundFile,
    bodyBackgroundFile,
}: Props) {
    const { t, locale } = useTranslation();
    const { errors } = usePage().props;
    const canEdit = can(permissions, Permission.TenantBranding);
    const canExport = can(permissions, Permission.RegistrationsExport);

    return (
        <>
            <Head title={t('ticket_template.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('ticket_template.title')}
                    description={`${t('ticket_template.description')} ${t('ticket_template.event.global_note')}`}
                />

                <TicketTemplateEditor
                    tenantSlug={tenant.slug}
                    brand={brand}
                    model={model}
                    elements={elements}
                    previewEvent={{
                        name: events[0]?.name ?? tenant.name,
                        startsAt: events[0]?.startsAt ?? null,
                        venue: events[0]?.venue ?? null,
                    }}
                    editable={canEdit}
                    canSave={canEdit}
                    saveUrl={ticketTemplateUpdate(tenant.slug).url}
                    backgrounds={
                        canEdit ? (
                            <>
                                <BrandFileField
                                    tenantSlug={tenant.slug}
                                    file={backgroundFile}
                                    url={brand.backgroundUrl}
                                    error={errors.file}
                                    cropGuide={<TicketStubGuide />}
                                    cropGuideHint={t(
                                        'organisation.files.crop.stub_guide',
                                    )}
                                />
                                <BrandFileField
                                    tenantSlug={tenant.slug}
                                    file={bodyBackgroundFile}
                                    url={brand.bodyBackgroundUrl}
                                    error={errors.file}
                                    cropGuide={<TicketBodyGuide />}
                                    cropGuideHint={t(
                                        'organisation.files.crop.body_guide',
                                    )}
                                />
                            </>
                        ) : undefined
                    }
                />

                <Card data-test="ticket-print">
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('ticket_template.print.title')}
                        </CardTitle>
                        <p className="text-muted-foreground text-sm">
                            {t('ticket_template.print.description')}
                        </p>
                    </CardHeader>
                    <CardContent>
                        {events.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                {t('ticket_template.print.empty')}
                            </p>
                        ) : (
                            <ul className="divide-border divide-y">
                                {events.map((event) => (
                                    <li
                                        key={event.id}
                                        className="flex items-center justify-between gap-3 py-2"
                                    >
                                        <span className="text-sm">
                                            <span className="font-medium">
                                                {event.name}
                                            </span>
                                            {event.startsAt ? (
                                                <span className="text-muted-foreground ml-2">
                                                    {formatDate(
                                                        event.startsAt,
                                                        locale,
                                                    )}
                                                </span>
                                            ) : null}
                                        </span>
                                        {canExport ? (
                                            <DownloadButton
                                                variant="outline"
                                                size="sm"
                                                href={
                                                    checklists([
                                                        tenant.slug,
                                                        event.id,
                                                    ]).url
                                                }
                                                data-test="ticket-print-link"
                                            >
                                                {t(
                                                    'ticket_template.print.button',
                                                )}
                                            </DownloadButton>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TicketTemplate.layout = (props: {
    tenant: { slug: string };
    translations: Translations;
}) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'ticket_template.title'),
            href: ticketTemplateEdit(props.tenant.slug),
        },
    ],
});
