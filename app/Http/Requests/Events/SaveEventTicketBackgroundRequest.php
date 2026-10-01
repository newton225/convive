<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\Tenants\SaveBrandFileRequest;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;

/**
 * Un fond du billet propre a un evenement : les memes controles que ceux de l'organisation (type
 * et contenu inspectes, taille, zone de rognage verifiee), sous la permission de modifier
 * l'evenement.
 */
class SaveEventTicketBackgroundRequest extends SaveBrandFileRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        abort_unless($event instanceof Event, 404);

        return Gate::allows('update', [$event, $this->tenant()]);
    }
}
