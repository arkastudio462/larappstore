<?php

namespace App\Http\Requests\Developer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVersionRequest extends FormRequest
{
    public const FILE_TYPES = ['apk', 'ipa', 'exe', 'msi', 'dmg', 'zip', 'pdf', 'dll'];

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
            'version' => ['required', 'string', 'max:50'],
            'file_path' => ['required', 'string', 'max:500'],
            'file_size' => ['required', 'integer', 'min:1'],
            'file_type' => ['required', 'string', Rule::in(self::FILE_TYPES)],
            'checksum_sha256' => ['nullable', 'string', 'size:64'],
            'changelog' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.required' => 'Nomor versi wajib diisi.',
            'file_path.required' => 'Berkas rilis belum diunggah.',
            'file_size.required' => 'Ukuran berkas tidak diketahui.',
            'file_type.in' => 'Tipe berkas tidak didukung.',
        ];
    }
}
