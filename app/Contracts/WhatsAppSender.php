<?php

namespace App\Contracts;

/**
 * Interface dediee au canal WhatsApp Business (CLAUDE.md, « Pile imposee »), pour rester
 * remplacable : la carte d'invitation et les rappels (README 2.7) ne connaissent que ce
 * contrat, jamais un client HTTP concret.
 */
interface WhatsAppSender
{
    /**
     * Send a WhatsApp text message to the given phone number.
     */
    public function send(string $to, string $message): void;

    /**
     * Determine whether messages really reach their recipient. Faux pour le palliatif qui ecrit au
     * journal : la console le signale, pour que personne ne croie des messages partis.
     */
    public function delivers(): bool;
}
