<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\ProductSearchService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const LIMIT = 30;

    public function index(Request $request, ProductSearchService $search): JsonResponse
    {
        $query = trim($request->string('q')->toString());

        $products = $query === ''
            ? new EloquentCollection
            : EloquentCollection::make($search->search($query, self::LIMIT)->all())
                ->load(['category', 'developer'])
                ->loadCount('reviews')
                ->loadAvg('reviews as reviews_avg_rating', 'rating');

        return ProductResource::collection($products)
            ->additional(['meta' => ['query' => $query, 'total' => $products->count()]])
            ->response();
    }
}
