<?php

return [
    'title' => 'Preuves à vérifier',

    'empty' => [
        'title' => 'Aucune preuve en attente',
        'description' => 'Toutes les preuves déposées pour cet événement ont été traitées.',
    ],

    'toolbar' => [
        'search' => 'Rechercher',
        'search_placeholder' => 'Nom, référence, téléphone, unité, accompagnateur',
        'filter_label' => 'Filtrer par signal',
        'count' => '{0} Aucune preuve|{1} 1 preuve|[2,*] :count preuves',
    ],

    'filters' => [
        'all' => 'Toutes les preuves',
        'anomaly' => 'Avec une anomalie',
        'clean' => 'Sans anomalie',
        'note' => 'Avec une précision de l’invité',
    ],

    'no_match' => [
        'title' => 'Aucune preuve ne correspond',
        'description' => 'Modifiez la recherche ou le filtre pour voir d’autres preuves.',
        'reset' => 'Effacer la recherche et le filtre',
    ],

    'columns' => [
        'name' => 'Inscrit',
        'unit' => 'Unité',
        'party_size' => 'Places',
        'amount_due' => 'Montant dû',
        'submitted_at' => 'Déposée le',
        'channel' => 'Canal',
        'reference' => 'Référence',
        'guest_note' => 'Précision de l’invité',
        'payment_account' => 'Compte visé',
        'payment' => 'Paiement',
        'signals' => 'Signaux',
        'actions' => 'Actions',
    ],

    'signals' => [
        'none' => 'Aucune anomalie détectée',
        'duplicate_reference' => 'Référence déjà utilisée',
        'duplicate_image' => 'Capture déjà vue',
        'reference_missing_from_statement' => 'Référence absente du relevé',
        'statement_amount_mismatch' => 'Montant du relevé différent',
        'guest_note' => 'Précision de l’invité',
    ],

    'details' => [
        'column' => 'Détail',
        'show' => 'Afficher le détail de :name',
        'hide' => 'Masquer le détail de :name',
        'companions' => 'Accompagnateurs',
        'no_note' => 'Aucune précision.',
        'single_expand' => 'Une seule ligne ouverte à la fois',
    ],

    'companions' => [
        'none' => 'Aucun accompagnateur',
        'count' => '{0} Aucun accompagnateur|{1} 1 accompagnateur|[2,*] :count accompagnateurs',
    ],

    'actions' => [
        'approve' => 'Valider',
        'reject' => 'Rejeter',
        'open_receipt' => 'Ouvrir le reçu',
        'approve_confirm_title' => 'Valider cette preuve ?',
        'approve_confirm_description' => "Vérifiez le montant et la référence sur le relevé. L'inscription sera confirmée, un billet émis et une table attribuée : cette validation ne s'annule pas.",
        'reject_confirm_title' => 'Rejeter cette preuve ?',
        'reject_confirm_description' => "L'inscrit devra déposer une nouvelle preuve ; les places qu'il occupe ne sont pas rendues immédiatement.",
    ],

    'flash' => [
        'validated' => 'Preuve validée, inscription confirmée.',
        'rejected' => 'Preuve rejetée.',
        'no_longer_pending' => "Cette preuve n'est plus en attente de vérification.",
    ],
];
