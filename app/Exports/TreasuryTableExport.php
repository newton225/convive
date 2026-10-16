<?php

namespace App\Exports;

use App\Support\SpreadsheetSafe;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export Excel et CSV d'un tableau de l'ecran Tresorerie (decision du 2026-10-16) : par evenement, par
 * compte de versement, ou les remboursements a faire. Les memes chiffres que l'ecran
 * (`App\Support\TreasuryOverview`), jamais un recalcul a part.
 *
 * Montants en entier brut, comme l'export des inscrits : une colonne d'export doit rester sommable
 * dans le tableur. Dates au format AAAA-MM-JJ, qui se trie. Le texte saisi par un invite ou une
 * equipe passe par `SpreadsheetSafe` (SECURITY.md M2).
 */
class TreasuryTableExport implements FromArray, WithCustomCsvSettings, WithHeadings
{
    public const Tables = ['events' => 'evenements', 'accounts' => 'comptes', 'refunds' => 'remboursements'];

    /**
     * @param  array<string, mixed>  $overview  le resultat de `TreasuryOverview::for()` sans plafond de liste
     */
    public function __construct(private readonly string $table, private readonly array $overview) {}

    /**
     * Le nom du fichier, sans extension.
     */
    public static function filename(string $table): string
    {
        return 'tresorerie-'.self::Tables[$table];
    }

    /**
     * Meme reglage que l'export des inscrits : un CSV qu'Excel ouvre tel quel en francais.
     *
     * @return array<string, mixed>
     */
    public function getCsvSettings(): array
    {
        return ['delimiter' => ';', 'use_bom' => true];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return match ($this->table) {
            'events' => [
                __('treasury.events.name'),
                __('treasury.events.date'),
                __('treasury.events.collected'),
                __('treasury.events.refunded'),
                __('treasury.events.fees'),
                __('treasury.events.due'),
                __('treasury.events.net'),
            ],
            'accounts' => [
                __('treasury.accounts.label'),
                __('treasury.accounts.channel'),
                __('treasury.accounts.collected'),
                __('treasury.accounts.refunded'),
                __('treasury.accounts.due'),
            ],
            'refunds' => [
                __('treasury.refunds.name'),
                __('treasury.refunds.reference'),
                __('treasury.refunds.event'),
                __('treasury.refunds.amount'),
                __('treasury.refunds.cancelled_on'),
            ],
        };
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return match ($this->table) {
            'events' => array_map(fn (array $row) => [
                SpreadsheetSafe::cell($row['name']),
                $this->date($row['startsAt']),
                $row['collected'],
                $row['refunded'],
                $row['fees'],
                $row['due'],
                $row['net'],
            ], $this->overview['events']),
            'accounts' => array_map(fn (array $row) => [
                SpreadsheetSafe::cell($row['label']),
                $row['channelLabel'],
                $row['collected'],
                $row['refunded'],
                $row['due'],
            ], $this->overview['accounts']),
            'refunds' => array_map(fn (array $row) => [
                SpreadsheetSafe::cell($row['name']),
                $row['reference'],
                SpreadsheetSafe::cell($row['eventName']),
                $row['amount'],
                $this->date($row['cancelledAt']),
            ], $this->overview['refundsDue']),
        };
    }

    private function date(?string $iso): ?string
    {
        return $iso !== null ? Carbon::parse($iso)->format('Y-m-d') : null;
    }
}
