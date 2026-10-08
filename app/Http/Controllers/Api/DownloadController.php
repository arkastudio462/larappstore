<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DownloadController extends Controller
{
    /**
     * Mulai unduhan produk.
     *
     * Produk gratis memakai tautan publik; produk berbayar hanya untuk pembeli
     * dan memakai presigned GET berumur pendek (PRD §6.2).
     */
    public function store(Request $request, Product $product, UploadService $uploads): JsonResponse
    {
        Gate::authorize('view', $product);

        $version = $product->versions()
            ->published()
            ->orderByDesc('is_latest')
            ->orderByDesc('id')
            ->first();

        if ($version === null) {
            return response()->json([
                'message' => 'Produk ini belum punya versi yang bisa diunduh.',
            ], 404);
        }

        $signed = false;

        if (! $product->isFree()) {
            $viewer = $request->user();
            $purchased = $viewer !== null
                && $product->purchases()->where('user_id', $viewer->id)->exists();

            if (! $purchased) {
                return response()->json([
                    'message' => 'Produk berbayar. Selesaikan pembelian dulu untuk mengunduh.',
                ], 402);
            }

            $signed = true;
        }

        $product->increment('downloads_count');
        $version->increment('download_count');

        return response()->json([
            'message' => 'Unduhan dimulai.',
            'data' => [
                'url' => $uploads->downloadUrl($version->file_path, signed: $signed),
                'version' => $version->version,
                'file_size' => $version->file_size,
                'file_type' => $version->file_type,
                'expires_in' => $signed ? 300 : null,
            ],
        ]);
    }
}
