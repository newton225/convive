import { Head, Link } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { ArrowRight, CalendarSearch, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ShowcaseCard } from '@/components/showcase/showcase-card';
import { SiteAnalytics } from '@/components/site/site-analytics';
import { Reveal } from '@/components/site/reveal';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { Duration, EaseOut } from '@/lib/motion';
import { register } from '@/routes';
import type { ShowcaseEvent } from '@/types';

type Props = {
    events: ShowcaseEvent[];
    analyticsId: string | null;
};

/**
 * La vitrine des evenements a la une (README ecran 1, CLAUDE.md « Annonce sur le site
 * produit ») : au-dela du lien direct que l'organisateur distribue lui-meme, un public qui n'a
 * jamais recu le lien decouvre ici les evenements que des organisations ont choisi de rendre
 * publics (opt-in, jamais automatique a la publication).
 *
 * Chaque carte renvoie vers l'adresse complete avec jeton de l'evenement : la vitrine ne fait
 * qu'exposer un lien deja rendu public, jamais une seconde facon d'y acceder. La recherche filtre
 * la liste deja chargee, sans requete : la vitrine ne porte que les evenements annonces.
 */
export default function Showcase({ events, analyticsId }: Props) {
    const { t, locale } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const [search, setSearch] = useState('');

    const visible = useMemo(() => {
        const needle = search.trim().toLocaleLowerCase(locale);

        return needle === ''
            ? events
            : events.filter((event) =>
                  [event.name, event.organisationName].some((value) =>
                      value.toLocaleLowerCase(locale).includes(needle),
                  ),
              );
    }, [events, search, locale]);

    const enter = (delay: number) =>
        reduceMotion
            ? {}
            : {
                  initial: { opacity: 0, y: 16 },
                  animate: { opacity: 1, y: 0 },
                  transition: { duration: Duration.base, delay, ease: EaseOut },
              };

    return (
        <>
            <Head title={t('showcase.head')} />

            <SiteHeader />

            <section className="bg-ink relative isolate overflow-hidden text-white">
                <div
                    className="site-grid pointer-events-none absolute inset-0 -z-10"
                    aria-hidden="true"
                />
                <div
                    className="site-glow pointer-events-none absolute -top-48 left-1/2 -z-10 size-[36rem] -translate-x-1/2"
                    aria-hidden="true"
                />
                <div className="mx-auto w-full max-w-3xl px-5 pt-14 pb-16 text-center sm:pt-20 sm:pb-20">
                    <motion.p
                        {...enter(0)}
                        className="text-primary text-sm font-semibold tracking-wide uppercase"
                    >
                        {t('showcase.eyebrow')}
                    </motion.p>
                    <motion.h1
                        {...enter(0.08)}
                        className="mt-3 text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                    >
                        {t('showcase.title')}
                    </motion.h1>
                    <motion.p
                        {...enter(0.16)}
                        className="mx-auto mt-4 max-w-xl text-lg text-white/70"
                    >
                        {t('showcase.description')}
                    </motion.p>
                    {events.length > 0 ? (
                        <motion.div
                            {...enter(0.24)}
                            className="relative mx-auto mt-8 max-w-md"
                        >
                            <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-white/50" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={t('showcase.search_placeholder')}
                                aria-label={t('showcase.search_placeholder')}
                                className="h-12 rounded-full border-white/15 bg-white/10 pl-11 text-base text-white placeholder:text-white/50 focus-visible:bg-white/15"
                            />
                        </motion.div>
                    ) : null}
                </div>
            </section>

            <main className="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
                {events.length === 0 ? (
                    <Reveal className="bg-card mx-auto flex max-w-lg flex-col items-center gap-3 rounded-3xl px-6 py-14 text-center">
                        <CalendarSearch className="text-muted-foreground size-10" />
                        <p className="font-medium" data-test="showcase-empty">
                            {t('showcase.empty')}
                        </p>
                    </Reveal>
                ) : (
                    <>
                        {/* Annonce le nombre de resultats aux lecteurs d'ecran a chaque frappe :
                            la grille change sans que le focus bouge. */}
                        <p
                            className="text-muted-foreground mb-4 text-sm"
                            aria-live="polite"
                        >
                            {t('showcase.results', { count: visible.length })}
                        </p>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {/* `popLayout` : les cartes qui ne correspondent plus quittent la grille
                                des le debut de leur effacement, les restantes glissent aussitot a
                                leur nouvelle place, en un seul mouvement. */}
                            <AnimatePresence initial={false} mode="popLayout">
                                {visible.map((event, index) => {
                                    const featured =
                                        index === 0 &&
                                        search === '' &&
                                        visible.length > 2;

                                    return (
                                        // Le format entre dans la cle : passer de l'affiche a
                                        // la carte ordinaire se fait en fondu enchaine, pas en
                                        // etirant le visuel.
                                        <ShowcaseCard
                                            key={`${event.publicUrl}-${featured ? 'featured' : 'card'}`}
                                            event={event}
                                            index={index}
                                            featured={featured}
                                        />
                                    );
                                })}
                            </AnimatePresence>
                        </div>

                        <AnimatePresence>
                            {visible.length === 0 ? (
                                <motion.div
                                    key="no-match"
                                    initial={
                                        reduceMotion
                                            ? false
                                            : { opacity: 0, y: 12 }
                                    }
                                    animate={{ opacity: 1, y: 0 }}
                                    exit={
                                        reduceMotion
                                            ? undefined
                                            : {
                                                  opacity: 0,
                                                  transition: {
                                                      duration: 0.15,
                                                  },
                                              }
                                    }
                                    transition={{
                                        duration: Duration.quick,
                                        delay: 0.15,
                                        ease: EaseOut,
                                    }}
                                    className="flex flex-col items-center gap-3 py-12 text-center"
                                >
                                    <CalendarSearch className="text-muted-foreground size-10" />
                                    <p className="text-muted-foreground">
                                        {t('showcase.no_match')}
                                    </p>
                                </motion.div>
                            ) : null}
                        </AnimatePresence>
                    </>
                )}

                <Reveal className="bg-card mt-16 flex flex-col items-start gap-4 rounded-3xl p-8 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold tracking-tight">
                            {t('showcase.organiser.title')}
                        </h2>
                        <p className="text-muted-foreground mt-1">
                            {t('showcase.organiser.body')}
                        </p>
                    </div>
                    <Button
                        asChild
                        size="lg"
                        className="group h-12 shrink-0 rounded-full px-6"
                    >
                        <Link href={register()}>
                            {t('showcase.organiser.button')}
                            <ArrowRight className="transition-transform duration-200 group-hover:translate-x-0.5" />
                        </Link>
                    </Button>
                </Reveal>
            </main>

            <SiteFooter analyticsEnabled={analyticsId !== null} />
            <SiteAnalytics measurementId={analyticsId} />
        </>
    );
}
