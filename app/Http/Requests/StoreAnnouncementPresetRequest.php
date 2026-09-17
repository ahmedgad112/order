<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'min:2', 'max:80'],
            'text' => ['required', 'string', 'min:2', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'اسم الرسالة مطلوب.',
            'label.min' => 'اسم الرسالة قصير جداً.',
            'text.required' => 'نص الرسالة مطلوب.',
            'text.min' => 'نص الرسالة قصير جداً.',
            'text.max' => 'نص الرسالة طويل جداً.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        if ($this->exists('label') && is_string($this->label)) {
            $merged['label'] = trim($this->label);
        }

        if ($this->exists('text') && is_string($this->text)) {
            $merged['text'] = trim($this->text);
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }
}
