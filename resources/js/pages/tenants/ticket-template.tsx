import { Head, usePage } from '@inertiajs/react';
import BrandFileField from '@/components/brand-file-field';
import Heading from '@/components/heading';
import { TicketBodyGuide } from '@/components/ticket-template/ticket-body-guide';
import { TicketStubGuide } from '@/components/ticket-template/ticket-stub-guide';
import { TicketTemplateEditor } from '@/components/ticket-template/ticket-template-editor';
import { translate, useTranslation } from '@/hooks/use-translation';
import { can, Permission } from '@/lib/permissions';
import {
    edit as ticketTemplateEdit,
    update as ticketTemplateUpdate,
} from '@/routes/tenants/ticket-template';
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
 * liste des accompagnateurs), apercu en direct.
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
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const canEdit = can(permissions, Permission.TenantBranding);

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
