import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    HandCoins,
    HeartPulse,
    Megaphone,
    Package,
    ScrollText,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
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
    plans,
    recovery,
    showcase,
    team,
} from '@/routes/console';
import { index as organisationsIndex } from '@/routes/console/organisations';
import type { NavItem } from '@/types';

/**
 * Le menu de la console d'exploitation (README section 3, ecrans 27 a 34) : distinct de celui
 * d'une organisation, la console administre les organisations clientes, pas leurs evenements.
 */
export function ConsoleSidebar() {
    const { t } = useTranslation();
    const tenant = usePage().props.currentTenant;
    const backOfficeUrl = tenant ? dashboard(tenant.slug) : '/';

    const clientItems: NavItem[] = [
        {
            title: t('console.nav.organisations'),
            href: organisationsIndex(),
            icon: Building2,
        },
        {
            title: t('console.nav.recovery'),
            href: recovery(),
            icon: HandCoins,
        },
        {
            title: t('console.nav.plans'),
            href: plans(),
            icon: Package,
        },
    ];

    const platformItems: NavItem[] = [
        {
            title: t('console.nav.health'),
            href: health(),
            icon: HeartPulse,
        },
        {
            title: t('console.nav.showcase'),
            href: showcase(),
            icon: Megaphone,
        },
        {
            title: t('console.nav.audit'),
            href: audit(),
            icon: ScrollText,
        },
        {
            title: t('console.nav.team'),
            href: team(),
            icon: UsersRound,
        },
    ];

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
                <NavMain
                    items={platformItems}
                    label={t('console.nav.group_platform')}
                />
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
            </SidebarFooter>
        </Sidebar>
    );
}
