<?php

namespace App\Contracts;

/**
 * L'envoi de SMS derriere une interface dediee, comme WhatsApp (CLAUDE.md, « Pile imposee ») :
 * changer de service ne change que la liaison dans `AppServiceProvider`.
 */
interface SmsSender
{
    /**
     * Send a text message to a number in its single E.164 form (`+2250707123456`).
     */
    public function send(string $to, string $message): void;

    /**
     * Determine whether the messages really leave, or are only written to the log.
     */
    public function delivers(): bool;
}
