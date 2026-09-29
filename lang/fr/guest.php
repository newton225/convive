<?php

return [
    'event' => [
        'no_date' => 'Date à venir',
        'venue' => 'Lieu',
        'price_per_person' => 'Tarif par personne',
        'capacity' => 'Capacité',
        'seats' => [
            'remaining' => '{0} Complet|{1} 1 place restante|[2,*] :count places restantes',
            'full' => 'Cet événement est complet',
        ],
        'companion_limit' => '{0} Sans accompagnateur|{1} 1 accompagnateur autorisé|[2,*] :count accompagnateurs autorisés',
        'deadline' => [
            'label' => "Date limite d'inscription",
            'passed' => "La date limite d'inscription est dépassée.",
        ],
        'register' => "S'inscrire",
        'registration_closed' => 'Les inscriptions sont closes pour cet événement.',
        'hosted_by' => 'Organisé par :name',
        'when' => 'Date',
        'per_person' => 'par personne',
        'seats_taken' => ':taken places prises sur :capacity',
        'how' => [
            'title' => 'Comment ça se passe',
            'form' => ['title' => 'Votre fiche', 'body' => 'Nom, téléphone, unité et accompagnateurs. Le montant se calcule seul.'],
            'pay' => ['title' => 'Votre paiement', 'body' => "Vous versez sur le compte de l'organisateur, puis déposez la preuve."],
            'ticket' => ['title' => 'Votre billet', 'body' => 'Il vous parvient dès que votre preuve est validée.'],
        ],
        'payment_accounts' => [
            'title' => 'Comptes de versement',
            'reference_hint' => 'Indiquez « :name » en motif du transfert.',
        ],
    ],

    'not_found' => [
        'title' => "Cet événement n'existe pas",
        'description' => "Le lien que vous avez suivi n'est plus valide, ou n'a jamais existé.",
    ],

    'registration' => [
        'errors' => [
            'phone_already_active' => 'Une réservation est déjà en cours pour ce numéro. Terminez-la, ou attendez la fin de son délai avant d\'en créer une autre.',
            'registrations_paused' => "Les inscriptions en ligne sont momentanément suspendues pour cet événement. Contactez l'organisateur pour réserver votre place.",
            'phone_backoff' => '{1} Plusieurs réservations ont expiré pour ce numéro sans preuve de paiement. Réessayez dans 1 minute.|[2,*] Plusieurs réservations ont expiré pour ce numéro sans preuve de paiement. Réessayez dans :minutes minutes.',
        ],
        'title' => 'Votre inscription',
        'reference' => 'Réf. :reference',
        'fields' => [
            'name' => 'Nom complet',
            'phone' => 'Téléphone',
            'email' => 'Email (facultatif)',
            'unit' => 'Unité',
            'unit_placeholder' => 'Choisir une unité',
            'companion_name' => 'Nom de l\'accompagnateur',
        ],
        'help' => [
            'phone' => 'Il sert à vous envoyer votre carte d’invitation et vos rappels par WhatsApp. Une seule réservation à la fois par numéro.',
            'unit' => 'Elle sert à placer les membres d’une même unité aux mêmes tables. Si aucune ne vous correspond, choisissez « Aucune ».',
            'companions' => 'Les personnes qui viennent avec vous. Chacune occupe une place, paie le tarif et reçoit son propre billet, que vous pourrez lui transmettre.',
        ],
        'companions' => [
            'title' => 'Accompagnateurs',
            'add' => 'Ajouter un accompagnateur',
            'remove' => 'Retirer',
            'limit_reached' => '{1} 1 accompagnateur au maximum pour cet événement.|[2,*] :count accompagnateurs au maximum pour cet événement.',
        ],
        'total' => [
            'label' => 'Montant total dû',
        ],
        'submit' => "Continuer l'inscription",
        'show' => [
            'countdown_label' => 'Temps restant pour envoyer votre preuve',
            'expired' => 'Le délai de réservation est écoulé.',
            'retry' => 'Vérifier les places et relancer',
            'proof_submitted' => 'Votre preuve a été reçue. Elle est en cours de vérification.',
            'proof_rejected' => "Votre preuve n'a pas pu être validée. Merci d'en envoyer une nouvelle.",
            'recap_title' => 'Récapitulatif',
            'seats_available' => '{0} Aucune place restante pour le moment|{1} 1 place restante|[2,*] :count places restantes',
            'cancelled_title' => 'Cette inscription a été annulée.',
            'cancelled_description' => "L'organisation a annulé cette inscription. Contactez-la si vous pensez qu'il s'agit d'une erreur.",
        ],
    ],

    'flash' => [
        'phone_verified' => 'Numéro vérifié : votre place est réservée.',
        'code_resent' => 'Un nouveau code vous a été envoyé sur WhatsApp.',
        'proof_sent' => 'Preuve envoyée. Elle va être vérifiée par l\'organisation.',
        'proof_too_late' => "Le délai de réservation était écoulé : la preuve n'a pas été enregistrée. Vérifiez les places et relancez votre réservation.",
        'no_seats_left' => "Il ne reste plus assez de places pour votre inscription. Vous pouvez rejoindre la liste d'attente si elle est ouverte.",
        'hold_restarted' => 'Votre réservation est relancée : le décompte repart.',
        'seats_available' => "Des places sont disponibles : inscrivez-vous directement, sans passer par la liste d'attente.",
        'waitlist_joined' => "Vous êtes inscrit sur la liste d'attente. Vous serez prévenu dès qu'une place se libère.",
        'waitlist_seat_taken' => "La place qui vous était proposée n'est plus disponible. Vous restez prévenu si une autre se libère.",
    ],

    'phone_verification' => [
        'title' => 'Vérifiez votre numéro',
        'description' => 'Nous avons envoyé un code à 6 chiffres sur WhatsApp au :phone. Saisissez-le pour réserver votre place.',
        'code_label' => 'Code de vérification',
        'expires' => 'Le code expire dans :minutes minutes.',
        'submit' => 'Vérifier et réserver',
        'resend' => 'Renvoyer le code',
        'errors' => [
            'invalid' => 'Ce code ne correspond pas. Vérifiez le message WhatsApp et réessayez.',
            'expired' => 'Ce code a expiré. Demandez-en un nouveau.',
            'too_many_attempts' => 'Trop d\'essais. Demandez un nouveau code.',
        ],
    ],

    'ticket' => [
        'title' => 'Votre billet',
        'guests_title' => 'Invités',
        'table' => 'Table :number',
        'no_table' => 'Table à venir',
        'scheduled_send' => 'Votre carte vous sera envoyée par WhatsApp (et par email si vous en avez renseigné un) le :date.',
        'passes_title' => 'Billets des accompagnateurs',
        'passes_description' => 'Chaque personne entre avec son propre billet. Transmettez le sien à un accompagnateur qui arrivera sans vous.',
        'pass_alt' => 'Billet de :name',
        'share_whatsapp' => 'Envoyer par WhatsApp',
        'copy_link' => 'Copier le lien',
        'share_message' => 'Votre billet pour :event : :url',
        'share_unavailable' => 'Présentez ce code depuis votre téléphone : le lien individuel sera disponible quand l\'organisation aura choisi son adresse.',
        'guest_of' => 'Accompagnateur de :name',
        'single_title' => 'Billet de :name',
        'single_notice' => 'Ce billet fait entrer une seule personne et ne sert qu\'une fois. Présentez-le à l\'entrée.',
    ],

    'mail' => [
        'invitation_card' => [
            'subject' => 'Votre billet pour :event',
            'intro' => 'Bonjour :name, votre inscription à :event est confirmée.',
            'table' => 'Vous êtes placé à la table :number.',
            'action' => 'Voir mon billet',
        ],
        'proof_reminder' => [
            'subject' => 'Il manque votre preuve de paiement pour :event',
            'intro' => "Bonjour :name, votre inscription à :event n'a pas encore de preuve de paiement validée.",
            'action' => 'Envoyer ma preuve',
        ],
        'ticket_reminder' => [
            'subject' => ':event, c\'est bientôt !',
            'intro' => 'Bonjour :name, :event a lieu dans trois heures. Gardez votre billet à portée de main.',
            'action' => 'Voir mon billet',
        ],
    ],

    'whatsapp' => [
        'phone_code' => 'Votre code Convive : :code. Il expire dans :minutes minutes. Ne le communiquez à personne.',
        'invitation_card' => 'Bonjour :name, votre inscription à :event est confirmée. Votre billet : :link',
        'proof_reminder' => 'Bonjour :name, il manque votre preuve de paiement pour :event. Envoyez-la ici : :link',
        'ticket_reminder' => 'Bonjour :name, :event a lieu dans trois heures. Votre billet : :link',
    ],

    'proof' => [
        'title' => 'Preuve de paiement',
        'fields' => [
            'payment_account' => 'Compte de versement utilisé',
            'payment_account_placeholder' => 'Choisir le compte utilisé',
            'channel' => 'Canal utilisé',
            'channel_placeholder' => 'Choisir le canal',
            'reference' => 'Référence de la transaction',
            'amount_declared' => 'Montant versé',
            'receipt' => 'Capture du reçu',
        ],
        'help' => [
            'reference' => 'Le code de la transaction, indiqué dans le message de confirmation de Wave, Orange Money, MTN ou Moov, ou sur votre reçu bancaire. Il permet à l’organisateur de retrouver votre paiement.',
        ],
        'submit' => 'Envoyer ma preuve',
    ],

    'waitlist' => [
        'join' => "Rejoindre la liste d'attente",
        'title' => "Liste d'attente",
        'description' => "Cet événement est complet. Dès qu'une place se libère, la première personne de la liste reçoit un lien valable six heures pour finaliser son inscription.",
        'submit' => "Rejoindre la liste d'attente",
        'show' => [
            'position_label' => 'Votre position dans la liste',
            'invited' => 'Une place est disponible pour vous !',
            'finalize' => 'Finaliser mon inscription',
            'expired' => 'Le délai pour finaliser est écoulé.',
            'converted' => 'Votre inscription a été finalisée.',
        ],
    ],

    'deleted' => [
        'title' => "Cette inscription n'existe plus",
        'description' => 'Elle a pu être supprimée après la date limite, ou une fois toutes les places prises. Vos données ont été effacées.',
        'register' => "S'inscrire de nouveau",
        'waitlist' => "Rejoindre la liste d'attente",
        'back' => "Voir l'événement",
    ],

    'resume_link' => [
        'title' => 'Gardez ce lien',
        'description' => "Il vous permet de reprendre votre inscription à tout moment avant l'échéance, depuis n'importe quel appareil.",
        'copy' => 'Copier le lien',
        'share' => "Me l'envoyer sur WhatsApp",
        'deadline' => 'À reprendre avant le :date.',
    ],
];
