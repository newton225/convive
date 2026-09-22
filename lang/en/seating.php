<?php

return [
    'title' => 'Seating plan',

    'tables' => [
        'title' => 'Tables',
        'empty' => 'No table for this event yet.',
        'seats' => ':used / :capacity seats',
        'reserved_for' => 'Reserved: :unit',
    ],

    'unseated' => [
        'title' => 'Registrations without a table',
        'empty' => 'Every confirmed registration is seated.',
    ],

    'columns' => [
        'name' => 'Guest',
        'unit' => 'Unit',
        'party_size' => 'Seats',
        'table' => 'Table',
        'actions' => 'Actions',
    ],

    'actions' => [
        'assign' => 'Seat',
        'move' => 'Move',
        'remove' => 'Remove',
        'choose_table' => 'Choose a table',
    ],

    'constraints' => [
        'title' => 'Separation constraints',
        'description' => 'Two separated units never share a table, including for automatic seating.',
        'empty' => 'No constraint for this event.',
        'unit_a' => 'First unit',
        'unit_b' => 'Second unit',
        'add' => 'Add a constraint',
        'remove' => 'Remove',
        'pair' => ':unitA and :unitB',
    ],

    'errors' => [
        'table_full' => 'This table no longer has enough free seats for this party.',
    ],

    'flash' => [
        'moved' => 'Seating updated.',
        'removed' => 'Registration removed from its table.',
        'constraint_added' => 'Separation constraint added.',
        'constraint_removed' => 'Separation constraint removed.',
    ],
];
