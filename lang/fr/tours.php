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

    'registrations' => [
        'intro' => [
            'title' => "La base d'inscrits",
            'body' => "Tous les dossiers de l'événement, avec leur état, leur montant et leur unité. La liste est paginée par 25 et filtrée par le serveur.",
        ],
        'filters' => [
            'title' => 'Rechercher et filtrer',
            'body' => "Cherchez par nom, téléphone, courriel, référence du dossier ou référence d'une transaction, sans tenir compte des accents, puis filtrez par état : réservation en cours, preuve à vérifier, confirmée, annulée.",
        ],
        'table' => [
            'title' => 'Les dossiers',
            'body' => "Dépliez une ligne pour voir chaque personne du groupe avec son tarif et sa présence. Depuis la ligne : l'historique du dossier (ce qui s'est passé et qui en est l'auteur), la carte d'invitation, le renvoi du lien de réservation et l'annulation.",
        ],
        'exports' => [
            'title' => 'Exporter',
            'body' => "Excel, CSV, PDF et listes de contrôle suivent le filtre affiché. Chaque export est inscrit à l'historique des actions.",
        ],
        'purge' => [
            'title' => 'Purger',
            'body' => "Efface les dossiers non finalisés, sans preuve validée : tous, ou ceux que vous cochez. « Tout purger » épargne les réservations dont le décompte tourne encore, au cas où l'invité serait en train de payer.",
        ],
        'after' => [
            'title' => 'Et ensuite',
            'body' => 'Une annulation demande ce que devient le paiement ; les remboursements à faire se suivent dans la Trésorerie.',
        ],
    ],

    'report' => [
        'intro' => [
            'title' => 'Le rapport',
            'body' => "Le bilan de l'événement : présence, absents, recettes et contrôle à l'entrée.",
        ],
        'cards' => [
            'title' => 'Les chiffres clés',
            'body' => 'Inscriptions confirmées, présents et absents, recettes, remboursé, à rembourser, et le net : recettes moins remboursements. La durée moyenne de contrôle mesure le débit à la porte.',
        ],
        'units' => [
            'title' => 'Par unité',
            'body' => "Chaque dossier est compté dans l'unité de son participant principal : confirmées, présents et recettes.",
        ],
        'export' => [
            'title' => 'Exporter',
            'body' => "Téléchargez le rapport en PDF, à l'en-tête de l'organisation. L'export est inscrit à l'historique des actions.",
        ],
    ],

    'team' => [
        'intro' => [
            'title' => "L'équipe",
            'body' => 'Les personnes qui travaillent avec votre organisation, et ce que chacune peut faire grâce à son profil.',
        ],
        'members' => [
            'title' => 'Les membres',
            'body' => "Changez le profil d'un membre, ou retirez-le. Une organisation garde toujours au moins un Propriétaire, et vous ne changez pas votre propre profil.",
        ],
        'invite' => [
            'title' => 'Inviter',
            'body' => "Saisissez l'adresse de la personne et choisissez son profil : elle reçoit un lien. Tant qu'elle n'a pas accepté, l'invitation reste en attente.",
        ],
        'invitations' => [
            'title' => 'Invitations en attente',
            'body' => "Celles qui n'ont pas encore été acceptées. Vous pouvez en annuler une tant qu'elle n'est pas acceptée.",
        ],
        'after' => [
            'title' => 'Tout est conservé',
            'body' => "Chaque invitation, retrait et changement de profil est inscrit à l'historique des actions, avec son auteur.",
        ],
    ],

    'profiles' => [
        'intro' => [
            'title' => 'Les profils',
            'body' => "Un profil est un ensemble de permissions que vous donnez à des membres. Vous composez les vôtres à partir d'un catalogue fixe.",
        ],
        'create' => [
            'title' => 'Créer un profil',
            'body' => 'Nommez-le et cochez ses permissions. Vous ne pouvez accorder que des permissions que vous détenez vous-même.',
        ],
        'list' => [
            'title' => 'Vos profils',
            'body' => 'Le Propriétaire et les profils de base (Trésorier, Hôtesse, Lecture) sont figés : pour une variante, dupliquez-en un. Vous pouvez masquer un profil de base ; ses porteurs le gardent. Un profil porté par des membres ne se supprime pas avant leur réaffectation.',
        ],
        'two_factor' => [
            'title' => 'Double authentification',
            'body' => "Un profil peut l'exiger : ses porteurs n'atteignent l'organisation qu'une fois la double authentification activée. Propriétaire et Trésorier l'exigent d'office, car ils touchent à l'argent.",
        ],
        'after' => [
            'title' => 'Effet immédiat',
            'body' => "Une modification prend effet tout de suite et s'inscrit à l'historique des actions, avec l'avant et l'après.",
        ],
    ],

    'claims' => [
        'intro' => [
            'title' => 'Les réclamations',
            'body' => "Les messages que vos invités vous écrivent depuis la page de leur dossier. Aucune de vos coordonnées n'est affichée : c'est vous qui les rappelez.",
        ],
        'filters' => [
            'title' => 'Chercher et filtrer',
            'body' => 'Cherchez une réclamation, et affichez celles à traiter ou celles déjà traitées.',
        ],
        'table' => [
            'title' => 'La liste',
            'body' => 'Chaque ligne donne le sujet, le message et le numéro à rappeler. Une fois la personne rappelée, marquez la réclamation comme traitée : le geste est inscrit à l\'historique des actions.',
        ],
        'after' => [
            'title' => 'Sans réponse dans l\'application',
            'body' => "L'application ne répond pas à l'invité : l'état dit seulement « à traiter » ou « traitée ». Un dossier accepte trois réclamations ouvertes au plus.",
        ],
    ],

    'audit' => [
        'intro' => [
            'title' => "L'historique des actions",
            'body' => "Les actions sensibles de l'organisation : qui, quoi, quand et depuis quelle adresse. Il est gardé 24 mois et personne ne peut le modifier ni l'effacer.",
        ],
        'filters' => [
            'title' => 'Chercher et filtrer',
            'body' => "Cherchez un nom ou un mot, ou limitez la liste à un type d'action : compte de versement, équipe, preuve, entrée sans scan...",
        ],
        'table' => [
            'title' => 'Les actions',
            'body' => "Triez la liste. Chaque ligne garde l'avant et l'après d'une modification : c'est ce qui permet de repérer un changement discret.",
        ],
        'after' => [
            'title' => 'Qui peut le lire',
            'body' => "Seuls les membres dont le profil le permet voient cet écran. Les entrées sans scan et les changements de compte de versement préviennent aussi l'équipe.",
        ],
    ],

    'units' => [
        'intro' => [
            'title' => 'Les unités',
            'body' => "Les groupes que vos invités choisissent à l'inscription. Chaque organisation compose les siennes.",
        ],
        'create' => [
            'title' => 'Ajouter une unité',
            'body' => 'Saisissez un nom : deux unités ne peuvent pas porter le même nom dans une organisation.',
        ],
        'list' => [
            'title' => 'Vos unités',
            'body' => 'Renommez une unité ou désactivez-la pour la retirer du formulaire : les inscriptions déjà prises la gardent. « Invité spécial » est protégée : toujours en dernier, ni renommée, ni désactivée, ni supprimée.',
        ],
        'after' => [
            'title' => 'Au moins une unité',
            'body' => "Il en reste toujours au moins une, sans quoi le formulaire d'inscription ne pourrait plus être rempli.",
        ],
    ],

    'payment_accounts' => [
        'intro' => [
            'title' => 'Les comptes de versement',
            'body' => "Les numéros où vos invités envoient leur paiement. C'est la partie la plus protégée de l'application : un numéro modifié discrètement détournerait l'argent d'un événement.",
        ],
        'add' => [
            'title' => 'Ajouter un compte',
            'body' => 'Choisissez le canal, le numéro et le titulaire. Le numéro doit correspondre au réseau du canal (par exemple 07 pour Orange Money).',
        ],
        'delay' => [
            'title' => 'Le délai de 24 heures',
            'body' => "Dès qu'un événement payant a été publié, changer le canal, le numéro ou le titulaire n'est actif qu'après 24 heures. L'ancien numéro reste affiché entre-temps, et tous les porteurs de cette permission sont alertés.",
        ],
        'list' => [
            'title' => 'Vos comptes',
            'body' => "Un second Propriétaire peut lever le délai, jamais celui qui a demandé le changement. Un changement reste signalé sept jours. Chaque geste demande votre mot de passe et s'inscrit à l'historique.",
        ],
    ],

    'reconciliation' => [
        'intro' => [
            'title' => 'Le rapprochement',
            'body' => 'Comparez un relevé Mobile Money ou bancaire aux preuves déposées, pour repérer un versement manquant ou un montant différent.',
        ],
        'import' => [
            'title' => 'Importer un relevé',
            'body' => 'Un fichier CSV avec les colonnes date, référence, émetteur et montant. Les lignes sont comparées aux preuves déposées de cet événement.',
        ],
        'stats' => [
            'title' => "L'état du relevé",
            'body' => 'Lignes rapprochées, montant divergent, nom approchant, sans inscription. Les lignes à trancher sont celles dont le montant diverge, le nom est approchant ou aucune inscription ne correspond.',
        ],
        'table' => [
            'title' => 'Les lignes',
            'body' => "Filtrez par résultat. Pour une ligne à trancher, choisissez l'inscription qui correspond ou indiquez qu'aucune ne correspond.",
        ],
        'after' => [
            'title' => 'Rien ne valide seul',
            'body' => 'Le rapprochement éclaire votre décision, il ne valide aucune preuve à votre place.',
        ],
    ],

    'organisation' => [
        'intro' => [
            'title' => "L'organisation",
            'body' => "Qui vous êtes aux yeux de vos invités : identité légale, marque et adresse. Rien n'est obligatoire à la saisie ; ce qui manque est exigé le jour où vous publiez.",
        ],
        'legal' => [
            'title' => 'Identité légale',
            'body' => "Nom affiché, raison sociale, forme juridique, signataire, ville, pays et téléphone sont exigés pour publier. Ils figurent sur les billets et les reçus : une modification est inscrite à l'historique.",
        ],
        'brand' => [
            'title' => 'La marque',
            'body' => "Couleurs, logo, bandeau, cachet et signature. Elles s'appliquent au parcours invité, au billet et aux messages, jamais au back-office. Images JPEG, PNG ou WebP, 5 Mo au plus ; le SVG est refusé.",
        ],
        'subdomain' => [
            'title' => 'Le sous-domaine',
            'body' => "L'adresse de vos liens publics. Publier un événement le fige : le changer casserait les liens déjà distribués.",
        ],
        'after' => [
            'title' => 'Prêt à publier',
            'body' => 'Le bandeau en haut de la page nomme ce qui manque encore avant de pouvoir publier.',
        ],
    ],

    'billing' => [
        'intro' => [
            'title' => 'La facturation',
            'body' => "Votre plan, votre consommation et vos factures. Rien n'est supprimé si une limite est atteinte : l'organisation retombe simplement sur les limites de son plan.",
        ],
        'plan' => [
            'title' => 'Votre plan',
            'body' => "Le plan en cours, la période d'essai et les gestes d'abonnement : moyen de paiement, changement de plan, résiliation.",
        ],
        'usage' => [
            'title' => 'Votre consommation',
            'body' => 'Événements actifs, inscrits, membres et messages du mois, face aux limites du plan. Un événement dont la fin est passée ne compte plus comme actif, même sans être clôturé, et ses inscrits non plus.',
        ],
        'invoices' => [
            'title' => 'Les factures',
            'body' => 'Les factures de votre abonnement, avec un lien pour les consulter en ligne.',
        ],
    ],

    'support_access' => [
        'intro' => [
            'title' => "L'accès du support",
            'body' => "Vous seul décidez si l'équipe Convive peut consulter votre organisation, pour combien de temps et sur quoi.",
        ],
        'rules' => [
            'title' => 'Les règles',
            'body' => "Lecture seule, durée limitée, personne nommée, chaque consultation tracée et un bandeau visible pendant l'accès.",
        ],
        'grant' => [
            'title' => 'Accorder un accès',
            'body' => 'Choisissez la personne du support, la durée, et si besoin un seul événement. Un seul accès à la fois ; vous pouvez le révoquer ou le prolonger, avec votre mot de passe.',
        ],
        'history' => [
            'title' => "L'historique",
            'body' => 'Les accès passés : qui, quand, pour quelle portée.',
        ],
    ],

    'console' => [
        'intro' => [
            'title' => "La console d'exploitation",
            'body' => "L'espace de l'éditeur : suivre les organisations clientes et faire tourner la plateforme. Il est réservé aux comptes autorisés, et sans accès du support accordé par une organisation, vous ne lisez aucune de ses données.",
        ],
        'clients' => [
            'title' => 'Clients',
            'body' => 'Les organisations, les comptes, la récupération d’un compte et le catalogue des plans.',
        ],
        'platform' => [
            'title' => 'Plateforme',
            'body' => 'La santé technique, les messages, la sécurité, la vitrine, le journal de la console et l’équipe de la console.',
        ],
        'filters' => [
            'title' => 'Chercher et filtrer',
            'body' => 'Retrouvez une organisation par son nom, ou limitez la liste à un état : essai, à jour, impayé, suspendue, suppression programmée.',
        ],
        'table' => [
            'title' => 'Les organisations',
            'body' => 'Chaque ligne donne le plan, l’état et la consommation. Le menu de la ligne ouvre les gestes : changer de plan, prolonger l’essai, régler les limites, suspendre, programmer la suppression. Chaque geste est inscrit au journal de la console.',
        ],
        'after' => [
            'title' => 'Aucun effacement immédiat',
            'body' => 'Une suppression passe par une corbeille de 30 jours : l’organisation disparaît aussitôt pour ses membres, mais peut être restaurée à la demande d’un Propriétaire.',
        ],
    ],
];
