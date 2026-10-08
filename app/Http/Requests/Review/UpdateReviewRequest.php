<?php

namespace App\Http\Requests\Review;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.between' => 'Penilaian harus antara 1 dan 5 bintang.',
            'rating.min' => 'Penilaian harus antara 1 dan 5 bintang.',
            'rating.max' => 'Penilaian harus antara 1 dan 5 bintang.',
            'body.max' => 'Ulasan maksimal 1000 karakter.',
        ];
    }
}
