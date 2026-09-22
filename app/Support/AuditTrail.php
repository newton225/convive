<?php

namespace App\Support;

/**
 * Le journal d'audit d'une organisation (README ecran 23). Sans modele Eloquent naturel a exposer
 * a une Policy : cette classe ne porte rien, elle sert de sujet a `App\Policies\AuditPolicy`, comme
 * `EventReport` pour `ReportPolicy`.
 */
final class AuditTrail {}
