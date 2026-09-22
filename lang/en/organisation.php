<?php

return [
    'title' => 'Workspace and brand',
    'description' => 'Legal identity, brand and public address of your organisation',

    'tabs' => [
        'legal' => 'Legal identity',
        'brand' => 'Brand',
        'subdomain' => 'Public address',
    ],

    'sections' => [
        'legal' => [
            'title' => 'Legal identity',
            'description' => 'These details appear on receipts, tickets and PDF exports.',
        ],
        'brand' => [
            'title' => 'Brand colours',
            'description' => 'They apply to the guest journey, the ticket and messages. The back office stays neutral.',
        ],
        'subdomain' => [
            'title' => 'Public address',
            'description' => 'The address your guests use to reach your events.',
        ],
    ],

    'fields' => [
        'display_name' => 'Display name',
        'display_name_hint' => 'The name your guests see, if it differs from the registered name.',
        'legal_name' => 'Registered name',
        'legal_form' => 'Legal form',
        'representative_name' => 'Authorised signatory',
        'representative_name_hint' => 'Their name appears under the signature on receipts.',
        'registration_number' => 'Trade register number',
        'tax_number' => 'Taxpayer number',
        'address' => 'Registered address',
        'city' => 'City',
        'country' => 'Country',
        'email' => 'Contact email',
        'phone' => 'Phone',
        'primary_color' => 'Primary colour',
        'secondary_color' => 'Secondary colour',
        'subdomain' => 'Subdomain',
    ],

    'files' => [
        'section' => 'Brand files',
        'section_description' => 'They appear on invitation links, tickets and receipts. Accepted formats: JPG, PNG and WebP, 5 MB at most.',
        'replace' => 'Replace',
        'choose' => 'Choose a file',
        'remove' => 'Remove',
        'empty' => 'No file',
        'logo' => [
            'label' => 'Square logo',
            'hint' => 'Shown at the top of the registration link and on the ticket.',
        ],
        'banner' => [
            'label' => 'Public link banner',
            'hint' => 'Wide image, at the top of the page your guests see.',
        ],
        'stamp' => [
            'label' => 'Stamp',
            'hint' => 'Applied to receipts and PDF exports.',
        ],
        'signature' => [
            'label' => 'Signatory signature',
            'hint' => 'Applied under the name of the authorised signatory.',
        ],
    ],

    'legal_forms' => [
        'association' => 'Association',
        'ngo' => 'NGO',
        'religious_body' => 'Religious body',
        'foundation' => 'Foundation',
        'cooperative' => 'Cooperative',
        'sole_proprietorship' => 'Sole proprietorship',
        'sarl' => 'Limited liability company',
        'sarlu' => 'Single-member limited liability company',
        'sa' => 'Public limited company',
        'sas' => 'Simplified joint-stock company',
        'sasu' => 'Single-member simplified joint-stock company',
        'gie' => 'Economic interest grouping',
        'public_body' => 'Public body',
        'other' => 'Other',
    ],

    'publishing' => [
        'ready' => 'This organisation can publish a public link.',
        'incomplete' => '{1} 1 detail is still missing before you can publish a public link.|[2,*] :count details are still missing before you can publish a public link.',
        'trial' => 'Trial workspace: fill in your legal identity and public address when you are ready to publish.',
    ],

    'flash' => [
        'legal_updated' => 'Legal identity saved.',
        'brand_updated' => 'Brand saved.',
        'subdomain_updated' => 'Public address saved.',
        'file_updated' => 'File saved.',
        'file_deleted' => 'File removed.',
    ],

    'errors' => [
        'registration_number' => 'The trade register number may only contain letters, digits, hyphens and slashes.',
        'tax_number' => 'The taxpayer number may only contain letters, digits and hyphens.',
        'phone' => 'Enter a valid phone number, including the dialling code.',
        'color' => 'Enter a colour in hexadecimal form, for example #7b1e3a.',
        'subdomain_format' => 'The subdomain may only contain lowercase letters, digits and hyphens, with no hyphen at either end.',
        'subdomain_reserved' => 'This subdomain is reserved by the application. Please choose another one.',
        'file_not_an_image' => 'This file is not an image. Send a JPG, a PNG or a WebP.',
        'file_type' => 'Accepted formats: JPG, PNG and WebP. SVG is refused for security reasons.',
        'file_too_large' => 'The file is larger than 5 MB.',
        'subdomain_frozen' => 'The subdomain can no longer change: a public link has already been handed out to guests.',
        'subdomain_taken' => 'This subdomain is already used by another organisation.',
    ],
];
