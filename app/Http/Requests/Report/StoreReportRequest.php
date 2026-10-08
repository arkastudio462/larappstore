<?php

namespace App\Http\Requests\Report;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    /**
     * Jenis konten yang boleh dilaporkan, dipetakan ke kelas modelnya.
     *
     * @var array<string, class-string>
     */
    public const TYPES = [
        'post' => Post::class,
        'comment' => Comment::class,
        'product' => Product::class,
        'review' => Review::class,
    ];

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
            'reportable_type' => ['required', 'string', Rule::in(array_keys(self::TYPES))],
            'reportable_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reportable_type.in' => 'Jenis konten yang dilaporkan tidak dikenal.',
            'reason.required' => 'Tuliskan alasan laporanmu.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}
