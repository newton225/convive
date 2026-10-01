import type { TicketCardData } from '@/types';

export type SampleTicketHolder = 'guest' | 'companion';

// Dix accompagnateurs, le plafond d'un evenement : l'apercu montre le billet le plus charge, celui
// qui doit encore tenir sur le talon.
const SampleCompanions: TicketCardData['companions'] = [
    { name: 'Kofi Kouassi', unit: 'QODESH' },
    { name: 'Marie Kouassi', unit: 'CHOSEN' },
    { name: 'Yao Konan', unit: 'ELIAKIM' },
    { name: 'Awa Diallo', unit: 'SENTINELLES' },
    { name: 'Serge Bamba', unit: 'ELISHAMA' },
    { name: 'Grace Aka', unit: 'ETAT MAJOR' },
    { name: 'Ibrahim Traoré', unit: 'Aucune' },
    { name: 'Esther N’Guessan', unit: 'QODESH' },
    { name: 'Paul Yapi', unit: 'CHOSEN' },
    { name: 'Fatou Koné', unit: 'ELIAKIM' },
];

/**
 * Les billets d'exemple de l'apercu du gabarit (README ecran 15) : un invite principal et l'un de
 * ses accompagnateurs, pour voir les deux billets que le groupe recevra. Noms d'exemple, QR
 * d'exemple (`qrImage` null). La liste des accompagnateurs suit la case du gabarit, comme
 * `App\Support\TicketCard` sur les vrais billets.
 */
export function sampleTicket(
    holder: SampleTicketHolder,
    withCompanions: boolean,
): TicketCardData {
    if (holder === 'companion') {
        return {
            holder: { name: 'Kofi Kouassi', unit: 'QODESH' },
            qrImage: null,
            tableNumber: 7,
            seats: 1,
            companions: [],
            host: {
                name: 'Aya Kouassi',
                unit: 'ELIAKIM',
                reference: 'CO-2026-0042',
            },
        };
    }

    return {
        holder: { name: 'Aya Kouassi', unit: 'ELIAKIM' },
        qrImage: null,
        tableNumber: 7,
        seats: SampleCompanions.length + 1,
        companions: withCompanions ? SampleCompanions : [],
        host: null,
    };
}
