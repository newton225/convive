<?php

namespace App\Models;

use LogicException;
use Spatie\Activitylog\Models\Activity;

/**
 * Une entree du journal d'audit (SECURITY.md M6), declaree comme `activity_model` du paquet
 * (`config/activitylog.php`) : point d'extension prevu par `spatie/laravel-activitylog`.
 *
 * Ecriture seule du point de vue de l'application : une entree ne se modifie ni ne se supprime par
 * Eloquent. La seule suppression legitime est la purge a 24 mois (`App\Actions\Audit\PurgeAuditLog`),
 * qui passe par une requete groupee et laisse elle-meme une entree. Le retrait des droits `UPDATE` et
 * `DELETE` en base reste a poser au passage sur PostgreSQL : SQLite n'a pas de droits par table.
 *
 * @property string|null $previous_hash
 * @property string|null $hash
 */
class AuditEntry extends Activity
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Une entree du journal d\'audit ne se modifie pas.');
        });

        static::deleting(function (): never {
            throw new LogicException('Une entree du journal d\'audit ne se supprime pas.');
        });
    }
}
