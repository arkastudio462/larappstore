<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVersionResource;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    private const SORTS = ['newest', 'popular', 'rating', 'price_low', 'price_high', 'title'];

    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->published()
            ->with(['category', 'developer'])
            ->withCount('reviews')
            ->withAvg('reviews as reviews_avg_rating', 'rating');

        $this->applyFilters($query, $request);
        $this->applySort($query, $request->string('sort', 'newest')->toString());

        $perPage = min(max($request->integer('per_page', 15), 1), 50);

        return $this->paginated(
            $query->paginate($perPage),
            ProductResource::class,
        );
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        $product->load(['category', 'developer', 'screenshots', 'latestVersion'])
            ->loadCount('reviews')
            ->loadAvg('reviews as reviews_avg_rating', 'rating');

        if ($viewer = $request->user()) {
            $product->setAttribute(
                'viewer_review_id',
                $product->reviews()->where('user_id', $viewer->id)->value('id'),
            );

            if (! $product->isFree()) {
                $product->setAttribute(
                    'viewer_has_purchased',
                    $product->purchases()->where('user_id', $viewer->id)->exists(),
                );
            }
        }

        return (new ProductResource($product))->response();
    }

    public function versions(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        $viewer = $request->user();
        $query = $product->versions();

        if ($viewer === null || ! ($viewer->id === $product->developer_id || $viewer->isAdmin())) {
            $query->published();
        }

        return $this->paginated($query->paginate(15), ProductVersionResource::class);
    }

    public function reviews(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        return $this->paginated(
            $product->reviews()->with('user')->latest('id')->paginate(15),
            ReviewResource::class,
        );
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($category = $request->string('category')->toString()) {
            $query->whereHas('category', fn (Builder $q) => $q->where('slug', $category));
        }

        $type = $request->string('type')->toString();

        if (in_array($type, Product::TYPES, true)) {
            $query->where('type', $type);
        }

        match ($request->string('price')->toString()) {
            'free' => $query->free(),
            'paid' => $query->where('price', '>', 0),
            default => null,
        };

        if ($request->boolean('featured')) {
            $query->featured();
        }
    }

    /**
     * Urutan selalu diakhiri tie-breaker `id` agar paginasi stabil (PRD §6).
     *
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match (in_array($sort, self::SORTS, true) ? $sort : 'newest') {
            'popular' => $query->orderByDesc('downloads_count')->orderByDesc('id'),
            'rating' => $query->orderByDesc('reviews_avg_rating')->orderByDesc('id'),
            'price_low' => $query->orderBy('price')->orderByDesc('id'),
            'price_high' => $query->orderByDesc('price')->orderByDesc('id'),
            'title' => $query->orderBy('title')->orderBy('id'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };
    }
}
