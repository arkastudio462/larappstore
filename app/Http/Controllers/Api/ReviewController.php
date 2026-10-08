<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        $viewer = $request->user();

        if ($product->reviews()->where('user_id', $viewer->id)->exists()) {
            return response()->json([
                'message' => 'Kamu sudah memberi ulasan untuk produk ini.',
            ], 422);
        }

        try {
            $review = $product->reviews()->create([
                'user_id' => $viewer->id,
                'rating' => $request->integer('rating'),
                'body' => $request->input('body'),
                'status' => Review::STATUS_PUBLISHED,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Permintaan ganda dari unggahan serentak tetap ditolak dengan pesan sama.
            return response()->json([
                'message' => 'Kamu sudah memberi ulasan untuk produk ini.',
            ], 422);
        }

        return (new ReviewResource($review->load('user')))
            ->additional(['message' => 'Ulasan dikirim. Terima kasih!'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateReviewRequest $request, Review $review): JsonResponse
    {
        Gate::authorize('update', $review);

        $review->fill($request->safe()->only(['rating', 'body']))->save();

        return (new ReviewResource($review->load('user')))
            ->additional(['message' => 'Ulasan diperbarui.'])
            ->response();
    }
}
