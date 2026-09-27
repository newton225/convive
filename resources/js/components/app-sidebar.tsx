import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CreditCard,
    LayoutGrid,
    ScanLine,
    ScrollText,
    Settings,
    ShieldCheck,
    Ticket,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { PlanUsage } from '@/components/plan-usage';
import { TenantSwitcher } from '@/components/tenant-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { can, Permission, type PermissionValue } from '@/lib/permissions';
import { dashboard } from '@/routes';
import { index as auditIndex } from '@/routes/tenants/audit';
import { show as billingShow } from '@/routes/tenants/billing';
import { index as profilesIndex } from '@/routes/tenants/profiles';
import { edit as ticketTemplateEdit } from '@/routes/tenants/ticket-template';
import { index as eventsIndex } from '@/routes/tenants/events';
import { entryControl, edit as tenantEdit } from '@/routes/tenants';
import { useTranslation } from '@/hooks/use-translation';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const { t } = useTranslation();
    const tenant = page.props.currentTenant;
    const dashboardUrl = tenant ? dashboard(tenant.slug) : '/';

    const permissions = page.props.tenantPermissions;
    const allowed = (permission: PermissionValue) =>
        permissions !== null && can(permissions, permission);

    const mainNavItems: NavItem[] = [
        {
            title: t('navigation.dashboard'),
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        ...(tenant
            ? [
                  {
                      title: t('events.title'),
                      href: eventsIndex(tenant.slug),
                      icon: CalendarDays,
                  },
                  ...(allowed(Permission.ScanPerform)
                      ? [
                            {
                                title: t('navigation.entry_control'),
                                href: entryControl(tenant.slug),
                                icon: ScanLine,
                            },
                        ]
                      : []),
                  ...(allowed(Permission.TenantBranding)
                      ? [
                            {
                                title: t('navigation.ticket_template'),
                                href: ticketTemplateEdit(tenant.slug),
                                icon: Ticket,
                            },
                        ]
                      : []),
                  ...(allowed(Permission.ProfilesManage)
                      ? [
                            {
                                title: t('navigation.profiles'),
                                href: profilesIndex(tenant.slug),
                                icon: ShieldCheck,
                            },
                        ]
                      : []),
                  ...(allowed(Permission.AuditView)
                      ? [
                            {
                                title: t('navigation.audit'),
                                href: auditIndex(tenant.slug),
                                icon: ScrollText,
                            },
                        ]
                      : []),
                  ...(allowed(Permission.BillingView)
                      ? [
                            {
                                title: t('navigation.billing'),
                                href: billingShow(tenant.slug),
                                icon: CreditCard,
                            },
                        ]
                      : []),
                  {
                      title: t('navigation.organisation'),
                      href: tenantEdit(tenant.slug),
                      icon: Settings,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TenantSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <PlanUsage />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
