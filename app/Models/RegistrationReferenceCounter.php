<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Le dernier numero de reference de dossier attribue dans l'annee (voir
 * `App\Support\RegistrationReference`). Vit dans la base du locataire.
 *
 * @property int $id
 * @property int $year
 * @property int $last_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['year', 'last_number'])]
class RegistrationReferenceCounter extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
