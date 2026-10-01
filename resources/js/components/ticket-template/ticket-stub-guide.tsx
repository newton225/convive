import { QrCode } from 'lucide-react';

/**
 * Repere pose sur la zone de rognage du fond du billet : l'emplacement du cadre du QR et de la
 * pastille de table, qui masqueront l'image sur le billet. Translucide, pour cadrer l'image en
 * voyant ce qui restera visible autour.
 *
 * Les pourcentages reprennent le haut du talon de `branded-ticket.tsx`, large de 320 px et haut de
 * 272 px : marge haute de 32 px, cadre du QR de 188 px, 8 px, pastille de 24 px. Les deux evoluent
 * ensemble. La pastille change de largeur avec le numero de table : la sienne est indicative.
 */
export function TicketStubGuide() {
    return (
        <div className="relative size-full" data-test="ticket-stub-guide">
            <span className="text-ink/60 absolute top-[11.765%] left-[20.625%] flex h-[69.118%] w-[58.75%] items-center justify-center rounded-md bg-white/50 ring-1 ring-black/30">
                <QrCode className="size-1/2" strokeWidth={1} />
            </span>
            <span className="absolute top-[83.824%] left-[37.5%] h-[8.824%] w-1/4 rounded-full bg-white/50 ring-1 ring-black/30" />
        </div>
    );
}
