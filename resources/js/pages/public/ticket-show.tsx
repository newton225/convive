import { Head } from '@inertiajs/react';
import { BrandColorStyle } from '@/components/brand-color-style';
import { OfflineBanner } from '@/components/offline-banner';
import { DirectionsLink } from '@/components/public/directions-link';
import { TicketPdfDownload } from '@/components/public/ticket-pdf-download';
import { BrandedTicket } from '@/components/ticket-template/branded-ticket';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import type {
    PublicRegistrationTenant,
    PublicTicketPass,
    TicketCardData,
    TicketCardEvent,
    TicketDesign,
} from '@/types';

type Props = {
    event: {
        name: string;
        startsAt: string | null;
        endsAt: string | null;
        venue: string | null;
        venueMapUrl: string | null;
    };
    tenant: PublicRegistrationTenant;
    ticket: PublicTicketPass;
    card: TicketCardData;
    ticketEvent: TicketCardEvent;
    design: TicketDesign;
};

/**
 * Le lien individuel d'un billet (README 2.8, un billet par personne) : ce que recoit un
 * accompagnateur qui arrivera sans l'invite. Ce seul billet, jamais le dossier du groupe.
 */
export default function PublicTicketShow({
    event,
    tenant,
    ticket,
    card,
    ticketEvent,
    design,
}: Props) {
    const { t, locale } = useTranslation();
    const title = t('guest.ticket.single_title', { name: ticket.name });

    return (
        <div className="bg-background flex min-h-screen flex-col items-center p-4">
            <BrandColorStyle colors={tenant.colors} />
            <Head title={title} />
            <OfflineBanner />

            <main className="w-full max-w-md space-y-6 pt-12">
                <div className="space-y-1 text-center">
                    {tenant.logoUrl ? (
                        <img
                            src={tenant.logoUrl}
                            alt={tenant.displayName}
                            className="mx-auto h-12 object-contain"
                        />
                    ) : null}
                    <h1 className="text-2xl font-semibold">{event.name}</h1>
                    {event.startsAt ? (
                        <p className="text-muted-foreground text-sm">
                            {formatDateTime(event.startsAt, locale)}
                            {event.endsAt
                                ? ` ${t('guest.event.until', { date: formatDateTime(event.endsAt, locale) })}`
                                : ''}
                        </p>
                    ) : null}
                    {event.venue ? (
                        <p className="text-muted-foreground text-sm">
                            {event.venue}
                        </p>
                    ) : null}
                    {event.venueMapUrl ? (
                        <DirectionsLink href={event.venueMapUrl} />
                    ) : null}
                </div>

                {/* Le meme talon que celui de l'invite principal et que le PDF. */}
                <section data-test="ticket-single">
                    <BrandedTicket
                        {...design}
                        event={ticketEvent}
                        ticket={card}
                    />
                    <p className="text-muted-foreground mt-4 text-center text-sm">
                        {t('guest.ticket.single_notice')}
                    </p>
                </section>

                {ticket.pdfUrl ? (
                    <TicketPdfDownload url={ticket.pdfUrl} />
                ) : null}
            </main>
        </div>
    );
}
