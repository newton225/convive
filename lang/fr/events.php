<?php

return [
    'title' => 'Mes événements',
    'description' => 'Créez un événement, réglez ses places et ses échéances, puis distribuez son lien public.',
    'duplicate_name' => ':name (copie)',
    'empty' => "Aucun événement pour l'instant. Créez le premier.",

    'statuses' => [
        'draft' => 'Brouillon',
        'open' => 'Ouvert',
        'ongoing' => 'En cours',
        'closed' => 'Terminé',
    ],

    'steps' => [
        'identity' => 'Identité',
        'seating' => 'Places et tarif',
        'deadlines' => 'Échéances',
    ],

    'sections' => [
        'identity' => "Nom, date et lieu de l'événement.",
        'seating' => "La capacité vaut tables × places par table : c'est le plan de salle qui fait foi.",
        'deadlines' => 'Date limite, purge, durée de réservation et envoi des cartes.',
    ],

    'fields' => [
        'name' => "Nom de l'événement",
        'name_placeholder' => 'Par exemple : Dîner de gala 2026',
        'subtitle' => 'Sous-titre',
        'starts_at' => 'Date et heure',
        'venue' => 'Lieu',
        'venue_address' => 'Adresse',
        'primary_color' => 'Couleur principale',
        'secondary_color' => 'Couleur secondaire',
        'override_colors' => 'Personnaliser les couleurs de cet événement',
        'override_colors_hint' => "Sans personnalisation, les couleurs de l'organisation s'appliquent.",
        'visual' => "Visuel de l'événement",
        'table_count' => 'Nombre de tables',
        'seats_per_table' => 'Places par table',
        'price_per_person' => 'Tarif par personne (F CFA)',
        'companion_limit' => "Plafond d'accompagnateurs",
        'registration_deadline' => 'Date limite des inscriptions',
        'purge_at' => 'Purge des dossiers non finalisés',
        'invitations_send_at' => 'Envoi des cartes',
        'hold_duration_minutes' => 'Durée de réservation (minutes)',
        'payment_accounts' => 'Comptes de versement proposés',
    ],

    'visual' => [
        'hint' => "Affiche ou photo propre à cet événement. Sans visuel, la bannière de l'organisation s'applique.",
        'choose' => 'Choisir un visuel',
        'replace' => 'Remplacer',
        'remove' => 'Retirer',
        'empty' => 'Aucun visuel',
    ],

    'summary' => [
        'capacity' => '{0} Aucune place|{1} 1 place|[2,*] :count places',
        'no_date' => 'Date à définir',
        'public_link' => 'Lien public',
    ],

    'actions' => [
        'create' => 'Nouvel événement',
        'edit' => "Modifier l'événement",
        'publish' => 'Publier le lien public',
        'close' => "Clôturer l'événement",
        'duplicate' => 'Dupliquer',
        'delete' => "Supprimer l'événement",
        'copy_link' => 'Copier le lien',
        'proofs' => 'Preuves',
        'seating' => 'Plan de salle',
        'scan' => 'Scan',
        'registrations' => 'Base d\'inscrits',
        'report' => 'Rapport',
        'reconciliation' => 'Rapprochement',
    ],

    'badges' => [
        'published' => 'Lien distribué',
        'not_ready' => 'Pas encore publiable',
    ],

    'publishing' => [
        'ready' => 'Cet événement peut être publié.',
        'blocked' => "Complétez l'identité légale de l'organisation, la capacité, la date et au moins un compte de versement visible avant de publier.",
        'frozen_subdomain' => "Une fois le lien distribué, le sous-domaine de l'organisation ne peut plus changer.",
    ],

    'flash' => [
        'created' => 'Événement créé.',
        'updated' => 'Événement mis à jour.',
        'published' => 'Lien public distribué.',
        'closed' => 'Événement clôturé.',
        'duplicated' => 'Événement dupliqué.',
        'deleted' => 'Événement supprimé.',
        'visual_updated' => "Visuel de l'événement mis à jour.",
        'visual_deleted' => "Visuel de l'événement retiré.",
    ],

    'errors' => [
        'deadline_after_event' => "La date limite des inscriptions ne peut pas être postérieure à l'événement.",
        'unknown_payment_account' => "Un des comptes de versement retenus n'appartient pas à cette organisation.",
        'not_ready_to_publish' => 'Cet événement ne peut pas encore être publié : identité légale, capacité, date et compte de versement visible sont requis.',
    ],

    'confirm_delete' => [
        'title' => "Supprimer l'événement",
        'description' => "L'événement \":name\" sera supprimé. Cette action est définitive.",
    ],

    'confirm_close' => [
        'title' => "Clôturer l'événement",
        'description' => 'Les inscriptions seront fermées pour ":name". Cette action ne se défait pas.',
    ],

    'settings' => [
        'link' => 'Réglages',
    ],
];
