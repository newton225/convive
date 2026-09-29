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
        'primary_color' => 'La couleur dominante du parcours invité de cet événement : bouton « S’inscrire », date, jauge des places, étapes, bandeau des emails. Elle s’applique du lien public jusqu’au billet.',
        'secondary_color' => 'Une couleur d’accompagnement, plus discrète : le bouton d’action des emails envoyés aux invités, et le halo du bandeau quand l’événement n’a pas de visuel.',
        'table_groups' => 'Décrivez la salle par groupes de tables de même taille, par exemple 3 tables de 12 puis 20 tables de 8. La capacité de l’événement est la somme des places de toutes les tables, et une table précise s’ajuste ensuite dans le plan de salle. Un invité et ses accompagnateurs sont toujours assis à la même table.',
        'price_per_person' => 'Montant en francs CFA, sans décimale. Chaque accompagnateur paie aussi ce tarif : l’invité doit verser le tarif multiplié par le nombre de personnes inscrites.',
        'companion_limit' => 'Nombre maximal de personnes qu’un invité peut inscrire avec lui (10 au plus). Chacune occupe une place et reçoit son propre billet.',
        'payment_accounts' => 'Les comptes sur lesquels vos invités vous versent l’argent. Ils se créent dans Organisation, Comptes de versement. Un compte nouveau ou modifié n’apparaît qu’après un délai de sécurité de 24 heures.',
        'registration_deadline' => 'Après cette date, le lien public n’accepte plus d’inscription. Les inscriptions déjà faites continuent normalement.',
        'purge_at' => 'À cette date, les dossiers non finalisés (sans preuve, expirés ou dont la preuve a été rejetée) sont supprimés et leurs places rendues. Les inscriptions validées ne sont jamais touchées.',
        'invitations_send_at' => 'Date d’envoi des cartes d’invitation, par WhatsApp et par email, à toutes les inscriptions validées. Une inscription validée après cette date reçoit sa carte tout de suite.',
        'hold_duration_minutes' => 'Temps laissé à l’invité pour déposer sa preuve de paiement. Pendant ce délai, ses places sont retenues ; ensuite, elles reviennent au stock. 10 minutes par défaut.',
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
        'announce' => 'Annoncer sur la vitrine',
        'withdraw_announcement' => 'Retirer de la vitrine',
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

    'badges' => [
        'published' => 'Lien distribué',
        'not_ready' => 'Pas encore publiable',
        'announced' => 'Sur la vitrine',
    ],

    'publishing' => [
        'ready' => 'Cet événement peut être publié.',
        'blocked' => "Complétez l'identité légale de l'organisation, la capacité, la date et au moins un compte de versement visible avant de publier.",
        'frozen_subdomain' => "Une fois le lien distribué, le sous-domaine de l'organisation ne peut plus changer.",
    ],

    'announcing' => [
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

    'errors' => [
        'deadline_after_event' => "La date limite des inscriptions ne peut pas être postérieure à l'événement.",
        'unknown_payment_account' => "Un des comptes de versement retenus n'appartient pas à cette organisation.",
        'not_ready_to_publish' => 'Cet événement ne peut pas encore être publié : identité légale, capacité, date et compte de versement visible sont requis.',
        'not_published_yet' => "Cet événement doit d'abord être publié avant de pouvoir apparaître sur la vitrine.",
    ],

    'confirm_delete' => [
        'title' => "Supprimer l'événement",
        'description' => "L'événement \":name\" sera supprimé. Cette action est définitive.",
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
        'description' => "Relisez ce que les invités vont voir : une fois le lien distribué, une erreur se corrige moins facilement.",
        'summary' => 'Ce que verront les invités',
        'capacity' => 'Capacité',
        'seats' => '{0} Aucune place|{1} 1 place|[2,*] :count places',
        'not_set' => 'Non renseigné',
        'saved_values' => "Valeurs enregistrées. Si vous avez modifié le formulaire, enregistrez d'abord.",
        'consequences' => 'Ce qui ne pourra plus changer',
        'consequence_link' => "Le lien public s'ouvre aux inscriptions et reste le même pour toujours.",
        'consequence_subdomain' => "Le sous-domaine de l'organisation est figé.",
        'consequence_delete' => "L'événement ne pourra plus être supprimé, seulement clôturé.",
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
    ],

    'settings' => [
        'link' => 'Réglages',
    ],
];
