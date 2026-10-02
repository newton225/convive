<?php

return [
    'title' => 'Support access',
    'description' => 'Give the Convive team temporary access to your space, so they can help you with a specific issue.',

    'rules' => [
        'title' => 'What an access allows',
        'read_only' => 'Read only: support sees your space and cannot change anything.',
        'limited' => '24 hours at most, and you can revoke it at any time.',
        'named' => 'Named: it is open to a single person of the Convive team.',
        'logged' => 'Every page viewed is listed below and in your audit log.',
        'banner' => 'While the access is open, a banner reminds every member.',
    ],

    'active' => [
        'title' => 'Current access',
        'summary' => ':operator can view your space until :expires.',
        'granted' => 'Opened by :granted_by on :date.',
        'reason' => 'Reason',
        'revoke' => 'Revoke the access',
        'views' => 'Pages viewed',
        'views_empty' => 'No page viewed yet.',
    ],

    'pages' => [
        'events' => 'Events',
        'registrations' => 'Registrations',
        'proofs' => 'Proofs to check',
        'audit' => 'Action history',
        'organisation' => 'Space and brand',
        'dashboard' => 'Dashboard',
        'seating' => 'Seating plan',
        'reports' => 'Report',
        'other' => 'Other page',
    ],

    'grant' => [
        'title' => 'Open an access',
        'none_active' => 'No access is open right now.',
        'operator' => 'Person of the Convive team',
        'operator_placeholder' => 'Choose a person',
        'no_operator' => 'Nobody from the Convive team is visible at the moment. Tell the team above: the person helping you will make themselves available, and their name will appear here.',
        'duration' => 'Duration',
        'hours' => '{1} 1 hour|[2,*] :count hours',
        'reason' => 'Why are you opening this access?',
        'reason_hint' => 'One or two sentences: the problem and the event concerned. The member of the Convive team reads this reason before entering, and it stays in your history. Do not put a password or an account number in it.',
        'submit' => 'Open the access',
        'confirm_title' => 'Open an access for :operator?',
        'confirm_description' => 'This member of the Convive team will be able to read your events, registrations, proofs and audit log for :duration. They cannot change anything, and you can revoke the access at any time.',
        'one_at_a_time' => 'One access at a time: revoke the current one to open another.',
    ],

    'request' => [
        'title' => 'Tell the Convive team',
        'intro' => 'Nobody from the Convive team is visible to receive an access. Say what brings you: the whole support team is told, and the person helping you writes to you as soon as they are available. This request opens no access.',
        'reason' => 'What do you need?',
        'reason_hint' => 'One or two sentences: the problem and the event concerned. Do not put a password or an account number in it.',
        'submit' => 'Tell the team',
        'sent' => 'Request sent on :date. The Convive team has been told; you will get an email as soon as someone makes themselves available.',
        'taken' => ':operator, from the Convive team, took your request. You now have to open the access to them below.',
        'your_message' => 'Your message',
        'cancel' => 'Cancel the request',
    ],

    'history' => [
        'title' => 'Past accesses',
        'empty' => 'No access has been opened yet.',
        'columns' => [
            'operator' => 'Person',
            'reason' => 'Reason',
            'granted_at' => 'Opened on',
            'ended_at' => 'Closed on',
            'end_reason' => 'End',
            'views' => 'Pages viewed',
            'closing_note' => 'Support’s conclusion',
        ],
        'end_reasons' => [
            'expired' => 'Expired',
            'revoked' => 'Revoked',
            'finished' => 'Closed by support',
        ],
    ],
    'errors' => [
        'note' => 'Write in one sentence what you found (at least 10 characters).',
        'reason' => 'Say in one sentence why you are opening this access (at least 10 characters).',
        'request_reason' => 'Say in one sentence what you need (at least 10 characters).',
        'already_requested' => 'A request is already waiting. The Convive team has been told.',
        'already_open' => 'An access is already open. Revoke it before opening another one.',
        'operator' => 'Choose a member of the Convive team.',
        'duration' => 'Choose one of the durations offered, 24 hours at most.',
        'read_only' => 'A support access is read only: this action is not allowed.',
    ],

    'flash' => [
        'finished' => 'The access to :organisation is closed. The organisation has been told.',
        'opened' => 'Access opened for :operator.',
        'revoked' => 'Access revoked.',
        'requested' => 'The Convive team has been told. You will get an email as soon as someone makes themselves available.',
        'request_cancelled' => 'Request cancelled.',
    ],

    'banner' => [
        'member' => ':operator, from the Convive team, can view this space in read-only mode until :expires.',
        'viewing' => 'Support access: you are viewing :organisation in read-only mode until :expires. Every page you view is logged.',
        'manage' => 'Manage the access',
    ],
    'finish' => [
        'button' => 'I am done',
        'title' => 'Close the access to :organisation?',
        'description' => 'You will no longer be able to view this space: the organisation would have to open an access for you again. The owners are told and read your note.',
        'note' => 'What you found',
        'note_hint' => 'In one or two sentences: the cause of the problem, or what remains to be done. The note is read by the organisation: no guest data, no internal information.',
        'submit' => 'Close the access',
    ],
    'mail' => [
        'opened' => [
            'subject' => ':organisation opened a support access for you',
            'intro' => ':granted_by opened a read-only access for you to the space of :organisation.',
            'reason' => 'Reason: :reason',
            'until' => 'The access closes on :expires.',
            'action' => 'Open the console',
            'outro' => 'Every page you view is written to the organisation’s audit log. When you are done, close the access with “I am done”.',
        ],
        'requested' => [
            'subject' => ':organisation is asking support for help',
            'intro' => ':requested_by, from :organisation, wants to open their space to the Convive team, and nobody is visible there at the moment.',
            'reason' => 'Their request: :reason',
            'action' => 'Open the console',
            'outro' => 'Take the request from the console: you will appear in the list of the organisation, which can then open the access to you. Nothing is open until then.',
        ],
        'taken' => [
            'subject' => ':operator, from the Convive team, can help you',
            'intro' => ':operator took the help request of :organisation and now appears in the list of people an access can be opened to.',
            'next' => 'No access is open yet: it is yours to open, for the duration you choose.',
            'action' => 'Open the access',
        ],
        'ended' => [
            'subject' => 'The support access to :organisation is closed',
            'reasons' => [
                'finished' => ':operator, from the Convive team, is done and closed their access to :organisation.',
                'revoked' => 'The access of :operator, from the Convive team, to :organisation was revoked.',
                'expired' => 'The access of :operator, from the Convive team, to :organisation reached its term.',
            ],
            'views' => '{0} No page was viewed.|{1} 1 page was viewed.|[2,*] :count pages were viewed.',
            'note' => 'Support’s conclusion: :note',
            'action' => 'See the detail',
        ],
    ],
];
