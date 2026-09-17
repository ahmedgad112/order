<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCallTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canControlSystem() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'call_template' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $template = trim((string) $value);

                    if ($template === '') {
                        return;
                    }

                    foreach (['{order}', '{counter}'] as $placeholder) {
                        if (! str_contains($template, $placeholder)) {
                            $fail('يجب أن يحتوي نص النداء على '.$placeholder.'.');
                        }
                    }
                },
            ],
        ];
    }
}
