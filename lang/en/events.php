<?php

return [
    'title' => 'My events',
    'description' => 'Create an event, set its seats and deadlines, then hand out its public link.',
    'duplicate_name' => ':name (copy)',
    'empty' => 'No event yet. Create the first one.',

    'statuses' => [
        'draft' => 'Draft',
        'open' => 'Open',
        'ongoing' => 'Ongoing',
        'closed' => 'Closed',
    ],

    'steps' => [
        'identity' => 'Identity',
        'seating' => 'Seats and price',
        'deadlines' => 'Deadlines',
    ],

    'sections' => [
        'identity' => 'Name, date and venue of the event.',
        'seating' => 'Capacity is the sum of the seats of every table: the room plan is what counts.',
        'deadlines' => 'Deadline, purge, hold duration and invitation sending.',
    ],

    'fields' => [
        'name' => 'Event name',
        'name_placeholder' => 'For example: Gala dinner 2026',
        'subtitle' => 'Subtitle',
        'starts_at' => 'Start date and time',
        'ends_at' => 'End date and time (optional)',
        'entry_opens_minutes_before' => "Doors open (minutes before the event starts)",
        'entry_grace_minutes' => "Grace after the event ends (minutes)",
        'venue' => 'Venue',
        'venue_address' => 'Address',
        'seats_at_tables' => 'Guests are seated at tables',
        'free_seats' => 'Number of seats',
        'venue_map_url' => 'Location on a map',
        'venue_map_url_placeholder' => 'Google Maps link, or coordinates "5.3364, -4.0267"',
        'primary_color' => 'Primary colour',
        'secondary_color' => 'Secondary colour',
        'override_colors' => 'Customise this event\'s colours',
        'override_colors_hint' => "Without customisation, the organisation's colours apply.",
        'visual' => 'Event visual',
        'table_count' => 'Number of tables',
        'seats_per_table' => 'Seats per table',
        'price_per_person' => 'Price per person (CFA francs)',
        'price_categories' => 'Available prices',
        'price_category_name' => 'Price name',
        'price_category_price' => 'Price per person (CFA francs)',
        'price_category_quota' => 'Limit seats to',
        'companion_limit' => 'Companion limit',
        'registration_deadline' => 'Registration deadline',
        'purge_at' => 'Purge of unfinished registrations',
        'invitations_send_at' => 'Invitation sending',
        'hold_duration_minutes' => 'Hold duration (minutes)',
        'payment_accounts' => 'Payment accounts offered',
        'table_groups' => 'Tables in the room',
    ],

    'table_groups' => [
        'tables' => 'tables of',
        'seats' => 'seats',
        'add' => 'Add tables of another size',
        'remove' => 'Remove this group of tables',
        'total' => '{0} No seat yet|{1} :tables table, 1 seat in total|[2,*] :tables tables, :count seats in total',
        'empty' => 'No table yet: add a group to set the capacity.',
    ],

    'help' => [
        'ends_at' => 'Optional. Without an end, the ticket stays valid for 24 hours after the start.',
        'entry_opens_minutes_before' => "Optional. Empty: a ticket opens the door at any time before the start. Otherwise it only works from this many minutes before the start, offline too.",
        'entry_grace_minutes' => "For how many minutes after the end (or 24 hours after the start when no end is given) a ticket still opens the door. 30 by default, 0 for no grace.",
        'venue' => 'The name of the place, as your guests know it: "Hôtel Ivoire, Palmiers room", "Palais de la Culture". It is required to publish the event.',
        'venue_address' => 'Optional. Where to find this place: street, area, city or a landmark, for example "Boulevard Latrille, Cocody, Abidjan". It is shown under the venue name. For directions, use the map location just below.',
        'seats_at_tables' => 'Tick if your guests are spread over tables. Untick for a gathering without tables, such as an outdoor event: you then only enter the number of seats, and no table is assigned.',
        'venue_map_url' => 'Optional. In Google Maps, tap the place, then "Share", and paste the link here; coordinates work too. Your guests will see a "Get directions" button. Accepted links: Google Maps, Apple Maps, OpenStreetMap, Waze.',
        'primary_color' => 'The dominant colour of this event’s guest journey: the “Register” button, date, seat gauge, steps and email header. It applies from the public link through to the ticket.',
        'secondary_color' => 'A quieter supporting colour: the action button in emails sent to guests, and the glow of the banner when the event has no visual.',
        'table_groups' => 'Describe the room in groups of tables of the same size, for example 3 tables of 12 then 20 tables of 8. The event capacity is the sum of the seats of every table, and a given table can then be adjusted in the seating plan. A guest and their companions always sit at the same table.',
        'price_per_person' => 'Amount in CFA francs, with no decimals; 0 for a free event. Each companion pays this price too: the guest pays the price times the number of people registered.',
        'price_categories' => 'Add the available prices. Each person chooses one; the seat limit is required and counts people in that category.',
        'companion_limit' => 'Maximum number of people a guest can register with them (10 at most). Each one takes a seat and gets their own ticket.',
        'payment_accounts' => 'The accounts your guests pay into. You create them under Organisation, Payment accounts. A new or changed account only shows after a 24-hour security delay.',
        'registration_deadline' => 'After this date, the public link no longer accepts registrations. Registrations already made carry on as usual.',
        'purge_at' => 'On this date, unfinished registrations (no proof, expired, or with a rejected proof) are deleted and their seats released. Approved registrations are never touched.',
        'invitations_send_at' => 'When invitation cards are sent, by WhatsApp and email, to every approved registration. A registration approved after this date gets its card right away.',
        'hold_duration_minutes' => 'Time the guest has to upload their payment proof. Their seats are held meanwhile, then go back to the pool. 10 minutes by default.',
    ],

    'payment_accounts_not_needed' => 'All your prices are free: no payment is expected, so you can publish without a payment account.',

    'venue_map' => [
        'preview' => 'Test this link',
        'host' => 'Opens :host in a new tab, as your guests will see it.',
    ],

    'price_categories' => [
        'default_name' => 'Standard price',
        'add' => 'Add a price',
        'remove' => 'Remove the :name price',
        'minimum' => 'The public event page will show “from :price”.',
        'free' => 'Free',
        'free_hint' => 'A price of 0 means free.',
        'locked' => 'Already chosen: the name and amount no longer change. The quota can still change.',
        'quotas_exceed' => 'The quotas add up to :total, more than the room (:capacity seats): saving is refused while their sum exceeds the room.',
    ],

    'visual' => [
        'hint' => "Poster or photo specific to this event. Without one, the organisation's banner applies.",
        'choose' => 'Choose a visual',
        'replace' => 'Replace',
        'remove' => 'Remove',
        'remove_confirm' => [
            'title' => 'Remove this event’s visual?',
            'description' => 'The organisation’s banner will replace it on the public link. To bring it back, you will have to upload it again.',
            'confirm' => 'Remove the visual',
        ],
        'empty' => 'No visual',
    ],

    'summary' => [
        'capacity' => '{0} No seat|{1} 1 seat|[2,*] :count seats',
        'no_date' => 'Date to be set',
        'public_link' => 'Public link',
    ],

    'actions' => [
        'create' => 'New event',
        'edit' => 'Edit event',
        'publish' => 'Hand out the public link',
        'announce' => 'Announce on the showcase',
        'withdraw_announcement' => 'Withdraw from showcase',
        'close' => 'Close the event',
        'duplicate' => 'Duplicate',
        'delete' => 'Delete event',
        'copy_link' => 'Copy the link',
        'proofs' => 'Proofs',
        'claims' => 'Claims',
        'seating' => 'Seating plan',
        'ticket_template' => 'Ticket template',
        'scan' => 'Scan',
        'registrations' => 'Registrations',
        'report' => 'Report',
        'reconciliation' => 'Reconciliation',
    ],

    'card' => [
        'fill_label' => 'Filled',
        'fill_value' => ':occupied / :capacity seats',
        'collected' => 'Collected',
        'proofs_to_check' => '{1} 1 proof to check|[2,*] :count proofs to check',
        'open_claims' => '{1} 1 claim to handle|[2,*] :count claims to handle',
        'draft_hint' => 'Draft: finish the details, then publish the link to open registrations.',
        'continue' => 'Continue preparing',
        'check_proofs' => '{1} Check the proof|[2,*] Check the :count proofs',
        'open_scan' => 'Open the scanner',
        'view_report' => 'View the report',
        'open' => 'Open the event',
        'more_actions' => 'More actions for :name',
        'group_follow' => 'Follow-up',
        'group_day' => 'On the day',
        'group_event' => 'Event',
        'link_copied' => 'Public link copied.',
        'link_copy_failed' => 'Could not copy the link: open the event to get it.',
    ],

    'filters' => [
        'label' => 'Filter events',
        'active' => 'Ongoing and upcoming',
        'closed' => 'Closed',
        'all' => 'All',
        'empty' => 'No event in this category.',
    ],

    'search' => [
        'label' => 'Search for an event',
        'placeholder' => 'Name or venue',
        'empty' => 'No event matches “:search” in this category.',
    ],

    'badges' => [
        'published' => 'Link handed out',
        'not_ready' => 'Not publishable yet',
        'announced' => 'On the showcase',
    ],

    'publishing' => [
        'unsaved' => 'Save your changes first: publishing now would publish the previous version.',
        'ready' => 'This event can be published.',
        'blocked' => 'Before publishing, still missing: :items.',
        'changes_not_notified' => 'Guests who already registered are not told about a change of date, time or venue: let them know yourself.',
        'capacity_reduced' => 'You are reducing the capacity from :from to :to seats. It cannot go below the :taken seats already taken or held.',
        'frozen_subdomain' => 'Once the link is handed out, the subdomain of the organisation can no longer change.',
    ],

    'companion_limit' => [
        'value' => '{0} No companion|{1} 1 companion|[2,*] :count companions',
    ],

    'hold_duration' => [
        'value' => '{1} 1 minute|[2,*] :count minutes',
        'bounds' => 'Between :min and :max minutes.',
    ],

    'preview' => [
        'title' => 'Showcase preview',
        'description' => 'The card as it will appear on the product site if you announce the event. It follows what you type; the visual is uploaded below.',
        'untitled' => 'Event name',
    ],

    'announcing' => [
        'blocked' => 'To appear on the showcase, still missing: :items.',
        'description' => 'Make this event appear on the product site showcase, to reach an audience that never received the link.',
        'announced' => 'This event appears on the product site showcase.',
    ],

    'flash' => [
        'created' => 'Event created.',
        'updated' => 'Event updated.',
        'published' => 'Public link handed out.',
        'announced' => 'Event announced on the showcase.',
        'announcement_withdrawn' => 'Event withdrawn from the showcase.',
        'closed' => 'Event closed.',
        'duplicated' => 'Event duplicated.',
        'deleted' => 'Event deleted.',
        'visual_updated' => 'Event visual updated.',
        'visual_deleted' => 'Event visual removed.',
    ],

    'missing_publish' => [
        'organisation' => 'the organisation identity (Space and brand page)',
        'capacity' => 'at least one table with seats',
        'date' => 'the start date and time',
        'date_past' => 'a start date still to come',
        'venue' => 'the venue',
        'seats' => 'the number of seats',
        'payment_account' => 'a visible payment account linked to the event',
    ],

    'missing_announce' => [
        'not_published' => 'publishing the link',
        'closed' => 'an open event (it is closed)',
        'past' => 'a start date still to come',
    ],
    'errors' => [
        'price_category_unknown' => 'This price does not belong to this event.',
        'price_category_duplicate' => 'Each price must have a different name.',
        'price_category_quota_below_taken' => 'The quota cannot be lower than the :count seats already taken in this price category.',
        'price_category_quota_above_capacity' => 'The quota cannot exceed the :capacity seats of the room.',
        'starts_at_past_when_published' => 'This event is published or already booked: its start date cannot be in the past, guests may already have paid. To end it, close it.',
        'ends_at_past_when_published' => 'This event is published or already booked: its end date cannot be in the past, guests may already have paid. To end it, close it.',
        'ends_at_before_start' => 'The end date must be after the start date.',
        'ends_at_without_start' => 'Give the start date first to give an end date.',
        'price_category_quotas_above_capacity' => 'The quotas add up to :total, more than the room (:capacity seats): lower them so that their sum fits in the room.',
        'seating_mode_locked' => 'Guests have already registered: the choice with or without tables no longer changes, as it decides what their ticket says.',
        'price_category_locked' => 'This price has already been chosen: its name and amount no longer change, so every guest pays what was announced. Only its quota can still change.',
        'price_category_in_use' => 'A price already chosen by guests or waitlisted people cannot be removed.',
        'venue_map_url' => 'Paste a Google Maps, Apple Maps, OpenStreetMap or Waze link (https), or coordinates such as "5.3364, -4.0267".',
        'deadline_after_event' => 'The registration deadline cannot be later than the start date of the event.',
        'unknown_payment_account' => 'One of the selected payment accounts does not belong to this organisation.',
        'not_ready_to_publish' => 'This event cannot be published yet. Missing: :items.',
        'not_ready_to_announce' => 'This event cannot appear on the showcase yet. Missing: :items.',
        'not_published_yet' => 'This event must be published first before it can appear on the showcase.',
    ],

    'confirm_delete' => [
        'title' => 'Delete event',
        'description' => 'The ":name" event disappears from your list right away, and is erased for good after 30 days.',
    ],

    'templates' => [
        'title' => 'Start from a template',
        'description' => 'Reuses tables, price, companions and payment accounts. Name and dates are left for you to fill in; the ticket template is already shared across the organisation.',
        'meta' => ':tables tables · :price',
        'blank' => 'Blank template',
        'blank_hint' => 'Set everything up manually',
    ],

    'confirm_publish' => [
        'title' => 'Publish the event?',
        'description' => 'Review what guests will see: once the link is handed out, a mistake is harder to fix.',
        'summary' => 'What guests will see',
        'capacity' => 'Capacity',
        'seats' => '{0} No seats|{1} 1 seat|[2,*] :count seats',
        'not_set' => 'Not set',
        'saved_values' => 'Saved values. If you changed the form, save it first.',
        'consequences' => 'What can no longer change',
        'consequence_link' => 'The public link opens for registration and stays the same for good.',
        'consequence_subdomain' => "The organisation's subdomain is locked.",
        'consequence_delete' => 'The event can no longer be deleted, only closed.',
        'consequence_payment_delay' => 'This is your first publication: from now on, any new payment account or change to one will wait 24 hours before becoming visible, even once your events are closed. Another Owner can approve it sooner, never the person who requested it.',
        'acknowledge' => 'I have checked this information and want to open registration.',
    ],

    'confirm_announce' => [
        'title' => 'Announce on the showcase?',
        'description' => '":name" will appear among the featured events on the Convive site, visible to every visitor, with its registration link. You can withdraw it at any time.',
    ],

    'confirm_withdraw_announcement' => [
        'title' => 'Withdraw from the showcase?',
        'description' => '":name" will disappear from the featured events on the Convive site. The public link stays valid and registration continues for those who already have it.',
    ],

    'confirm_close' => [
        'title' => 'Close event',
        'description' => 'Registrations will be closed for ":name". This cannot be undone.',
        'pending_proofs' => 'Pending proofs',
    ],

    'settings' => [
        'link' => 'Settings',
    ],

    // Courriel a l'organisation quand l'editeur retire son annonce de la vitrine.
    'announcement_withdrawn_mail' => [
        'subject' => 'The announcement of “:event” has been withdrawn from the showcase',
        'intro' => 'The Convive team withdrew the announcement of your event “:event” from the site showcase.',
        'reason' => 'Reason: :reason',
        'outro' => 'Your event and its public link are untouched: your guests can still register. To discuss it, reply to this message.',
    ],
];
