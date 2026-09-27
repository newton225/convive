<?php

namespace App\Exports;

use App\Models\Registration;
use App\Support\SpreadsheetSafe;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export Excel et CSV de la base d'inscrits (README ecran 20), etape 9. Le meme filtre que
 * l'ecran (`Events\RegistrationController::filteredQuery()`) alimente cet export : jamais un
 * ecart entre ce qui est affiche et ce qui est exporte.
 *
 * Montants en entier brut, pas `App\Support\Money::format()` : une colonne d'export doit rester
 * sommable dans le tableur, pas seulement lisible.
 *
 * @implements WithMapping<Registration>
 */
class RegistrationsExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Builder<Registration>  $query
     */
    public function __construct(private readonly Builder $query) {}

    /**
     * @return Builder<Registration>
     */
    public function query(): Builder
    {
        // Tri deterministe (identifiant en repli) : FromQuery pagine par lots avec LIMIT/OFFSET,
        // un ORDER BY non unique peut sauter ou dupliquer des lignes entre deux lots.
        return $this->query->orderBy('id');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            __('registrations.columns.export.name'),
            __('registrations.columns.export.phone'),
            __('registrations.columns.export.email'),
            __('registrations.columns.export.unit'),
            __('registrations.columns.export.party_size'),
            __('registrations.columns.export.amount_due'),
            __('registrations.columns.export.status'),
            __('registrations.columns.export.table'),
            __('registrations.columns.export.cancellation_reason'),
        ];
    }

    /**
     * @param  Registration  $row
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        // Texte libre saisi par l'invite ou l'equipe : neutralise contre l'injection de formules
        // (SECURITY.md M2). Le telephone n'y passe pas, voir `SpreadsheetSafe`.
        return [
            SpreadsheetSafe::cell($row->name),
            $row->phone,
            SpreadsheetSafe::cell($row->email),
            SpreadsheetSafe::cell($row->unit->name),
            $row->party_size,
            $row->amount_due,
            $row->status->label(),
            $row->tableAssignment?->seatingTable->number,
            SpreadsheetSafe::cell($row->cancellation_reason),
        ];
    }
}
