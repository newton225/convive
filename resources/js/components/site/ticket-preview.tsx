import { Check, Clock } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

const QrRows = [
    '1110111',
    '1010101',
    '1110111',
    '0001010',
    '1101101',
    '0111001',
    '1010110',
];

/**
 * Le billet, tel qu'un invite le recoit : l'artefact reel du produit plutot qu'une illustration
 * abstraite. Les noms sont des donnees d'exemple, les libelles passent par les traductions. Le
 * serif est reserve aux billets et aux invitations (CLAUDE.md). Un billet est du carton : papier
 * ivoire, encre dessus et filet or, dans le theme sombre aussi (decision du 2026-10-09 : le blanc
 * pur ressemblait a une page web, pas a une carte).
 *
 * Les attributs `data-stage` sont les prises de la scene d'accroche (`HeroStage`), qui anime l'etat
 * du billet : en attente, puis valide, puis scanne. Sans animation, le billet est dans son etat
 * final : valide, QR complet.
 */
export function TicketPreview() {
    const { t } = useTranslation();

    return (
        <div
            className="text-ink relative w-full max-w-sm rounded-2xl bg-[oklch(0.955_0.025_85)] p-6 [transform-style:preserve-3d]"
            data-test="site-ticket-preview"
        >
            {/* Le filet or d'une carte d'invitation, juste en retrait du bord. */}
            <span
                className="pointer-events-none absolute inset-2 [transform:translateZ(2px)] rounded-xl border border-[oklch(0.72_0.1_80/0.45)]"
                aria-hidden="true"
            />
            {/* Trois plans de profondeur : le titre, la pastille et le QR se decollent du billet
                quand la scene l'incline (`HeroStage`), sans effet au repos. */}
            <div className="[transform:translateZ(18px)]">
                <p className="font-serif text-2xl leading-tight font-semibold">
                    {t('site.preview.ticket.event')}
                </p>
                <p className="text-ink/60 mt-1 text-sm">
                    {t('site.preview.ticket.date')}
                </p>
            </div>

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
                {/* Les deux etats se superposent dans la meme cellule : la pastille garde la largeur
                    du plus long, rien ne bouge quand l'un remplace l'autre. */}
                <span
                    className="grid [transform:translateZ(26px)]"
                    role="status"
                >
                    <span
                        data-stage="pending"
                        className="inline-flex items-center gap-1.5 rounded-full bg-amber-100/80 px-3 py-1 text-xs font-medium whitespace-nowrap text-amber-800 opacity-0 [grid-area:1/1]"
                    >
                        <Clock className="size-3.5" />
                        {t('site.preview.ticket.pending')}
                    </span>
                    <span
                        data-stage="valid"
                        className="inline-flex items-center gap-1.5 rounded-full bg-indigo-100/80 px-3 py-1 text-xs font-medium whitespace-nowrap text-indigo-700 [grid-area:1/1]"
                    >
                        <Check className="size-3.5" />
                        {t('site.preview.ticket.valid')}
                    </span>
                </span>

                {/* Un motif de QR : illustratif, jamais un vrai jeton. */}
                <span className="relative block size-16 shrink-0 [transform:translateZ(34px)] overflow-hidden">
                    <svg
                        viewBox="0 0 7 7"
                        className="text-ink size-16"
                        aria-hidden="true"
                        shapeRendering="crispEdges"
                    >
                        {QrRows.flatMap((row, y) =>
                            row
                                .split('')
                                .map((cell, x) =>
                                    cell === '1' ? (
                                        <rect
                                            key={`${x}-${y}`}
                                            data-stage="qr-cell"
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
                    <span
                        data-stage="scanline"
                        className="absolute inset-x-0 top-0 h-0.5 bg-indigo-500 opacity-0 shadow-[0_0_10px_2px_rgb(99_102_241/0.6)]"
                        aria-hidden="true"
                    />
                </span>
            </div>
        </div>
    );
}
