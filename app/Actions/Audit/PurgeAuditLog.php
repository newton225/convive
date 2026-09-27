<?php

namespace App\Actions\Audit;

use App\Models\AuditEntry;
use App\Support\AuditChain;

/**
 * Purge du journal d'audit a 24 mois (CLAUDE.md, « Securite », SECURITY.md M6), sur la base active.
 *
 * La purge elle-meme laisse une entree : combien de lignes, avant quelle date, et l'ancre, c'est a
 * dire l'empreinte vers laquelle pointe desormais la premiere entree restante. La verification de
 * la chaine s'appuie sur cette ancre : une suppression hors de la purge reste detectable.
 */
class PurgeAuditLog
{
    public const RetentionMonths = 24;

    /**
     * Purge the entries older than the retention period, returning how many were removed.
     */
    public function handle(): int
    {
        $cutoff = now()->subMonths(self::RetentionMonths);

        $survivor = AuditEntry::query()
            ->where('created_at', '>=', $cutoff)
            ->orderBy('id')
            ->first();

        // Requete groupee, sans evenement de modele : `AuditEntry` refuse toute suppression unitaire.
        $purged = AuditEntry::query()->where('created_at', '<', $cutoff)->delete();

        if ($purged > 0) {
            activity()
                ->event('purged')
                ->withProperties([
                    'count' => $purged,
                    'before' => $cutoff->toISOString(),
                    'anchor' => $survivor?->previous_hash,
                ])
                ->log(AuditChain::PurgeDescription);
        }

        return $purged;
    }
}
