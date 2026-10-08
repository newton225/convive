<?php

namespace App\Models;

use Database\Factories\EventPriceCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une categorie de tarif d'un evenement, nommee par l'organisateur (« VIP », « Etudiant »...),
 * decision du proprietaire du projet, 2026-10-07. Chaque personne inscrite en choisit une ; un
 * quota facultatif la limite, dans la capacite de la salle. Vit dans la base du locataire.
 *
 * @property int $id
 * @property int $event_id
 * @property string $name
 * @property int $price
 * @property int|null $quota
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 */
#[Fillable(['name', 'price', 'quota', 'position'])]
class EventPriceCategory extends Model
{
    /** @use HasFactory<EventPriceCategoryFactory> */
    use HasFactory;

    /**
     * Voir `Event::$dateFormat` : meme raison, meme valeur constante pour toutes les
     * connexions SQLite de l'application.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Count the people holding a seat in this category : the guests and companions of the
     * registrations that occupy seats (confirmed, or held with a running countdown).
     */
    public function seatsTaken(): int
    {
        $occupying = Registration::query()->where('event_id', $this->event_id)->occupyingSeats();

        $guests = (clone $occupying)->where('price_category_id', $this->id)->count();
        $companions = RegistrationCompanion::query()
            ->where('price_category_id', $this->id)
            ->whereIn('registration_id', (clone $occupying)->select('id'))
            ->count();

        return $guests + $companions;
    }

    /**
     * Determine whether anybody chose this category : a registration, a companion, or a waiting
     * list entry. Son nom et son prix sont alors figes (decision du 2026-10-08) : le montant d'une
     * personne ne doit pas dependre du moment ou elle s'est inscrite, et elle ne disparait pas.
     */
    public function isChosen(): bool
    {
        if (Registration::query()->where('price_category_id', $this->id)->exists()
            || RegistrationCompanion::query()->where('price_category_id', $this->id)->exists()) {
            return true;
        }

        return WaitlistEntry::query()
            ->where('event_id', $this->event_id)
            ->get()
            ->contains(fn (WaitlistEntry $entry) => (int) $entry->price_category_id === $this->id
                || collect($entry->companions)
                    ->contains(fn (array $companion) => (int) ($companion['price_category_id'] ?? 0) === $this->id));
    }

    /**
     * Get the seats left in this category, or null when it has no quota of its own.
     */
    public function remaining(): ?int
    {
        return $this->quota === null ? null : max(0, $this->quota - $this->seatsTaken());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quota' => 'integer',
            'position' => 'integer',
        ];
    }
}
