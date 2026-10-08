<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories)->response();
    }

    public function store(SaveCategoryRequest $request): JsonResponse
    {
        $category = Category::query()->create($this->payload($request));

        return (new CategoryResource($category->refresh()))
            ->additional(['message' => 'Kategori dibuat.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(SaveCategoryRequest $request, Category $category): JsonResponse
    {
        $category->fill($this->payload($request, $category))->save();

        return (new CategoryResource($category->refresh()))
            ->additional(['message' => 'Kategori diperbarui.'])
            ->response();
    }

    /**
     * Kategori tidak dihapus bila masih dipakai produk atau punya sub-kategori,
     * karena foreign key memasang `cascadeOnDelete` (produk bisa ikut terhapus).
     */
    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Kategori masih dipakai produk. Pindahkan produknya dulu.',
            ], 422);
        }

        if ($category->children()->exists()) {
            return response()->json([
                'message' => 'Kategori masih punya sub-kategori. Pindahkan dulu.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Kategori dihapus.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SaveCategoryRequest $request, ?Category $category = null): array
    {
        $data = $request->safe()->only(['parent_id', 'name', 'slug', 'icon', 'position', 'is_active']);

        if (array_key_exists('name', $data)) {
            $data['slug'] = $data['slug'] ?? $category?->slug ?? Str::slug($data['name']);
        }

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }
}
