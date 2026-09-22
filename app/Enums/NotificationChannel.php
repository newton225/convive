<?php

namespace App\Enums;

/**
 * Le canal d'une alerte, au choix de chaque membre et par type (README section 5) : dans
 * l'application, par courriel, ou les deux.
 */
enum NotificationChannel: string
{
    case App = 'app';
    case Mail = 'mail';
    case Both = 'both';

    /**
     * Get the channel used until the member chooses otherwise : dans l'application seulement, un
     * courriel non demande est du bruit.
     */
    public static function default(): self
    {
        return self::App;
    }

    /**
     * Get the Laravel notification channels this choice stands for.
     *
     * @return array<int, string>
     */
    public function laravelChannels(): array
    {
        return match ($this) {
            self::App => ['database'],
            self::Mail => ['mail'],
            self::Both => ['database', 'mail'],
        };
    }

    /**
     * Get the label shown to the member.
     */
    public function label(): string
    {
        return __("notifications.preferences.channels.{$this->value}");
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $channel) => $channel->value, self::cases());
    }
}
