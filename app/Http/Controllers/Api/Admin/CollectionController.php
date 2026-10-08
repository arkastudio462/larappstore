<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCollectionRequest;
use App\Http\Resources\CollectionResource;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CollectionController extends Controller
{
    public function index(): JsonResponse
    {
        $collections = Collection::query()
            ->withCount('products')
            ->with('products')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return CollectionResource::collection($collections)->response();
    }

    public function store(SaveCollectionRequest $request): JsonResponse
    {
        $collection = Collection::query()->create($this->payload($request));
        $this->syncProducts($request, $collection);

        return (new CollectionResource($collection->refresh()->load('products')))
            ->additional(['message' => 'Koleksi dibuat.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(SaveCollectionRequest $request, Collection $collection): JsonResponse
    {
        $collection->fill($this->payload($request, $collection))->save();
        $this->syncProducts($request, $collection);

        return (new CollectionResource($collection->refresh()->load('products')))
            ->additional(['message' => 'Koleksi diperbarui.'])
            ->response();
    }

    public function destroy(Collection $collection): JsonResponse
    {
        $collection->delete();

        return response()->json(['message' => 'Koleksi dihapus.']);
    }

    /**
     * Urutan produk di koleksi disimpan di kolom pivot `position`.
     */
    private function syncProducts(SaveCollectionRequest $request, Collection $collection): void
    {
        if (! $request->has('product_ids')) {
            return;
        }

        $sync = [];

        foreach (array_values((array) $request->input('product_ids')) as $index => $productId) {
            $sync[(int) $productId] = ['position' => $index];
        }

        $collection->products()->sync($sync);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SaveCollectionRequest $request, ?Collection $collection = null): array
    {
        $data = $request->safe()->only(['name', 'slug', 'description', 'cover_path', 'position', 'is_active']);

        if (array_key_exists('name', $data)) {
            $data['slug'] = $data['slug'] ?? $collection?->slug ?? Str::slug($data['name']);
        }

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }
}
