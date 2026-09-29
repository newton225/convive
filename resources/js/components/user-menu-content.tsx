import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Settings, ShieldEllipsis } from 'lucide-react';
import { AppearanceSubmenu } from '@/components/appearance-submenu';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { useTranslation } from '@/hooks/use-translation';
import { clearScanStorage } from '@/lib/scan-queue';
import { logout } from '@/routes';
import { index as consoleOrganisations } from '@/routes/console/organisations';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { t } = useTranslation();
    const { canAccessConsole } = usePage().props;

    const handleLogout = () => {
        cleanup();
        router.flushAll();
        // La file de scan hors ligne (jetons de billets) ne survit pas a la deconnexion : un
        // telephone partage ou perdu ne doit rien exposer (SECURITY.md M8).
        clearScanStorage();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        {t('navigation.settings')}
                    </Link>
                </DropdownMenuItem>
                {canAccessConsole && (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={consoleOrganisations()}
                            onClick={cleanup}
                            data-test="console-link"
                        >
                            <ShieldEllipsis className="mr-2" />
                            {t('navigation.console')}
                        </Link>
                    </DropdownMenuItem>
                )}
                <AppearanceSubmenu />
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    {t('navigation.logout')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
