/**
 * Repere pose sur la zone de rognage du fond du bas du billet : le voile blanc sous lequel le
 * billet affiche cette image, pour la cadrer telle qu'elle sera rendue. Sa force est le complement
 * de l'opacite de l'image dans `branded-ticket.tsx` et `resources/views/pdf/tickets.blade.php`
 * (15 %) : les trois evoluent ensemble.
 */
export function TicketBodyGuide() {
    return (
        <div className="size-full bg-white/85" data-test="ticket-body-guide" />
    );
}
