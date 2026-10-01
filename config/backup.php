<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

/*
 * Sauvegardes (`spatie/laravel-backup`, CLAUDE.md, table Spatie). Une archive contient la base
 * centrale, la base de chaque organisation, les fichiers de marque et les preuves de paiement.
 *
 * La commande a lancer est `convive:backup`, pas `backup:run` : elle prend d'abord une copie
 * coherente de chaque base (`App\Support\Backup\DatabaseSnapshots`), que le paquet archive ensuite
 * comme de simples fichiers. D'ou `databases` vide ci-dessous.
 */

// Sans adresse d'alerte, aucun courriel ne part. Le paquet exige quand meme une adresse valide
// dans sa configuration : celle-ci ne recoit jamais rien, les canaux etant vides.
$alertEmail = env('CONVIVE_ALERT_EMAIL');
$alertChannels = $alertEmail ? ['mail'] : [];

$name = env('BACKUP_NAME', 'convive');
$disk = env('BACKUP_DISK', 'backups');

// Le separateur du systeme, pas une barre oblique ecrite en dur : le paquet compare ces chemins
// a `relative_path` caractere par caractere pour nommer les fichiers dans l'archive.
$storage = storage_path('app').DIRECTORY_SEPARATOR;

return [

    'backup' => [
        'name' => $name,

        'source' => [
            'files' => [
                'include' => [
                    $storage.'backup-databases',
                    $storage.'tenant-media',
                    $storage.'payment-proofs',
                ],

                'exclude' => [],

                'follow_links' => false,

                'ignore_unreadable_directories' => false,

                // L'archive s'ouvre sur `backup-databases/`, `tenant-media/` et `payment-proofs/`,
                // sans le chemin absolu du serveur.
                'relative_path' => storage_path('app'),
            ],

            'databases' => [],
        ],

        'database_dump_compressor' => null,

        'database_dump_file_timestamp_format' => null,

        'database_dump_filename_base' => 'database',

        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,

            'compression_level' => 9,

            'filename_prefix' => '',

            /*
             * Le disque `backups` de `config/filesystems.php` est sur le serveur de l'application :
             * il protege d'une erreur de manipulation, pas de la perte du serveur. En production,
             * `BACKUP_DISK` designe un stockage exterieur (s3 ou equivalent).
             */
            'disks' => [$disk],

            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        // L'archive porte des donnees personnelles et des preuves de paiement : en production,
        // un mot de passe la chiffre (AES-256). Sans lui, elle est lisible par qui la detient.
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'default',

        // Une archive illisible ne se decouvre pas le jour ou l'on en a besoin.
        'verify_backup' => true,

        'tries' => 1,

        'retry_delay' => 0,
    ],

    'notifications' => [
        // Seuls les echecs previennent : un courriel par jour pour dire que tout va bien finit par
        // ne plus etre lu, et l'etat se lit sur l'ecran de sante technique de la console.
        'notifications' => [
            BackupHasFailedNotification::class => $alertChannels,
            UnhealthyBackupWasFoundNotification::class => $alertChannels,
            CleanupHasFailedNotification::class => $alertChannels,
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => $alertEmail ?: 'sauvegardes@example.com',

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

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],

        'webhook' => [
            'url' => '',
        ],
    ],

    'log_channel' => null,

    /*
     * Une sauvegarde est saine si la plus recente a moins de deux jours (la tache est quotidienne,
     * un passage manque se voit le lendemain) et si l'ensemble tient dans la place prevue.
     */
    'monitor_backups' => [
        [
            'name' => $name,
            'disks' => [$disk],
            'health_checks' => [
                MaximumAgeInDays::class => 2,
                MaximumStorageInMegabytes::class => (int) env('BACKUP_MAX_STORAGE_MB', 5000),
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        // Tout pendant 7 jours, puis une par jour pendant 16 jours, une par semaine pendant
        // 8 semaines, une par mois pendant 4 mois, une par an pendant 2 ans. La plus recente
        // n'est jamais supprimee.
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => (int) env('BACKUP_MAX_STORAGE_MB', 5000),
        ],

        'tries' => 1,

        'retry_delay' => 0,
    ],

];
