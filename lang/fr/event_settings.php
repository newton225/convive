<?php

return [
    'title' => 'Réglages de l\'événement',
    'edit_event' => 'Modifier l\'événement',

    'identity' => [
        'title' => 'Identité visuelle',
        'description' => 'Le visuel et les couleurs que verront les invités de cet événement.',
        'primary' => 'Couleur principale',
        'secondary' => 'Couleur secondaire',
        'edit' => "Modifier la marque de l'organisation",
        'edit_event_visual' => 'Modifier le visuel et les couleurs',
        'visual_alt' => 'Visuel de :name',
        'no_visual' => "Aucun visuel : le bandeau de l'organisation est utilisé",
        'own_colors' => 'Cet événement a ses propres couleurs.',
        'brand_colors' => "Cet événement reprend les couleurs de la marque de l'organisation.",
    ],

    'seating' => [
        'title' => 'Places',
        'description' => 'La capacité est déduite du plan de salle : tables multipliées par places par table.',
        'tables' => 'Tables',
        'per_table' => 'Places par table',
        'capacity' => 'Capacité totale',
        'open' => 'Ouvrir le plan de salle',
    ],

    'deadlines' => [
        'title' => 'Échéances',
        'description' => 'Modifiées depuis la fiche de l\'événement.',
        'registration_deadline' => 'Date limite d\'inscription',
        'purge_at' => 'Purge des non finalisées',
        'invitations_send_at' => 'Envoi des cartes d\'invitation',
        'hold_duration' => 'Durée de réservation',
        'minutes' => ':count minutes',
        'not_set' => 'Non définie',
    ],

    'reminders' => [
        'title' => 'Rappels automatiques',
        'description' => 'Envoyés par WhatsApp, et par email quand l\'invité en a donné un.',
        'd7' => 'J-7, aux inscrits sans preuve',
        'd2' => 'J-2, aux inscrits sans preuve',
        'd1' => 'J-1, aux inscrits sans preuve',
        'day_of' => 'Jour J moins 3 h, aux billets validés',
    ],

    'rules' => [
        'title' => 'Règles',
        'scheduled_send' => 'Envoyer les cartes à l\'échéance programmée',
        'auto_seating' => 'Attribuer les tables automatiquement à la validation',
        'allow_without_proof' => 'Accepter une inscription enregistrée sans preuve',
        'proof_legibility' => 'Exiger un reçu lisible avant l\'envoi',
        'purge_on_exhaustion' => 'Purger dès que les places sont épuisées',
        'temporary_hold' => 'Retenir la place pendant la réservation',
        'phone_verification' => "Vérifier le téléphone par un code avant de réserver (protège contre les réservations automatisées ; une étape de plus pour l'invité et un message WhatsApp par code)",
        'not_enforced' => 'Enregistrée, mais n\'agit pas encore sur le fonctionnement de l\'événement.',
    ],

    'flash' => [
        'updated' => 'Réglages mis à jour.',
    ],
];
