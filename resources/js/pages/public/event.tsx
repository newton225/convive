import { Head, Link } from '@inertiajs/react';
import { BrandColorStyle } from '@/components/brand-color-style';
import { InstallPrompt } from '@/components/install-prompt';
import { OfflineBanner } from '@/components/offline-banner';
import { Calendar, MapPin, Users } from 'lucide-react';
import LocaleSwitcher from '@/components/locale-switcher';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import { create } from '@/routes/public/registrations';
import { create as createWaitlistEntry } from '@/routes/public/waitlist';
import type { PublicEvent, PublicTenant } from '@/types';

type Props = {
    event: PublicEvent;
    tenant: PublicTenant;
    token: string;
};

/**
 * README ecran 3, en lecture seule : visuel, date, heure, capacite, tarif, places restantes,
 * date limite. Pas de mise en page d'authentification ici : c'est la premiere surface non
 * authentifiee du produit, mobile d'abord.
 *
 * Les couleurs de marque du locataire s'appliquent uniquement au parcours invite (CLAUDE.md) :
 * elles arrivent deja validees par une expression reguliere hexadecimale stricte cote serveur,
 * elles peuvent donc entrer sans risque dans une variable CSS.
 *
 * `event.colors` prime sur `tenant.colors` (README ecran 13) : `Event::colors()` retombe deja
 * sur celles de l'organisation quand l'evenement n'en definit pas, ce composant n'a pas a
 * choisir entre les deux lui-meme.
 */
export default function PublicEvent({ event, tenant, token }: Props) {
    const { t, locale } = useTranslation();

    const bannerUrl = event.visualUrl ?? tenant.bannerUrl;

    return (
        <div className="bg-background flex min-h-screen flex-col">
            <BrandColorStyle colors={event.colors} />
            <Head title={event.name} />
            <OfflineBanner />

            <header className="flex items-center justify-between p-4">
                <div className="flex items-center gap-2">
                    {tenant.logoUrl ? (
                        <img
                            src={tenant.logoUrl}
                            alt={tenant.displayName}
                            className="h-8 w-8 rounded object-contain"
                        />
                    ) : null}
                    <span className="text-sm font-medium">
                        {tenant.displayName}
                    </span>
                </div>
                <LocaleSwitcher />
            </header>

            {bannerUrl ? (
                <img
                    src={bannerUrl}
                    alt=""
                    className="h-40 w-full object-cover sm:h-56"
                />
            ) : null}

            <main className="mx-auto w-full max-w-lg flex-1 space-y-6 p-4">
                <InstallPrompt />

                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-[color:var(--brand-primary)]">
                        {event.name}
                    </h1>
                    {event.subtitle ? (
                        <p className="text-muted-foreground">
                            {event.subtitle}
                        </p>
                    ) : null}
                </div>

                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <InfoRow icon={Calendar}>
                            {event.startsAt
                                ? formatDateTime(event.startsAt, locale)
                                : t('guest.event.no_date')}
                        </InfoRow>

                        {event.venue ? (
                            <InfoRow icon={MapPin}>
                                {event.venue}
                                {event.venueAddress
                                    ? `, ${event.venueAddress}`
                                    : ''}
                            </InfoRow>
                        ) : null}

                        <InfoRow icon={Users}>
                            {event.isFull
                                ? t('guest.event.seats.full')
                                : t('guest.event.seats.remaining', {
                                      count: event.remainingSeats,
                                  })}
                        </InfoRow>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('guest.event.price_per_person')}
                        </CardTitle>
                        <CardDescription>
                            {t('guest.event.companion_limit', {
                                count: event.companionLimit,
                            })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-2xl font-semibold text-[color:var(--brand-primary)]">
                            {formatAmount(event.pricePerPerson, locale)}
                        </p>
                    </CardContent>
                </Card>

                {event.registrationDeadline ? (
                    <p className="text-muted-foreground text-center text-sm">
                        {t('guest.event.deadline.label')} :{' '}
                        {formatDateTime(event.registrationDeadline, locale)}
                    </p>
                ) : null}

                <RegistrationState event={event} token={token} />
            </main>
        </div>
    );
}

function InfoRow({
    icon: Icon,
    children,
}: {
    icon: typeof Calendar;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-start gap-3 text-sm">
            <Icon className="mt-0.5 h-4 w-4 shrink-0 text-[var(--brand-primary)]" />
            <span>{children}</span>
        </div>
    );
}

function RegistrationState({
    event,
    token,
}: {
    event: PublicEvent;
    token: string;
}) {
    const { t } = useTranslation();

    if (event.acceptsRegistrations) {
        return (
            <Button asChild className="w-full">
                <Link href={create(token)} data-test="register-link">
                    {t('guest.event.register')}
                </Link>
            </Button>
        );
    }

    if (event.isFull) {
        return (
            <div className="space-y-3 text-center">
                <p className="text-sm font-medium">
                    {t('guest.event.seats.full')}
                </p>
                <Button asChild variant="secondary" className="w-full">
                    <Link
                        href={createWaitlistEntry(token)}
                        data-test="waitlist-link"
                    >
                        {t('guest.waitlist.join')}
                    </Link>
                </Button>
            </div>
        );
    }

    if (event.registrationDeadlineHasPassed) {
        return (
            <p className="text-muted-foreground text-center text-sm">
                {t('guest.event.deadline.passed')}
            </p>
        );
    }

    return (
        <p className="text-muted-foreground text-center text-sm">
            {t('guest.event.registration_closed')}
        </p>
    );
}
