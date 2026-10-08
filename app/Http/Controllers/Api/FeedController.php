<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeedItemResource;
use App\Models\FeedItem;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    private const PER_PAGE = 15;

    /**
     * Timeline gabungan postingan + rilis aplikasi.
     *
     * Satu query cursor ke `feed_items` lalu subjeknya dimuat sekaligus per
     * jenis, sehingga jumlah query tidak bertambah seiring isi halaman.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FeedItem::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $viewer = $request->user();

        if ($request->string('scope')->toString() === 'following') {
            $query->whereIn(
                'actor_id',
                $viewer === null
                    ? collect()
                    : $viewer->following()->select('users.id'),
            );
        }

        match ($request->string('type')->toString()) {
            'post' => $query->where('subject_type', FeedItem::SUBJECT_POST),
            'product' => $query->whereIn('subject_type', [
                FeedItem::SUBJECT_PRODUCT,
                FeedItem::SUBJECT_RELEASE,
            ]),
            default => null,
        };

        $paginator = $query->cursorPaginate(self::PER_PAGE);
        $items = $paginator->getCollection();

        $posts = Post::query()
            ->with('user')
            ->withExists(['likedByViewer as is_liked'])
            ->whereIn('id', $items->where('subject_type', FeedItem::SUBJECT_POST)->pluck('subject_id'))
            ->get()
            ->keyBy('id');

        $products = Product::query()
            ->with(['category', 'developer'])
            ->whereIn('id', $items->where('subject_type', FeedItem::SUBJECT_PRODUCT)->pluck('subject_id'))
            ->get()
            ->keyBy('id');

        $releases = ProductVersion::query()
            ->with(['product.category', 'product.developer'])
            ->whereIn('id', $items->where('subject_type', FeedItem::SUBJECT_RELEASE)->pluck('subject_id'))
            ->get()
            ->keyBy('id');

        $items->each(function (FeedItem $item) use ($posts, $products, $releases): void {
            $subject = match ($item->subject_type) {
                FeedItem::SUBJECT_POST => $posts->get($item->subject_id),
                FeedItem::SUBJECT_PRODUCT => $products->get($item->subject_id),
                FeedItem::SUBJECT_RELEASE => $releases->get($item->subject_id),
                default => null,
            };

            if ($subject !== null) {
                $item->setRelation('subject', $subject);
            }
        });

        // Subjek yang sudah dihapus (mis. postingan soft delete) dilewati.
        $visible = $items
            ->filter(fn (FeedItem $item): bool => $item->relationLoaded('subject'))
            ->values();

        return FeedItemResource::collection($visible)
            ->additional([
                'meta' => [
                    'per_page' => $paginator->perPage(),
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ])
            ->response();
    }
}
