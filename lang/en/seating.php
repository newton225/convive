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

    'confirm_remove' => [
        'title' => 'Remove from their table?',
        'description' => ':name leaves table :number and joins the registrations without a table. If the table fills up meanwhile, their seat will not be kept.',
    ],

    'confirm_remove_constraint' => [
        'title' => 'Remove this constraint?',
        'description' => ':unitA and :unitB will be able to share a table again, including for automatic seating.',
    ],

    'errors' => [
        'table_full' => 'This table no longer has enough free seats for this party.',
        'table_occupied' => '{1} Table :number already seats 1 person: move them before removing the table.|[2,*] Table :number already seats :count people: move them before removing the table.',
        'table_too_small' => '{1} Table :number already seats 1 person: it cannot have fewer seats.|[2,*] Table :number already seats :count people: it cannot have fewer seats.',
        'below_taken' => 'Only :capacity seats would remain for :taken already taken or held: the event cannot go below that number.',
    ],

    'capacity' => [
        'label' => 'Seats',
        'edit' => 'Change the number of seats',
        'save' => 'Save',
        'flash' => 'Table :number now has :count seats.',
    ],

    'flash' => [
        'moved' => 'Seating updated.',
        'removed' => 'Registration removed from its table.',
        'constraint_added' => 'Separation constraint added.',
        'constraint_removed' => 'Separation constraint removed.',
    ],
];
