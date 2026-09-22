<?php

namespace App\Support;

/**
 * Le rapport post-evenement (README ecran 22). Ce domaine n'a pas de modele Eloquent naturel :
 * cette classe ne porte rien, elle sert seulement de sujet a `App\Policies\ReportPolicy`, comme
 * `ScanEvent` pour `ScanPolicy`.
 */
final class EventReport {}
