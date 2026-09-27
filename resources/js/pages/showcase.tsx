import { Head } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format-date';

type ShowcaseEvent = {
    name: string;
    organisationName: string;
    startsAt: string | null;
    publicUrl: string;
};

type Props = {
    events: ShowcaseEvent[];
};

/**
 * La vitrine des evenements a la une (README ecran 1, CLAUDE.md « Annonce sur le site
 * produit ») : au-dela du lien direct que l'organisateur distribue lui-meme, un public qui n'a
 * jamais recu le lien decouvre ici les evenements que des organisations ont choisi de rendre
 * publics (opt-in, jamais automatique a la publication).
 *
 * Chaque carte renvoie vers l'adresse complete avec jeton de l'evenement : la vitrine ne fait
 * qu'exposer un lien deja rendu public, jamais une seconde facon d'y acceder.
 */
export default function Showcase({ events }: Props) {
    const { t, locale } = useTranslation();

    return (
        <>
            <Head title={t('showcase.head')} />

            <SiteHeader />

            <main className="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6">
                <div className="mx-auto max-w-2xl text-center">
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                        {t('showcase.title')}
                    </h1>
                    <p className="text-muted-foreground mt-3 text-base">
                        {t('showcase.description')}
                    </p>
                </div>

                {events.length === 0 ? (
                    <p
                        className="text-muted-foreground mt-16 text-center text-sm"
                        data-test="showcase-empty"
                    >
                        {t('showcase.empty')}
                    </p>
                ) : (
                    <div className="mt-12 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {events.map((event) => (
                            <Card
                                key={event.publicUrl}
                                data-test="showcase-card"
                            >
                                <CardHeader>
                                    <CardTitle className="text-lg">
                                        {event.name}
                                    </CardTitle>
                                    <p className="text-muted-foreground text-sm">
                                        {t('showcase.card.by', {
                                            organisation:
                                                event.organisationName,
                                        })}
                                    </p>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <p className="text-muted-foreground flex items-center gap-2 text-sm">
                                        <CalendarDays className="size-4 shrink-0" />
                                        {event.startsAt
                                            ? formatDate(event.startsAt, locale)
                                            : t('showcase.card.date_unknown')}
                                    </p>
                                    <Button asChild className="w-full">
                                        <a
                                            href={event.publicUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            {t('showcase.card.cta')}
                                        </a>
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </main>

            <SiteFooter />
        </>
    );
}
