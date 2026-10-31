<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * La video courte de presentation de l'evenement (decision du 2026-10-31) : mp4 ou webm, 25 Mo au plus. Le type
 * se lit dans le contenu du fichier (`mimetypes`), jamais dans son nom.
 */
class SaveEventVideoRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimetypes:video/mp4,video/webm', 'max:'.Event::PresentationVideoMaxKilobytes],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimetypes' => __('events.video.errors.type'),
            'file.max' => __('events.video.errors.too_large'),
            'file.uploaded' => __('events.video.errors.too_large'),
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
