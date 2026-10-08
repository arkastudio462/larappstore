<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Developer\StoreVersionRequest;
use App\Http\Resources\ProductVersionResource;
use App\Models\Product;
use App\Models\ProductVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DeveloperVersionController extends Controller
{
    public function store(StoreVersionRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $data = $request->safe()->only([
            'version', 'file_path', 'file_size', 'file_type', 'checksum_sha256', 'changelog',
        ]);

        if ($product->versions()->where('version', $data['version'])->exists()) {
            return response()->json([
                'message' => 'Versi '.$data['version'].' sudah ada untuk produk ini.',
            ], 422);
        }

        $version = $product->versions()->create([
            ...$data,
            'checksum_sha256' => $data['checksum_sha256'] ?? null,
            'changelog' => $data['changelog'] ?? null,
            'status' => ProductVersion::STATUS_DRAFT,
            'is_latest' => false,
            'published_at' => null,
        ]);

        return (new ProductVersionResource($version))
            ->additional(['message' => 'Versi draft dibuat. Terbitkan bila sudah siap.'])
            ->response()
            ->setStatusCode(201);
    }

    public function publish(Product $product, ProductVersion $version): JsonResponse
    {
        abort_unless($version->product_id === $product->getKey(), 404, 'Versi tidak ditemukan.');

        Gate::authorize('publish', $product);

        $published = $version->publish();

        return (new ProductVersionResource($version->refresh()))
            ->additional(['message' => $published ? 'Versi diterbitkan.' : 'Versi sudah terbit.'])
            ->response();
    }
}
