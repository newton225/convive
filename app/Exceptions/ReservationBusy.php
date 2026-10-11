<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Trop de reservations au meme instant : la base a refuse une ecriture (SQLite : « database is
 * locked ») apres plusieurs tentatives, ou le verrou de l'evenement est reste pris. Ce n'est pas un
 * evenement complet : aucune place n'a ete prise ni refusee, l'invite doit simplement reessayer.
 * Rendue en message par `bootstrap/app.php`, jamais en page d'erreur.
 */
class ReservationBusy extends RuntimeException
{
    public function __construct(string $message = 'Reservation momentanement saturee.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
