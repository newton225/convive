import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { register } from '@/routes';
import { Reveal } from './reveal';

/**
 * La bande de conclusion : une phrase, un bouton, sur le meme fond encre, la meme grille et le
 * meme halo que l'accroche, pour refermer la page comme elle s'ouvre.
 */
export function SiteCta() {
    const { t } = useTranslation();

    return (
        <section className="px-4 pb-6 sm:px-6" data-test="site-cta">
            <div className="bg-ink relative isolate mx-auto w-full max-w-6xl overflow-hidden rounded-[2rem] text-white">
                <div
                    className="site-grid pointer-events-none absolute inset-0 -z-10"
                    aria-hidden="true"
                />
                <div
                    className="site-glow pointer-events-none absolute -bottom-64 left-1/2 -z-10 size-[40rem] -translate-x-1/2"
                    aria-hidden="true"
                />
                <Reveal className="flex flex-col items-center gap-6 px-6 py-20 text-center sm:py-24">
                    <h2 className="max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-5xl">
                        {t('site.cta.title')}
                    </h2>
                    <p className="max-w-xl text-lg text-white/70">
                        {t('site.cta.body')}
                    </p>
                    <Button
                        size="lg"
                        className="group mt-2 h-12 rounded-full px-7"
                        asChild
                    >
                        <Link href={register()} data-test="site-cta-register">
                            {t('site.cta.button')}
                            <ArrowRight className="transition-transform duration-200 group-hover:translate-x-0.5" />
                        </Link>
                    </Button>
                </Reveal>
            </div>
        </section>
    );
}
