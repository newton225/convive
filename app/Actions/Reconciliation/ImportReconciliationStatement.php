<?php

namespace App\Actions\Reconciliation;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\StatementImport;
use App\Models\StatementLine;
use App\Models\User;
use App\Support\ReconciliationMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Import CSV d'un releve Mobile Money ou bancaire et rapprochement automatique de ses lignes
 * (README 2.10, ecran 19), etape 9 de « Ordre de construction ».
 *
 * Un releve est un document financier : une ligne mal formee rejette l'import entier, avec son
 * numero, plutot que d'en importer une partie en silence. Reimporter le meme contenu pour le meme
 * evenement est refuse (`content_hash`, unique avec `event_id`).
 */
class ImportReconciliationStatement
{
    /**
     * Nombre maximal de lignes par releve. Le rapprochement compare chaque ligne aux preuves de
     * l'evenement : borner l'entree borne le travail d'un seul envoi.
     */
    public const MaxRows = 5000;

    /**
     * Noms d'en-tete acceptes pour chaque colonne, apres normalisation (minuscules, sans
     * accents). Le francais est la langue des releves visee, l'anglais couvre les exports de
     * banques en ligne.
     *
     * @var array<string, array<int, string>>
     */
    private const HeaderAliases = [
        'date' => ['date'],
        'reference' => ['reference', 'ref'],
        'issuer' => ['emetteur', 'issuer', 'sender'],
        'amount' => ['montant', 'amount'],
    ];

    /**
     * Import the statement and match each of its lines against the event's proofs.
     *
     * @throws ValidationException Sur le champ `file`, sans rien ecrire.
     */
    public function handle(Event $event, UploadedFile $file, User $actor): StatementImport
    {
        $content = $this->readContent($file);
        $hash = hash('sha256', $content);

        if (StatementImport::where('event_id', $event->id)->where('content_hash', $hash)->exists()) {
            throw $this->error(__('reconciliation.errors.already_imported'));
        }

        $rows = $this->parse($content);

        try {
            return DB::transaction(fn () => $this->store($event, $file, $actor, $hash, $rows));
        } catch (UniqueConstraintViolationException) {
            // Deux envois simultanes du meme fichier : la contrainte d'unicite tranche.
            throw $this->error(__('reconciliation.errors.already_imported'));
        }
    }

    /**
     * @param  array<int, array{date: Carbon, reference: string|null, issuer: string, amount: int}>  $rows
     */
    private function store(Event $event, UploadedFile $file, User $actor, string $hash, array $rows): StatementImport
    {
        $import = StatementImport::create([
            'event_id' => $event->id,
            'imported_by_user_id' => $actor->id,
            'original_filename' => Str::limit($file->getClientOriginalName(), 250, ''),
            'content_hash' => $hash,
            'row_count' => count($rows),
        ]);

        $candidates = PaymentProof::query()
            ->whereHas('registration', fn (Builder $query) => $query
                ->where('event_id', $event->id)
                ->whereIn('status', [RegistrationStatus::ProofSubmitted, RegistrationStatus::Confirmed]))
            ->with('registration')
            ->get();

        foreach ($rows as $index => $row) {
            $line = new StatementLine([
                'reference' => $row['reference'],
                'issuer' => $row['issuer'],
                'amount' => $row['amount'],
            ]);

            $result = ReconciliationMatcher::match($line, $candidates);

            // Une preuve ne solde qu'une ligne : les lignes suivantes du meme releve ne peuvent
            // plus la reprendre.
            if ($result['proof'] !== null) {
                $candidates = $candidates->reject(fn (PaymentProof $proof) => $proof->is($result['proof']));
            }

            StatementLine::create([
                'statement_import_id' => $import->id,
                'line_number' => $index + 1,
                'occurred_on' => $row['date'],
                'reference' => $row['reference'],
                'issuer' => $row['issuer'],
                'amount' => $row['amount'],
                'outcome' => $result['outcome'],
                'matched_registration_id' => $result['proof']?->registration_id,
                'matched_payment_proof_id' => $result['proof']?->id,
            ]);
        }

        activity()
            ->performedOn($import)
            ->causedBy($actor)
            ->event('created')
            ->withProperties(['attributes' => [
                'event_id' => $event->id,
                'filename' => $import->original_filename,
                'rows' => $import->row_count,
            ]])
            ->log('reconciliation.imported');

        return $import;
    }

    /**
     * Read the uploaded file as UTF-8 : sans BOM, et converti depuis Windows-1252 quand le
     * fichier n'est pas de l'UTF-8 valide (export courant de Excel en francais).
     */
    private function readContent(UploadedFile $file): string
    {
        $content = (string) $file->get();
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        return mb_check_encoding($content, 'UTF-8')
            ? $content
            : mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
    }

    /**
     * @return array<int, array{date: Carbon, reference: string|null, issuer: string, amount: int}>
     */
    private function parse(string $content): array
    {
        $lines = array_values(array_filter(
            preg_split('/\r\n|\n|\r/', $content) ?: [],
            fn (string $line) => trim($line) !== '',
        ));

        if ($lines === []) {
            throw $this->error(__('reconciliation.errors.empty'));
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $columns = $this->columnPositions(str_getcsv(array_shift($lines), $delimiter, '"', ''));

        if ($lines === []) {
            throw $this->error(__('reconciliation.errors.empty'));
        }

        if (count($lines) > self::MaxRows) {
            throw $this->error(__('reconciliation.errors.too_many_rows', ['max' => self::MaxRows]));
        }

        $rows = [];

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $cells = str_getcsv($line, $delimiter, '"', '');

            $date = $this->parseDate($cells[$columns['date']] ?? '');
            $amount = $this->parseAmount($cells[$columns['amount']] ?? '');

            if ($date === null) {
                throw $this->error(__('reconciliation.errors.invalid_date', ['line' => $number]));
            }

            if ($amount === null) {
                throw $this->error(__('reconciliation.errors.invalid_amount', ['line' => $number]));
            }

            $reference = trim((string) ($cells[$columns['reference']] ?? ''));

            $rows[] = [
                'date' => $date,
                'reference' => $reference === '' ? null : Str::limit($reference, 250, ''),
                'issuer' => Str::limit(trim((string) ($cells[$columns['issuer']] ?? '')), 250, ''),
                'amount' => $amount,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array{date: int, reference: int, issuer: int, amount: int}
     */
    private function columnPositions(array $header): array
    {
        $normalized = array_map(
            fn (?string $name) => mb_strtolower(trim(Str::ascii((string) $name))),
            $header,
        );

        $positions = [];
        $missing = [];

        foreach (self::HeaderAliases as $column => $aliases) {
            $position = null;

            foreach ($aliases as $alias) {
                $found = array_search($alias, $normalized, true);

                if ($found !== false) {
                    $position = (int) $found;

                    break;
                }
            }

            if ($position === null) {
                $missing[] = $aliases[0];
            } else {
                $positions[$column] = $position;
            }
        }

        if ($missing !== []) {
            throw $this->error(__('reconciliation.errors.missing_columns', ['columns' => implode(', ', $missing)]));
        }

        /** @var array{date: int, reference: int, issuer: int, amount: int} $positions */
        return $positions;
    }

    /**
     * Parse `2026-09-20` or `20/09/2026` (an optional time is ignored), or null when unreadable.
     */
    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $value, $parts) === 1) {
            [$year, $month, $day] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
        } elseif (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $value, $parts) === 1) {
            [$day, $month, $year] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? Carbon::create($year, $month, $day)->startOfDay() : null;
    }

    /**
     * Parse a whole amount in francs CFA, tolerating thousands separators and a `,00` tail, or
     * null when it is not a positive number.
     */
    private function parseAmount(string $value): ?int
    {
        $value = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $value) ?? '';

        if (preg_match('/^(\d+)(?:[.,]0+)?$/', $value, $parts) !== 1) {
            return null;
        }

        $amount = (int) $parts[1];

        return $amount > 0 ? $amount : null;
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['file' => $message]);
    }
}
