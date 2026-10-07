import { Head } from '@inertiajs/react';
import { CalendarClock, MapPin, Users } from 'lucide-react';
import { BrandColorStyle } from '@/components/brand-color-style';
import { InstallPrompt } from '@/components/install-prompt';
import LocaleSwitcher from '@/components/locale-switcher';
import { OfflineBanner } from '@/components/offline-banner';
import { EventDateTile } from '@/components/public/event-date-tile';
import { EventHero } from '@/components/public/event-hero';
import { EventHowItWorks } from '@/components/public/event-how-it-works';
import { EventRegistrationState } from '@/components/public/event-registration-state';
import { SeatsMeter } from '@/components/public/seats-meter';
import { Reveal } from '@/components/site/reveal';
import { DirectionsLink } from '@/components/public/directions-link';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import type { PublicEvent as PublicEventData, PublicTenant } from '@/types';

type Props = {
    event: PublicEventData;
    tenant: PublicTenant;
    token: string;
};

/**
 * README ecran 3, en lecture seule : visuel, date, heure, lieu, tarif, places restantes, date
 * limite. Premiere surface non authentifiee du produit, mobile d'abord : sur telephone, le
 * contenu remonte sur le visuel et l'inscription reste a portee de pouce dans une barre fixee en
 * bas ; sur grand ecran, une carte d'inscription reste visible pendant le defilement.
 *
 * Les couleurs de marque du locataire s'appliquent uniquement au parcours invite (CLAUDE.md) :
 * elles arrivent deja validees par une expression reguliere hexadecimale stricte cote serveur.
 * `event.colors` prime sur `tenant.colors` (README ecran 13) : `Event::colors()` retombe deja
 * sur celles de l'organisation.
 */
export default function PublicEvent({ event, tenant, token }: Props) {
    const { t, locale } = useTranslation();
    const hasAction = event.acceptsRegistrations || event.isFull;

    // Tarif 0 : un evenement gratuit (decision du 2026-10-07), dit en toutes lettres.
    const isFree = event.pricePerPerson === 0;

    const price = (
        <p>
            <span className="text-3xl font-semibold tracking-tight tabular-nums">
                {isFree
                    ? t('guest.event.free')
                    : formatAmount(event.pricePerPerson, locale)}
            </span>{' '}
            {isFree ? null : (
                <span className="text-muted-foreground text-sm">
                    {t('guest.event.per_person')}
                </span>
            )}
        </p>
    );

    return (
        <div className="bg-background flex min-h-screen flex-col">
            <BrandColorStyle colors={event.colors} />
            <Head title={event.name} />
            <OfflineBanner />

            <div className="absolute top-0 right-0 z-30 p-3 [&_button]:text-white">
                <LocaleSwitcher />
            </div>

            <EventHero event={event} tenant={tenant} />

            <main
                className={`bg-background relative z-10 -mt-6 flex-1 rounded-t-3xl md:mt-0 md:rounded-none ${hasAction ? 'pb-32 md:pb-16' : 'pb-16'}`}
            >
                <div className="mx-auto grid w-full max-w-5xl gap-8 px-5 pt-8 md:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] md:gap-10 md:pt-10">
                    <div className="space-y-8">
                        <InstallPrompt />

                        <Reveal className="grid gap-5 sm:grid-cols-2">
                            <EventDateTile startsAt={event.startsAt} />
                            {event.venue ? (
                                <div className="flex items-center gap-4">
                                    <span className="bg-card flex size-16 shrink-0 items-center justify-center rounded-2xl">
                                        <MapPin className="size-6 text-[var(--brand-primary)]" />
                                    </span>
                                    <div>
                                        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                            {t('guest.event.venue')}
                                        </p>
                                        <p className="font-medium">
                                            {event.venue}
                                        </p>
                                        {event.venueAddress ? (
                                            <p className="text-muted-foreground text-sm">
                                                {event.venueAddress}
                                            </p>
                                        ) : null}
                                        {event.venueMapUrl ? (
                                            <div className="mt-2">
                                                <DirectionsLink
                                                    href={event.venueMapUrl}
                                                />
                                            </div>
                                        ) : null}
                                    </div>
                                </div>
                            ) : null}
                        </Reveal>

                        <Reveal
                            delay={0.05}
                            className="bg-card space-y-4 rounded-3xl p-5"
                        >
                            <SeatsMeter
                                capacity={event.capacity}
                                remainingSeats={event.remainingSeats}
                                isFull={event.isFull}
                            />
                            <div className="text-muted-foreground flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                <span className="inline-flex items-center gap-2">
                                    <Users className="size-4" />
                                    {t('guest.event.companion_limit', {
                                        count: event.companionLimit,
                                    })}
                                </span>
                                {event.registrationDeadline ? (
                                    <span className="inline-flex items-center gap-2">
                                        <CalendarClock className="size-4" />
                                        {t('guest.event.deadline.label')} :{' '}
                                        {formatDateTime(
                                            event.registrationDeadline,
                                            locale,
                                        )}
                                    </span>
                                ) : null}
                            </div>
                        </Reveal>

                        <EventHowItWorks />
                    </div>

                    {/* Sur telephone, la barre fixee en bas porte deja le tarif et l'action :
                        la carte n'y reste que pour dire pourquoi les inscriptions sont closes. */}
                    <aside
                        className={`md:sticky md:top-6 md:block md:self-start ${hasAction ? 'hidden' : ''}`}
                    >
                        <Reveal
                            delay={0.1}
                            className="bg-card space-y-5 rounded-3xl p-6"
                        >
                            <div>
                                <p className="text-muted-foreground text-sm">
                                    {t('guest.event.price_per_person')}
                                </p>
                                {price}
                            </div>
                            <EventRegistrationState
                                event={event}
                                token={token}
                            />
                        </Reveal>
                    </aside>
                </div>
            </main>

            {hasAction ? (
                <div className="bg-background/85 fixed inset-x-0 bottom-0 z-40 border-t px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-xl md:hidden">
                    <div className="mx-auto flex max-w-lg items-center gap-3">
                        <div className="shrink-0">
                            <p className="text-lg leading-tight font-semibold tabular-nums">
                                {isFree
                                    ? t('guest.event.free')
                                    : formatAmount(
                                          event.pricePerPerson,
                                          locale,
                                      )}
                            </p>
                            {isFree ? null : (
                                <p className="text-muted-foreground text-xs">
                                    {t('guest.event.per_person')}
                                </p>
                            )}
                        </div>
                        <div className="min-w-0 flex-1">
                            <EventRegistrationState
                                event={event}
                                token={token}
                                compact
                            />
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
