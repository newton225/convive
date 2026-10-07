<?php

return [
    'title' => 'Tableau de bord',
    'event_label' => 'Événement',
    'event_picker' => [
        'label' => 'Événement affiché',
        'automatic' => 'Le plus proche (automatique)',
    ],

    'kpis' => [
        'registrations' => 'Inscrits',
        'validated' => 'Preuves validées',
        'to_check' => 'Preuves à vérifier',
        'without_proof' => 'Sans preuve',
        'seats_left' => 'Places restantes',
    ],

    'countdown' => '{0} Aujourd\'hui|{1} Demain|[2,*] Dans :days jours',
    'check_proofs' => 'Vérifier les preuves (:count)',

    'refunds_due' => '{1} 1 inscription annulée attend son remboursement : :amount à rendre.|[2,*] :count inscriptions annulées attendent leur remboursement : :amount à rendre.',
    'refunds_due_action' => 'Voir les annulations',

    'hints' => [
        'this_week' => '{0} Aucune cette semaine|{1} +1 cette semaine|[2,*] +:count cette semaine',
        'validated' => ':share % du total · :amount encaissés',
        'waiting' => '{1} dont 1 en attente depuis plus de 24 h|[2,*] dont :count en attente depuis plus de 24 h',
        'purge' => 'purge automatique :when',
        'capacity' => '{0} aucune place au plan de salle|{1} sur 1 place au total|[2,*] sur :count places au total',
    ],

    // Ce que chaque bloc compte exactement, dans sa bulle d'aide.
    'help' => [
        'registrations' => 'Toutes les inscriptions reçues pour cet événement, quel que soit leur état : confirmées, en attente, expirées ou annulées. Une inscription peut compter plusieurs personnes, l’invité et ses accompagnateurs.',
        'validated' => 'Les inscriptions dont la preuve de paiement a été validée : leurs places sont acquises. Le montant encaissé additionne ce que ces inscriptions ont versé, y compris celles annulées après validation.',
        'to_check' => 'Les inscriptions dont la preuve a été déposée et attend d’être validée ou rejetée dans la file des preuves.',
        'without_proof' => 'Les inscriptions sans preuve valable : formulaire non terminé, réservation en cours ou expirée, preuve rejetée. À la purge, celles qui n’ont toujours rien déposé sont supprimées.',
        'seats_left' => 'Ce qu’un nouvel invité peut encore réserver : la capacité du plan de salle, moins les places des inscriptions confirmées et des réservations en cours.',
        'hold_expiry' => 'Une réservation bloque des places pendant son décompte. Sans preuve déposée avant la fin, elle expire et ses places sont rendues. Une inscription relancée après expiration compte pour une réservation de plus.',
        'per_day' => 'Le nombre d’inscriptions créées chaque jour sur la période affichée, tous états confondus. Utile pour voir l’effet d’une annonce ou d’une relance.',
        'by_channel' => 'Toutes les preuves déposées pour cet événement, validées ou non, réparties selon le moyen de paiement utilisé.',
        'tables' => 'Pour chaque table du plan de salle, les places déjà attribuées sur sa capacité. Une inscription confirmée mais pas encore placée n’y figure pas.',
        'activity' => 'Les derniers faits de cet événement : preuves déposées, validées ou rejetées, inscriptions annulées, réservations expirées et billets refusés à l’entrée.',
    ],

    'hold_expiry' => [
        'title' => 'Réservations expirées',
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
                'hint' => 'Raison sociale, forme juridique, signataire, ville, téléphone et sous-domaine : ils figurent sur les reçus et les billets.',
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
