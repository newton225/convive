<?php

namespace App\Support;

use App\Models\RegistrationEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Chainage par empreinte de l'historique d'un dossier (`registration_events`), sur la base active.
 *
 * Une chaine par dossier, ou par entree de liste d'attente avant sa conversion : les lignes partent
 * avec leur dossier, une chaine globale se romprait a chaque purge. L'empreinte d'une ligne couvre son
 * contenu et celle de la precedente de la meme chaine : modifier une ligne casse la sienne, en retirer
 * une au milieu casse le lien de la suivante. Retirer la derniere ligne d'une chaine ne se voit pas.
 *
 * L'adresse IP n'entre pas dans l'empreinte : elle s'efface au bout de douze mois, et cet effacement
 * prevu ne doit pas passer pour une alteration.
 */
final class RegistrationChain
{
    /**
     * Write the fact as the next link of its chain. Le verrou serialise deux ecritures simultanees sur
     * le meme dossier, qui feraient sinon bifurquer la chaine.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function append(array $attributes): RegistrationEvent
    {
        $key = isset($attributes['registration_id'])
            ? 'registration:'.$attributes['registration_id']
            : 'waitlist:'.($attributes['waitlist_entry_id'] ?? 'none');

        return Cache::lock('registration-chain:'.$key, 10)->block(5, function () use ($attributes) {
            $fact = new RegistrationEvent($attributes);
            $fact->forceFill(['created_at' => now()->startOfSecond()]);
            $fact->previous_hash = self::chainOf($fact)->orderByDesc('id')->value('hash');
            $fact->hash = self::hashFor($fact);
            $fact->save();

            return $fact;
        });
    }

    public static function hashFor(RegistrationEvent $fact): string
    {
        return hash('sha256', (string) json_encode([
            $fact->previous_hash,
            $fact->event_id,
            $fact->registration_id,
            $fact->waitlist_entry_id,
            $fact->type->value,
            $fact->actor->value,
            $fact->properties,
            $fact->created_at?->format('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get the id of the first fact whose content or link does not check out, or null when every chain
     * is intact. Les lignes anterieures au chainage (empreinte absente) sont ignorees.
     */
    public static function firstBrokenEvent(): ?int
    {
        $brokenId = null;
        $expected = [];

        RegistrationEvent::query()->orderBy('id')->chunk(500, function ($facts) use (&$brokenId, &$expected) {
            foreach ($facts as $fact) {
                if ($fact->hash === null) {
                    continue;
                }

                $chain = $fact->registration_id !== null ? 'r'.$fact->registration_id : 'w'.$fact->waitlist_entry_id;

                if ($fact->previous_hash !== ($expected[$chain] ?? null) || self::hashFor($fact) !== $fact->hash) {
                    $brokenId = $fact->id;

                    return false;
                }

                $expected[$chain] = $fact->hash;
            }

            return true;
        });

        return $brokenId;
    }

    /**
     * @return Builder<RegistrationEvent>
     */
    private static function chainOf(RegistrationEvent $fact)
    {
        return RegistrationEvent::query()
            ->when(
                $fact->registration_id !== null,
                fn ($query) => $query->where('registration_id', $fact->registration_id),
                fn ($query) => $query->where('waitlist_entry_id', $fact->waitlist_entry_id)->whereNull('registration_id'),
            );
    }
}
