<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\UpdateReservationBounds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\UpdateReservationBoundsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Le reglage des bornes de la duree de reservation, depuis l'ecran Securite de la console.
 */
class ReservationBoundsController extends Controller
{
    public function __invoke(UpdateReservationBoundsRequest $request, UpdateReservationBounds $update): RedirectResponse
    {
        $update->handle($request->integer('min'), $request->integer('max'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('console.security.reservation_bounds.flash')]);

        return to_route('console.security');
    }
}
