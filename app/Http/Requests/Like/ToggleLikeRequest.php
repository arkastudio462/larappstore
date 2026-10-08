<?php

namespace App\Http\Requests\Like;

use App\Models\Post;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ToggleLikeRequest extends FormRequest
{
    /**
     * Jenis konten yang boleh disukai, dipetakan ke kelas modelnya.
     *
     * @var array<string, class-string>
     */
    public const TYPES = [
        'post' => Post::class,
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
            'likeable_type' => ['required', 'string', Rule::in(array_keys(self::TYPES))],
            'likeable_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'likeable_type.in' => 'Jenis konten yang ingin disukai tidak dikenal.',
        ];
    }
}
