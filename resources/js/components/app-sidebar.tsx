import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    CreditCard,
    Layers,
    LayoutGrid,
    LifeBuoy,
    ScanLine,
    ScrollText,
    ShieldCheck,
    Ticket,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { AppVersion } from '@/components/app-version';
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
import { edit as organisationEdit } from '@/routes/tenants/organisation';
import { index as paymentAccountsIndex } from '@/routes/tenants/payment-accounts';
import { index as profilesIndex } from '@/routes/tenants/profiles';
import { show as supportAccessShow } from '@/routes/tenants/support-access';
import { edit as ticketTemplateEdit } from '@/routes/tenants/ticket-template';
import { index as unitsIndex } from '@/routes/tenants/units';
import { index as eventsIndex } from '@/routes/tenants/events';
import { entryControl, edit as tenantEdit } from '@/routes/tenants';
import { useTranslation } from '@/hooks/use-translation';
import type { NavItem } from '@/types';

/**
 * Le menu du back-office, en deux groupes : le pilotage des evenements, puis tout ce qui
 * concerne l'organisation. Chaque entree n'apparait qu'a qui detient sa permission (le serveur
 * revalide toujours). Les reglages personnels vivent dans le menu du compte, en bas.
 */
export function AppSidebar() {
    const page = usePage();
    const { t } = useTranslation();
    const tenant = page.props.currentTenant;
    const dashboardUrl = tenant ? dashboard(tenant.slug) : '/';

    const permissions = page.props.tenantPermissions;
    const allowed = (permission: PermissionValue) =>
        permissions !== null && can(permissions, permission);
    const only = (condition: boolean, item: NavItem): NavItem[] =>
        condition ? [item] : [];

    const operationItems: NavItem[] = [
        {
            title: t('navigation.dashboard'),
            href: dashboardUrl,
            icon: LayoutGrid,
            tourId: 'nav-dashboard',
        },
        ...(tenant
            ? [
                  {
                      title: t('events.title'),
                      href: eventsIndex(tenant.slug),
                      icon: CalendarDays,
                      tourId: 'nav-events',
                  },
                  ...only(allowed(Permission.ScanPerform), {
                      title: t('navigation.entry_control'),
                      href: entryControl(tenant.slug),
                      icon: ScanLine,
                      tourId: 'nav-entry-control',
                  }),
              ]
            : []),
    ];

    const organisationItems: NavItem[] = tenant
        ? [
              ...only(
                  allowed(Permission.TenantLegal) ||
                      allowed(Permission.TenantBranding) ||
                      allowed(Permission.TenantDomain),
                  {
                      title: t('navigation.brand'),
                      href: organisationEdit(tenant.slug),
                      icon: Building2,
                  },
              ),
              ...only(allowed(Permission.TenantPaymentAccounts), {
                  title: t('navigation.payment_accounts'),
                  href: paymentAccountsIndex(tenant.slug),
                  icon: Wallet,
              }),
              ...only(allowed(Permission.TenantUnits), {
                  title: t('navigation.units'),
                  href: unitsIndex(tenant.slug),
                  icon: Layers,
              }),
              {
                  title: t('navigation.team'),
                  href: tenantEdit(tenant.slug),
                  icon: Users,
              },
              // Reserve au Proprietaire, pas a une permission du catalogue (`TenantPolicy`).
              ...only(tenant.isOwner === true, {
                  title: t('navigation.support_access'),
                  href: supportAccessShow(tenant.slug),
                  icon: LifeBuoy,
              }),
              ...only(allowed(Permission.ProfilesManage), {
                  title: t('navigation.profiles'),
                  href: profilesIndex(tenant.slug),
                  icon: ShieldCheck,
              }),
              ...only(allowed(Permission.TenantBranding), {
                  title: t('navigation.ticket_template'),
                  href: ticketTemplateEdit(tenant.slug),
                  icon: Ticket,
              }),
              ...only(allowed(Permission.BillingView), {
                  title: t('navigation.billing'),
                  href: billingShow(tenant.slug),
                  icon: CreditCard,
              }),
              ...only(allowed(Permission.AuditView), {
                  title: t('navigation.audit'),
                  href: auditIndex(tenant.slug),
                  icon: ScrollText,
              }),
          ]
        : [];

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
                <SidebarMenu data-tour="tenant-switcher">
                    <SidebarMenuItem>
                        <TenantSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={operationItems}
                    label={t('navigation.group_operations')}
                />
                <NavMain
                    items={organisationItems}
                    label={t('navigation.group_organisation')}
                    tourId="nav-organisation"
                />
            </SidebarContent>

            <SidebarFooter data-tour="user-menu">
                <PlanUsage />
                <NavUser />
                <AppVersion />
            </SidebarFooter>
        </Sidebar>
    );
}
