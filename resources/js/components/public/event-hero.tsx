import { motion, useReducedMotion } from 'framer-motion';
import { FadeInImage } from '@/components/fade-in-image';
import { useTranslation } from '@/hooks/use-translation';
import { Duration, EaseOut } from '@/lib/motion';
import type { PublicEvent, PublicTenant } from '@/types';

type Props = {
    event: PublicEvent;
    tenant: PublicTenant;
};

/**
 * Le bandeau d'un evenement (README ecran 3) : le visuel en plein cadre, ou a defaut un fond aux
 * couleurs de l'organisation, et le nom de l'evenement en serif, reserve aux invitations
 * (CLAUDE.md). Un voile sombre garantit la lisibilite du titre quelles que soient l'image et les
 * couleurs de marque. Le visuel se pose en se resserrant legerement (echelle), le texte monte.
 */
export function EventHero({ event, tenant }: Props) {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const visualUrl = event.visualUrl ?? tenant.bannerUrl;

    const rise = (delay: number) =>
        reduceMotion
            ? {}
            : {
                  initial: { opacity: 0, y: 16 },
                  animate: { opacity: 1, y: 0 },
                  transition: { duration: Duration.base, delay, ease: EaseOut },
              };

    return (
        <section className="relative isolate flex min-h-[52svh] items-end overflow-hidden text-white md:min-h-[26rem]">
            {visualUrl ? (
                // Le visuel se pose en fondu une fois charge, sur le degrade de la marque qui
                // tient le cadre pendant le telechargement (et le remplace s'il echoue).
                <div className="absolute inset-0 -z-20">
                    <FadeInImage
                        src={visualUrl}
                        alt=""
                        loading="eager"
                        loadingClassName="brand-hero-fallback"
                        fallback={
                            <div className="brand-hero-fallback absolute inset-0" />
                        }
                    />
                </div>
            ) : (
                <div
                    className="brand-hero-fallback absolute inset-0 -z-20"
                    aria-hidden="true"
                />
            )}
            <div
                className="absolute inset-0 -z-10 bg-gradient-to-t from-black/80 via-black/35 to-black/10"
                aria-hidden="true"
            />

            <div className="mx-auto w-full max-w-5xl px-5 pt-24 pb-14 md:pb-16">
                <motion.p
                    {...rise(0.1)}
                    className="inline-flex items-center gap-2 rounded-full bg-white/15 py-1 pr-3 pl-1 text-xs font-medium backdrop-blur-md"
                >
                    {tenant.logoUrl ? (
                        <img
                            src={tenant.logoUrl}
                            alt=""
                            className="size-6 rounded-full bg-white object-contain"
                        />
                    ) : (
                        <span className="size-2" aria-hidden="true" />
                    )}
                    {t('guest.event.hosted_by', { name: tenant.displayName })}
                </motion.p>
                <motion.h1
                    {...rise(0.2)}
                    className="mt-4 font-serif text-4xl leading-[1.05] font-semibold text-balance sm:text-5xl md:text-6xl"
                >
                    {event.name}
                </motion.h1>
                {event.subtitle ? (
                    <motion.p
                        {...rise(0.3)}
                        className="mt-3 max-w-2xl text-lg text-white/80"
                    >
                        {event.subtitle}
                    </motion.p>
                ) : null}
            </div>
        </section>
    );
}
