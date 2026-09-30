import { Head } from '@inertiajs/react';
import { BrandColorStyle } from '@/components/brand-color-style';
import { OfflineBanner } from '@/components/offline-banner';
import { TicketPdfDownload } from '@/components/public/ticket-pdf-download';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import type { PublicRegistrationTenant, PublicTicketPass } from '@/types';

type Props = {
    event: { name: string; startsAt: string | null };
    tenant: PublicRegistrationTenant;
    ticket: PublicTicketPass;
};

/**
 * Le lien individuel d'un billet (README 2.8, un billet par personne) : ce que recoit un
 * accompagnateur qui arrivera sans l'invite. Ce seul billet, jamais le dossier du groupe.
 */
export default function PublicTicketShow({ event, tenant, ticket }: Props) {
    const { t, locale } = useTranslation();
    const title = t('guest.ticket.single_title', { name: ticket.name });

    return (
        <div className="bg-background flex min-h-screen flex-col items-center p-4">
            <BrandColorStyle colors={tenant.colors} />
            <Head title={title} />
            <OfflineBanner />

            <main className="w-full max-w-sm space-y-6 pt-12">
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
                        </p>
                    ) : null}
                </div>

                <Card data-test="ticket-single">
                    <CardHeader className="text-center">
                        <CardTitle>{ticket.name}</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            {ticket.unit}
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-4 text-center">
                        <img
                            src={ticket.qrImage}
                            alt={title}
                            className="mx-auto h-56 w-56 bg-white"
                            data-test="ticket-qr"
                        />
                        <p className="font-medium">
                            {ticket.tableNumber !== null
                                ? t('guest.ticket.table', {
                                      number: String(ticket.tableNumber),
                                  })
                                : t('guest.ticket.no_table')}
                        </p>
                        {ticket.host ? (
                            <div
                                className="bg-muted rounded-lg px-4 py-3 text-left text-sm"
                                data-test="ticket-host"
                            >
                                <p className="text-muted-foreground text-xs font-medium uppercase">
                                    {t('guest.ticket.host_title')}
                                </p>
                                <p>
                                    <span className="font-medium">
                                        {ticket.host.name}
                                    </span>
                                    {' · '}
                                    {ticket.host.unit}
                                </p>
                                {ticket.host.reference ? (
                                    <p className="text-muted-foreground text-xs">
                                        {t('guest.ticket.host_reference', {
                                            reference: ticket.host.reference,
                                        })}
                                    </p>
                                ) : null}
                            </div>
                        ) : null}
                        <p className="text-muted-foreground text-sm">
                            {t('guest.ticket.single_notice')}
                        </p>
                    </CardContent>
                </Card>

                {ticket.pdfUrl ? (
                    <TicketPdfDownload url={ticket.pdfUrl} />
                ) : null}
            </main>
        </div>
    );
}
