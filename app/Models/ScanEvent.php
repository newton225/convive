<?php

namespace App\Models;

use App\Enums\ScanResult;
use Database\Factories\ScanEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une ligne du journal des passages a l'entree (README 2.8, ecran 26). Vit dans la base du
 * locataire (voir CLAUDE.md, « Multi-locataire »). Ecriture seule : jamais mise a jour.
 *
 * `ticket_id` est nullable : un jeton dont la signature ne verifie pas ne resout aucun billet
 * reel, la tentative refusee est journalisee quand meme. `performed_by_user_id` reference
 * `User`, qui vit dans la base centrale, sans cle etrangere possible entre deux bases physiques.
 *
 * @property int $id
 * @property int $event_id
 * @property int|null $ticket_id
 * @property int $performed_by_user_id
 * @property ScanResult $result
 * @property bool $forced
 * @property bool $manual
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $station
 * @property-read Event $event
 * @property-read Ticket|null $ticket
 */
#[Fillable(['event_id', 'ticket_id', 'performed_by_user_id', 'result', 'forced', 'manual', 'station'])]
class ScanEvent extends Model
{
    /** @use HasFactory<ScanEventFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the event this scan attempt happened at.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the ticket this scan attempt resolved to, if any.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => ScanResult::class,
            'forced' => 'boolean',
            'manual' => 'boolean',
        ];
    }
}
