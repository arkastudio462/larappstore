<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Aksi hapus cepat admin. Otorisasi tetap lewat policy yang sama dengan
 * pemilik konten, yang memang mengizinkan admin (PRD §3).
 */
class ContentController extends Controller
{
    public function destroyPost(Post $post): JsonResponse
    {
        Gate::authorize('delete', $post);

        $post->withdrawFromFeed();
        $post->delete();

        return response()->json(['message' => 'Postingan dihapus.']);
    }

    public function destroyComment(Comment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $comment->purge();

        return response()->json(['message' => 'Komentar dihapus.']);
    }

    public function destroyProduct(Product $product): JsonResponse
    {
        Gate::authorize('delete', $product);

        $product->archive();
        $product->delete();

        return response()->json(['message' => 'Produk dihapus.']);
    }

    public function destroyReview(Review $review): JsonResponse
    {
        Gate::authorize('delete', $review);

        $review->delete();

        return response()->json(['message' => 'Ulasan dihapus.']);
    }
}
