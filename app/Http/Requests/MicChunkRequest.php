<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MicChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'audio' => ['nullable', 'file', 'max:4096'],
            'session_id' => ['required', 'string', 'uuid'],
            'seq' => ['sometimes', 'integer', 'min:0'],
            'final' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'session_id.required' => 'معرف الجلسة مطلوب.',
            'session_id.uuid' => 'معرف الجلسة غير صحيح.',
            'audio.max' => 'حجم المقطع الصوتي كبير جداً.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $validator->errors()->isEmpty()) {
                return;
            }

            if (! $this->hasFile('audio') && ! $this->boolean('final')) {
                $validator->errors()->add('audio', 'مقطع الصوت مطلوب.');
            }
        });
    }
}
