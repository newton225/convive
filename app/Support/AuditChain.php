<?php

namespace App\Support;

use App\Models\AuditEntry;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Chainage par empreinte du journal d'audit (SECURITY.md M6), sur la base active : la centrale, ou
 * celle du locataire quand la tenancy est initialisee (les deux journaux sont distincts, CLAUDE.md
 * « Multi-locataire »).
 *
 * L'empreinte d'une entree couvre son contenu et l'empreinte de la precedente : modifier une entree
 * casse la sienne, en supprimer une casse le lien de la suivante.
 */
final class AuditChain
{
    public const PurgeDescription = 'audit.purged';

    private static ?Lock $lock = null;

    /**
     * Link the entry being created to the last one. Called from the `creating` event.
     *
     * Le verrou est tenu jusqu'a `release()`, appele sur `created` : deux ecritures simultanees
     * liees a la meme precedente feraient bifurquer la chaine, et la verification y verrait une
     * alteration qui n'en est pas une.
     */
    public static function link(AuditEntry $entry): void
    {
        self::$lock = Cache::lock('audit-chain:'.$entry->getConnectionName(), 10);
        self::$lock->block(5);

        $entry->created_at ??= now();
        $entry->previous_hash = AuditEntry::query()->latest('id')->value('hash');
        $entry->hash = self::hashFor($entry);
    }

    public static function release(): void
    {
        self::$lock?->release();
        self::$lock = null;
    }

    public static function hashFor(AuditEntry $entry): string
    {
        return hash('sha256', (string) json_encode([
            $entry->previous_hash,
            $entry->log_name,
            $entry->description,
            $entry->subject_type,
            $entry->subject_id === null ? null : (string) $entry->subject_id,
            $entry->event,
            $entry->causer_type,
            $entry->causer_id === null ? null : (string) $entry->causer_id,
            collect($entry->properties)->toArray(),
            $entry->created_at?->format('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get the id of the first entry whose content or link does not check out, or null when the
     * whole chain is intact.
     *
     * Les entrees anterieures au chainage (empreinte absente) sont ignorees tant qu'aucune entree
     * chainee ne les precede. Apres une purge, la premiere entree restante doit pointer vers
     * l'ancre consignee par cette purge, sinon des entrees ont disparu en dehors d'elle.
     */
    public static function firstBrokenEntry(): ?int
    {
        $expectedPrevious = self::purgeAnchor();
        $chainStarted = false;
        $brokenId = null;

        AuditEntry::query()->orderBy('id')->chunk(500, function ($entries) use (&$expectedPrevious, &$chainStarted, &$brokenId) {
            foreach ($entries as $entry) {
                if ($entry->hash === null) {
                    if ($chainStarted) {
                        $brokenId = $entry->id;

                        return false;
                    }

                    continue;
                }

                if ($entry->previous_hash !== $expectedPrevious || self::hashFor($entry) !== $entry->hash) {
                    $brokenId = $entry->id;

                    return false;
                }

                $chainStarted = true;
                $expectedPrevious = $entry->hash;
            }

            return true;
        });

        return $brokenId;
    }

    /**
     * The hash the first surviving entry points to after the latest purge, or null if the log
     * was never purged.
     */
    private static function purgeAnchor(): ?string
    {
        $purge = AuditEntry::query()
            ->where('description', self::PurgeDescription)
            ->latest('id')
            ->first();

        $anchor = $purge?->properties['anchor'] ?? null;

        return is_string($anchor) ? $anchor : null;
    }
}
