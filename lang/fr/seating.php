<?php

return [
    'title' => 'Plan de salle',

    'tables' => [
        'title' => 'Tables',
        'empty' => 'Aucune table pour cet événement.',
        'seats' => ':used / :capacity places',
        'reserved_for' => 'Réservée : :unit',
    ],

    'unseated' => [
        'title' => 'Inscriptions sans table',
        'empty' => 'Toutes les inscriptions confirmées sont placées.',
    ],

    'columns' => [
        'name' => 'Inscrit',
        'unit' => 'Unité',
        'party_size' => 'Places',
        'table' => 'Table',
        'actions' => 'Actions',
    ],

    'actions' => [
        'assign' => 'Placer',
        'move' => 'Déplacer',
        'remove' => 'Retirer',
        'choose_table' => 'Choisir une table',
    ],

    'constraints' => [
        'title' => 'Contraintes de séparation',
        'description' => 'Deux unités séparées ne partagent jamais une même table, y compris pour le placement automatique.',
        'empty' => 'Aucune contrainte pour cet événement.',
        'unit_a' => 'Première unité',
        'unit_b' => 'Seconde unité',
        'add' => 'Ajouter une contrainte',
        'remove' => 'Retirer',
        'pair' => ':unitA et :unitB',
    ],

    'confirm_remove' => [
        'title' => 'Retirer de sa table ?',
        'description' => ':name quitte la table :number et rejoint les inscriptions sans table. Si la table se remplit entre-temps, sa place ne lui sera pas gardée.',
    ],

    'confirm_remove_constraint' => [
        'title' => 'Retirer cette contrainte ?',
        'description' => ':unitA et :unitB pourront de nouveau partager une table, y compris au placement automatique.',
    ],

    'errors' => [
        'table_full' => "Cette table n'a plus assez de places libres pour ce groupe.",
        'table_occupied' => '{1} La table :number accueille déjà 1 personne : déplacez-la avant de retirer la table.|[2,*] La table :number accueille déjà :count personnes : déplacez-les avant de retirer la table.',
        'table_too_small' => '{1} La table :number accueille déjà 1 personne : elle ne peut pas avoir moins de places.|[2,*] La table :number accueille déjà :count personnes : elle ne peut pas avoir moins de places.',
        'below_taken' => "Il resterait :capacity places pour :taken déjà prises ou réservées : l'événement ne peut pas descendre sous ce nombre.",
    ],

    'capacity' => [
        'label' => 'Places',
        'edit' => 'Modifier le nombre de places',
        'save' => 'Enregistrer',
        'flash' => 'La table :number compte désormais :count places.',
    ],

    'flash' => [
        'moved' => 'Placement mis à jour.',
        'removed' => 'Inscription retirée de sa table.',
        'constraint_added' => 'Contrainte de séparation ajoutée.',
        'constraint_removed' => 'Contrainte de séparation retirée.',
    ],
];
