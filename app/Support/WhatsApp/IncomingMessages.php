<?php

namespace App\Support\WhatsApp;

/**
 * Les messages texte d'un envoi du webhook de Meta, avec leur expediteur.
 *
 * Forme : `entry[].changes[].value.messages[]`, chaque message portant `from` (le numero WhatsApp,
 * chiffres sans `+`), `type` et, pour un texte, `text.body`.
 */
final class IncomingMessages
{
    /**
     * @param  array<mixed>  $payload
     * @return array<int, array{from: string, text: string}>
     */
    public static function from(array $payload): array
    {
        $messages = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) (is_array($entry) ? ($entry['changes'] ?? []) : []) as $change) {
                $value = is_array($change) ? (array) ($change['value'] ?? []) : [];

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    if (! is_array($message) || ($message['type'] ?? null) !== 'text') {
                        continue;
                    }

                    $from = $message['from'] ?? null;
                    $text = is_array($message['text'] ?? null) ? ($message['text']['body'] ?? null) : null;

                    if (is_string($from) && is_string($text)) {
                        $messages[] = ['from' => $from, 'text' => $text];
                    }
                }
            }
        }

        return $messages;
    }
}
