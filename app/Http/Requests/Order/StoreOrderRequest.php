<?php

namespace App\Http\Requests\Order;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in([
                Order::TYPE_APP_PURCHASE,
                Order::TYPE_UPLOAD_SLOTS,
                Order::TYPE_UNLIMITED_UPLOAD,
            ])],
            'quantity' => [
                'required_if:type,'.Order::TYPE_UPLOAD_SLOTS,
                'integer',
                'min:1',
                'max:50',
            ],
            'product_id' => [
                'required_if:type,'.Order::TYPE_APP_PURCHASE,
                'integer',
                Rule::exists('products', 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'Jenis pembelian tidak dikenal.',
            'quantity.required_if' => 'Tentukan dulu berapa slot yang ingin dibeli.',
            'quantity.min' => 'Minimal beli 1 slot.',
            'quantity.max' => 'Maksimal 50 slot per transaksi.',
            'product_id.required_if' => 'Pilih produk yang ingin dibeli.',
        ];
    }
}
