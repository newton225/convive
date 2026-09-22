<?php

namespace App\Enums;

/**
 * Canaux de versement acceptes. Catalogue ferme : le canal apparait sur le lien public et sur
 * le recu, et il pilote le rapprochement du releve. Il ne se saisit pas en texte libre.
 */
enum PaymentChannel: string
{
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';
    case MtnMoney = 'mtn_money';
    case MoovMoney = 'moov_money';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';

    /**
     * Get the label shown to the operator and to the guest.
     */
    public function label(): string
    {
        return __("payment_accounts.channels.{$this->value}");
    }

    /**
     * Determine whether this channel carries a number the guest transfers money to.
     *
     * Les especes n'ont pas de numero : le champ est alors une consigne, pas un compte.
     */
    public function hasAccountNumber(): bool
    {
        return $this !== self::Cash;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $channel) => $channel->value, self::cases());
    }
}
