<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCollectionRequest extends FormRequest
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
            'name' => [$required, 'string', 'max:150'],
            'slug' => [
                'nullable',
                'string',
                'max:150',
                'alpha_dash',
                Rule::unique('collections', 'slug')->ignore($this->route('collection')),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_path' => ['nullable', 'string', 'max:500'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama koleksi wajib diisi.',
            'slug.alpha_dash' => 'Slug hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'slug.unique' => 'Slug koleksi sudah dipakai.',
            'product_ids.*.exists' => 'Ada produk yang tidak ditemukan.',
        ];
    }
}
