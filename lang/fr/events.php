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
        'seating' => "La capacité est la somme des places de toutes les tables : c'est le plan de salle qui fait foi.",
        'deadlines' => 'Date limite, purge, durée de réservation et envoi des cartes.',
    ],

    'fields' => [
        'name' => "Nom de l'événement",
        'name_placeholder' => 'Par exemple : Dîner de gala 2026',
        'subtitle' => 'Sous-titre',
        'starts_at' => 'Date et heure',
        'venue' => 'Lieu',
        'venue_address' => 'Adresse',
        'seats_at_tables' => 'Les invités sont assis à des tables',
        'free_seats' => 'Nombre de places',
        'venue_map_url' => 'Localisation sur une carte',
        'venue_map_url_placeholder' => 'Lien Google Maps, ou coordonnées « 5.3364, -4.0267 »',
        'primary_color' => 'Couleur principale',
        'secondary_color' => 'Couleur secondaire',
        'override_colors' => 'Personnaliser les couleurs de cet événement',
        'override_colors_hint' => "Sans personnalisation, les couleurs de l'organisation s'appliquent.",
        'visual' => "Visuel de l'événement",
        'table_count' => 'Nombre de tables',
        'seats_per_table' => 'Places par table',
        'price_per_person' => 'Tarif par personne (F CFA)',
        'price_categories' => 'Tarifs proposés',
        'price_category_name' => 'Nom du tarif',
        'price_category_price' => 'Prix par personne (F CFA)',
        'price_category_quota' => 'Nombre de places limité à',
        'companion_limit' => "Plafond d'accompagnateurs",
        'registration_deadline' => 'Date limite des inscriptions',
        'purge_at' => 'Purge des dossiers non finalisés',
        'invitations_send_at' => 'Envoi des cartes',
        'hold_duration_minutes' => 'Durée de réservation (minutes)',
        'payment_accounts' => 'Comptes de versement proposés',
        'table_groups' => 'Tables de la salle',
    ],

    'table_groups' => [
        'tables' => 'tables de',
        'seats' => 'places',
        'add' => 'Ajouter des tables d’une autre taille',
        'remove' => 'Retirer ce groupe de tables',
        'total' => '{0} Aucune place pour le moment|{1} :tables table, 1 place au total|[2,*] :tables tables, :count places au total',
        'empty' => 'Aucune table pour le moment : ajoutez un groupe pour définir la capacité.',
    ],

    'help' => [
        'venue' => 'Le nom de l’endroit, tel que vos invités le reconnaissent : « Hôtel Ivoire, salle des Palmiers », « Palais de la Culture ». Il est nécessaire pour publier l’événement.',
        'venue_address' => 'Facultatif. Où trouver ce lieu : rue, quartier, ville ou un repère, par exemple « Boulevard Latrille, Cocody, Abidjan ». Elle s’affiche sous le nom du lieu. Pour l’itinéraire, utilisez la localisation sur une carte juste en dessous.',
        'seats_at_tables' => 'Cochez si vos invités sont répartis à des tables. Décochez pour un rassemblement sans table, comme un événement en plein air : vous indiquez alors seulement le nombre de places, et aucune table n’est attribuée.',
        'venue_map_url' => "Facultatif. Dans Google Maps, touchez le lieu puis « Partager » et collez le lien ici ; des coordonnées marchent aussi. Vos invités verront un bouton « Voir l'itinéraire ». Liens acceptés : Google Maps, Apple Plans, OpenStreetMap, Waze.",
        'primary_color' => 'La couleur dominante du parcours invité de cet événement : bouton « S’inscrire », date, jauge des places, étapes, bandeau des emails. Elle s’applique du lien public jusqu’au billet.',
        'secondary_color' => 'Une couleur d’accompagnement, plus discrète : le bouton d’action des emails envoyés aux invités, et le halo du bandeau quand l’événement n’a pas de visuel.',
        'table_groups' => 'Décrivez la salle par groupes de tables de même taille, par exemple 3 tables de 12 puis 20 tables de 8. La capacité de l’événement est la somme des places de toutes les tables, et une table précise s’ajuste ensuite dans le plan de salle. Un invité et ses accompagnateurs sont toujours assis à la même table.',
        'price_per_person' => 'Montant en francs CFA, sans décimale ; 0 pour un événement gratuit. Chaque accompagnateur paie aussi ce tarif : l’invité doit verser le tarif multiplié par le nombre de personnes inscrites.',
        'price_categories' => 'Ajoutez les tarifs proposés. Chaque personne choisit son tarif ; le nombre de places de chaque tarif est obligatoire et compte les places de cette catégorie.',
        'companion_limit' => 'Nombre maximal de personnes qu’un invité peut inscrire avec lui (10 au plus). Chacune occupe une place et reçoit son propre billet.',
        'payment_accounts' => 'Les comptes sur lesquels vos invités vous versent l’argent. Ils se créent dans Organisation, Comptes de versement. Un compte nouveau ou modifié n’apparaît qu’après un délai de sécurité de 24 heures.',
        'registration_deadline' => 'Après cette date, le lien public n’accepte plus d’inscription. Les inscriptions déjà faites continuent normalement.',
        'purge_at' => 'À cette date, les dossiers non finalisés (sans preuve, expirés ou dont la preuve a été rejetée) sont supprimés et leurs places rendues. Les inscriptions validées ne sont jamais touchées.',
        'invitations_send_at' => 'Date d’envoi des cartes d’invitation, par WhatsApp et par email, à toutes les inscriptions validées. Une inscription validée après cette date reçoit sa carte tout de suite.',
        'hold_duration_minutes' => 'Temps laissé à l’invité pour déposer sa preuve de paiement. Pendant ce délai, ses places sont retenues ; ensuite, elles reviennent au stock. 10 minutes par défaut.',
    ],

    'payment_accounts_not_needed' => 'Tous vos tarifs sont gratuits : aucun versement n’est attendu, vous pouvez publier sans compte de versement.',

    'venue_map' => [
        'preview' => 'Tester ce lien',
        'host' => 'Ouvre :host dans un nouvel onglet, comme le verront vos invités.',
    ],

    'price_categories' => [
        'default_name' => 'Tarif unique',
        'add' => 'Ajouter un tarif',
        'remove' => 'Retirer le tarif :name',
        'minimum' => 'Le tarif affiché sur le lien public sera « à partir de :price ».',
        'free' => 'Gratuit',
        'free_hint' => 'Un prix à 0 équivaut à gratuit.',
        'locked' => 'Déjà choisi : le nom et le prix ne changent plus. Le quota reste modifiable.',
        'quotas_exceed' => 'Les quotas additionnés (:total) dépassent la salle (:capacity places) : l’enregistrement sera refusé tant que leur somme dépasse la salle.',
    ],

    'visual' => [
        'hint' => "Affiche ou photo propre à cet événement. Sans visuel, la bannière de l'organisation s'applique.",
        'choose' => 'Choisir un visuel',
        'replace' => 'Remplacer',
        'remove' => 'Retirer',
        'remove_confirm' => [
            'title' => 'Retirer le visuel de cet événement ?',
            'description' => 'La bannière de l’organisation le remplacera sur le lien public. Pour le remettre, il faudra le déposer à nouveau.',
            'confirm' => 'Retirer le visuel',
        ],
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
        'announce' => 'Annoncer sur la vitrine',
        'withdraw_announcement' => 'Retirer de la vitrine',
        'close' => "Clôturer l'événement",
        'duplicate' => 'Dupliquer',
        'delete' => "Supprimer l'événement",
        'copy_link' => 'Copier le lien',
        'proofs' => 'Preuves',
        'seating' => 'Plan de salle',
        'ticket_template' => 'Gabarit du billet',
        'scan' => 'Scan',
        'registrations' => 'Base d\'inscrits',
        'report' => 'Rapport',
        'reconciliation' => 'Rapprochement',
    ],

    'card' => [
        'fill_label' => 'Remplissage',
        'fill_value' => ':occupied / :capacity places',
        'collected' => 'Montant collecté',
        'proofs_to_check' => '{1} 1 preuve à vérifier|[2,*] :count preuves à vérifier',
        'draft_hint' => 'Brouillon : terminez la fiche puis publiez le lien pour ouvrir les inscriptions.',
        'continue' => 'Continuer la préparation',
        'check_proofs' => '{1} Vérifier la preuve|[2,*] Vérifier les :count preuves',
        'open_scan' => 'Ouvrir le scan',
        'view_report' => 'Voir le rapport',
        'open' => "Ouvrir l'événement",
        'more_actions' => 'Autres actions pour :name',
        'group_follow' => 'Suivi',
        'group_day' => 'Jour J',
        'group_event' => 'Événement',
        'link_copied' => 'Lien public copié.',
        'link_copy_failed' => "Impossible de copier le lien : ouvrez l'événement pour le récupérer.",
    ],

    'filters' => [
        'label' => 'Filtrer les événements',
        'active' => 'En cours et à venir',
        'closed' => 'Terminés',
        'all' => 'Tous',
        'empty' => 'Aucun événement dans cette catégorie.',
    ],

    'search' => [
        'label' => 'Rechercher un événement',
        'placeholder' => 'Nom ou lieu',
        'empty' => 'Aucun événement ne correspond à « :search » dans cette catégorie.',
    ],

    'badges' => [
        'published' => 'Lien distribué',
        'not_ready' => 'Pas encore publiable',
        'announced' => 'Sur la vitrine',
    ],

    'publishing' => [
        'ready' => 'Cet événement peut être publié.',
        'blocked' => 'Avant de publier, il manque : :items.',
        'unsaved' => 'Enregistrez d’abord vos modifications : publier publierait la version précédente.',
        'changes_not_notified' => 'Les invités déjà inscrits ne sont pas prévenus d’un changement de date, d’heure ou de lieu : prévenez-les vous-même.',
        'capacity_reduced' => 'Vous réduisez la capacité de :from à :to places. Elle ne peut pas descendre sous les :taken places déjà prises ou réservées.',
        'frozen_subdomain' => "Une fois le lien distribué, le sous-domaine de l'organisation ne peut plus changer.",
    ],

    'companion_limit' => [
        'value' => '{0} Aucun accompagnateur|{1} 1 accompagnateur|[2,*] :count accompagnateurs',
    ],

    'hold_duration' => [
        'value' => '{1} 1 minute|[2,*] :count minutes',
        'bounds' => 'Entre :min et :max minutes.',
    ],

    'preview' => [
        'title' => 'Aperçu dans la vitrine',
        'description' => 'La carte telle qu\'elle apparaîtra sur le site produit si vous annoncez l\'événement. Elle suit votre saisie ; le visuel se dépose plus bas.',
        'untitled' => 'Nom de l\'événement',
    ],

    'announcing' => [
        'blocked' => 'Pour apparaître sur la vitrine, il manque : :items.',
        'description' => "Faites apparaître cet événement dans la vitrine du site produit, pour toucher un public qui n'a jamais reçu le lien.",
        'announced' => 'Cet événement apparaît sur la vitrine du site produit.',
    ],

    'flash' => [
        'created' => 'Événement créé.',
        'updated' => 'Événement mis à jour.',
        'published' => 'Lien public distribué.',
        'announced' => 'Événement annoncé sur la vitrine.',
        'announcement_withdrawn' => 'Événement retiré de la vitrine.',
        'closed' => 'Événement clôturé.',
        'duplicated' => 'Événement dupliqué.',
        'deleted' => 'Événement supprimé.',
        'visual_updated' => "Visuel de l'événement mis à jour.",
        'visual_deleted' => "Visuel de l'événement retiré.",
    ],

    'missing_publish' => [
        'organisation' => "l'identité de l'organisation (page Espace et marque)",
        'capacity' => 'au moins une table avec des places',
        'date' => 'la date et l\'heure',
        'date_past' => 'une date à venir',
        'venue' => 'le lieu',
        'seats' => 'le nombre de places',
        'payment_account' => 'un compte de versement visible, rattaché à l\'événement',
    ],

    'missing_announce' => [
        'not_published' => 'la publication du lien',
        'closed' => 'un événement ouvert (il est clos)',
        'past' => 'une date à venir',
    ],
    'errors' => [
        'price_category_unknown' => 'Ce tarif ne correspond pas à cet événement.',
        'price_category_duplicate' => 'Chaque tarif doit avoir un nom différent.',
        'price_category_quota_below_taken' => 'Le quota ne peut pas être inférieur aux :count places déjà prises dans ce tarif.',
        'price_category_quota_above_capacity' => 'Le quota ne peut pas dépasser les :capacity places de la salle.',
        'starts_at_past_when_published' => 'Cet événement est publié ou déjà réservé : sa date ne peut pas reculer dans le passé, des invités ont peut-être déjà payé. Pour le terminer, clôturez-le.',
        'price_category_quotas_above_capacity' => 'Les quotas additionnés (:total) dépassent la salle (:capacity places) : réduisez-les pour que leur somme tienne dans la salle.',
        'seating_mode_locked' => 'Des invités sont déjà inscrits : le choix avec ou sans tables ne change plus, car il décide de ce que dit leur billet.',
        'price_category_locked' => 'Ce tarif a déjà été choisi : son nom et son prix ne changent plus, pour que chaque invité paie ce qui lui a été annoncé. Seul son quota peut encore bouger.',
        'price_category_in_use' => 'Un tarif déjà choisi par des inscrits ou des personnes en attente ne peut pas être retiré.',
        'venue_map_url' => 'Collez un lien Google Maps, Apple Plans, OpenStreetMap ou Waze (en https), ou des coordonnées comme « 5.3364, -4.0267 ».',
        'deadline_after_event' => "La date limite des inscriptions ne peut pas être postérieure à l'événement.",
        'unknown_payment_account' => "Un des comptes de versement retenus n'appartient pas à cette organisation.",
        'not_ready_to_publish' => 'Cet événement ne peut pas encore être publié. Il manque : :items.',
        'not_ready_to_announce' => 'Cet événement ne peut pas encore apparaître sur la vitrine. Il manque : :items.',
        'not_published_yet' => "Cet événement doit d'abord être publié avant de pouvoir apparaître sur la vitrine.",
    ],

    'confirm_delete' => [
        'title' => "Supprimer l'événement",
        'description' => "L'événement \":name\" disparaît tout de suite de votre liste, et il est effacé définitivement au bout de 30 jours.",
    ],

    'templates' => [
        'title' => "Partir d'un modèle",
        'description' => 'Reprend les tables, le tarif, les accompagnateurs et les comptes de versement. Le nom et les dates restent à saisir ; le gabarit du billet est déjà commun à l\'organisation.',
        'meta' => ':tables tables · :price',
        'blank' => 'Modèle vierge',
        'blank_hint' => 'Tout paramétrer manuellement',
    ],

    'confirm_publish' => [
        'title' => "Publier l'événement ?",
        'description' => 'Relisez ce que les invités vont voir : une fois le lien distribué, une erreur se corrige moins facilement.',
        'summary' => 'Ce que verront les invités',
        'capacity' => 'Capacité',
        'seats' => '{0} Aucune place|{1} 1 place|[2,*] :count places',
        'not_set' => 'Non renseigné',
        'saved_values' => "Valeurs enregistrées. Si vous avez modifié le formulaire, enregistrez d'abord.",
        'consequences' => 'Ce qui ne pourra plus changer',
        'consequence_link' => "Le lien public s'ouvre aux inscriptions et reste le même pour toujours.",
        'consequence_subdomain' => "Le sous-domaine de l'organisation est figé.",
        'consequence_delete' => "L'événement ne pourra plus être supprimé, seulement clôturé.",
        'consequence_payment_delay' => "C'est votre première publication : ensuite, toute création ou modification d'un compte de versement attendra 24 heures avant d'être visible, même une fois vos événements clôturés. Un autre Propriétaire peut la valider plus tôt, jamais la personne qui l'a demandée.",
        'acknowledge' => "J'ai vérifié ces informations et je veux ouvrir les inscriptions.",
    ],

    'confirm_announce' => [
        'title' => 'Annoncer sur la vitrine ?',
        'description' => "« :name » apparaîtra dans les événements à la une du site Convive, visible par tous les visiteurs, avec son lien d'inscription. Vous pourrez le retirer à tout moment.",
    ],

    'confirm_withdraw_announcement' => [
        'title' => 'Retirer de la vitrine ?',
        'description' => "« :name » disparaîtra des événements à la une du site Convive. Le lien public reste valable et les inscriptions continuent pour ceux qui l'ont déjà.",
    ],

    'confirm_close' => [
        'title' => "Clôturer l'événement",
        'description' => 'Les inscriptions seront fermées pour ":name". Cette action ne se défait pas.',
        'pending_proofs' => 'Preuves en attente',
    ],

    'settings' => [
        'link' => 'Réglages',
    ],

    // Courriel a l'organisation quand l'editeur retire son annonce de la vitrine.
    'announcement_withdrawn_mail' => [
        'subject' => 'L’annonce de « :event » a été retirée de la vitrine',
        'intro' => 'L’équipe Convive a retiré de la vitrine du site l’annonce de votre événement « :event ».',
        'reason' => 'Motif : :reason',
        'outro' => 'Votre événement et son lien public ne sont pas touchés : vos invités peuvent toujours s’inscrire. Pour en parler, répondez à ce message.',
    ],
];
