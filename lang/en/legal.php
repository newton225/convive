<?php

/*
 * The legal documents of the site (privacy, terms, legal notice). Read server side by
 * `App\Support\LegalDocument`, never sent with the shared translations.
 *
 * The French version is the reference: every sentence describes what the application really does,
 * and a duration or a rule that changes in the code changes here in the same commit.
 */

return [
    'updated' => 'Last updated: :date',
    'contents' => 'Contents',
    'placeholder' => '[to be completed]',
    'not_reviewed' => 'This document is undergoing legal review. It describes how the service really works, but has not yet been checked by a lawyer.',

    'nav' => [
        'title' => 'Legal information',
        'privacy' => 'Privacy',
        'terms' => 'Terms of use',
        'notice' => 'Legal notice',
    ],

    'documents' => [

        'privacy' => [
            'title' => 'Privacy policy',
            'summary' => 'What data :app handles, why, for how long, who can access it, and how you can have it corrected or erased.',
            'sections' => [
                [
                    'title' => 'Who is responsible for your data',
                    'paragraphs' => [
                        ':app is a service published by :editor (:editor_address). It lets organisations, associations, churches, companies, manage the registrations to their events.',
                        'Two situations must be told apart, because the party responsible for your data is not the same.',
                    ],
                    'items' => [
                        'You have a :app account (you are a member of an organisation): :editor is the controller of your account data, of billing and of the site.',
                        'You are a guest at an event: the organisation inviting you is the controller of your data. :editor handles it on its behalf, as a processor, following its instructions. For any request, contact that organisation first.',
                    ],
                ],
                [
                    'title' => 'The data we handle',
                    'paragraphs' => [
                        'We only ask for what the service needs to work.',
                    ],
                    'items' => [
                        'Account: name, email address, phone number, password (stored hashed, never in clear), two-factor authentication key, and the list of your connected devices (IP address, browser, date of last activity).',
                        'Organisation: name, legal identity (company name, legal form, trade register number, tax number, address), logo, banner, stamp and signature, payout accounts (channel, number, holder), members and their profiles.',
                        'Guests: name, phone number, email address when provided, unit, name and unit of companions, amount due, and the payment proof submitted (screenshot, transaction reference, channel, free note).',
                        'Tickets and entry: the ticket issued, its QR code, and the scans recorded at the entrance (date, time, agent).',
                        'Subscription: the plan, the invoices and the payment status. Card payment is handled by Stripe: :app neither receives nor stores your card number.',
                        'Log: every sensitive action is recorded with its author, the date, the IP address and the browser used.',
                        'Audience measurement: only on the home page and the featured events page, and only if you accept it.',
                    ],
                ],
                [
                    'title' => 'Why we handle it',
                    'items' => [
                        'To provide the service: create an account, manage events, registrations, payment proofs, tickets and entry control. This is the performance of the contract between us and the organisation.',
                        'To send the messages tied to a registration: invitation card, reminders, verification code, ticket. They go out by WhatsApp and, when an address was provided, by email.',
                        'To protect the service and the money of organisations: two-factor authentication, action log, rate limiting, detection of doubtful proofs. This is our legitimate interest and that of the organisations.',
                        'To invoice the subscription and keep our accounts: this is a legal obligation.',
                        'To measure traffic on the commercial site: only with your consent.',
                    ],
                    'paragraphs_after' => [
                        'We sell no data, we run no targeted advertising, and no decision about you is taken in a fully automated way: a payment proof is always approved or rejected by a person of the organisation.',
                    ],
                ],
                [
                    'title' => 'Who can access your data',
                    'items' => [
                        'The members of the organisation, according to their profile: a front desk agent does not see what a treasurer sees.',
                        'The team of :editor has no access to the content of an organisation. It only enters when an owner opens a support access: named, read-only, limited to 24 hours, revocable at any time, and every page viewed is written to the log of the organisation.',
                        'Our hosting provider: :host.',
                        'Stripe, for the payment of the subscription.',
                        'Our email delivery provider (:mail_provider) and WhatsApp (Meta), to carry the messages.',
                        'Google Analytics, only if you accepted audience measurement.',
                        'The authorities, when the law requires it.',
                    ],
                    'paragraphs_after' => [
                        'The data of an organisation is kept in a database of its own, physically separate from that of other organisations.',
                    ],
                ],
                [
                    'title' => 'Transfers outside Côte d’Ivoire',
                    'paragraphs' => [
                        'Some of these providers handle data outside Côte d’Ivoire. These transfers are limited to what the service requires and governed by the safeguards set by Ivorian law no. 2013-450 on the protection of personal data and, for people located in the European Union, by the General Data Protection Regulation (GDPR).',
                    ],
                ],
                [
                    'title' => 'How long we keep it',
                    'items' => [
                        'Account: until you delete it.',
                        'Organisation and all its content (events, guests, proofs, tickets): as long as the organisation exists. The organisation decides how long the data of its guests is kept.',
                        'Unfinished registrations (no approved proof): deleted at the deadline set by the organiser, with the screenshot of any proof submitted.',
                        'Deleted organisation: invisible immediately, then erased for good 30 days later.',
                        'Deleted event: erased for good 30 days later.',
                        'Action log: 24 months.',
                        'Login session: 12 hours at most, after which you must sign in again.',
                        'Backup copies: one year at most.',
                        'Invoices and accounting records: for the period required by accounting regulations.',
                    ],
                ],
                [
                    'title' => 'Deletion and erasure',
                    'paragraphs' => [
                        'Only an owner can delete an organisation. It then disappears right away for all its members, and its public links stop working.',
                        'For 30 days, nothing is erased yet: the team of :editor can restore the organisation at the request of an owner, with its members and its events. All owners are told by email of the deletion and of the erasure date.',
                        'After 30 days, the database of the organisation, its files and the payment proofs of its guests are erased from our live systems. This erasure is final.',
                        'Copies may remain for up to one year in our backups. They are only used to bring the service back after a failure, are not consulted otherwise, and disappear by themselves when that period expires.',
                        'When you delete your account, your personal space follows the same path. You cannot delete your account while you are the last owner of an organisation: hand it over or delete it first.',
                    ],
                ],
                [
                    'title' => 'How we protect it',
                    'items' => [
                        'Encrypted connection (HTTPS) across the whole service.',
                        'Two-factor authentication required for the profiles that handle money.',
                        'A separate database for each organisation.',
                        'Uploaded images are re-encoded to strip hidden information (GPS position, device model).',
                        'Files can only be reached through signed links that expire.',
                        'Any change of payout account is delayed by 24 hours and reported to everyone in charge.',
                        'Every sensitive action is written to a log that nobody can alter.',
                    ],
                    'paragraphs_after' => [
                        'No system is infallible. Should a data breach concerning you occur, we would inform the organisations concerned and the data protection authority within the time limits set by law.',
                    ],
                ],
                [
                    'title' => 'Your rights',
                    'paragraphs' => [
                        'You may ask to access your data, to have it corrected or erased, object to its processing on legitimate grounds, and withdraw at any time a consent you gave.',
                    ],
                    'items' => [
                        'You have an account: most of these actions are done from your settings. For the rest, write to :privacy_email.',
                        'You are a guest: contact the organisation that invited you. If you write to us, we pass your request on to it.',
                    ],
                    'paragraphs_after' => [
                        'We answer within one month. If the answer does not satisfy you, you may refer the matter to the Autorité de Régulation des Télécommunications/TIC de Côte d’Ivoire (ARTCI), the data protection authority, or to the data protection authority of your country of residence.',
                    ],
                ],
                [
                    'title' => 'Cookies and storage in your browser',
                    'items' => [
                        'Session cookie and form protection cookie: essential to sign you in and secure your actions. They serve no other purpose.',
                        'Display preferences (light or dark theme, collapsed menu, language): saved in your browser for your comfort.',
                        'Entry control: scans made offline are kept on the agent’s device until they are sent, then erased.',
                        'Audience measurement (Google Analytics): no cookie is set before you agree. You can change your mind at any time from the “Audience measurement” link at the bottom of the site. It only covers the home page and the featured events page.',
                    ],
                ],
                [
                    'title' => 'Minors',
                    'paragraphs' => [
                        'Creating an account is reserved for adults. When an event welcomes minors, it is up to the organisation to obtain the agreement of their parents or guardians.',
                    ],
                ],
                [
                    'title' => 'Changes to this policy',
                    'paragraphs' => [
                        'This policy evolves with the service. The date of the last update appears at the top of the page. A significant change is announced to organisations by email before it takes effect.',
                    ],
                ],
                [
                    'title' => 'Contact us',
                    'paragraphs' => [
                        'For any question about your data: :privacy_email. By post: :editor, :editor_address.',
                    ],
                ],
            ],
        ],

        'terms' => [
            'title' => 'Terms of use',
            'summary' => 'The rules that apply between :editor and the organisations that use :app.',
            'sections' => [
                [
                    'title' => 'Purpose and acceptance',
                    'paragraphs' => [
                        'These terms govern the use of :app, a service published by :editor. By creating an account, you accept them, together with the privacy policy. If you act for an organisation, you declare that you have the authority to bind it.',
                    ],
                ],
                [
                    'title' => 'Definitions',
                    'items' => [
                        'Organisation: the association, church, company or person that opens a space on :app to manage its events.',
                        'Owner: the member who holds every right over the organisation.',
                        'Member: any person added to an organisation, with the profile the owner gives them.',
                        'Guest: the person who registers for an event through the public link.',
                    ],
                ],
                [
                    'title' => 'Account and security',
                    'items' => [
                        'The information given at sign-up must be accurate and kept up to date.',
                        'Your password and your second authentication factor are personal. You are responsible for what is done from your account.',
                        'Two-factor authentication is required for the profiles that handle money (payout accounts, proof approval, billing).',
                        'Tell us without delay if you suspect unauthorised access.',
                    ],
                ],
                [
                    'title' => 'The service',
                    'paragraphs' => [
                        ':app lets you publish an event, receive registrations, check payment proofs, assign tables, issue tickets and control entries.',
                        'We do our best to keep the service available at all times, without being able to guarantee it: it may be interrupted for maintenance or for an outside cause. We keep improving the service; a feature may be changed or withdrawn.',
                    ],
                ],
                [
                    'title' => 'Guests’ money does not go through :app',
                    'paragraphs' => [
                        'Guests pay their contribution directly into the payout accounts of the organisation (mobile money, bank transfer, cash). :editor neither receives, holds nor pays out any of these sums.',
                        'The proof submitted by a guest is a declaration: it is up to the organisation to check, on its own account, that the money did arrive before approving. The signals displayed (reference already seen, screenshot already submitted) are an aid, not a guarantee.',
                        'The organisation alone is responsible for the payout accounts it displays, the sums it collects, the refunds it owes its guests and the running of its event.',
                    ],
                ],
                [
                    'title' => 'Plans, trial and payment of the subscription',
                    'items' => [
                        'The plans, their limits and their prices are shown on the site and in the subscription screen. A free plan exists; paid plans are billed monthly.',
                        'A trial period may be offered. Its conditions are shown in the application.',
                        'Payment is made online, by card, through Stripe. The subscription renews every month until it is cancelled, which is possible at any time: cancellation takes effect at the end of the period already paid.',
                        'When a payment fails, a reminder is sent after 3 days. After 10 days, the organisation is suspended: its space becomes read-only and its public links no longer accept registrations. Tickets already issued remain valid at the entrance.',
                        'A price or a limit may change. Subscribed organisations are told in advance; the change only applies to the following period.',
                        'Unless the law provides otherwise, a period that has started is not refunded.',
                    ],
                ],
                [
                    'title' => 'What the organisation undertakes to respect',
                    'items' => [
                        'To use the service only for lawful events, and to publish nothing illegal, misleading or infringing the rights of others.',
                        'To publish an event only after filling in its accurate legal identity.',
                        'To inform its guests of how their data is used, to collect only what is necessary, and to answer their requests.',
                        'To carry out the formalities it owes to the data protection authority.',
                        'Not to try to bypass the limits of the service, to access the data of another organisation, or to disrupt its operation.',
                    ],
                ],
                [
                    'title' => 'Guest data: who does what',
                    'paragraphs' => [
                        'The organisation is the controller of the data of its guests. :editor acts as a processor: it only handles this data to provide the service, following the instructions the organisation gives by using the application.',
                    ],
                    'items' => [
                        ':editor undertakes to keep this data confidential and not to use it for its own purposes.',
                        'Its staff only access it through a support access opened by an owner, read-only and logged.',
                        'It puts in place the security measures described in the privacy policy, and tells the organisation without delay of any data breach concerning it.',
                        'It uses the providers listed in the privacy policy, and remains responsible for their involvement.',
                        'At the end of the relationship, the data is erased according to the rules of the “Deletion” section.',
                    ],
                ],
                [
                    'title' => 'Events announced on the site',
                    'paragraphs' => [
                        'An organisation may choose to announce an event on the public page of featured events. This is a voluntary choice, which it may withdraw at any time. :editor may withdraw an announcement that breaches these terms; the reason is passed on to the organisation.',
                    ],
                ],
                [
                    'title' => 'Intellectual property',
                    'paragraphs' => [
                        'The service, its code, its brand and its appearance belong to :editor. The organisation receives a non-exclusive, non-transferable right of use for as long as it uses the service.',
                        'The organisation remains the owner of its content (texts, logos, visuals, data). It authorises us to host and display it only as far as the service requires, and warrants that it has the right to use it.',
                    ],
                ],
                [
                    'title' => 'Suspension, termination and deletion',
                    'paragraphs' => [
                        'An owner may delete their organisation at any time from its settings. :editor may suspend or close an organisation in case of non-payment, of serious breach of these terms or at the request of an authority; the reason is communicated to it.',
                        'A deleted organisation can no longer be reached by its members. It remains restorable for 30 days, at the request of an owner, after which its data is erased for good. Copies may remain for up to one year in the backups, which are only used to bring the service back after a failure.',
                        'It is up to the organisation to export its data before deletion: registration lists, reports, receipts.',
                    ],
                ],
                [
                    'title' => 'Liability',
                    'paragraphs' => [
                        ':editor must bring to the service the care of a diligent professional. It is not liable for indirect damage (loss of income, of customers, of reputation), nor for the consequences of misuse of the service, of inaccurate information supplied by the organisation, or of a payment between a guest and the organisation.',
                        'To the extent permitted by law, its liability is capped at the amount paid by the organisation over the twelve months preceding the event giving rise to it.',
                        'Neither party is liable for a failure caused by force majeure: network or power outage, failure of an operator, decision of an authority.',
                    ],
                ],
                [
                    'title' => 'Changes to these terms',
                    'paragraphs' => [
                        'These terms may evolve. Organisations are told by email of a significant change at least 30 days before it takes effect. Continuing to use the service after that date amounts to acceptance; otherwise, the organisation may terminate and delete its space.',
                    ],
                ],
                [
                    'title' => 'Governing law and disputes',
                    'paragraphs' => [
                        'These terms are governed by Ivorian law and by the OHADA Uniform Acts. In case of a dispute, the parties first seek an amicable solution. Failing agreement within 30 days, the dispute is brought before the competent courts of Abidjan, subject to the protective rules a consumer benefits from.',
                    ],
                ],
                [
                    'title' => 'Contact us',
                    'paragraphs' => [
                        ':editor, :editor_address. Email: :contact_email.',
                    ],
                ],
            ],
        ],

        'notice' => [
            'title' => 'Legal notice',
            'summary' => 'Who publishes and who hosts :app.',
            'sections' => [
                [
                    'title' => 'Publisher',
                    'items' => [
                        'Name: :editor',
                        'Legal form: :legal_form',
                        'Share capital: :share_capital',
                        'Trade register (RCCM): :registration_number',
                        'Tax number: :tax_number',
                        'Registered office: :editor_address',
                        'Email: :contact_email',
                        'Publication director: :publication_director',
                    ],
                ],
                [
                    'title' => 'Hosting provider',
                    'paragraphs' => [
                        ':host, :host_address.',
                    ],
                ],
                [
                    'title' => 'Content published by organisations',
                    'paragraphs' => [
                        'Event pages are written by the organisations, which are responsible for them. To report unlawful content, write to :contact_email with the address of the page and the reason: it is examined and, where appropriate, removed.',
                    ],
                ],
                [
                    'title' => 'Intellectual property',
                    'paragraphs' => [
                        'The :app brand, the site and the software are the property of :editor. Any reproduction without authorisation is forbidden.',
                    ],
                ],
                [
                    'title' => 'Personal data',
                    'paragraphs' => [
                        'The handling of personal data is described in the privacy policy. Contact: :privacy_email.',
                    ],
                ],
            ],
        ],
    ],
];
