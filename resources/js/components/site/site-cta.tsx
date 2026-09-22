import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { register } from '@/routes';
import { Reveal } from './reveal';

/**
 * La bande de conclusion : une phrase, un bouton, sur le meme fond encre que le bandeau
 * d'accroche pour refermer la page comme elle s'ouvre.
 */
export function SiteCta() {
    const { t } = useTranslation();

    return (
        <section className="bg-ink text-white" data-test="site-cta">
            <Reveal className="mx-auto flex w-full max-w-6xl flex-col items-start gap-6 px-6 py-16 md:flex-row md:items-center md:justify-between">
                <div className="max-w-xl">
                    <h2 className="text-3xl font-semibold tracking-tight text-balance">
                        {t('site.cta.title')}
                    </h2>
                    <p className="mt-3 text-white/70">{t('site.cta.body')}</p>
                </div>
                <Button size="lg" asChild>
                    <Link href={register()} data-test="site-cta-register">
                        {t('site.cta.button')}
                    </Link>
                </Button>
            </Reveal>
        </section>
    );
}
