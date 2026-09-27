import { Head, Link, router } from '@inertiajs/react';
import { Copy, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DeleteProfileModal from '@/components/delete-profile-modal';
import Heading from '@/components/heading';
import { PermissionMatrix } from '@/components/profiles/permission-matrix';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { translate, useTranslation } from '@/hooks/use-translation';
import { edit, index as tenantsIndex } from '@/routes/tenants';
import {
    create,
    duplicate,
    edit as editProfile,
    index,
} from '@/routes/tenants/profiles';
import type {
    PermissionDomain,
    Tenant,
    TenantProfile,
    Translations,
} from '@/types';

type Props = {
    tenant: Pick<Tenant, 'id' | 'name' | 'slug'>;
    profiles: TenantProfile[];
    catalogue: PermissionDomain[];
};

export default function TenantProfiles({ tenant, profiles, catalogue }: Props) {
    const { t } = useTranslation();
    const [deleting, setDeleting] = useState<TenantProfile | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const openDelete = (profile: TenantProfile) => {
        setDeleting(profile);
        setDeleteOpen(true);
    };

    return (
        <>
            <Head title={t('profiles.title')} />

            <h1 className="sr-only">{t('profiles.title')}</h1>

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={t('profiles.title')}
                        description={t('profiles.description')}
                    />

                    <Button data-test="profile-create-button" asChild>
                        <Link href={create(tenant.slug)}>
                            <Plus /> {t('profiles.actions.create')}
                        </Link>
                    </Button>
                </div>

                <div className="space-y-3">
                    {profiles.map((profile) => (
                        <div
                            key={profile.id}
                            data-test="profile-row"
                            className="flex items-start justify-between gap-4 rounded-lg border p-4"
                        >
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {profile.name}
                                    </span>
                                    {profile.isSystem ? (
                                        <Badge variant="secondary">
                                            {t('profiles.badges.system')}
                                        </Badge>
                                    ) : null}
                                    {profile.requiresTwoFactor ? (
                                        <Badge variant="outline">
                                            <ShieldCheck className="h-3 w-3" />
                                            {t('profiles.badges.two_factor')}
                                        </Badge>
                                    ) : null}
                                    <Badge variant="outline">
                                        {t('profiles.badges.members', {
                                            count: profile.memberCount,
                                        })}
                                    </Badge>
                                </div>
                                {profile.description ? (
                                    <p className="text-muted-foreground text-sm">
                                        {profile.description}
                                    </p>
                                ) : null}
                                <p className="text-muted-foreground text-sm">
                                    {t('profiles.badges.permissions', {
                                        count: profile.permissions.length,
                                    })}
                                </p>
                            </div>

                            <TooltipProvider>
                                <div className="flex shrink-0 items-center gap-2">
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                data-test="profile-duplicate-button"
                                                onClick={() =>
                                                    router.post(
                                                        duplicate([
                                                            tenant.slug,
                                                            profile.id,
                                                        ]).url,
                                                    )
                                                }
                                            >
                                                <Copy className="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>
                                                {t(
                                                    'profiles.actions.duplicate',
                                                )}
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>

                                    {profile.isSystem ? null : (
                                        <>
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                data-test="profile-edit-button"
                                                asChild
                                            >
                                                <Link
                                                    href={editProfile([
                                                        tenant.slug,
                                                        profile.id,
                                                    ])}
                                                >
                                                    {t('profiles.actions.edit')}
                                                </Link>
                                            </Button>

                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="profile-delete-button"
                                                        onClick={() =>
                                                            openDelete(profile)
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>
                                                        {t(
                                                            'profiles.actions.delete',
                                                        )}
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </>
                                    )}
                                </div>
                            </TooltipProvider>
                        </div>
                    ))}
                </div>

                <PermissionMatrix profiles={profiles} catalogue={catalogue} />
            </div>

            <DeleteProfileModal
                tenantSlug={tenant.slug}
                profile={deleting}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

TenantProfiles.layout = (props: {
    tenant: Pick<Tenant, 'name' | 'slug'>;
    translations: Translations;
}) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'tenants.index.title'),
            href: tenantsIndex(),
        },
        {
            title: props.tenant.name,
            href: edit(props.tenant.slug),
        },
        {
            title: translate(props.translations, 'profiles.title'),
            href: index(props.tenant.slug),
        },
    ],
});
