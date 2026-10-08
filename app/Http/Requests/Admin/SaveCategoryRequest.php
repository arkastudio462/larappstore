<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
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
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'name' => [$required, 'string', 'max:100'],
            'slug' => [
                'nullable',
                'string',
                'max:150',
                'alpha_dash',
                Rule::unique('categories', 'slug')->ignore($this->route('category')),
            ],
            'icon' => ['nullable', 'string', 'max:100'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'slug.alpha_dash' => 'Slug hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'slug.unique' => 'Slug kategori sudah dipakai.',
            'parent_id.exists' => 'Induk kategori tidak ditemukan.',
        ];
    }
}
