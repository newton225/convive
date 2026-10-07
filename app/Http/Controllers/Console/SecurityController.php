<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Settings\ProtectionSettings;
use App\Settings\ReservationSettings;
use App\Settings\SupportSettings;
use App\Support\Console\SecurityJournal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'ecran « Securite » de la console (README section 3) : l'integrite des journaux d'audit, les
 * limites de debit atteintes et les connexions verrouillees. La zone `security` de la route le
 * reserve aux Fondateurs.
 */
class SecurityController extends Controller
{
    public function __invoke(Request $request, ProtectionSettings $protection, SupportSettings $support, ReservationSettings $reservation): Response
    {
        return Inertia::render('console/security', [
            'isSample' => false,
            // La limite d'exports par heure en vigueur, reglee depuis cet ecran (SECURITY.md M3).
            'exportsPerHour' => $protection->exports_per_hour,
            // Les durees qu'une organisation peut choisir pour un acces de support.
            'supportDurations' => $support->durations,
            // Les bornes de la duree de reservation proposee aux organisateurs.
            'reservationBounds' => ['min' => $reservation->hold_min_minutes, 'max' => $reservation->hold_max_minutes],
            ...SecurityJournal::overview($request),
        ]);
    }
}
