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

    'refunds_due' => '{1} 1 inscription annulée attend son remboursement : :amount à rendre.|[2,*] :count inscriptions annulées attendent leur remboursement : :amount à rendre.',
    'refunds_due_action' => 'Voir les annulations',

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

    'getting_started' => [
        'title' => 'Premiers pas',
        'description' => 'Cinq étapes pour ouvrir les inscriptions de votre premier événement.',
        'progress' => ':done sur :total',
        'done' => 'Fait',
        'steps' => [
            'identity' => [
                'title' => "Compléter l'identité de l'organisation",
                'hint' => 'Raison sociale, forme juridique, numéros, adresse et sous-domaine : ils figurent sur les reçus et les billets.',
                'action' => 'Compléter',
            ],
            'payment_account' => [
                'title' => 'Ajouter un compte de versement',
                'hint' => "C'est là que vos invités verseront leur participation (actif 24 h après sa création).",
                'action' => 'Ajouter',
            ],
            'event' => [
                'title' => 'Créer un événement',
                'hint' => 'Nom, date, lieu, places et tarif : il reste en brouillon tant que vous ne publiez pas.',
                'action' => 'Créer',
            ],
            'publish' => [
                'title' => 'Publier le lien public',
                'hint' => "Depuis la fiche de l'événement, puis copiez le lien pour l'envoyer à vos invités.",
                'action' => 'Voir mes événements',
            ],
            'team' => [
                'title' => 'Inviter votre équipe',
                'hint' => "Trésorier pour les preuves, hôtesse pour l'entrée : chacun avec son profil.",
                'action' => 'Inviter',
            ],
        ],
    ],

    'empty' => [
        'title' => 'Aucun événement pour le moment',
        'description' => 'Créez votre premier événement pour voir vos chiffres ici.',
        'cta' => 'Créer un événement',
    ],
];
