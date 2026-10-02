import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    HandCoins,
    HeartPulse,
    Megaphone,
    Send,
    Package,
    ScrollText,
    ShieldAlert,
    UserSearch,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { AppVersion } from '@/components/app-version';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import {
    audit,
    health,
    messages,
    plans,
    recovery,
    security,
    showcase,
    team,
} from '@/routes/console';
import { index as accountsIndex } from '@/routes/console/accounts';
import { index as organisationsIndex } from '@/routes/console/organisations';
import type { NavItem } from '@/types';

/**
 * Le menu de la console d'exploitation (README section 3, ecrans 27 a 34) : distinct de celui
 * d'une organisation, la console administre les organisations clientes, pas leurs evenements.
 */
export function ConsoleSidebar() {
    const { t } = useTranslation();
    const { currentTenant: tenant, consoleAreas } = usePage().props;
    // Seuls les ecrans que le profil editeur ouvre ; chaque route revalide.
    const allowed = (item: NavItem & { area: string }) =>
        consoleAreas.includes(item.area);
    const backOfficeUrl = tenant ? dashboard(tenant.slug) : '/';

    const clientItems = [
        {
            title: t('console.nav.organisations'),
            href: organisationsIndex(),
            icon: Building2,
            area: 'organisations',
        },
        {
            title: t('console.nav.accounts'),
            href: accountsIndex(),
            icon: UserSearch,
            area: 'accounts',
        },
        {
            title: t('console.nav.recovery'),
            href: recovery(),
            icon: HandCoins,
            area: 'recovery',
        },
        {
            title: t('console.nav.plans'),
            href: plans(),
            icon: Package,
            area: 'plans',
        },
    ].filter(allowed);

    const platformItems = [
        {
            title: t('console.nav.health'),
            href: health(),
            icon: HeartPulse,
            area: 'health',
        },
        {
            title: t('console.nav.messages'),
            href: messages(),
            icon: Send,
            area: 'messages',
        },
        {
            title: t('console.nav.security'),
            href: security(),
            icon: ShieldAlert,
            area: 'security',
        },
        {
            title: t('console.nav.showcase'),
            href: showcase(),
            icon: Megaphone,
            area: 'showcase',
        },
        {
            title: t('console.nav.audit'),
            href: audit(),
            icon: ScrollText,
            area: 'audit',
        },
        {
            title: t('console.nav.team'),
            href: team(),
            icon: UsersRound,
            area: 'team',
        },
    ].filter(allowed);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={organisationsIndex()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <p className="text-muted-foreground px-2 text-xs font-medium tracking-wide uppercase group-data-[collapsible=icon]:hidden">
                    {t('console.title')}
                </p>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={clientItems}
                    label={t('console.nav.group_clients')}
                />
                {platformItems.length > 0 ? (
                    <NavMain
                        items={platformItems}
                        label={t('console.nav.group_platform')}
                    />
                ) : null}
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            tooltip={{
                                children: t('console.nav.back_to_backoffice'),
                            }}
                        >
                            <Link href={backOfficeUrl}>
                                <ArrowLeft />
                                <span>
                                    {t('console.nav.back_to_backoffice')}
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
                <AppVersion />
            </SidebarFooter>
        </Sidebar>
    );
}
