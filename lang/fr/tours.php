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
            'body' => "Créez un événement, publiez son lien, puis retrouvez depuis sa ligne les preuves, le plan de salle, la base d'inscrits, les réclamations et le rapport.",
        ],
        'treasury' => [
            'title' => 'Trésorerie',
            'body' => "L'argent de toute l'organisation : ce qui a été encaissé, remboursé et ce qui reste à rendre, par événement et par compte de versement.",
        ],
        'entry_control' => [
            'title' => "Contrôle à l'entrée",
            'body' => "Le soir venu, l'hôtesse ouvre ce raccourci sur son téléphone et scanne les billets. Si le QR est illisible, elle retrouve l'invité par son nom, selon son profil.",
        ],
        'organisation' => [
            'title' => 'Organisation',
            'body' => "Identité légale et marque, comptes de versement, unités, équipe et profils, gabarit du billet, abonnement et historique des actions : tout ce qui concerne votre organisation est rangé ici. Complétez l'identité légale et un compte de versement avant de publier votre premier événement.",
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
            'body' => "Nom, date, heure de début, fin facultative et lieu (avec un lien de carte si vous le souhaitez), tels que les invités les liront sur le lien et le billet. La fenêtre d'entrée du billet se règle ici aussi.",
        ],
        'seating' => [
            'title' => '2. Places et tarifs',
            'body' => 'Décrivez la salle par groupes de tables de même taille, ou choisissez un événement sans tables et indiquez un nombre de places. Créez ensuite vos catégories de tarifs (nom, prix, quota) : chaque personne choisit la sienne, et un prix à 0 veut dire gratuit.',
        ],
        'payment_accounts' => [
            'title' => 'Comptes de versement',
            'body' => "Cochez au moins un compte : c'est là que les invités verseront leur participation. Un événement entièrement gratuit n'en demande aucun.",
        ],
        'deadlines' => [
            'title' => '3. Échéances',
            'body' => "Date limite d'inscription, purge des dossiers sans preuve, durée de réservation d'une place et envoi des cartes.",
        ],
        'save' => [
            'title' => 'Enregistrer',
            'body' => "L'événement reste un brouillon : vous pouvez y revenir autant que nécessaire.",
        ],
        'publish' => [
            'title' => 'Publier',
            'body' => "Quand l'identité légale de l'organisation, son sous-domaine, un lieu, une date à venir et, si l'événement est payant, un compte de versement sont prêts, ce bouton ouvre les inscriptions. La fiche nomme ce qui manque encore.",
        ],
        'share' => [
            'title' => 'Partager le lien',
            'body' => 'Dans la liste des événements, « Copier le lien » donne l\'adresse à envoyer aux invités. Vous pouvez aussi annoncer l\'événement sur la vitrine du site Convive.',
        ],
    ],

    'proofs' => [
        'intro' => [
            'title' => 'Vérifier les preuves',
            'body' => "Chaque invité dépose la capture de son versement. Rien ne se confirme sans votre validation (un événement gratuit n'en demande pas).",
        ],
        'queue' => [
            'title' => 'La file',
            'body' => 'Les preuves en attente, avec le montant attendu, le canal et la référence déclarée.',
        ],
        'receipt' => [
            'title' => 'Le reçu',
            'body' => "Ouvrez la capture et comparez-la au montant et à la référence de la ligne. Elle doit être l'originale, et le montant complet, en un seul paiement : sinon, rejetez.",
        ],
        'signals' => [
            'title' => 'Signaux de doute',
            'body' => "Référence déjà utilisée, capture déjà vue, référence absente du relevé, montant différent, précision laissée par l'invité : un signal invite à regarder de plus près.",
        ],
        'approve' => [
            'title' => 'Valider',
            'body' => "La validation confirme l'inscription, attribue une table (sauf pour un événement sans tables) et émet un billet par personne.",
        ],
        'reject' => [
            'title' => 'Rejeter',
            'body' => "Indiquez le motif : l'invité le lit et peut déposer une nouvelle preuve.",
        ],
        'after' => [
            'title' => 'Et ensuite',
            'body' => 'Le rapprochement avec votre relevé bancaire se fait depuis la ligne de l\'événement, « Rapprochement ». Les remboursements dus après une annulation se suivent dans la Trésorerie.',
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
        'lookup' => [
            'title' => 'Sans scan',
            'body' => "QR illisible ou billet oublié : cherchez l'invité par son nom ou la référence de son dossier, puis validez son entrée. Ce geste demande une permission dédiée et prévient les responsables.",
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
            'body' => 'Le scan continue hors ligne : les billets sont vérifiés sur le téléphone et envoyés dès que le réseau revient. La recherche sans scan, elle, demande le réseau.',
        ],
    ],

    'seating' => [
        'intro' => [
            'title' => 'Le plan de salle',
            'body' => 'Chaque inscription confirmée est placée à une table automatiquement. Ici, vous voyez la salle le jour J et vous ajustez à la main.',
        ],
        'tables' => [
            'title' => 'Les tables',
            'body' => "Chaque table avec ses occupants, regroupés par unité (une couleur par unité). Vous pouvez changer le nombre de places d'une table, jamais en dessous des personnes déjà placées.",
        ],
        'unseated' => [
            'title' => 'Sans table',
            'body' => 'Les inscriptions confirmées qui n\'ont pas de table : placez-les à la main.',
        ],
        'constraints' => [
            'title' => 'Séparer des unités',
            'body' => 'Deux unités séparées ne partagent jamais une même table, y compris pour le placement automatique.',
        ],
        'after' => [
            'title' => 'Tout est conservé',
            'body' => "Chaque placement, déplacement ou contrainte est inscrit à l'historique des actions, avec son auteur.",
        ],
    ],

    'treasury' => [
        'intro' => [
            'title' => 'La trésorerie',
            'body' => "L'argent de toute l'organisation, tous événements confondus, en francs CFA.",
        ],
        'period' => [
            'title' => 'La période',
            'body' => 'Limitez les chiffres aux événements dont la date tombe dans la période choisie.',
        ],
        'totals' => [
            'title' => 'Les totaux',
            'body' => 'Encaissé (preuves validées, annulations comprises), remboursé, à rembourser, et le net : encaissé moins remboursé.',
        ],
        'events' => [
            'title' => 'Par événement',
            'body' => 'Une ligne par événement, avec un accès direct à ses inscrits pour comprendre un écart.',
        ],
        'accounts' => [
            'title' => 'Par compte de versement',
            'body' => 'Chaque montant est rangé sous le compte de la preuve de paiement du dossier.',
        ],
        'refunds' => [
            'title' => 'Remboursements à faire',
            'body' => "Les annulations dont le remboursement reste à faire, les plus anciennes d'abord. Ouvrez le dossier pour l'enregistrer une fois rendu.",
        ],
        'export' => [
            'title' => 'Exporter',
            'body' => "Téléchargez le PDF de la période ; chaque tableau a aussi son export. Chaque export est inscrit à l'historique des actions.",
        ],
    ],
];
