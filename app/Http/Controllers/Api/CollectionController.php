<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;

class CollectionController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $collection = Collection::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with(['products' => fn ($query) => $query
                ->published()
                ->with(['category', 'developer'])
                ->withCount('reviews')
                ->withAvg('reviews as reviews_avg_rating', 'rating')])
            ->withCount(['products' => fn ($query) => $query->published()])
            ->first();

        abort_if($collection === null, 404, 'Koleksi tidak ditemukan.');

        return (new CollectionResource($collection))->response();
    }
}
