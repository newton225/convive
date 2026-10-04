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
        'description' => 'La capacité est déduite du plan de salle : la somme des places de toutes les tables.',
        'tables' => 'Tables',
        'layout' => 'Composition (tables × places)',
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
        'phone_verification' => 'Vérifier le téléphone avant de réserver',
        'show_remaining_seats' => 'Afficher le nombre de places restantes aux invités',
        'bot_protection' => "Protéger le formulaire d'inscription contre les robots",
        'not_enforced' => 'Enregistrée, mais n\'agit pas encore sur le fonctionnement de l\'événement.',
    ],

    'rules_help' => [
        'scheduled_send' => "Les cartes d'invitation partent d'elles-mêmes à la date d'envoi de l'événement, puis à chaque validation faite après cette date. Décochée, aucune carte ne part automatiquement.",
        'auto_seating' => "À chaque preuve validée, l'invité et ses accompagnateurs reçoivent une table, dans l'ordre des validations et en regroupant les unités. Vous pouvez toujours déplacer quelqu'un à la main.",
        'purge_on_exhaustion' => "Dès que les inscriptions validées remplissent toutes les places, les dossiers non finalisés sont supprimés sans attendre la date de purge : ils n'avaient plus aucune chance d'obtenir une place.",
        'phone_verification' => "Avant de réserver, l'invité prouve que le numéro saisi est bien le sien : il envoie un message WhatsApp déjà écrit au numéro de Convive, depuis ce numéro (gratuit). Cela empêche de bloquer des places avec des numéros inventés, au prix d'une étape de plus. Tant que le numéro WhatsApp de Convive n'est pas en service, la vérification passe par SMS (numéros ivoiriens seulement).",
        'show_remaining_seats' => "Cochée, le lien public montre combien de places restent et la jauge des places prises. Décochée, les invités ne voient aucun chiffre : seulement « Complet » quand il n'y a plus de place.",
        'bot_protection' => "Une vérification invisible de Cloudflare s'assure qu'une personne remplit le formulaire, et non un robot qui bloquerait toutes les places. La plupart des invités ne voient rien ; en cas de doute, une case à cocher s'affiche. Gratuit.",
    ],

    'flash' => [
        'updated' => 'Réglages mis à jour.',
    ],
];
