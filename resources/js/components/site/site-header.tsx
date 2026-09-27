import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import LocaleSwitcher from '@/components/locale-switcher';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { login, register } from '@/routes';
import { index as showcaseIndex } from '@/routes/showcase';
import { ThemeSwitcher } from './theme-switcher';

/**
 * L'en-tete de la vitrine (visiteurs seulement : un membre connecte n'y accede pas, voir la route
 * `home`) : toujours visible, colle en haut de l'ecran pendant tout le
 * defilement. Il garde le fond encre en clair comme en sombre, donc son texte reste clair dans les
 * deux themes. Les ancres menent aux sections (elles portent une marge de defilement pour ne pas
 * finir sous lui), la connexion et la creation d'espace restent toujours a portee.
 *
 * Sur petit ecran, les liens d'ancre et le bouton de creation disparaissent : le bandeau
 * d'accroche et la bande de conclusion portent deja l'appel a l'action, et la connexion, le
 * theme et la langue tiennent sur une ligne.
 */
export function SiteHeader() {
    const { t } = useTranslation();
    const { name } = usePage().props;

    return (
        <header
            className="bg-ink/90 sticky top-0 z-50 border-b border-white/10 text-white backdrop-blur-md"
            data-test="site-header"
        >
            <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
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
                    className="hidden items-center gap-6 text-sm text-white/75 md:flex"
                    aria-label={name}
                >
                    <a href="#features" className="hover:text-white">
                        {t('site.nav.features')}
                    </a>
                    <a href="#steps" className="hover:text-white">
                        {t('site.nav.steps')}
                    </a>
                    <a href="#pricing" className="hover:text-white">
                        {t('site.nav.pricing')}
                    </a>
                    <Link
                        href={showcaseIndex()}
                        className="hover:text-white"
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
                        className="hover:bg-white/10 hover:text-white"
                        asChild
                    >
                        <Link href={login()} data-test="site-login">
                            {t('site.nav.login')}
                        </Link>
                    </Button>
                    <Button size="sm" className="hidden sm:inline-flex" asChild>
                        <Link href={register()} data-test="site-register">
                            {t('site.nav.register')}
                        </Link>
                    </Button>
                </div>
            </div>
        </header>
    );
}
