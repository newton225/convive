<?php

use Spatie\Health\Notifications\CheckFailedNotification;
use Spatie\Health\Notifications\Notifiable;
use Spatie\Health\ResultStores\CacheHealthResultStore;

/*
 * Surveillance de l'application (`spatie/laravel-health`, CLAUDE.md, table Spatie). Les controles
 * sont declares dans `App\Providers\HealthServiceProvider`, joues chaque minute par le
 * planificateur (`routes/console.php`) et lus par l'ecran de sante technique de la console.
 */

// Sans adresse d'alerte, aucun courriel ne part : l'etat ne se lit alors que sur l'ecran.
$alertEmail = env('CONVIVE_ALERT_EMAIL');

return [

    /*
     * Le dernier resultat de chaque controle, garde en cache : l'ecran n'a besoin que du plus
     * recent, pas d'un historique en base.
     */
    'result_stores' => [
        CacheHealthResultStore::class => [
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    'notifications' => [
        'enabled' => (bool) env('HEALTH_NOTIFICATIONS_ENABLED', true),

        'notifications' => [
            CheckFailedNotification::class => $alertEmail ? ['mail'] : [],
        ],

        'notifiable' => Notifiable::class,

        // Un controle qui reste en echec ne previent qu'une fois par heure.
        'throttle_notifications_for_minutes' => 60,
        'throttle_notifications_key' => 'health:latestNotificationSentAt:',

        // Un avertissement se lit a l'ecran ; seul un echec ecrit.
        'only_on_failure' => true,

        'mail' => [
            'to' => $alertEmail ?: '',

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Convive'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],
    ],

    'oh_dear_endpoint' => [
        'enabled' => false,
        'always_send_fresh_results' => true,
        'secret' => env('OH_DEAR_HEALTH_CHECK_SECRET'),
        'url' => '/oh-dear-health-check-results',
    ],

    'horizon' => [
        'heartbeat_url' => env('HORIZON_HEARTBEAT_URL'),
    ],

    /*
     * Une adresse exterieure a appeler a chaque passage du planificateur. C'est le seul moyen d'etre
     * prevenu d'un arret complet : un planificateur arrete ne peut pas envoyer sa propre alerte.
     */
    'schedule' => [
        'heartbeat_url' => env('SCHEDULE_HEARTBEAT_URL'),
    ],

    'theme' => 'light',

    'silence_health_queue_job' => true,

    'json_results_failure_status' => 200,

    'secret_token' => env('HEALTH_SECRET_TOKEN'),

];
