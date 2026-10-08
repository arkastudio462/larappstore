<?php

namespace App\Http\Requests\Post;

use App\Models\Post;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in([Post::STATUS_PUBLISHED, Post::STATUS_DRAFT])],
            'media' => ['sometimes', 'array', 'max:4'],
            'media.*.path' => ['required', 'string', 'max:500'],
            'media.*.type' => ['nullable', 'string', Rule::in(['image', 'video', 'audio'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Tulisan postingan wajib diisi.',
            'body.max' => 'Tulisan postingan maksimal 5000 karakter.',
            'media.max' => 'Maksimal 4 lampiran per postingan.',
        ];
    }
}
