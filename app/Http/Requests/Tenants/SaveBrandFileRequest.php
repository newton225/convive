<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveBrandFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('updateBrand', $this->tenant());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `image` inspecte le contenu, pas le nom : un script PHP renomme en `.png` est refuse.
     * Le SVG est exclu du type `image` par Laravel, et nous ne le reintroduisons pas : il peut
     * porter du script. La limite de 5 Mo est celle de CLAUDE.md.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                // `dimensions` force la lecture de l'image par getimagesize() : un fichier
                // qui porte le nom et le type d'une image sans en etre une est refuse ici,
                // avant que le reencodage n'echoue.
                'dimensions:min_width=1,min_height=1',
            ],
        ];
    }

    /**
     * Get the custom validation messages.
     *
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
