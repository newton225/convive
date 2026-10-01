import { CalendarDays, Check, ImageOff, QrCode } from 'lucide-react';
import { useId } from 'react';
import { BrandColorStyle } from '@/components/brand-color-style';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { cn } from '@/lib/utils';
import type { TicketCardData, TicketCardEvent, TicketDesign } from '@/types';

type Props = TicketDesign & {
    event: TicketCardEvent;
    ticket: TicketCardData;
    // Apercu du gabarit : un fichier de marque active mais absent se signale, au lieu de
    // disparaitre sans que l'exploitant comprenne pourquoi. Jamais sur le billet d'un invite.
    showMissing?: boolean;
    // Faux quand la page pose elle-meme le fond gris, pour animer le billet seul sur un fond fixe
    // (apercu du gabarit). Ce fond doit rester `bg-muted` : festons et encoches en ont la couleur.
    withBackdrop?: boolean;
};

/**
 * Le billet au format talon, le meme pour l'invite principal et chacun de ses accompagnateurs
 * (README ecran 7 et 15) : sur la page de l'invite, sur le lien individuel d'un accompagnateur,
 * dans l'apercu du gabarit, et reproduit a l'identique par `resources/views/pdf/tickets.blade.php`.
 * Ses donnees viennent toutes de `App\Support\TicketCard`.
 *
 * Bords festonnes, le QR seul dans la partie haute (c'est elle que l'agent scanne), une ligne
 * perforee, puis ce que l'invite lit : son nom, sa table, et selon le billet ses accompagnateurs
 * ou la personne qui l'invite. C'est du papier : blanc, encre sur fond clair, dans le theme sombre
 * aussi. Les couleurs de marque n'y entrent qu'en variables CSS (validees par une expression
 * reguliere hexadecimale stricte cote serveur).
 *
 * Festons et encoches sont des disques de la couleur du fond gris sur lequel le billet est pose,
 * fourni ici meme : ils se lisent comme des decoupes sans masque ni SVG dessine a la main.
 */
export function BrandedTicket({
    model,
    elements,
    brand,
    event,
    ticket,
    showMissing = false,
    withBackdrop = true,
}: Props) {
    const { t, locale } = useTranslation();
    // Selecteur scope, pas `:root` : ce billet s'affiche aussi dans une page de back-office (ecran
    // 15), qui doit rester neutre (CLAUDE.md). `useId()` porte des deux-points, invalides dans un
    // selecteur de classe brut : on les retire.
    const scopeClass = `brand-ticket-${useId().replace(/:/g, '')}`;
    const centered = model !== 'sober';

    const asset = (
        enabled: boolean,
        url: string | null,
        alt: string,
        className: string,
    ) =>
        enabled && url ? (
            <img src={url} alt={alt} className={className} />
        ) : enabled && showMissing ? (
            <span
                className={cn(
                    'text-ink/50 inline-flex items-center justify-center rounded border border-dashed border-current',
                    className,
                )}
                title={t('ticket_template.elements.missing')}
            >
                <ImageOff className="size-4" />
                <span className="sr-only">{alt}</span>
            </span>
        ) : null;

    const notches = (
        <>
            <span
                className="bg-muted absolute -top-2.5 -left-2.5 size-5 rounded-full"
                aria-hidden="true"
            />
            <span
                className="bg-muted absolute -top-2.5 -right-2.5 size-5 rounded-full"
                aria-hidden="true"
            />
        </>
    );

    const logo = asset(
        elements.logo,
        brand.logoUrl,
        brand.displayName,
        'size-8 object-contain',
    );
    const stamp = asset(
        elements.stamp,
        brand.stampUrl,
        t('ticket_template.elements.stamp'),
        'size-12 object-contain',
    );
    const signature = asset(
        elements.signature,
        brand.signatureUrl,
        t('ticket_template.elements.signature'),
        'h-9 w-20 object-contain',
    );

    return (
        <div
            className={cn(
                'flex justify-center',
                withBackdrop && 'bg-muted rounded-xl px-3 py-8',
            )}
        >
            <article
                className={cn(
                    scopeClass,
                    'text-ink relative flex min-h-[36rem] w-full max-w-80 flex-col bg-white',
                )}
                data-test="branded-ticket"
                data-model={model}
            >
                <BrandColorStyle
                    colors={brand.colors}
                    selector={`.${scopeClass}`}
                />

                <span
                    className="absolute inset-x-0 -top-1.5 z-10 h-3 bg-[radial-gradient(circle,var(--muted)_5px,transparent_5.5px)] bg-[length:14px_12px] bg-repeat-x"
                    aria-hidden="true"
                />
                <span
                    className="absolute inset-x-0 -bottom-1.5 z-10 h-3 bg-[radial-gradient(circle,var(--muted)_5px,transparent_5.5px)] bg-[length:14px_12px] bg-repeat-x"
                    aria-hidden="true"
                />

                {/* Partie haute : le QR, et la table pour que l'agent oriente l'invite d'un regard. */}
                <div
                    className={cn(
                        'relative isolate flex flex-col items-center gap-2 overflow-hidden px-5 pt-8 pb-5',
                        model === 'classic' &&
                            !brand.backgroundUrl &&
                            'bg-[color:var(--brand-primary)]/8',
                    )}
                >
                    {/* Fond personnalise de l'organisation, derriere le QR. Une image plutot qu'un
                        style en ligne (CSP). Le QR garde son cadre blanc et la table sa pastille :
                        le scan et la lecture ne dependent jamais de l'image choisie. */}
                    {brand.backgroundUrl ? (
                        <img
                            src={brand.backgroundUrl}
                            alt=""
                            className="absolute inset-0 -z-10 size-full object-cover"
                            data-test="ticket-background"
                        />
                    ) : null}
                    <span className="rounded-md bg-white p-1.5 ring-1 ring-black/10">
                        {ticket.qrImage ? (
                            <img
                                src={ticket.qrImage}
                                alt={t('ticket_template.preview.qr_of', {
                                    name: ticket.holder.name,
                                })}
                                className="size-44"
                                data-test="ticket-qr"
                            />
                        ) : (
                            <QrCode
                                className="size-44"
                                strokeWidth={1}
                                aria-label={t('ticket_template.preview.qr')}
                                data-test="ticket-qr"
                            />
                        )}
                    </span>
                    <p
                        className={cn(
                            'text-sm font-semibold',
                            brand.backgroundUrl &&
                                'rounded-full bg-white px-3 py-0.5',
                        )}
                    >
                        {ticket.tableNumber !== null
                            ? t('guest.ticket.table', {
                                  number: String(ticket.tableNumber),
                              })
                            : t('guest.ticket.no_table')}
                    </p>
                </div>

                <div className="relative" aria-hidden="true">
                    {notches}
                    <div className="border-ink/40 mx-4 border-t-2 border-dotted" />
                </div>

                <div
                    className={cn(
                        'flex flex-1 flex-col px-5 pt-5',
                        centered ? 'text-center' : 'text-left',
                    )}
                >
                    <div
                        className={cn(
                            'flex items-center gap-2',
                            centered ? 'justify-center' : 'justify-start',
                        )}
                    >
                        {logo}
                        <span className="text-ink/70 text-xs font-medium tracking-wide uppercase">
                            {brand.displayName}
                        </span>
                    </div>

                    <h3
                        className={cn(
                            'mt-3 text-xl leading-tight font-semibold break-words text-[color:var(--brand-primary)]',
                            model === 'elegant' && 'font-serif text-2xl',
                        )}
                    >
                        {event.name}
                    </h3>

                    {model === 'elegant' ? (
                        <div
                            className="mx-auto mt-2 h-0.5 w-12 bg-[color:var(--brand-secondary)]"
                            aria-hidden="true"
                        />
                    ) : null}

                    {event.startsAt || event.venue ? (
                        <p
                            className={cn(
                                'text-ink/70 mt-2 flex items-start gap-1.5 text-xs',
                                centered && 'justify-center',
                            )}
                        >
                            <CalendarDays
                                className="mt-px size-3.5 shrink-0"
                                aria-hidden
                            />
                            <span>
                                {[
                                    event.startsAt
                                        ? formatDateTime(event.startsAt, locale)
                                        : null,
                                    event.venue,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                        </p>
                    ) : null}

                    <dl className="mt-5 space-y-3 text-sm">
                        <div>
                            <dt className="text-ink/60 text-xs">
                                {t('ticket_template.preview.guest')}
                            </dt>
                            <dd
                                className="text-base font-semibold break-words"
                                data-test="ticket-holder"
                            >
                                {ticket.holder.name}
                            </dd>
                            <dd className="text-ink/70 text-xs">
                                {ticket.holder.unit}
                            </dd>
                        </div>
                        <div
                            className={cn(
                                'grid grid-cols-2 gap-3',
                                !centered && 'max-w-40',
                            )}
                        >
                            <div>
                                <dt className="text-ink/60 text-xs">
                                    {t('ticket_template.preview.table')}
                                </dt>
                                <dd className="font-medium">
                                    {ticket.tableNumber ?? '-'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-ink/60 text-xs">
                                    {t('ticket_template.preview.seats')}
                                </dt>
                                <dd className="font-medium">{ticket.seats}</dd>
                            </div>
                        </div>
                        {ticket.host ? (
                            <div
                                className="rounded-md bg-[color:var(--brand-primary)]/6 px-3 py-2"
                                data-test="ticket-host"
                            >
                                <dt className="text-ink/60 text-xs">
                                    {t('ticket_template.preview.host')}
                                </dt>
                                <dd className="font-medium break-words">
                                    {ticket.host.name}
                                </dd>
                                <dd className="text-ink/70 text-xs">
                                    {[ticket.host.unit, ticket.host.reference]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </dd>
                            </div>
                        ) : null}
                        {ticket.companions.length > 0 ? (
                            <div data-test="ticket-companions">
                                <dt className="text-ink/60 text-xs">
                                    {t('ticket_template.preview.companions')}
                                </dt>
                                <dd>
                                    <ul className="space-y-0.5">
                                        {ticket.companions.map(
                                            (companion, index) => (
                                                <li
                                                    key={`${companion.name}-${index}`}
                                                    className="break-words"
                                                >
                                                    {companion.name}
                                                    <span className="text-ink/60 text-xs">
                                                        {' · '}
                                                        {companion.unit}
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </dd>
                            </div>
                        ) : null}
                    </dl>
                </div>

                {/* Pied : validite, cachet et signature, sous la seconde paire d'encoches. */}
                <div className="relative mt-5 px-5 pt-4 pb-8">
                    {notches}
                    <div
                        className={cn(
                            'flex flex-col gap-3',
                            centered ? 'items-center' : 'items-start',
                        )}
                    >
                        <span
                            className="inline-flex items-center gap-1.5 rounded-full bg-[color:var(--brand-primary)]/10 px-3 py-1 text-xs font-medium whitespace-nowrap text-[color:var(--brand-primary)]"
                            role="status"
                        >
                            <Check className="size-3.5" />
                            {t('ticket_template.preview.valid')}
                        </span>
                        {stamp || signature ? (
                            <div className="flex items-end gap-2">
                                {stamp}
                                {signature}
                            </div>
                        ) : null}
                    </div>
                </div>
            </article>
        </div>
    );
}
