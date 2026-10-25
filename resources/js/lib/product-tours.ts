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
    | 'entry_control'
    | 'seating'
    | 'treasury'
    | 'registrations'
    | 'report'
    | 'team'
    | 'profiles';

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
        { anchor: 'nav-treasury', key: 'treasury', side: 'right' },
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
        { anchor: 'scan-lookup', key: 'lookup', side: 'top' },
        { anchor: 'scan-station', key: 'station', side: 'right' },
        { anchor: 'scan-counter', key: 'counter', side: 'right' },
        { anchor: 'scan-recent', key: 'recent', side: 'left' },
        { key: 'offline' },
    ],
    seating: [
        { key: 'intro' },
        { anchor: 'seating-tables', key: 'tables', side: 'top' },
        { anchor: 'seating-unseated', key: 'unseated', side: 'top' },
        { anchor: 'seating-constraints', key: 'constraints', side: 'top' },
        { key: 'after' },
    ],
    treasury: [
        { key: 'intro' },
        { anchor: 'treasury-period', key: 'period', side: 'bottom' },
        { anchor: 'treasury-totals', key: 'totals', side: 'bottom' },
        { anchor: 'treasury-events', key: 'events', side: 'top' },
        { anchor: 'treasury-accounts', key: 'accounts', side: 'top' },
        { anchor: 'treasury-refunds', key: 'refunds', side: 'top' },
        { anchor: 'treasury-export', key: 'export', side: 'left' },
    ],
    registrations: [
        { key: 'intro' },
        { anchor: 'registrations-filters', key: 'filters', side: 'bottom' },
        { anchor: 'registrations-table', key: 'table', side: 'top' },
        { anchor: 'registrations-exports', key: 'exports', side: 'bottom' },
        { anchor: 'registrations-purge', key: 'purge', side: 'left' },
        { key: 'after' },
    ],
    report: [
        { key: 'intro' },
        { anchor: 'report-cards', key: 'cards', side: 'bottom' },
        { anchor: 'report-units', key: 'units', side: 'top' },
        { anchor: 'report-export', key: 'export', side: 'left' },
    ],
    team: [
        { key: 'intro' },
        { anchor: 'team-members', key: 'members', side: 'top' },
        { anchor: 'team-invite', key: 'invite', side: 'left' },
        { anchor: 'team-invitations', key: 'invitations', side: 'top' },
        { key: 'after' },
    ],
    profiles: [
        { key: 'intro' },
        { anchor: 'profiles-create', key: 'create', side: 'left' },
        { anchor: 'profiles-list', key: 'list', side: 'top' },
        { key: 'two_factor' },
        { key: 'after' },
    ],
};
