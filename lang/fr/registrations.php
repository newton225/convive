<?php

return [
    'title' => 'Base d\'inscrits',
    'description' => 'Recherche, filtres et export des inscriptions de cet événement.',

    'entry' => [
        'entered' => 'Entré à :time',
        'group' => ':count sur :total entrés, dès :time',
        'not_yet' => 'Pas encore entré',
    ],

    'cancellations' => [
        'title' => 'Annulations',
        'empty_title' => 'Aucune inscription annulée',
        'empty_description' => "Les annulations et remboursements apparaîtront ici avec leur motif et l'auteur de l'action.",
        'no_reason' => 'Motif non renseigné',
        'by' => 'Par :name, le :date',
        'unknown_author' => 'un membre retiré',
    ],

    'columns' => [
        'name' => 'Nom',
        'unit' => 'Unité',
        'party_size' => 'Groupe',
        'amount_due' => 'Montant dû',
        'status' => 'Statut',
        'table' => 'Table',
        'channel' => 'Canal',
        'entry' => 'Entrée',
        'actions' => 'Actions',
        'export' => [
            'name' => 'Nom',
            'phone' => 'Téléphone',
            'email' => 'Email',
            'unit' => 'Unité',
            'party_size' => 'Taille du groupe',
            'amount_due' => 'Montant dû',
            'status' => 'Statut',
            'table' => 'Table',
            'cancellation_reason' => "Motif d'annulation",
        ],
    ],

    'filters' => [
        'all' => 'Toutes',
        'confirmed' => 'Validées',
        'proof_submitted' => 'À vérifier',
        'without_proof' => 'Sans preuve',
        'cancelled' => 'Annulées',
        'search_placeholder' => 'Nom, référence, téléphone ou email',
    ],

    'statuses' => [
        'draft' => 'Brouillon',
        'held' => 'Réservation en cours',
        'proof_submitted' => 'Preuve à vérifier',
        'confirmed' => 'Validée',
        'expired' => 'Expirée',
        'proof_rejected' => 'Preuve rejetée',
        'cancelled' => 'Annulée',
    ],

    'actions' => [
        'export' => 'Exporter',
        'export_excel' => 'Excel',
        'export_csv' => 'CSV',
        'export_pdf' => 'PDF',
        'export_checklists' => 'Listes de contrôle',
        'purge' => 'Purger',
        'cancel' => 'Annuler',
        'no_table' => 'Aucune',
    ],

    'modals' => [
        'cancel' => [
            'title' => 'Annuler cette inscription',
            'description' => 'Cette action libère immédiatement la place et la table de :name. Le motif est conservé dans le journal.',
            'reason_label' => 'Motif',
            'submit' => 'Confirmer l\'annulation',
        ],
        'purge' => [
            'title' => 'Purger les inscriptions non finalisées',
            'description' => 'Les dossiers brouillon, en réservation expirée ou à preuve rejetée sont supprimés et leurs places rendues.',
            'submit' => 'Confirmer la purge',
        ],
    ],

    'flash' => [
        'cancelled' => 'Inscription annulée.',
        'purged' => '{0} Aucun dossier à purger.|{1} 1 dossier purgé.|[2,*] :count dossiers purgés.',
    ],

    'pdf' => [
        'title' => 'Base d\'inscrits',
        'empty' => 'Aucune inscription ne correspond à ce filtre.',
        'total' => '{1} 1 inscription|[0,*] :count inscriptions',
        'checklists_title' => 'Listes de contrôle par table',
        'checklists_empty' => 'Aucune inscription confirmée n\'est encore placée à une table.',
        'table_heading' => 'Table :number (:seats / :capacity places)',
        'present' => 'Présent',
    ],

    'empty' => [
        'all' => [
            'title' => 'Aucune inscription pour le moment',
            'description' => 'Les inscriptions apparaîtront ici dès que des invités s\'inscriront.',
        ],
        'confirmed' => [
            'title' => 'Aucune inscription validée',
            'description' => 'Les inscriptions confirmées apparaîtront ici après validation d\'une preuve.',
        ],
        'proof_submitted' => [
            'title' => 'Aucune preuve à vérifier',
            'description' => 'Les preuves en attente de vérification apparaîtront ici.',
        ],
        'without_proof' => [
            'title' => 'Aucune inscription sans preuve',
            'description' => 'Les brouillons, réservations expirées et preuves rejetées apparaîtront ici.',
        ],
        'cancelled' => [
            'title' => 'Aucune inscription annulée',
            'description' => 'Les inscriptions annulées par l\'organisation apparaîtront ici.',
        ],
    ],
];
