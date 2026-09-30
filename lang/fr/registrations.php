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
        'empty_description' => "Les annulations apparaîtront ici avec leur motif, l'auteur de l'action et le sort du paiement.",
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

    'confirm' => [
        'reference' => 'Dossier',
        'phone' => 'Téléphone',
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

    'card' => [
        'column' => 'Carte',
        'sent_on' => 'Envoyée le :date',
        'not_sent' => 'Pas encore envoyée',
        'open' => 'Carte',
        'title' => "Carte d'invitation",
        'description' => "Pour :name et son groupe. À utiliser si l'envoi automatique n'a pas fonctionné ou si la carte a été perdue.",
        'last_sent' => 'Dernier envoi',
        'holder' => 'Invité principal',
        'holder_hint' => 'Sa carte donne accès aux billets de tout le groupe, et le message comprend les billets des accompagnateurs.',
        'companion' => 'Accompagnateur',
        'companion_hint' => "Son seul billet. Aucun numéro n'est connu : choisissez le destinataire dans WhatsApp.",
        'send' => "Envoyer depuis l'application",
        'resend' => "Renvoyer depuis l'application",
        'send_hint' => 'Par WhatsApp, et par email si l\'invité en a donné un.',
        'copy' => 'Copier le lien',
        'copied' => 'Lien copié',
        'whatsapp' => 'Ouvrir WhatsApp',
        'no_link' => "Le lien ne peut pas être construit : l'organisation n'a pas de sous-domaine ou l'événement n'est pas publié.",
        'traced' => 'Chaque envoi, copie ou ouverture de WhatsApp est journalisé : le lien permet d\'entrer.',
        'flash' => [
            'sent' => 'Carte envoyée à :name.',
        ],
        'errors' => [
            'not_confirmed' => "Seule une inscription validée a une carte d'invitation.",
            'not_sent' => "La carte n'est pas partie : le quota d'envois du plan est atteint, ou le lien ne peut pas être construit. Copiez le lien pour l'envoyer vous-même.",
            'copy_failed' => 'Le lien n\'a pas pu être copié. Sélectionnez-le et copiez-le à la main.',
        ],
    ],

    'refund' => [
        'title' => 'Paiement',
        'question' => 'Que devient le paiement de :amount ?',
        'no_permission' => 'Le paiement de :amount sera noté « à rembourser ». Un membre autorisé décidera ensuite de son sort.',
        'statuses' => [
            'due' => 'À rembourser',
            'refunded' => 'Remboursé',
            'kept' => 'Conservé',
        ],
        'hints' => [
            'due' => "L'organisation doit cette somme. Elle reste signalée jusqu'au remboursement.",
            'refunded' => "L'argent est déjà reparti vers l'invité.",
            'kept' => "L'organisation garde cette somme, pour un motif à préciser.",
        ],
        'fields' => [
            'channel' => 'Moyen du remboursement',
            'channel_placeholder' => 'Choisir le moyen',
            'refunded_on' => 'Date du remboursement',
            'fee' => 'Frais de transaction prélevés (F CFA)',
            'fee_help' => "Le montant exact prélevé par l'opérateur. Ils sont à la charge de l'invité et déduits de ce qu'il reçoit.",
            'reference' => 'Référence de la transaction (facultatif)',
            'kept_reason' => 'Motif',
            'kept_reason_placeholder' => 'Par exemple : annulation hors délai, don à l\'association',
        ],
        'net' => "L'invité reçoit :net (:amount moins :fee de frais).",
        'amount_paid' => 'Montant payé',
        'mark_refunded' => 'Marquer comme remboursé',
        'mark_title' => 'Marquer le remboursement',
        'mark_description' => "À faire une fois l'argent parti vers :name. L'inscription reste annulée ; l'invité est prévenu.",
        'submit' => 'Enregistrer le remboursement',
        'summary' => [
            'due' => ':amount à rembourser',
            'refunded' => ':net remboursés le :date par :channel (:fee de frais)',
            'kept' => ':amount conservés : :reason',
        ],
        'errors' => [
            'fee_too_high' => 'Les frais doivent rester inférieurs au montant payé : sinon, rien ne serait remboursé.',
            'future_date' => "La date du remboursement ne peut pas être dans le futur : il se marque une fois l'argent parti.",
            'kept_reason_required' => 'Indiquez pourquoi l\'organisation garde ce paiement.',
            'not_due' => "Ce paiement n'est plus à rembourser : il a déjà été traité.",
        ],
    ],

    'flash' => [
        'refunded' => 'Remboursement enregistré. L\'invité est prévenu.',
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
