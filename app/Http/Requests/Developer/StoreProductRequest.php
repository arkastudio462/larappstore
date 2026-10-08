<?php

namespace App\Http\Requests\Developer;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'type' => ['required', 'string', Rule::in(Product::TYPES)],
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'icon_path' => ['nullable', 'string', 'max:500'],
            'banner_path' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Pilih kategori produk.',
            'category_id.exists' => 'Kategori yang dipilih tidak tersedia.',
            'type.in' => 'Jenis produk tidak dikenal.',
            'title.required' => 'Judul produk wajib diisi.',
            'title.max' => 'Judul produk maksimal 200 karakter.',
            'summary.max' => 'Ringkasan maksimal 500 karakter.',
            'price.required' => 'Tentukan harga produk (isi 0 bila gratis).',
            'price.min' => 'Harga tidak boleh negatif.',
        ];
    }
}
