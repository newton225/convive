<?php

return [
    'title' => 'Post-event report',
    'description' => 'Attendance, no-shows, revenue and entrance control.',

    'cards' => [
        'confirmed' => 'Confirmed registrations',
        'present' => 'Present',
        'absent' => 'Absent',
        'collected' => 'Revenue',
        'seats' => '{0} No seats|{1} 1 seat|[2,*] :count seats',
        'average_scan_interval' => 'Average control time',
        'seconds' => '{0} Under a second|{1} 1 second|[2,*] :count seconds',
        'not_available' => 'Not available (fewer than two accepted tickets)',
    ],

    'units' => [
        'title' => 'Attendance and revenue by unit',
        'unit' => 'Unit',
        'confirmed' => 'Confirmed',
        'present' => 'Present',
        'collected' => 'Revenue',
        'empty' => 'No confirmed registration yet.',
        'note' => 'Each registration is counted under its main participant\'s unit. Control time is the average gap between two accepted tickets, a throughput indicator at the entrance.',
    ],

    'actions' => [
        'export_pdf' => 'Export as PDF',
    ],

    'pdf' => [
        'title' => 'Post-event report',
        'registration_number' => 'Company reg. :number',
        'tax_number' => 'Tax no. :number',
        'signed_by' => 'Signed by: :name',
        'generated_at' => 'Document generated on :date',
    ],
];
