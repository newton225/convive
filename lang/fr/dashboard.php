<?php

return [
    'title' => 'Tableau de bord',
    'event_label' => 'Événement',

    'kpis' => [
        'registrations' => 'Inscrits',
        'validated' => 'Preuves validées',
        'to_check' => 'Preuves à vérifier',
        'without_proof' => 'Sans preuve',
        'seats_left' => 'Places restantes',
    ],

    'countdown' => '{0} Aujourd\'hui|[1,*] J-:days',
    'check_proofs' => 'Vérifier les preuves (:count)',

    'hints' => [
        'this_week' => '{0} Aucune cette semaine|{1} +1 cette semaine|[2,*] +:count cette semaine',
        'validated' => ':share % du total · :amount encaissés',
        'waiting' => '{1} dont 1 en attente depuis plus de 24 h|[2,*] dont :count en attente depuis plus de 24 h',
        'purge' => 'purge automatique :when',
    ],

    'hold_expiry' => [
        'rate' => ':rate % des réservations ont expiré sans preuve (:lapsed sur :holds).',
        'none' => 'Aucune réservation pour le moment.',
        'help' => 'Un taux qui monte brusquement peut signaler des réservations faites par un robot pour bloquer les places.',
    ],

    'charts' => [
        'per_day' => 'Inscriptions par jour',
        'per_day_summary' => 'Inscriptions par jour sur les :days derniers jours, :total au total.',
        'by_channel' => 'Preuves par canal',
        'by_channel_summary' => 'Répartition des preuves déposées par canal de paiement.',
        'tables' => 'Occupation des tables',
        'table_label' => 'Table :number',
        'seated' => ':seated sur :capacity',
        'registrations_series' => 'Inscriptions',
        'proofs_series' => 'Preuves',
    ],

    'activity' => [
        'title' => 'Activité récente',
        'empty' => 'Aucune activité pour le moment.',
        'types' => [
            'proof_received' => ':name a déposé une preuve de paiement.',
            'proof_approved' => 'La preuve de :name a été validée.',
            'proof_rejected' => 'La preuve de :name a été rejetée.',
            'registration_cancelled' => 'L\'inscription de :name a été annulée.',
            'hold_expired' => 'La réservation de :name a expiré.',
            'scan_refused' => 'Un billet a été refusé à l\'entrée.',
        ],
    ],

    'empty' => [
        'title' => 'Aucun événement pour le moment',
        'description' => 'Créez votre premier événement pour voir vos chiffres ici.',
        'cta' => 'Créer un événement',
    ],
];
