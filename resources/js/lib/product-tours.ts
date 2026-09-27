/**
 * Les visites guidees du back-office. Chaque etape pointe un repere `data-tour` de la page ; une
 * etape sans repere s'affiche au centre de l'ecran. Une etape dont le repere est absent (menu replie
 * sur telephone, bouton reserve a un autre profil) est simplement sautee.
 *
 * Les identifiants recopient `App\Enums\ProductTour`.
 */
export type ProductTourId =
    | 'welcome'
    | 'first_event'
    | 'proofs'
    | 'entry_control';

export type ProductTourStep = {
    // Le repere vise, sans le selecteur : `nav-events` pour `[data-tour="nav-events"]`.
    anchor?: string;
    // Cle de traduction du groupe `tours` : `<tour>.<step>.title` et `.body`.
    key: string;
    side?: 'top' | 'right' | 'bottom' | 'left';
};

export const productTours: Record<ProductTourId, ProductTourStep[]> = {
    welcome: [
        { key: 'intro' },
        { anchor: 'tenant-switcher', key: 'tenant', side: 'right' },
        { anchor: 'nav-dashboard', key: 'dashboard', side: 'right' },
        { anchor: 'nav-events', key: 'events', side: 'right' },
        { anchor: 'nav-entry-control', key: 'entry_control', side: 'right' },
        { anchor: 'nav-organisation', key: 'organisation', side: 'right' },
        { anchor: 'notifications', key: 'notifications', side: 'bottom' },
        { anchor: 'user-menu', key: 'account', side: 'right' },
    ],
    first_event: [
        { key: 'intro' },
        { anchor: 'event-step-1', key: 'identity', side: 'top' },
        { anchor: 'event-step-2', key: 'seating', side: 'top' },
        {
            anchor: 'event-payment-accounts',
            key: 'payment_accounts',
            side: 'top',
        },
        { anchor: 'event-step-3', key: 'deadlines', side: 'top' },
        { anchor: 'event-submit', key: 'save', side: 'top' },
        { anchor: 'event-publish', key: 'publish', side: 'bottom' },
        { key: 'share' },
    ],
    proofs: [
        { key: 'intro' },
        { anchor: 'proof-queue', key: 'queue', side: 'top' },
        { anchor: 'proof-receipt', key: 'receipt', side: 'left' },
        { anchor: 'proof-signals', key: 'signals', side: 'top' },
        { anchor: 'proof-approve', key: 'approve', side: 'left' },
        { anchor: 'proof-reject', key: 'reject', side: 'left' },
        { key: 'after' },
    ],
    entry_control: [
        { key: 'intro' },
        { anchor: 'scan-pin', key: 'pin', side: 'bottom' },
        { anchor: 'scan-viewfinder', key: 'viewfinder', side: 'right' },
        { key: 'results' },
        { anchor: 'scan-station', key: 'station', side: 'right' },
        { anchor: 'scan-counter', key: 'counter', side: 'right' },
        { anchor: 'scan-recent', key: 'recent', side: 'left' },
        { key: 'offline' },
    ],
};
