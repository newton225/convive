<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Une alerte de l'application. Toujours dans la base centrale, meme quand une tenancy est
 * active : les alertes sont produites par des actions qui s'executent sous la base d'un
 * locataire, et la table `notifications` n'existe que dans la base centrale.
 *
 * Sans ce modele, `User::notifications()` utiliserait la connexion par defaut du moment, donc
 * celle du locataire, ou la table n'existe pas.
 */
class DatabaseNotification extends BaseDatabaseNotification
{
    use CentralConnection;
}
