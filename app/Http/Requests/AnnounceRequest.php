<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\SpeechService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnounceRequest extends FormRequest
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
            'text' => ['required', 'string', 'min:2', 'max:500'],
            'voice' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(SpeechService::voices()))],
            'rate' => ['sometimes', 'nullable', 'string', Rule::in(SpeechService::rates())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text.required' => 'نص الإعلان مطلوب.',
            'text.min' => 'نص الإعلان قصير جداً.',
            'text.max' => 'نص الإعلان طويل جداً (500 حرف كحد أقصى).',
            'voice.in' => 'الصوت المختار غير متاح.',
            'rate.in' => 'سرعة الكلام غير صحيحة.',
        ];
    }
}
