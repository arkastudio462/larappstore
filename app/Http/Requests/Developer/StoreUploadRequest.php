<?php

namespace App\Http\Requests\Developer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUploadRequest extends FormRequest
{
    /**
     * Batas ekstensi dan ukuran per jenis unggahan (PRD §6.1).
     *
     * @var array<string, array{extensions: array<int, string>, max_kb: int, label: string}>
     */
    public const KINDS = [
        'product_file' => [
            'extensions' => ['apk', 'ipa', 'exe', 'msi', 'dmg', 'zip', 'pdf', 'dll'],
            'max_kb' => 512_000,
            'label' => 'Berkas produk',
        ],
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_kb' => 5_120,
            'label' => 'Gambar',
        ],
        'video' => [
            'extensions' => ['mp4', 'webm', 'mov'],
            'max_kb' => 102_400,
            'label' => 'Video',
        ],
        'audio' => [
            'extensions' => ['mp3', 'wav', 'm4a', 'ogg', 'webm'],
            'max_kb' => 20_480,
            'label' => 'Audio',
        ],
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
            'kind' => ['required', 'string', Rule::in(array_keys(self::KINDS))],
            'file' => ['required', 'file'],
        ];
    }

    /**
     * Ekstensi dan ukuran hanya bisa diperiksa setelah `kind` valid.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $kind = self::KINDS[$this->string('kind')->toString()];
                $file = $this->file('file');
                $extension = strtolower($file->getClientOriginalExtension());

                if (! in_array($extension, $kind['extensions'], true)) {
                    $validator->errors()->add(
                        'file',
                        $kind['label'].' harus berformat: '.implode(', ', $kind['extensions']).'.',
                    );

                    return;
                }

                if ($file->getSize() > $kind['max_kb'] * 1024) {
                    $validator->errors()->add(
                        'file',
                        'Ukuran maksimal '.number_format($kind['max_kb'] / 1024, 0, ',', '.').' MB.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kind.in' => 'Jenis unggahan tidak dikenal.',
            'file.required' => 'Berkas wajib diunggah.',
        ];
    }
}
