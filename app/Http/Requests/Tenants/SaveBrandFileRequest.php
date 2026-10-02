<?php

namespace App\Http\Requests\Tenants;

use App\Enums\BrandFile;
use App\Models\Tenant;
use App\Rules\ImagePixelBudget;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Image\Image;

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
                // Une image trop grande epuiserait la memoire au reencodage.
                new ImagePixelBudget,
            ],
            // La zone de rognage (en pixels de l'image redressee) : seulement pour les fonds du
            // billet, les seuls fichiers dont les proportions sont imposees.
            'crop' => [
                Rule::prohibitedIf(fn () => $this->imposedProportions() === null),
                'nullable', 'array:x,y,width,height',
            ],
            'crop.x' => ['required_with:crop', 'integer', 'min:0'],
            'crop.y' => ['required_with:crop', 'integer', 'min:0'],
            'crop.width' => ['required_with:crop', 'integer', 'min:1'],
            'crop.height' => ['required_with:crop', 'integer', 'min:1'],
        ];
    }

    /**
     * Check the crop area against the image itself : inside it, and in the ticket proportions.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $crop = $this->crop();
                $file = $this->file('file');
                $proportions = $this->imposedProportions();

                if ($crop === null || $proportions === null || $validator->errors()->isNotEmpty() || ! $file instanceof UploadedFile) {
                    return;
                }

                // Dimensions de l'image redressee : celles que le navigateur a affichees au
                // rognage, et celles que `SaveTenantBrandFile` decoupe.
                $image = Image::load($file->getRealPath())->orientation();

                if ($image->getWidth() < $crop['x'] + $crop['width'] || $image->getHeight() < $crop['y'] + $crop['height']) {
                    $validator->errors()->add('crop', __('organisation.errors.crop_outside'));

                    return;
                }

                // Tolerance de 2 % : la zone arrive arrondie au pixel.
                $expected = $proportions['width'] / $proportions['height'];

                if (abs($crop['width'] / $crop['height'] - $expected) > $expected * 0.02) {
                    $validator->errors()->add('crop', __('organisation.errors.crop_ratio'));
                }
            },
        ];
    }

    /**
     * Get the crop area, or null when none was sent. Its bounds are checked in `after()`.
     *
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    public function crop(): ?array
    {
        $crop = $this->input('crop');

        if (! is_array($crop)) {
            return null;
        }

        return [
            'x' => (int) ($crop['x'] ?? 0),
            'y' => (int) ($crop['y'] ?? 0),
            'width' => max(1, (int) ($crop['width'] ?? 1)),
            'height' => max(1, (int) ($crop['height'] ?? 1)),
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

    /**
     * Get the proportions imposed on the file named by the route, or null when it has none.
     *
     * @return array{width: int, height: int}|null
     */
    private function imposedProportions(): ?array
    {
        $file = $this->route('file');

        return is_string($file) ? BrandFile::tryFrom($file)?->crop() : null;
    }

    protected function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
