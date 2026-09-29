import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editNotifications } from '@/routes/notification-preferences';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as tenants } from '@/routes/tenants';
import { useTranslation } from '@/hooks/use-translation';
import type { NavItem } from '@/types';

type Props = PropsWithChildren<{
    // Pages d'edition riches (apercu cote a cote, tableaux) : toute la largeur disponible. Les
    // formulaires simples gardent une colonne etroite, plus lisible. Pose par la page via ses
    // props de mise en page (`Page.layout`), qu'Inertia transmet a chaque layout de la pile.
    wide?: boolean;
}>;

export default function SettingsLayout({ children, wide = false }: Props) {
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

                <div className={cn('min-w-0 flex-1', !wide && 'md:max-w-2xl')}>
                    <section className={cn('space-y-12', !wide && 'max-w-xl')}>
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
