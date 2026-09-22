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

    'errors' => [
        'table_full' => "Cette table n'a plus assez de places libres pour ce groupe.",
    ],

    'flash' => [
        'moved' => 'Placement mis à jour.',
        'removed' => 'Inscription retirée de sa table.',
        'constraint_added' => 'Contrainte de séparation ajoutée.',
        'constraint_removed' => 'Contrainte de séparation retirée.',
    ],
];
