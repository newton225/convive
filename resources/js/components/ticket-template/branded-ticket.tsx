import { Check, ImageOff } from 'lucide-react';
import { useId } from 'react';
import { BrandColorStyle } from '@/components/brand-color-style';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { TicketBrand, TicketElements, TicketModel } from '@/types';

type Props = {
    brand: TicketBrand;
    model: TicketModel;
    elements: TicketElements;
    eventName: string;
};

/**
 * Le billet aux couleurs de l'organisation, tel que l'invite le recevra. C'est une feuille de
 * papier : elle reste blanche, encre sur fond clair, dans le theme sombre aussi. Les couleurs de
 * marque n'y entrent qu'en variables CSS (elles arrivent validees par une expression reguliere
 * hexadecimale stricte cote serveur). Les noms sont des donnees d'exemple.
 */
export function BrandedTicket({ brand, model, elements, eventName }: Props) {
    const { t } = useTranslation();
    // Selecteur scope, pas `:root` : ce billet s'affiche imbrique dans une page de back-office
    // (ecran 15), qui doit rester neutre (CLAUDE.md). `useId()` porte deja des deux-points,
    // invalides dans un selecteur de classe brut : on les retire.
    const scopeClass = `brand-ticket-${useId().replace(/:/g, '')}`;

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

    return (
        <article
            className={cn(
                scopeClass,
                'text-ink w-full max-w-sm bg-white p-6',
                model === 'classic' &&
                    'rounded-xl border-2 border-[color:var(--brand-primary)] text-center',
                model === 'sober' && 'rounded-lg text-left',
                model === 'elegant' && 'rounded-2xl text-center',
            )}
            data-test="branded-ticket"
            data-model={model}
        >
            <BrandColorStyle
                colors={brand.colors}
                selector={`.${scopeClass}`}
            />
            <div
                className={cn(
                    'flex items-center gap-3',
                    model === 'sober' ? 'justify-start' : 'justify-center',
                )}
            >
                {asset(
                    elements.logo,
                    brand.logoUrl,
                    brand.displayName,
                    'size-10 object-contain',
                )}
                <span className="text-sm font-medium">{brand.displayName}</span>
            </div>

            <h3
                className={cn(
                    'mt-4 text-2xl leading-tight font-semibold text-[color:var(--brand-primary)]',
                    model === 'elegant' && 'font-serif text-3xl',
                )}
            >
                {eventName}
            </h3>

            {model === 'elegant' ? (
                <div
                    className="mx-auto mt-3 h-0.5 w-16 bg-[color:var(--brand-secondary)]"
                    aria-hidden="true"
                />
            ) : null}

            <dl
                className={cn(
                    'mt-5 grid gap-x-6 gap-y-1 text-sm',
                    model === 'sober'
                        ? 'grid-cols-[minmax(0,1fr)_auto_auto]'
                        : 'grid-cols-3',
                )}
            >
                <div>
                    <dt className="text-ink/60 text-xs">
                        {t('ticket_template.preview.guest')}
                    </dt>
                    <dd className="font-medium">Aya Kouassi</dd>
                </div>
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
            </dl>

            {elements.companions ? (
                <div className="mt-4 text-sm">
                    <p className="text-ink/60 text-xs">
                        {t('ticket_template.preview.companions')}
                    </p>
                    <ul className="mt-1">
                        <li>Kofi Kouassi</li>
                        <li>Marie Kouassi</li>
                    </ul>
                </div>
            ) : null}

            <div className="border-ink/20 mt-5 flex items-end justify-between gap-4 border-t border-dashed pt-4">
                <span
                    className="inline-flex items-center gap-1.5 rounded-full bg-[color:var(--brand-primary)]/10 px-3 py-1 text-xs font-medium text-[color:var(--brand-primary)]"
                    role="status"
                >
                    <Check className="size-3.5" />
                    {t('ticket_template.preview.valid')}
                </span>
                <div className="flex items-end gap-2">
                    {asset(
                        elements.stamp,
                        brand.stampUrl,
                        t('ticket_template.elements.stamp'),
                        'size-14 object-contain',
                    )}
                    {asset(
                        elements.signature,
                        brand.signatureUrl,
                        t('ticket_template.elements.signature'),
                        'h-10 w-20 object-contain',
                    )}
                </div>
            </div>
        </article>
    );
}
