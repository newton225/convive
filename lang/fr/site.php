<?php

return [
    'nav' => [
        'features' => 'Fonctionnalités',
        'steps' => 'Comment ça marche',
        'pricing' => 'Tarifs',
        'showcase' => 'Évènements à la une',
        'login' => 'Connexion',
        'register' => 'Créer mon espace',
        'dashboard' => 'Mon espace',
        'menu' => 'Ouvrir le menu',
    ],

    'theme' => [
        'label' => 'Changer de thème',
    ],

    'hero' => [
        'eyebrow' => 'Inscriptions événementielles',
        'badge' => 'Pensé pour Wave, Orange Money, MTN et Moov',
        'title_lines' => [
            'fill' => 'Remplissez la salle.',
            'verify' => 'Vérifiez chaque preuve.',
            'control' => 'Contrôlez chaque entrée.',
        ],
        'description' => 'Convive gère les inscriptions de vos événements payants à places limitées : réservation chronométrée, preuves de paiement vérifiées, plan de salle, billets signés et contrôle à l\'entrée, même sans réseau.',
        'primary' => 'Créer mon espace',
        'secondary' => 'Voir les tarifs',
        'reassurance' => 'Plan Essentiel gratuit, sans limite de durée.',
        'stage' => [
            'hold' => 'Réservation en cours',
            'proof_title' => 'Preuve validée',
            'proof_body' => 'Table 7 attribuée, billet envoyé',
            'scan' => 'Entrée acceptée',
        ],
    ],

    'figures' => [
        'no_collection' => ['value' => '0', 'label' => 'encaissement dans l\'application : l\'argent va sur vos comptes'],
        'hold' => ['value' => '10 min', 'label' => 'de réservation par défaut, réglables par événement'],
        'reminders' => ['value' => 'J-7, J-2, J-1', 'label' => 'rappels automatiques aux inscrits sans preuve'],
        'signature' => ['value' => 'Ed25519', 'label' => 'signature des billets, vérifiable hors ligne'],
    ],

    'features' => [
        'title' => 'Tout ce qu\'il faut entre « je veux venir » et « je suis entré »',
        'hold' => [
            'title' => 'Une place retenue, un compte à rebours',
            'body' => 'L\'invité s\'inscrit avec ses accompagnateurs. La place est retenue le temps qu\'il paie, puis rendue toute seule.',
        ],
        'proofs' => [
            'title' => 'Des preuves vérifiées, pas seulement reçues',
            'body' => 'Référence déjà utilisée, capture déjà vue, montant différent du relevé : les signaux d\'anomalie s\'affichent avant que vous validiez.',
        ],
        'seating' => [
            'title' => 'Un plan de salle qui se remplit seul',
            'body' => 'À la validation, chaque inscrit reçoit sa table, unités regroupées. Vous déplacez à la main quand il le faut.',
        ],
        'ticket' => [
            'title' => 'Un billet impossible à falsifier',
            'body' => 'QR signé côté serveur, vérifié à l\'entrée sur le téléphone de l\'agent, même hors ligne. Un billet déjà scanné est signalé.',
        ],
        'reports' => [
            'title' => 'Rapprochement et rapports',
            'body' => 'Importez le relevé Mobile Money ou bancaire : chaque ligne retrouve son inscription. Après l\'événement, présence, absents et recettes par unité.',
        ],
    ],

    'preview' => [
        'ticket' => [
            'event' => 'Dîner de gala',
            'date' => 'Samedi 14 novembre, 19 h',
            'guest' => 'Invité',
            'table' => 'Table',
            'seats' => 'Places',
            'valid' => 'Billet valide',
        ],
        'hold' => [
            'label' => 'Réservation en cours',
            'help' => 'Il reste 9 minutes pour déposer la preuve de paiement.',
        ],
        'proofs' => [
            'title' => 'Preuves à vérifier',
            'duplicate' => 'Référence déjà utilisée',
            'mismatch' => 'Montant du relevé différent',
            'clean' => 'Aucune anomalie',
        ],
        'seating' => [
            'title' => 'Plan de salle',
            'table' => 'Table :number',
        ],
        'reports' => [
            'title' => 'Présence par unité',
        ],
        'scan' => [
            'accepted' => 'Entrée acceptée',
            'already' => 'Déjà scanné à 19 h 12',
        ],
    ],

    'steps' => [
        'title' => 'Du lien public au dernier scan',
        'publish' => ['title' => 'Publiez le lien', 'body' => 'Un lien par événement, aux couleurs de votre organisation.'],
        'register' => ['title' => 'Vos invités s\'inscrivent', 'body' => 'Nom, unité, accompagnateurs. Le montant se calcule seul.'],
        'pay' => ['title' => 'Ils paient chez vous', 'body' => 'Sur votre compte Mobile Money ou bancaire, puis ils déposent leur preuve.'],
        'validate' => ['title' => 'Vous validez', 'body' => 'Le billet part, la table est attribuée.'],
        'scan' => ['title' => 'Vous scannez', 'body' => 'À l\'entrée, un scan, un résultat : valide, déjà scanné ou refusé.'],
    ],

    'pricing' => [
        'title' => 'Un tarif pour chaque taille d\'organisation',
        'subtitle' => 'Vous changez de plan quand votre activité change.',
        'recommended' => 'Recommandé',
        'free' => 'Gratuit',
        'on_quote' => 'Sur devis',
        'per_month' => ':price par mois',
        'unlimited' => 'Illimité',
        'events' => 'Événements actifs : :count',
        'registrations' => 'Inscrits : :count',
        'members' => 'Membres : :count',
        'messages' => 'Messages aux invités par mois : :count',
        'reconciliation' => 'Rapprochement du relevé',
        'reports' => 'Rapports après événement',
        'custom_domain' => 'Domaine propre',
        'sso' => 'Connexion unique (SSO)',
        'choose' => 'Commencer',
    ],

    'cta' => [
        'title' => 'Prêt à remplir votre prochaine salle ?',
        'body' => 'Créez votre espace en quelques minutes, publiez votre premier lien dans la foulée.',
        'button' => 'Créer mon espace',
    ],

    'footer' => [
        'tagline' => 'Inscriptions événementielles pour les associations, les églises et les entreprises.',
        'rights' => 'Tous droits réservés.',
        'product' => 'Produit',
        'account' => 'Compte',
        'analytics' => "Mesure d'audience",
        'legal' => 'Informations légales',
        'privacy' => 'Confidentialité',
        'terms' => 'Conditions d’utilisation',
        'notice' => 'Mentions légales',
    ],

    'consent' => [
        'title' => 'Mesurer la fréquentation de ce site ?',
        'body' => "Avec votre accord, Google Analytics compte les visites de ces pages de présentation, pour nous aider à les améliorer. Rien n'est mesuré dans votre espace ni lors d'une inscription, et aucune donnée ne sert à la publicité.",
        'accept' => 'Accepter',
        'decline' => 'Refuser',
    ],
];
