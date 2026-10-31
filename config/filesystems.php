<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Fichiers deposes par les locataires : logo, bandeau, cachet, signature. Ranges hors
         * de la racine web et servis uniquement par URL signee expirante (`serve => true`
         * enregistre la route signee et active `temporaryUrl` sur un disque local). Le jour du
         * basculement vers S3, seule cette entree change.
         */
        'tenant_media' => [
            'driver' => 'local',
            'root' => storage_path('app/tenant-media'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/tenant-media',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Videos courtes de presentation d'un evenement (decision du 2026-10-31). Sur le disque du
         * serveur tant que `EVENT_VIDEO_DRIVER` n'est pas `s3` ; avec `s3`, elles vont chez un
         * stockage compatible S3 (Cloudflare R2 : `R2_ENDPOINT`, `R2_BUCKET`, cles d'acces), prive,
         * lu par des URL signees expirantes, jamais par une adresse publique. Le paquet
         * `league/flysystem-aws-s3-v3` est requis pour ce mode.
         */
        'event_videos' => env('EVENT_VIDEO_DRIVER', 'local') === 's3'
            ? [
                'driver' => 's3',
                'key' => env('R2_ACCESS_KEY_ID'),
                'secret' => env('R2_SECRET_ACCESS_KEY'),
                'region' => env('R2_REGION', 'auto'),
                'bucket' => env('R2_BUCKET'),
                'endpoint' => env('R2_ENDPOINT'),
                // L'adresse du compte, jamais celle du bucket : un seul nom de domaine a declarer dans la CSP.
                'use_path_style_endpoint' => true,
                'throw' => true,
                'report' => false,
            ]
            : [
                'driver' => 'local',
                'root' => storage_path('app/event-videos'),
                'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/event-videos',
                'serve' => true,
                'throw' => false,
                'report' => false,
            ],

        /*
         * Preuves de paiement deposees par les invites (SECURITY.md H1) : disque distinct de
         * `tenant_media`, jamais melange aux fichiers de marque. Un vrai domaine separe reste a
         * batir (DNS, certificat) ; en attendant, `PaymentProofServiceProvider` force ce disque
         * a servir en piece jointe (`Content-Disposition: attachment`, `X-Content-Type-Options:
         * nosniff`), jamais en affichage direct dans l'onglet du navigateur sur le domaine
         * principal. La CSP `sandbox` vient deja de `Illuminate\Filesystem\ServeFile`, sans rien
         * a ecrire ici.
         */
        'payment_proofs' => [
            'driver' => 'local',
            'root' => storage_path('app/payment-proofs'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/payment-proofs',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Destination par defaut des sauvegardes (`config/backup.php`). Sur le serveur de
         * l'application, donc sans protection contre sa perte : en production, `BACKUP_DISK`
         * designe un stockage exterieur. Jamais servi par HTTP.
         */
        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/backups'),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
