import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editNotifications } from '@/routes/notification-preferences';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as tenants } from '@/routes/tenants';
import { useTranslation } from '@/hooks/use-translation';
import type { NavItem } from '@/types';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { t } = useTranslation();

    const sidebarNavItems: NavItem[] = [
        { title: t('navigation.profile'), href: edit(), icon: null },
        { title: t('navigation.security'), href: editSecurity(), icon: null },
        { title: t('navigation.tenants'), href: tenants(), icon: null },
        {
            title: t('navigation.notifications'),
            href: editNotifications(),
            icon: null,
        },
        {
            title: t('navigation.appearance'),
            href: editAppearance(),
            icon: null,
        },
    ];

    return (
        <div className="px-4 py-6">
            <Heading
                title={t('navigation.settings')}
                description={t('account.settings_description')}
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label={t('navigation.settings')}
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
