<?php

return [
    'sidebar' => [
        'plan' => 'Plan :name',
        'events' => '{0} Aucun événement actif sur :max|{1} 1 événement actif sur :max|[2,*] :count événements actifs sur :max',
        'events_unlimited' => '{0} Aucun événement actif|{1} 1 événement actif|[2,*] :count événements actifs',
    ],

    'title' => 'Abonnement',
    'back' => 'Retour aux réglages',

    'statuses' => [
        'active' => 'À jour',
        'past_due' => 'Paiement en retard',
        'suspended' => 'Suspendu',
        'canceled' => 'Résilié',
    ],

    'invoice_statuses' => [
        'open' => 'À payer',
        'paid' => 'Payée',
        'failed' => 'Échec',
        'void' => 'Annulée',
    ],

    'banner' => [
        'past_due' => 'Le dernier prélèvement a échoué. Mettez à jour votre moyen de paiement : sans règlement, l\'espace sera suspendu au bout de dix jours.',
        'suspended' => 'Cet espace est suspendu pour impayé : les événements, les preuves et le scan sont arrêtés, et les inscriptions publiques sont fermées. Réglez l\'abonnement pour le rouvrir.',
    ],

    'confirm_cancel' => [
        'title' => "Résilier l'abonnement ?",
        'description' => "Le prélèvement s'arrête. Pour retrouver ce plan ensuite, il faudra souscrire de nouveau.",
    ],

    'subscription' => [
        'renews' => 'Prochain prélèvement le :date.',
        'canceled' => 'Abonnement résilié le :date.',
        'payment_method' => 'Moyen de paiement : :brand se terminant par :last4.',
        'no_payment_method' => 'Aucun moyen de paiement enregistré.',
        'change_payment_method' => 'Changer de moyen de paiement',
        'cancel' => 'Résilier l\'abonnement',
    ],

    'usage' => [
        'title' => 'Consommation',
        'events' => 'Événements actifs',
        'registrations' => 'Inscrits',
        'members' => 'Membres et invitations',
        'messages' => 'Messages aux invités ce mois-ci',
        'of' => ':used sur :max',
        'unlimited' => ':used (illimité)',
    ],

    'plans' => [
        'title' => 'Plans',
        'current' => 'Plan actuel',
        'currency' => 'Devise',
        'free' => 'Gratuit',
        'on_quote' => 'Sur devis',
        'per_month' => ':price par mois',
        'unlimited' => 'Illimité',
        'events' => 'Événements actifs : :count',
        'registrations' => 'Inscrits : :count',
        'members' => 'Membres : :count',
        'reconciliation' => 'Rapprochement du relevé',
        'reports' => 'Rapports après événement',
        'custom_domain' => 'Domaine propre',
        'sso' => 'Connexion unique (SSO)',
        'choose' => 'Choisir ce plan',
        'contact' => 'Nous contacter',
    ],

    'invoices' => [
        'title' => 'Factures',
        'empty' => 'Aucune facture pour le moment.',
        'number' => 'Numéro',
        'date' => 'Date',
        'amount' => 'Montant',
        'status' => 'Statut',
        'open' => 'Ouvrir',
    ],

    'errors' => [
        'suspended' => 'Cet espace est suspendu pour impayé. Réglez l\'abonnement pour le rouvrir.',
        'suspended_by_editor' => 'Cet espace a été suspendu par l’équipe Convive. Écrivez au support pour en connaître la raison et le rouvrir.',
        'event_quota' => 'Le plan :plan n\'autorise pas d\'autre événement actif. Clôturez un événement ou passez à un plan supérieur.',
        'member_quota' => 'Le plan :plan ne permet pas d\'ajouter d\'autre membre, invitations en attente comprises. Passez à un plan supérieur.',
        'not_purchasable' => 'Ce plan ne se souscrit pas en ligne : il est gratuit, ou se négocie sur devis.',
        'not_configured' => 'Le paiement en ligne n\'est pas encore disponible. Réessayez plus tard ou contactez-nous.',
        'no_provider_customer' => 'Aucun paiement n\'a encore été enregistré pour cette organisation.',
        'no_subscription' => 'Cette organisation n\'a pas d\'abonnement à résilier.',
    ],

    'flash' => [
        'canceled' => 'Abonnement résilié.',
    ],

    'mail' => [
        'action' => 'Ouvrir l\'abonnement',
        'overdue' => [
            'subject' => 'Le paiement de l\'abonnement de :tenant est en retard',
            'line' => 'Le prélèvement de l\'abonnement de :tenant a échoué.',
            'deadline' => '{0} Sans règlement, l\'espace sera suspendu aujourd\'hui.|{1} Sans règlement, l\'espace sera suspendu demain.|[2,*] Sans règlement sous :days jours, l\'espace sera suspendu.',
        ],
        'suspended' => [
            'subject' => 'L\'espace :tenant est suspendu',
            'line' => 'L\'abonnement de :tenant reste impayé : l\'espace est suspendu. Réglez l\'abonnement pour le rouvrir.',
        ],
    ],
];
