<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Unggahan lewat server ke R2 (PRD §6.1): browser mengirim berkas sebagai
 * multipart POST, lalu Laravel yang menulisnya ke R2 lewat driver S3. Validasi
 * ekstensi dan ukuran dilakukan di Form Request sebelum metode ini dipanggil.
 */
class UploadService
{
    public const DISK = 'r2';

    /**
     * @return array{key: string, url: string, size: int, content_type: string|null}
     */
    public function store(UploadedFile $file, string $kind): array
    {
        $key = $this->keyFor($kind, $file->getClientOriginalExtension());

        $disk = Storage::disk(self::DISK);
        $disk->putFileAs(dirname($key), $file, basename($key), 'public');

        return [
            'key' => $key,
            'url' => $this->downloadUrl($key),
            'size' => (int) $file->getSize(),
            'content_type' => $file->getMimeType(),
        ];
    }

    /**
     * URL unduhan. Produk gratis memakai domain publik R2; produk berbayar
     * memakai presigned GET berumur pendek setelah pembelian dicek (PRD §6.2).
     */
    public function downloadUrl(?string $path, bool $signed = false, int $minutes = 5): ?string
    {
        if ($path === null) {
            return null;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        if ($signed) {
            return Storage::disk(self::DISK)->temporaryUrl($path, now()->addMinutes($minutes));
        }

        $base = rtrim((string) config('filesystems.disks.r2.url'), '/');

        return $base === '' ? $path : $base.'/'.ltrim($path, '/');
    }

    private function keyFor(string $kind, string $extension): string
    {
        $extension = strtolower($extension);
        $suffix = $extension !== '' ? '.'.$extension : '';

        return $kind.'/'.now()->format('Y/m').'/'.Str::uuid()->toString().$suffix;
    }
}
