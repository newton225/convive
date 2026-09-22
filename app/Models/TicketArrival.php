<?php

namespace App\Models;

use Database\Factories\TicketArrivalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Le premier passage accepte d'un billet (README 2.8). Vit dans la base du locataire (voir
 * CLAUDE.md, « Multi-locataire »).
 *
 * `ticket_id` unique porte la garantie contre deux scans concurrents accepteraient tous les deux
 * le meme billet (voir la migration). `performed_by_user_id` reference `User`, qui vit dans la
 * base centrale : pas de cle etrangere possible entre deux bases physiques distinctes, la lecture
 * de l'agent passe par `User::find()`.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int $performed_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ticket $ticket
 */
#[Fillable(['ticket_id', 'performed_by_user_id'])]
class TicketArrival extends Model
{
    /** @use HasFactory<TicketArrivalFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the ticket that arrived.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
