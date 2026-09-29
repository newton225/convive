<?php

return [
    'title' => 'Rapprochement du relevé',
    'description' => 'Comparez un relevé Mobile Money ou bancaire aux preuves déposées.',

    'import' => [
        'title' => 'Importer un relevé',
        'hint' => 'Fichier CSV avec les colonnes date, référence, émetteur et montant.',
        'file_label' => 'Fichier du relevé',
        'submit' => 'Importer et rapprocher',
        'current' => 'Relevé affiché',
        'rows' => 'Lignes : :count',
    ],

    'toolbar' => [
        'search' => 'Rechercher une ligne',
        'search_placeholder' => 'Référence ou émetteur',
        'filter_label' => 'Filtrer par résultat du rapprochement',
        'all' => 'Toutes les lignes',
        'count' => '{0} Aucune ligne|{1} 1 ligne|[2,*] :count lignes',
    ],

    'stats' => [
        'matched' => 'Rapprochées',
        'amount_mismatch' => 'Montant divergent',
        'approximate_name' => 'Nom approchant',
        'no_registration' => 'Sans inscription',
    ],

    'outcomes' => [
        'matched' => 'Rapprochée',
        'amount_mismatch' => 'Montant divergent',
        'approximate_name' => 'Nom approchant',
        'no_registration' => 'Sans inscription',
    ],

    'columns' => [
        'line' => 'Ligne',
        'date' => 'Date',
        'reference' => 'Référence',
        'issuer' => 'Émetteur',
        'amount' => 'Montant',
        'outcome' => 'Issue',
        'registration' => 'Inscription',
        'actions' => 'Actions',
    ],

    'actions' => [
        'resolve' => 'Résoudre',
        'seen' => 'Vue',
    ],

    'modals' => [
        'resolve' => [
            'title' => 'Résoudre la ligne :line',
            'description' => 'Choisissez l\'inscription qui correspond à ce versement de :issuer, ou indiquez qu\'aucune ne correspond.',
            'registration_label' => 'Inscription',
            'none' => 'Aucune inscription ne correspond',
            'submit' => 'Enregistrer',
            'amount_matches' => 'Montant dû par cette inscription : :amount, identique au relevé.',
            'amount_differs' => 'Montant dû par cette inscription : :amount, différent du montant du relevé.',
        ],
    ],

    'flash' => [
        'imported' => 'Relevé importé et rapproché.',
        'resolved' => 'Ligne résolue.',
    ],

    'errors' => [
        'already_imported' => 'Ce relevé a déjà été importé pour cet événement.',
        'empty' => 'Le fichier ne contient aucune ligne de données.',
        'too_many_rows' => 'Le relevé dépasse la limite de :max lignes. Scindez-le en plusieurs fichiers.',
        'missing_columns' => 'Colonnes manquantes dans l\'en-tête : :columns.',
        'invalid_date' => 'Ligne :line : la date est illisible (formats acceptés : 2026-09-20 ou 20/09/2026). L\'en-tête n\'est pas compté. Aucune ligne n\'a été importée.',
        'invalid_amount' => 'Ligne :line : le montant est illisible ou nul. L\'en-tête n\'est pas compté. Aucune ligne n\'a été importée.',
        'registration_other_event' => 'Cette inscription n\'appartient pas à cet événement.',
    ],

    'empty' => [
        'title' => 'Aucun relevé importé',
        'description' => 'Importez un relevé CSV pour rapprocher les versements des preuves déposées.',
    ],
];
