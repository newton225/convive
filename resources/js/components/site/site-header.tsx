import { Link, usePage } from '@inertiajs/react';
import { useMotionValueEvent, useScroll } from 'framer-motion';
import { Menu } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import LocaleSwitcher from '@/components/locale-switcher';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { home, login } from '@/routes';
import { index as showcaseIndex } from '@/routes/showcase';
import { SiteHeaderCta } from './site-header-cta';
import { ThemeSwitcher } from './theme-switcher';

/**
 * L'en-tete de la vitrine (visiteurs seulement : un membre connecte n'y accede pas, voir la route
 * `home`). Colle en haut pendant tout le defilement : transparent sur l'accroche, il prend un
 * fond encre flou et se resserre des qu'on descend, pour rester lisible au-dessus des sections
 * claires. Il garde le texte clair dans les deux themes.
 *
 * Sur petit ecran, les ancres passent dans un panneau lateral : la navigation reste complete au
 * lieu de disparaitre.
 */
export function SiteHeader() {
    const { t } = useTranslation();
    const { name } = usePage().props;
    const { scrollY } = useScroll();
    const [scrolled, setScrolled] = useState(false);

    useMotionValueEvent(scrollY, 'change', (value) => setScrolled(value > 12));

    // Sections de la page d'accueil : l'en-tete sert aussi la vitrine, une ancre nue y resterait
    // sur place.
    const anchors = [
        { href: `${home().url}#features`, label: t('site.nav.features') },
        { href: `${home().url}#steps`, label: t('site.nav.steps') },
        { href: `${home().url}#pricing`, label: t('site.nav.pricing') },
    ];

    return (
        <header
            className={cn(
                'sticky top-0 z-50 text-white transition-[background-color,border-color,backdrop-filter] duration-300',
                scrolled
                    ? 'bg-ink/80 border-b border-white/10 backdrop-blur-xl'
                    : 'bg-ink border-b border-transparent',
            )}
            data-test="site-header"
        >
            <div
                className={cn(
                    'mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 transition-[padding] duration-300 sm:px-6',
                    scrolled ? 'py-2.5' : 'py-4',
                )}
            >
                <Link
                    href="/"
                    className="flex items-center gap-2"
                    aria-label={name}
                >
                    <AppLogoIcon className="size-7 fill-current" />
                    <span className="text-lg font-semibold tracking-tight">
                        {name}
                    </span>
                </Link>

                <nav
                    className="hidden items-center gap-1 text-sm text-white/70 md:flex"
                    aria-label={name}
                >
                    {anchors.map((anchor) => (
                        <a
                            key={anchor.href}
                            href={anchor.href}
                            className="rounded-full px-3 py-1.5 transition-colors hover:bg-white/10 hover:text-white"
                        >
                            {anchor.label}
                        </a>
                    ))}
                    <Link
                        href={showcaseIndex()}
                        className="rounded-full px-3 py-1.5 transition-colors hover:bg-white/10 hover:text-white"
                        data-test="site-nav-showcase"
                    >
                        {t('site.nav.showcase')}
                    </Link>
                </nav>

                <div className="flex items-center gap-1 sm:gap-2 [&_button]:text-white">
                    <LocaleSwitcher />
                    <ThemeSwitcher />
                    <Button
                        variant="ghost"
                        size="sm"
                        className="hidden rounded-full hover:bg-white/10 hover:text-white sm:inline-flex"
                        asChild
                    >
                        <Link href={login()} data-test="site-login">
                            {t('site.nav.login')}
                        </Link>
                    </Button>
                    <SiteHeaderCta className="hidden sm:inline-flex" />

                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-11 hover:bg-white/10 md:hidden"
                                aria-label={t('site.nav.menu')}
                            >
                                <Menu className="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" className="w-72">
                            <SheetHeader>
                                <SheetTitle>{name}</SheetTitle>
                                <SheetDescription className="sr-only">
                                    {t('site.nav.menu')}
                                </SheetDescription>
                            </SheetHeader>
                            <nav className="flex flex-col gap-1 px-4 text-base">
                                {anchors.map((anchor) => (
                                    <SheetClose key={anchor.href} asChild>
                                        <a
                                            href={anchor.href}
                                            className="hover:bg-muted rounded-lg px-3 py-3"
                                        >
                                            {anchor.label}
                                        </a>
                                    </SheetClose>
                                ))}
                                <Link
                                    href={showcaseIndex()}
                                    className="hover:bg-muted rounded-lg px-3 py-3"
                                >
                                    {t('site.nav.showcase')}
                                </Link>
                            </nav>
                            <div className="mt-auto flex flex-col gap-2 p-4">
                                <Button variant="outline" asChild>
                                    <Link href={login()}>
                                        {t('site.nav.login')}
                                    </Link>
                                </Button>
                                <SiteHeaderCta className="h-10 w-full justify-center" />
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>
    );
}
