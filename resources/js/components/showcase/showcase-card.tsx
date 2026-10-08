import { motion, useReducedMotion } from 'framer-motion';
import { ArrowUpRight } from 'lucide-react';
import type { Ref } from 'react';
import { FadeInImage } from '@/components/fade-in-image';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateParts } from '@/lib/format-date';
import { EaseOut } from '@/lib/motion';
import { cn } from '@/lib/utils';
import type { ShowcaseEvent } from '@/types';

type Props = {
    event: ShowcaseEvent;
    index: number;
    featured?: boolean;
    // Transmise par `AnimatePresence` en mode `popLayout`, qui mesure la carte sortante pour la
    // retirer de la grille des le debut de son effacement.
    ref?: Ref<HTMLAnchorElement>;
};

// Les degrades de l'affiche generee quand un evenement n'a pas de visuel. Le meme titre prend
// toujours le meme : une carte ne change pas de couleur d'un affichage a l'autre.
const PosterTones = [
    'from-[#3b0d1d] via-[#7b1e3a] to-[#c9a227]/70',
    'from-[#10213f] via-[#26407a] to-[#7a8fd6]/70',
    'from-[#0e2a22] via-[#1f5c46] to-[#c9a227]/60',
    'from-[#2a1033] via-[#5b2a7a] to-[#d17a9b]/60',
];

function posterTone(name: string): string {
    const sum = Array.from(name).reduce(
        (total, character) => total + character.charCodeAt(0),
        0,
    );

    return PosterTones[sum % PosterTones.length] ?? PosterTones[0];
}

/**
 * Une carte de la vitrine, cliquable en entier : elle mene a l'adresse complete avec jeton de
 * l'evenement, jamais a une seconde facon d'y acceder. La date se lit en vignette.
 *
 * Aucune dimension ne vient de l'image : un visuel depose peut avoir n'importe quel format, et
 * c'est lui qui etirait toute la grille. Carte ordinaire : visuel en 16/10, corps compact. Carte
 * mise en avant : une affiche, visuel en plein cadre et texte pose dessus sur un voile sombre, a
 * la hauteur des deux cartes voisines sur grand ecran.
 */
export function ShowcaseCard({ event, index, featured = false, ref }: Props) {
    const { t, locale } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const date = event.startsAt
        ? formatDateParts(event.startsAt, locale)
        : null;

    // Sans visuel (ou si le visuel ne se charge pas), la carte dessine une affiche : un degrade, le
    // titre et la date (decision du 2026-10-08). Sur la carte mise en avant le titre est deja pose
    // sur le visuel, il n'est pas redit.
    const placeholder = (
        <div
            className={cn(
                'absolute inset-0 flex flex-col items-center justify-center gap-2 bg-gradient-to-br px-6 text-center text-white',
                posterTone(event.name),
            )}
            data-test="showcase-poster"
        >
            {featured ? null : (
                <>
                    <p className="line-clamp-3 font-serif text-xl leading-tight font-semibold">
                        {event.name}
                    </p>
                    {date ? (
                        <p className="text-xs tracking-wide text-white/80 uppercase">
                            {date.day} {date.month}
                        </p>
                    ) : null}
                </>
            )}
        </div>
    );

    const visual = event.visualUrl ? (
        <FadeInImage
            src={event.visualUrl}
            alt=""
            fallback={placeholder}
            className="group-hover:scale-105"
            data-test="showcase-visual"
        />
    ) : (
        placeholder
    );

    const dateTile = date ? (
        <div className="bg-background/90 text-foreground absolute top-4 left-4 z-10 flex min-w-12 flex-col items-center rounded-xl px-2.5 py-1.5 leading-none backdrop-blur-md">
            <span className="text-primary text-[0.65rem] font-semibold tracking-wide uppercase">
                {date.month}
            </span>
            <span className="mt-1 text-lg font-semibold tabular-nums">
                {date.day}
            </span>
        </div>
    ) : null;

    const when = date
        ? `${date.weekday}, ${date.time}`
        : t('showcase.card.date_unknown');

    const cta = (
        <span className="inline-flex shrink-0 items-center gap-1 font-medium">
            {t('showcase.card.cta')}
            <ArrowUpRight className="size-4 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
        </span>
    );

    return (
        <motion.a
            ref={ref}
            href={event.publicUrl}
            target="_blank"
            rel="noopener noreferrer"
            // Recherche : les cartes restantes glissent vers leur nouvelle place (position
            // seulement, jamais d'etirement qui deformerait le visuel), celles qui ne
            // correspondent plus s'effacent en se resserrant, les nouvelles montent en cascade.
            layout={reduceMotion ? false : 'position'}
            initial={reduceMotion ? false : { opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            exit={
                reduceMotion
                    ? undefined
                    : {
                          opacity: 0,
                          scale: 0.94,
                          transition: { duration: 0.22, ease: 'easeIn' },
                      }
            }
            viewport={{ once: true, margin: '-60px' }}
            transition={{
                duration: 0.5,
                delay: (index % 6) * 0.06,
                ease: EaseOut,
                layout: { duration: 0.45, ease: EaseOut },
            }}
            className={cn(
                'group bg-card focus-visible:ring-ring relative flex flex-col overflow-hidden rounded-3xl outline-none focus-visible:ring-2',
                featured &&
                    'min-h-[24rem] justify-end text-white sm:col-span-2 lg:row-span-2 lg:min-h-0',
            )}
            data-test="showcase-card"
        >
            {featured ? (
                <>
                    {visual}
                    <div
                        className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent"
                        aria-hidden="true"
                    />
                    {dateTile}
                    <div className="relative space-y-3 p-6 sm:p-8">
                        <div>
                            <h2 className="text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                                {event.name}
                            </h2>
                            <p className="mt-1 text-white/75">
                                {t('showcase.card.by', {
                                    organisation: event.organisationName,
                                })}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-white/80">
                            <span className="capitalize">{when}</span>
                            <span className="text-white">{cta}</span>
                        </div>
                    </div>
                </>
            ) : (
                <>
                    <div className="relative aspect-[16/10] overflow-hidden">
                        {visual}
                        {dateTile}
                    </div>
                    <div className="flex flex-1 flex-col gap-4 p-5">
                        <div>
                            <h2 className="line-clamp-2 text-lg font-semibold tracking-tight">
                                {event.name}
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {t('showcase.card.by', {
                                    organisation: event.organisationName,
                                })}
                            </p>
                        </div>
                        <div className="text-muted-foreground mt-auto flex items-center justify-between gap-3 text-sm">
                            <span className="capitalize">{when}</span>
                            <span className="text-foreground">{cta}</span>
                        </div>
                    </div>
                </>
            )}
        </motion.a>
    );
}
