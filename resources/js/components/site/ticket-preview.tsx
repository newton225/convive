import { Check } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Le billet, tel qu'un invite le recoit : l'artefact reel du produit plutot qu'une illustration
 * abstraite. Les noms sont des donnees d'exemple, les libelles passent par les traductions. Le
 * serif est reserve aux billets et aux invitations (CLAUDE.md). Un billet est une feuille de
 * papier : il reste blanc, encre sur fond clair, dans le theme sombre aussi.
 */
export function TicketPreview() {
    const { t } = useTranslation();

    return (
        <div
            className="text-ink w-full max-w-sm rounded-2xl bg-white p-6"
            data-test="site-ticket-preview"
        >
            <p className="font-serif text-2xl leading-tight font-semibold">
                {t('site.preview.ticket.event')}
            </p>
            <p className="text-ink/60 mt-1 text-sm">
                {t('site.preview.ticket.date')}
            </p>

            <dl className="mt-6 grid grid-cols-[minmax(0,1fr)_auto_auto] gap-x-6 gap-y-1 text-sm">
                <div>
                    <dt className="text-ink/60 text-xs">
                        {t('site.preview.ticket.guest')}
                    </dt>
                    <dd className="font-medium">Aya Kouassi</dd>
                </div>
                <div>
                    <dt className="text-ink/60 text-xs">
                        {t('site.preview.ticket.table')}
                    </dt>
                    <dd className="font-medium">7</dd>
                </div>
                <div>
                    <dt className="text-ink/60 text-xs">
                        {t('site.preview.ticket.seats')}
                    </dt>
                    <dd className="font-medium">3</dd>
                </div>
            </dl>

            <div className="border-ink/20 mt-6 flex items-center justify-between gap-4 border-t border-dashed pt-5">
                <span
                    className="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700"
                    role="status"
                >
                    <Check className="size-3.5" />
                    {t('site.preview.ticket.valid')}
                </span>

                {/* Un motif de QR : illustratif, jamais un vrai jeton. */}
                <svg
                    viewBox="0 0 7 7"
                    className="text-ink size-16 shrink-0"
                    aria-hidden="true"
                    shapeRendering="crispEdges"
                >
                    {[
                        '1110111',
                        '1010101',
                        '1110111',
                        '0001010',
                        '1101101',
                        '0111001',
                        '1010110',
                    ].flatMap((row, y) =>
                        row
                            .split('')
                            .map((cell, x) =>
                                cell === '1' ? (
                                    <rect
                                        key={`${x}-${y}`}
                                        x={x}
                                        y={y}
                                        width="1"
                                        height="1"
                                        fill="currentColor"
                                    />
                                ) : null,
                            ),
                    )}
                </svg>
            </div>
        </div>
    );
}
