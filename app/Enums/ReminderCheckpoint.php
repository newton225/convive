<?php

namespace App\Enums;

/**
 * Les trois rappels de preuve manquante (README 2.7), mesures en jours avant l'evenement.
 */
enum ReminderCheckpoint: int
{
    case SevenDaysBefore = 7;
    case TwoDaysBefore = 2;
    case OneDayBefore = 1;

    /**
     * Get the column on `registrations` that marks this checkpoint as sent.
     */
    public function column(): string
    {
        return match ($this) {
            self::SevenDaysBefore => 'proof_reminder_j7_sent_at',
            self::TwoDaysBefore => 'proof_reminder_j2_sent_at',
            self::OneDayBefore => 'proof_reminder_j1_sent_at',
        };
    }
}
