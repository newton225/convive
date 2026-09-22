<?php

return [
    'title' => 'Rapport post-événement',
    'description' => 'Présence, absents, recettes et contrôle à l\'entrée.',

    'cards' => [
        'confirmed' => 'Inscriptions confirmées',
        'present' => 'Présents',
        'absent' => 'Absents',
        'collected' => 'Recettes',
        'seats' => '{0} Aucune place|{1} 1 place|[2,*] :count places',
        'average_scan_interval' => 'Durée moyenne de contrôle',
        'seconds' => '{0} Moins d\'une seconde|{1} 1 seconde|[2,*] :count secondes',
        'not_available' => 'Indisponible (moins de deux billets acceptés)',
    ],

    'units' => [
        'title' => 'Présence et recettes par unité',
        'unit' => 'Unité',
        'confirmed' => 'Confirmées',
        'present' => 'Présents',
        'collected' => 'Recettes',
        'empty' => 'Aucune inscription confirmée pour le moment.',
        'note' => 'Chaque dossier est compté dans l\'unité de son participant principal. La durée de contrôle est l\'écart moyen entre deux billets acceptés, un indicateur de débit à l\'entrée.',
    ],

    'actions' => [
        'export_pdf' => 'Exporter en PDF',
    ],

    'pdf' => [
        'title' => 'Rapport post-événement',
        'registration_number' => 'RCCM :number',
        'tax_number' => 'N° contribuable :number',
        'signed_by' => 'Le responsable : :name',
        'generated_at' => 'Document généré le :date',
    ],
];
