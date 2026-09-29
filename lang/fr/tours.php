<?php

return [
    'replay' => 'Revoir la visite',
    'next' => 'Suivant',
    'previous' => 'Précédent',
    'done' => 'Terminer',
    // Les accolades sont remplacées par driver.js, pas par la traduction.
    'progress' => 'Étape {{current}} sur {{total}}',

    'welcome' => [
        'intro' => [
            'title' => 'Bienvenue dans Convive',
            'body' => 'Deux minutes pour repérer où tout se trouve. Vous pourrez revoir cette visite à tout moment depuis le tableau de bord.',
        ],
        'tenant' => [
            'title' => 'Votre organisation',
            'body' => "Si vous travaillez pour plusieurs organisations, changez d'espace ici. Les données de l'une ne sont jamais visibles depuis l'autre.",
        ],
        'dashboard' => [
            'title' => 'Tableau de bord',
            'body' => "L'essentiel de l'événement en cours : inscrits, preuves à vérifier, places restantes et alertes du jour.",
        ],
        'events' => [
            'title' => 'Événements',
            'body' => "Créez un événement, publiez son lien, puis retrouvez depuis sa ligne les preuves, le plan de salle, la base d'inscrits et le rapport.",
        ],
        'entry_control' => [
            'title' => "Contrôle à l'entrée",
            'body' => "Le soir venu, l'hôtesse ouvre ce raccourci sur son téléphone et scanne les billets.",
        ],
        'organisation' => [
            'title' => 'Organisation',
            'body' => 'Identité légale et marque, comptes de versement, unités, équipe, abonnement : tout ce qui concerne votre organisation est rangé ici. Complétez-les avant de publier votre premier événement.',
        ],
        'notifications' => [
            'title' => 'Alertes',
            'body' => 'Nouvelle preuve déposée, compte de versement modifié, billet refusé à la porte : tout arrive ici.',
        ],
        'account' => [
            'title' => 'Votre compte',
            'body' => "Profil, double authentification, code de scan et préférences d'alertes.",
        ],
    ],

    'first_event' => [
        'intro' => [
            'title' => 'Créer un événement',
            'body' => "Trois étapes, puis la publication. Rien n'est visible des invités tant que vous n'avez pas publié.",
        ],
        'identity' => [
            'title' => '1. Identité',
            'body' => 'Nom, date, heure et lieu, tels que les invités les liront sur le lien et le billet.',
        ],
        'seating' => [
            'title' => '2. Places et tarif',
            'body' => 'Décrivez la salle par groupes de tables de même taille : la capacité est la somme de leurs places, et le plan de salle fait foi le jour J.',
        ],
        'payment_accounts' => [
            'title' => 'Comptes de versement',
            'body' => "Cochez au moins un compte : c'est là que les invités verseront leur participation.",
        ],
        'deadlines' => [
            'title' => '3. Échéances',
            'body' => "Date limite d'inscription, purge des dossiers sans preuve, durée de réservation d'une place.",
        ],
        'save' => [
            'title' => 'Enregistrer',
            'body' => "L'événement reste un brouillon : vous pouvez y revenir autant que nécessaire.",
        ],
        'publish' => [
            'title' => 'Publier',
            'body' => "Une fois l'identité de l'organisation et un compte de versement prêts, ce bouton ouvre les inscriptions.",
        ],
        'share' => [
            'title' => 'Partager le lien',
            'body' => 'Dans la liste des événements, « Copier le lien » donne l\'adresse à envoyer aux invités.',
        ],
    ],

    'proofs' => [
        'intro' => [
            'title' => 'Vérifier les preuves',
            'body' => 'Chaque invité dépose la capture de son versement. Rien ne se confirme sans votre validation.',
        ],
        'queue' => [
            'title' => 'La file',
            'body' => 'Les preuves en attente, avec le montant attendu, le canal et la référence déclarée.',
        ],
        'receipt' => [
            'title' => 'Le reçu',
            'body' => 'Ouvrez la capture et comparez-la au montant et à la référence de la ligne.',
        ],
        'signals' => [
            'title' => 'Signaux de doute',
            'body' => 'Référence déjà utilisée, capture déjà vue, montant différent du relevé : un signal invite à regarder de plus près.',
        ],
        'approve' => [
            'title' => 'Valider',
            'body' => "La validation confirme l'inscription, attribue une table et émet un billet par personne.",
        ],
        'reject' => [
            'title' => 'Rejeter',
            'body' => "Indiquez le motif : l'invité le lit et peut déposer une nouvelle preuve.",
        ],
        'after' => [
            'title' => 'Et ensuite',
            'body' => 'Le rapprochement avec votre relevé bancaire se fait depuis la ligne de l\'événement, « Rapprochement ».',
        ],
    ],

    'entry_control' => [
        'intro' => [
            'title' => "Contrôle à l'entrée",
            'body' => 'Scannez le QR de chaque billet. Chaque personne a le sien, accompagnateurs compris, et chaque billet ne sert qu\'une fois.',
        ],
        'pin' => [
            'title' => 'Votre code à 4 chiffres',
            'body' => "Il verrouille l'écran si vous posez le téléphone. Choisissez-le une fois, il sert ensuite sur tous les événements.",
        ],
        'viewfinder' => [
            'title' => 'Le viseur',
            'body' => 'Présentez le QR devant la caméra : la lecture est automatique, aucun bouton à toucher.',
        ],
        'results' => [
            'title' => 'Trois résultats',
            'body' => "Valide : la personne entre. Déjà scanné : l'heure du premier passage s'affiche. Refusé : orientez l'invité vers l'accueil.",
        ],
        'station' => [
            'title' => 'Votre poste',
            'body' => 'Nommez votre entrée (porte A, parking) : le journal dira qui a scanné où.',
        ],
        'counter' => [
            'title' => 'Le compteur',
            'body' => 'Les personnes entrées sur les personnes attendues.',
        ],
        'recent' => [
            'title' => 'Derniers passages',
            'body' => 'Les scans récents, pour lever un doute sans rescanner.',
        ],
        'offline' => [
            'title' => 'Sans réseau',
            'body' => 'Le scan continue hors ligne : les billets sont vérifiés sur le téléphone et envoyés dès que le réseau revient.',
        ],
    ],
];
