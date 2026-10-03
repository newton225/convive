<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * WhatsApp (decision du proprietaire du projet, 2026-10-03) : `twilio` pour commencer, `meta`
     * (API Cloud, en direct) ensuite ; vide, les messages sont seulement ecrits au journal.
     *
     * `templates` : pour chaque sorte de message, le modele approuve qui lui correspond chez le
     * service choisi, son identifiant de contenu chez Twilio (« HX... ») ou son nom chez Meta.
     * WhatsApp exige un modele hors des 24 heures qui suivent un message de la personne ; sans
     * modele declare, le texte part tel quel. Les textes a faire approuver et l'ordre de leurs
     * variables sont dans `docs/whatsapp-modeles.md`.
     */
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER'),
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            // Le numero d'envoi WhatsApp, au format international : celui du bac a sable de
            // Twilio pour les essais, le votre ensuite.
            'from' => env('TWILIO_WHATSAPP_FROM'),
        ],
        'meta' => [
            'access_token' => env('WHATSAPP_META_ACCESS_TOKEN'),
            'phone_number_id' => env('WHATSAPP_META_PHONE_NUMBER_ID'),
            'api_version' => env('WHATSAPP_META_API_VERSION', 'v23.0'),
            'template_language' => env('WHATSAPP_META_TEMPLATE_LANGUAGE', 'fr'),
        ],
        'templates' => [
            'invitation_card' => env('WHATSAPP_TEMPLATE_INVITATION_CARD'),
            'proof_reminder' => env('WHATSAPP_TEMPLATE_PROOF_REMINDER'),
            'ticket_reminder' => env('WHATSAPP_TEMPLATE_TICKET_REMINDER'),
            'registration_cancelled' => env('WHATSAPP_TEMPLATE_REGISTRATION_CANCELLED'),
            'refund_sent' => env('WHATSAPP_TEMPLATE_REFUND_SENT'),
            'payment_account_changed' => env('WHATSAPP_TEMPLATE_PAYMENT_ACCOUNT_CHANGED'),
        ],
    ],

    /*
     * SMS : le code de verification du telephone seulement (decision du proprietaire du projet,
     * 2026-10-03), Meta refusant ce modele en WhatsApp a une entreprise non verifiee. `SMS_DRIVER`
     * vaut `orange` (API SMS d'Orange Cote d'Ivoire) ; vide ou sans identifiants, le journal.
     */
    'sms' => [
        'driver' => env('SMS_DRIVER'),
        'orange' => [
            'client_id' => env('ORANGE_SMS_CLIENT_ID'),
            'client_secret' => env('ORANGE_SMS_CLIENT_SECRET'),
            // Le nom d'expediteur, une fois accorde par Orange (11 caracteres au plus) ; vide, le
            // numero generique d'Orange.
            'sender_name' => env('ORANGE_SMS_SENDER_NAME'),
        ],
    ],

    /*
     * Stripe : le fournisseur de paiement de l'abonnement des organisations (README section 3).
     * Aucun encaissement des participations ne passe par la : voir `SubscriptionBillingGateway`.
     */
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
    ],

    /*
     * Google Analytics (README, « Mesure d'audience ») : sur les pages commerciales seulement,
     * apres consentement du visiteur. Sans identifiant, rien n'est charge et la CSP reste fermee
     * a Google : c'est l'etat voulu en local et en test.
     */
    'google_analytics' => [
        'measurement_id' => env('GOOGLE_ANALYTICS_ID'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
