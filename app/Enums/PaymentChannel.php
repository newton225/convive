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
     * Get the number prefixes this mobile money channel accepts, or `null` when the channel is
     * not a mobile network (bank transfer, cash) and its number follows no telephone format.
     *
     * Numeros ivoiriens (decision du 2026-09-29) : Orange 07, MTN 05, Moov 01. Wave n'est pas un
     * operateur, il s'appuie sur le numero de l'abonne, quel que soit son reseau mobile.
     *
     * @return array<int, string>|null
     */
    public function mobilePrefixes(): ?array
    {
        return match ($this) {
            self::OrangeMoney => ['07'],
            self::MtnMoney => ['05'],
            self::MoovMoney => ['01'],
            self::Wave => ['01', '05', '07'],
            self::BankTransfer, self::Cash => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $channel) => $channel->value, self::cases());
    }
}
