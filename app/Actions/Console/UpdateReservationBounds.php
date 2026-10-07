<?php

namespace App\Actions\Console;

use App\Models\User;
use App\Settings\ReservationSettings;
use App\Support\Console\ConsoleJournal;

/**
 * Regler les bornes de la duree de reservation proposee aux organisateurs. Sans effet sur les
 * reservations en cours ni sur la duree deja enregistree d'un evenement : elle sera ramenee dans
 * les bornes a sa prochaine modification. L'avant et l'apres vont au journal central.
 */
class UpdateReservationBounds
{
    public function __construct(private ReservationSettings $settings)
    {
        //
    }

    public function handle(int $min, int $max, User $actor): void
    {
        $before = ['min' => $this->settings->hold_min_minutes, 'max' => $this->settings->hold_max_minutes];

        if ($before === ['min' => $min, 'max' => $max]) {
            return;
        }

        $this->settings->hold_min_minutes = $min;
        $this->settings->hold_max_minutes = $max;
        $this->settings->save();

        ConsoleJournal::record('reservation_bounds_updated', $actor, null, [
            'old' => $before,
            'attributes' => ['min' => $min, 'max' => $max],
        ]);
    }
}
