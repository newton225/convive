import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { useTranslation } from '@/hooks/use-translation';
import { home, login, register } from '@/routes';
import { index as showcaseIndex } from '@/routes/showcase';

/**
 * Le pied de la vitrine : l'identite du produit, puis les memes chemins que l'en-tete, ranges en
 * deux colonnes, pour qui arrive en bas de page sans remonter.
 */
export function SiteFooter() {
    const { t } = useTranslation();
    const { name } = usePage().props;

    const linkClass =
        'text-muted-foreground hover:text-foreground transition-colors';

    return (
        <footer className="mx-auto w-full max-w-6xl px-6 pt-16 pb-10 text-sm">
            <div className="grid gap-10 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <div className="space-y-3">
                    <p className="flex items-center gap-2 text-base font-semibold">
                        <AppLogoIcon className="size-6 fill-current" />
                        {name}
                    </p>
                    <p className="text-muted-foreground max-w-sm leading-relaxed">
                        {t('site.footer.tagline')}
                    </p>
                </div>

                <nav
                    className="space-y-3"
                    aria-label={t('site.footer.product')}
                >
                    <p className="font-semibold">{t('site.footer.product')}</p>
                    <ul className="space-y-2">
                        <li>
                            <a
                                href={`${home().url}#features`}
                                className={linkClass}
                            >
                                {t('site.nav.features')}
                            </a>
                        </li>
                        <li>
                            <a
                                href={`${home().url}#steps`}
                                className={linkClass}
                            >
                                {t('site.nav.steps')}
                            </a>
                        </li>
                        <li>
                            <a
                                href={`${home().url}#pricing`}
                                className={linkClass}
                            >
                                {t('site.nav.pricing')}
                            </a>
                        </li>
                        <li>
                            <Link href={showcaseIndex()} className={linkClass}>
                                {t('site.nav.showcase')}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <nav
                    className="space-y-3"
                    aria-label={t('site.footer.account')}
                >
                    <p className="font-semibold">{t('site.footer.account')}</p>
                    <ul className="space-y-2">
                        <li>
                            <Link href={login()} className={linkClass}>
                                {t('site.nav.login')}
                            </Link>
                        </li>
                        <li>
                            <Link href={register()} className={linkClass}>
                                {t('site.nav.register')}
                            </Link>
                        </li>
                    </ul>
                </nav>
            </div>

            <p className="text-muted-foreground border-border mt-12 border-t pt-6">
                {new Date().getFullYear()} {name}. {t('site.footer.rights')}
            </p>
        </footer>
    );
}
