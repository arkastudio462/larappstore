<?php

namespace App\Http\Requests\Comment;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:1000'],
            /*
             * Balasan dibatasi satu tingkat: induk harus komentar utama
             * (`parent_id` null) pada postingan yang sama.
             */
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('comments', 'id')
                    ->where('post_id', $this->route('post')->id)
                    ->whereNull('parent_id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Komentar wajib diisi.',
            'body.max' => 'Komentar maksimal 1000 karakter.',
            'parent_id.exists' => 'Komentar yang dibalas tidak ditemukan.',
        ];
    }
}
