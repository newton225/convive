import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus, Users } from 'lucide-react';
import CreateTenantModal from '@/components/create-tenant-modal';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useIsMobile } from '@/hooks/use-mobile';
import { switchMethod } from '@/routes/tenants';
import type { Tenant } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

type TenantSwitcherProps = {
    inHeader?: boolean;
};

export function TenantSwitcher({ inHeader = false }: TenantSwitcherProps) {
    const { t } = useTranslation();
    const page = usePage();
    const isMobile = useIsMobile();
    const currentTenant = page.props.currentTenant;
    const tenants = page.props.tenants ?? [];

    const switchTenant = (tenant: Tenant) => {
        const previousTenantSlug = currentTenant?.slug;

        router.visit(switchMethod(tenant.slug), {
            onFinish: () => {
                if (!previousTenantSlug || typeof window === 'undefined') {
                    router.reload();

                    return;
                }

                const currentUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`;
                const segment = `/${previousTenantSlug}`;

                if (currentUrl.includes(segment)) {
                    router.visit(
                        currentUrl.replace(segment, `/${tenant.slug}`),
                        {
                            replace: true,
                        },
                    );

                    return;
                }

                router.reload();
            },
        });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    data-test="tenant-switcher-trigger"
                    className={
                        inHeader
                            ? 'h-8 gap-1 px-2'
                            : 'data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground w-full justify-start px-2 has-[>svg]:px-2'
                    }
                >
                    <Users
                        className={
                            inHeader
                                ? 'hidden'
                                : 'hidden size-4 shrink-0 group-data-[collapsible=icon]:block'
                        }
                    />
                    <div
                        className={
                            inHeader
                                ? 'grid flex-1 text-left text-sm leading-tight'
                                : 'grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden'
                        }
                    >
                        <span
                            className={
                                inHeader
                                    ? 'max-w-[120px] truncate font-medium'
                                    : 'truncate font-semibold'
                            }
                        >
                            {currentTenant?.name ??
                                t('tenants.switcher.placeholder')}
                        </span>
                    </div>
                    <ChevronsUpDown
                        className={
                            inHeader
                                ? 'size-4 opacity-50'
                                : 'ml-auto group-data-[collapsible=icon]:hidden'
                        }
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className={
                    inHeader
                        ? 'w-56'
                        : 'w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg'
                }
                side={inHeader ? undefined : isMobile ? 'bottom' : 'right'}
                align={inHeader ? 'end' : 'start'}
                sideOffset={inHeader ? undefined : 4}
            >
                <DropdownMenuLabel className="text-muted-foreground text-xs">
                    {t('tenants.switcher.label')}
                </DropdownMenuLabel>
                {tenants.map((tenant) => (
                    <DropdownMenuItem
                        key={tenant.id}
                        data-test="tenant-switcher-item"
                        className={
                            inHeader
                                ? 'cursor-pointer gap-2'
                                : 'cursor-pointer gap-2 p-2'
                        }
                        onSelect={() => switchTenant(tenant)}
                    >
                        <span className="grid min-w-0">
                            <span className="truncate">{tenant.name}</span>
                            {tenant.planName ? (
                                <span className="text-muted-foreground truncate text-xs">
                                    {tenant.planName}
                                </span>
                            ) : null}
                        </span>
                        {currentTenant?.id === tenant.id && (
                            <Check
                                className={
                                    inHeader
                                        ? 'ml-auto size-4'
                                        : 'ml-auto h-4 w-4'
                                }
                            />
                        )}
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <CreateTenantModal>
                    <DropdownMenuItem
                        data-test="tenant-switcher-new-tenant"
                        className={
                            inHeader
                                ? 'cursor-pointer gap-2'
                                : 'cursor-pointer gap-2 p-2'
                        }
                        onSelect={(event) => event.preventDefault()}
                    >
                        <Plus className={inHeader ? 'size-4' : 'h-4 w-4'} />
                        <span className="text-muted-foreground">
                            {t('tenants.actions.create')}
                        </span>
                    </DropdownMenuItem>
                </CreateTenantModal>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
