import { Head, Link } from '@inertiajs/react';
import { Eye, LogOut, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import CreateTenantModal from '@/components/create-tenant-modal';
import Heading from '@/components/heading';
import LeaveTenantModal from '@/components/leave-tenant-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit, index } from '@/routes/tenants';
import { translate, useTranslation } from '@/hooks/use-translation';
import type { Tenant, Translations } from '@/types';

type Props = {
    tenants: Tenant[];
};

export default function TenantsIndex({ tenants }: Props) {
    const { t } = useTranslation();
    const [leaveTenantDialogOpen, setLeaveTenantDialogOpen] = useState(false);
    const [tenantLeaving, setTenantLeaving] = useState<Tenant | null>(null);

    const openLeaveTenantDialog = (tenant: Tenant) => {
        setTenantLeaving(tenant);
        setLeaveTenantDialogOpen(true);
    };

    return (
        <>
            <Head title={t('tenants.index.title')} />

            <h1 className="sr-only">{t('tenants.index.title')}</h1>

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={t('tenants.index.title')}
                        description={t('tenants.index.description')}
                    />

                    <CreateTenantModal>
                        <Button data-test="tenants-new-tenant-button">
                            <Plus /> {t('tenants.actions.create')}
                        </Button>
                    </CreateTenantModal>
                </div>

                <div className="space-y-3">
                    {tenants.map((tenant) => {
                        const canLeaveTenant =
                            !tenant.isPersonal && !tenant.isOwner;

                        return (
                            <div
                                key={tenant.id}
                                data-test="tenant-row"
                                className="flex items-center justify-between gap-4 rounded-lg border p-4"
                            >
                                <div className="flex items-center gap-4">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">
                                                {tenant.name}
                                            </span>
                                            {tenant.isPersonal ? (
                                                <Badge variant="secondary">
                                                    {t(
                                                        'tenants.badge.personal',
                                                    )}
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <span className="text-muted-foreground text-sm">
                                            {tenant.profileName}
                                        </span>
                                    </div>
                                </div>

                                <TooltipProvider>
                                    <div className="flex items-center gap-2">
                                        {canLeaveTenant ? (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="tenant-leave-button"
                                                        onClick={() =>
                                                            openLeaveTenantDialog(
                                                                tenant,
                                                            )
                                                        }
                                                    >
                                                        <LogOut className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>
                                                        {t(
                                                            'tenants.actions.leave',
                                                        )}
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        ) : null}

                                        {!tenant.isOwner ? (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="tenant-view-button"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={edit(
                                                                tenant.slug,
                                                            )}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>
                                                        {t(
                                                            'tenants.actions.view',
                                                        )}
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        ) : (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="tenant-edit-button"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={edit(
                                                                tenant.slug,
                                                            )}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>
                                                        {t(
                                                            'tenants.actions.edit',
                                                        )}
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        )}
                                    </div>
                                </TooltipProvider>
                            </div>
                        );
                    })}

                    {tenants.length === 0 ? (
                        <p className="text-muted-foreground py-8 text-center">
                            {t('tenants.index.empty')}
                        </p>
                    ) : null}
                </div>
            </div>

            <LeaveTenantModal
                tenant={tenantLeaving}
                open={leaveTenantDialogOpen}
                onOpenChange={setLeaveTenantDialogOpen}
            />
        </>
    );
}

TenantsIndex.layout = ({ translations }: { translations: Translations }) => ({
    breadcrumbs: [
        {
            title: translate(translations, 'tenants.index.title'),
            href: index(),
        },
    ],
});
