import { CalendarDays, Check, ImageOff, QrCode } from 'lucide-react';
import { useId } from 'react';
import { BrandColorStyle } from '@/components/brand-color-style';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { cn } from '@/lib/utils';
import type { TicketBrand, TicketElements, TicketModel } from '@/types';

type Props = {
    brand: TicketBrand;
    model: TicketModel;
    elements: TicketElements;
    eventName: string;
    eventStartsAt: string | null;
};

/**
 * Le billet aux couleurs de l'organisation, tel que l'invite le recevra, au format talon : bords
 * festonnes en haut et en bas, le QR seul dans la partie haute (c'est elle que l'agent scanne),
 * une ligne perforee, puis ce que l'invite lit. C'est du papier : blanc, encre sur fond clair,
 * dans le theme sombre aussi. Les couleurs de marque n'y entrent qu'en variables CSS (validees par
 * une expression reguliere hexadecimale stricte cote serveur). Les noms sont des donnees d'exemple.
 *
 * Festons et encoches sont des disques de la couleur du fond sur lequel le billet est pose
 * (`bg-muted`, voir la page), poses sur le bord : ils se lisent comme des decoupes sans masque ni
 * SVG dessine a la main. Le fond ne doit donc pas changer sans elles.
 */
export function BrandedTicket({
    brand,
    model,
    elements,
    eventName,
    eventStartsAt,
}: Props) {
    const { t, locale } = useTranslation();
    // Selecteur scope, pas `:root` : ce billet s'affiche imbrique dans une page de back-office
    // (ecran 15), qui doit rester neutre (CLAUDE.md). `useId()` porte deja des deux-points,
    // invalides dans un selecteur de classe brut : on les retire.
    const scopeClass = `brand-ticket-${useId().replace(/:/g, '')}`;
    const centered = model !== 'sober';

    const asset = (
        enabled: boolean,
        url: string | null,
        alt: string,
        className: string,
    ) =>
        enabled ? (
            url ? (
                <img src={url} alt={alt} className={className} />
            ) : (
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
            )
        ) : null;

    const notches = (position: string) => (
        <>
            <span
                className={cn(
                    'bg-muted absolute -left-2.5 size-5 rounded-full',
                    position,
                )}
                aria-hidden="true"
            />
            <span
                className={cn(
                    'bg-muted absolute -right-2.5 size-5 rounded-full',
                    position,
                )}
                aria-hidden="true"
            />
        </>
    );

    return (
        <article
            className={cn(
                scopeClass,
                'text-ink relative flex min-h-[34rem] w-64 flex-col bg-white',
            )}
            data-test="branded-ticket"
            data-model={model}
        >
            <BrandColorStyle
                colors={brand.colors}
                selector={`.${scopeClass}`}
            />

            <span
                className="absolute inset-x-0 -top-1.5 h-3 bg-[radial-gradient(circle,var(--muted)_5px,transparent_5.5px)] bg-[length:14px_12px] bg-repeat-x"
                aria-hidden="true"
            />
            <span
                className="absolute inset-x-0 -bottom-1.5 h-3 bg-[radial-gradient(circle,var(--muted)_5px,transparent_5.5px)] bg-[length:14px_12px] bg-repeat-x"
                aria-hidden="true"
            />

            {/* Partie haute : le QR, et la table pour que l'agent oriente l'invite d'un regard. */}
            <div
                className={cn(
                    'flex flex-col items-center gap-2 px-5 pt-7 pb-5',
                    model === 'classic' && 'bg-[color:var(--brand-primary)]/8',
                )}
            >
                <span
                    className="rounded-md bg-white p-1.5 ring-1 ring-black/10"
                    data-test="ticket-qr"
                >
                    <QrCode
                        className="size-24"
                        strokeWidth={1.25}
                        aria-label={t('ticket_template.preview.qr')}
                    />
                </span>
                <p className="text-sm font-semibold">
                    {t('ticket_template.preview.table')} 7
                </p>
            </div>

            <div className="relative" aria-hidden="true">
                {notches('-top-2.5')}
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
                    {asset(
                        elements.logo,
                        brand.logoUrl,
                        brand.displayName,
                        'size-8 object-contain',
                    )}
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
                    {eventName}
                </h3>

                {model === 'elegant' ? (
                    <div
                        className="mx-auto mt-2 h-0.5 w-12 bg-[color:var(--brand-secondary)]"
                        aria-hidden="true"
                    />
                ) : null}

                {eventStartsAt ? (
                    <p
                        className={cn(
                            'text-ink/70 mt-2 flex items-center gap-1.5 text-xs',
                            centered && 'justify-center',
                        )}
                    >
                        <CalendarDays className="size-3.5" aria-hidden />
                        {formatDateTime(eventStartsAt, locale)}
                    </p>
                ) : null}

                <dl className="mt-5 space-y-3 text-sm">
                    <div>
                        <dt className="text-ink/60 text-xs">
                            {t('ticket_template.preview.guest')}
                        </dt>
                        <dd className="text-base font-semibold">Aya Kouassi</dd>
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
                            <dd className="font-medium">7</dd>
                        </div>
                        <div>
                            <dt className="text-ink/60 text-xs">
                                {t('ticket_template.preview.seats')}
                            </dt>
                            <dd className="font-medium">3</dd>
                        </div>
                    </div>
                    {elements.companions ? (
                        <div>
                            <dt className="text-ink/60 text-xs">
                                {t('ticket_template.preview.companions')}
                            </dt>
                            <dd>
                                <ul>
                                    <li>Kofi Kouassi</li>
                                    <li>Marie Kouassi</li>
                                </ul>
                            </dd>
                        </div>
                    ) : null}
                </dl>
            </div>

            {/* Pied : validite, cachet et signature, sous la seconde paire d'encoches. */}
            <div className="relative mt-5 px-5 pt-4 pb-7">
                {notches('-top-2.5')}
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
                    {elements.stamp || elements.signature ? (
                        <div className="flex items-end gap-2">
                            {asset(
                                elements.stamp,
                                brand.stampUrl,
                                t('ticket_template.elements.stamp'),
                                'size-12 object-contain',
                            )}
                            {asset(
                                elements.signature,
                                brand.signatureUrl,
                                t('ticket_template.elements.signature'),
                                'h-9 w-20 object-contain',
                            )}
                        </div>
                    ) : null}
                </div>
            </div>
        </article>
    );
}
