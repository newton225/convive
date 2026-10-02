<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Models\Tenant;
use App\Rules\ImagePixelBudget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Le visuel de l'evenement (README ecran 13). Memes regles que les fichiers de marque de
 * l'organisation (CLAUDE.md, « Fichiers de marque ») : le SVG est exclu, le contenu est
 * inspecte, pas le nom.
 */
class SaveEventVisualRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        abort_unless($event instanceof Event, 404);

        return Gate::allows('update', [$event, $this->tenant()]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                'dimensions:min_width=1,min_height=1',
                // Une image trop grande epuiserait la memoire au reencodage.
                new ImagePixelBudget,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.image' => __('organisation.errors.file_not_an_image'),
            'file.mimes' => __('organisation.errors.file_type'),
            'file.max' => __('organisation.errors.file_too_large'),
            'file.dimensions' => __('organisation.errors.file_not_an_image'),
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
