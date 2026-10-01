<?php

namespace App\Support\Backup;

use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;

/**
 * L'etat des sauvegardes pour l'ecran de sante technique (README ecran 31), lu sur la destination
 * elle-meme : ce qui s'affiche est ce qui existe reellement sur le disque, pas ce qu'une tache a
 * declare avoir fait. Les criteres de sante sont ceux de `backup.monitor_backups`, les memes que
 * ceux de la tache de surveillance.
 */
class BackupStatus
{
    /**
     * @return array{healthy: bool, lastAt: string|null, lastSizeBytes: int, count: int, totalSizeBytes: int, onApplicationServer: bool, encrypted: bool}
     */
    public static function current(): array
    {
        /** @var BackupDestinationStatus $status */
        $status = BackupDestinationStatusFactory::createForMonitorConfig(app(Config::class)->monitoredBackups)->firstOrFail();

        $destination = $status->backupDestination();
        $reachable = $destination->isReachable();
        $newest = $reachable ? $destination->newestBackup() : null;

        return [
            'healthy' => $status->isHealthy(),
            'lastAt' => $newest?->date()->toISOString(),
            'lastSizeBytes' => (int) ($newest?->sizeInBytes() ?? 0),
            'count' => $reachable ? $destination->backups()->count() : 0,
            'totalSizeBytes' => $reachable ? (int) $destination->usedStorage() : 0,
            // Un disque local est sur le serveur de l'application : la sauvegarde disparait avec
            // lui. L'ecran le dit tant que la destination n'est pas un stockage exterieur.
            'onApplicationServer' => config("filesystems.disks.{$destination->diskName()}.driver") === 'local',
            'encrypted' => filled(config('backup.backup.password')),
        ];
    }
}
