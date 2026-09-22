<?php

return [
    'title' => 'Preuves à vérifier',

    'empty' => [
        'title' => 'Aucune preuve en attente',
        'description' => 'Toutes les preuves déposées pour cet événement ont été traitées.',
    ],

    'columns' => [
        'name' => 'Inscrit',
        'unit' => 'Unité',
        'party_size' => 'Places',
        'amount_due' => 'Montant dû',
        'submitted_at' => 'Déposée le',
        'channel' => 'Canal',
        'reference' => 'Référence',
        'amount_declared' => 'Montant versé',
        'payment_account' => 'Compte visé',
        'signals' => 'Signaux',
        'actions' => 'Actions',
    ],

    'signals' => [
        'duplicate_reference' => 'Référence déjà utilisée',
        'duplicate_image' => 'Capture déjà vue',
        'reference_missing_from_statement' => 'Référence absente du relevé',
        'statement_amount_mismatch' => 'Montant du relevé différent',
    ],

    'actions' => [
        'approve' => 'Valider',
        'reject' => 'Rejeter',
        'open_receipt' => 'Ouvrir le reçu',
        'reject_confirm_title' => 'Rejeter cette preuve ?',
        'reject_confirm_description' => "L'inscrit devra déposer une nouvelle preuve ; les places qu'il occupe ne sont pas rendues immédiatement.",
    ],

    'flash' => [
        'validated' => 'Preuve validée, inscription confirmée.',
        'rejected' => 'Preuve rejetée.',
        'no_longer_pending' => "Cette preuve n'est plus en attente de vérification.",
    ],
];
