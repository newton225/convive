import { Form, Head, usePage } from '@inertiajs/react';
import BrandFileField from '@/components/brand-file-field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { SubmitButton } from '@/components/submit-button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { translate, useTranslation } from '@/hooks/use-translation';
import { can, Permission } from '@/lib/permissions';
import {
    branding as brandRoute,
    edit as organisationEdit,
    legal as legalRoute,
    subdomain as subdomainRoute,
} from '@/routes/tenants/organisation';
import type {
    BrandFileOption,
    LegalFormOption,
    TenantBranding,
    TenantOrganisation,
    TenantPermissions,
    Translations,
} from '@/types';

type Props = {
    tenant: TenantOrganisation;
    branding: TenantBranding;
    legalForms: LegalFormOption[];
    brandFiles: BrandFileOption[];
    permissions: TenantPermissions;
};

export default function Organisation({
    tenant,
    branding,
    legalForms,
    brandFiles,
    permissions,
}: Props) {
    const { t } = useTranslation();
    // Le depot d'un fichier passe par sa propre requete : ses erreurs arrivent par les erreurs
    // partagees de la page, pas par le formulaire des couleurs.
    const pageErrors = usePage().props.errors as Record<string, string>;

    const mayEditLegal = can(permissions, Permission.TenantLegal);
    const mayEditBrand = can(permissions, Permission.TenantBranding);
    const mayEditSubdomain = can(permissions, Permission.TenantDomain);

    return (
        <>
            <Head title={t('organisation.title')} />

            <h1 className="sr-only">{t('organisation.title')}</h1>

            <div className="flex flex-col space-y-8">
                <Heading
                    variant="small"
                    title={t('organisation.title')}
                    description={t('organisation.description')}
                />

                <PublishingState tenant={tenant} />

                {mayEditLegal ? (
                    <section className="space-y-4">
                        <Heading
                            variant="small"
                            title={t('organisation.sections.legal.title')}
                            description={t(
                                'organisation.sections.legal.description',
                            )}
                        />

                        <Form
                            setDefaultsOnSuccess
                            {...legalRoute.form(tenant.slug)}
                            className="space-y-6"
                        >
                            {({ errors, processing, isDirty }) => (
                                <>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            name="display_name"
                                            label={t(
                                                'organisation.fields.display_name',
                                            )}
                                            hint={t(
                                                'organisation.fields.display_name_hint',
                                            )}
                                            defaultValue={branding.displayName}
                                            error={errors.display_name}
                                        />

                                        <Field
                                            name="legal_name"
                                            label={t(
                                                'organisation.fields.legal_name',
                                            )}
                                            defaultValue={branding.legalName}
                                            error={errors.legal_name}
                                        />

                                        <div className="grid gap-2">
                                            <Label htmlFor="legal_form">
                                                {t(
                                                    'organisation.fields.legal_form',
                                                )}
                                            </Label>
                                            <Select
                                                name="legal_form"
                                                defaultValue={
                                                    branding.legalForm ??
                                                    undefined
                                                }
                                            >
                                                <SelectTrigger
                                                    id="legal_form"
                                                    className="w-full"
                                                    data-test="organisation-legal-form"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {legalForms.map((form) => (
                                                        <SelectItem
                                                            key={form.value}
                                                            value={form.value}
                                                        >
                                                            {form.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.legal_form}
                                            />
                                        </div>

                                        <Field
                                            name="representative_name"
                                            label={t(
                                                'organisation.fields.representative_name',
                                            )}
                                            hint={t(
                                                'organisation.fields.representative_name_hint',
                                            )}
                                            defaultValue={
                                                branding.representativeName
                                            }
                                            error={errors.representative_name}
                                        />

                                        <Field
                                            name="registration_number"
                                            label={t(
                                                'organisation.fields.registration_number',
                                            )}
                                            defaultValue={
                                                branding.registrationNumber
                                            }
                                            error={errors.registration_number}
                                        />

                                        <Field
                                            name="tax_number"
                                            label={t(
                                                'organisation.fields.tax_number',
                                            )}
                                            defaultValue={branding.taxNumber}
                                            error={errors.tax_number}
                                        />

                                        <Field
                                            name="address"
                                            label={t(
                                                'organisation.fields.address',
                                            )}
                                            defaultValue={branding.address}
                                            error={errors.address}
                                        />

                                        <Field
                                            name="city"
                                            label={t(
                                                'organisation.fields.city',
                                            )}
                                            defaultValue={branding.city}
                                            error={errors.city}
                                        />

                                        <Field
                                            name="country"
                                            label={t(
                                                'organisation.fields.country',
                                            )}
                                            defaultValue={branding.country}
                                            error={errors.country}
                                        />

                                        <Field
                                            name="email"
                                            type="email"
                                            label={t(
                                                'organisation.fields.email',
                                            )}
                                            defaultValue={branding.email}
                                            error={errors.email}
                                        />

                                        <Field
                                            name="phone"
                                            label={t(
                                                'organisation.fields.phone',
                                            )}
                                            defaultValue={branding.phone}
                                            error={errors.phone}
                                        />
                                    </div>

                                    <SubmitButton
                                        data-test="organisation-legal-submit"
                                        processing={processing}
                                        dirty={isDirty}
                                    >
                                        {t('common.actions.save')}
                                    </SubmitButton>
                                </>
                            )}
                        </Form>
                    </section>
                ) : null}

                {mayEditBrand ? (
                    <section className="space-y-4">
                        <Heading
                            variant="small"
                            title={t('organisation.sections.brand.title')}
                            description={t(
                                'organisation.sections.brand.description',
                            )}
                        />

                        <Form
                            setDefaultsOnSuccess
                            {...brandRoute.form(tenant.slug)}
                            className="space-y-6"
                        >
                            {({ errors, processing, isDirty }) => (
                                <>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            name="primary_color"
                                            type="color"
                                            label={t(
                                                'organisation.fields.primary_color',
                                            )}
                                            defaultValue={
                                                branding.colors.primary
                                            }
                                            error={errors.primary_color}
                                        />

                                        <Field
                                            name="secondary_color"
                                            type="color"
                                            label={t(
                                                'organisation.fields.secondary_color',
                                            )}
                                            defaultValue={
                                                branding.colors.secondary
                                            }
                                            error={errors.secondary_color}
                                        />
                                    </div>

                                    <SubmitButton
                                        data-test="organisation-brand-submit"
                                        processing={processing}
                                        dirty={isDirty}
                                    >
                                        {t('common.actions.save')}
                                    </SubmitButton>
                                </>
                            )}
                        </Form>

                        <Heading
                            variant="small"
                            title={t('organisation.files.section')}
                            description={t(
                                'organisation.files.section_description',
                            )}
                        />

                        <div className="grid gap-6 sm:grid-cols-2">
                            {brandFiles.map((file) => (
                                <BrandFileField
                                    key={file.value}
                                    tenantSlug={tenant.slug}
                                    file={file}
                                    url={branding.files[file.value] ?? null}
                                    error={pageErrors.file}
                                />
                            ))}
                        </div>
                    </section>
                ) : null}

                {mayEditSubdomain ? (
                    <section className="space-y-4">
                        <Heading
                            variant="small"
                            title={t('organisation.sections.subdomain.title')}
                            description={t(
                                'organisation.sections.subdomain.description',
                            )}
                        />

                        <Form
                            setDefaultsOnSuccess
                            {...subdomainRoute.form(tenant.slug)}
                            className="space-y-6"
                        >
                            {({ errors, processing, isDirty }) => (
                                <>
                                    <div className="grid max-w-md gap-2">
                                        <Label htmlFor="subdomain">
                                            {t('organisation.fields.subdomain')}
                                        </Label>
                                        <Input
                                            id="subdomain"
                                            name="subdomain"
                                            data-test="organisation-subdomain"
                                            defaultValue={
                                                tenant.subdomain ?? ''
                                            }
                                            autoComplete="off"
                                            spellCheck={false}
                                        />
                                        <InputError
                                            message={errors.subdomain}
                                        />
                                    </div>

                                    <SubmitButton
                                        data-test="organisation-subdomain-submit"
                                        processing={processing}
                                        dirty={isDirty}
                                    >
                                        {t('common.actions.save')}
                                    </SubmitButton>
                                </>
                            )}
                        </Form>
                    </section>
                ) : null}
            </div>
        </>
    );
}

function PublishingState({ tenant }: { tenant: TenantOrganisation }) {
    const { t } = useTranslation();

    if (tenant.isReadyToPublish) {
        return (
            <Badge variant="outline" data-test="organisation-ready">
                {t('organisation.publishing.ready')}
            </Badge>
        );
    }

    // Un espace personnel est un espace d'essai : on annonce ce qui manquera le jour de la
    // publication, on ne le presente pas comme une erreur.
    const missing = tenant.missingBeforePublishing.length;

    return (
        <p
            className="text-muted-foreground text-sm"
            data-test="organisation-incomplete"
        >
            {tenant.isPersonal
                ? t('organisation.publishing.trial')
                : t('organisation.publishing.incomplete', { count: missing })}
        </p>
    );
}

function Field({
    name,
    label,
    hint,
    defaultValue,
    error,
    type = 'text',
}: {
    name: string;
    label: string;
    hint?: string;
    defaultValue: string | null;
    error?: string;
    type?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                data-test={`organisation-${name}`}
                defaultValue={defaultValue ?? ''}
            />
            {hint ? (
                <p className="text-muted-foreground text-xs">{hint}</p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}

Organisation.layout = (props: {
    tenant: { name: string; slug: string };
    translations: Translations;
}) => ({
    narrow: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'organisation.title'),
            href: organisationEdit(props.tenant.slug),
        },
    ],
});
