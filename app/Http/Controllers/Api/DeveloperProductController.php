<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Developer\StoreProductRequest;
use App\Http\Requests\Developer\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\CreditLedger;
use App\Models\DeveloperProfile;
use App\Models\DownloadStatsDaily;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DeveloperProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->products()
            ->with('category')
            ->withCount('reviews')
            ->withAvg('reviews as reviews_avg_rating', 'rating')
            ->latest('id');

        return $this->paginated($query->paginate(15), ProductResource::class);
    }

    /**
     * Konsumsi satu kredit upload secara atomik; habis → `402` (PRD §5).
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->consumeUploadCredit()) {
            return response()->json([
                'message' => 'Slot upload habis. Beli slot tambahan atau paket unlimited untuk melanjutkan.',
                'data' => [
                    'credits' => 0,
                    'unlimited_uploads' => false,
                ],
            ], 402);
        }

        $data = $request->safe()->only([
            'category_id', 'type', 'title', 'summary', 'description', 'icon_path', 'banner_path', 'price',
        ]);

        $product = $user->products()->create([
            'category_id' => $data['category_id'],
            'type' => $data['type'],
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'description' => $data['description'] ?? null,
            'icon_path' => $data['icon_path'] ?? null,
            'banner_path' => $data['banner_path'] ?? null,
            'price' => $data['price'],
            'currency' => 'IDR',
            'slug' => $this->uniqueSlug($data['title']),
            'status' => Product::STATUS_DRAFT,
            'published_at' => null,
        ]);

        $this->recordUsage($user, $product);

        return (new ProductResource($product->load('category')))
            ->additional(['message' => 'Produk dibuat sebagai draft.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $product->fill($request->safe()->only([
            'category_id', 'type', 'title', 'summary', 'description', 'icon_path', 'banner_path', 'price',
        ]))->save();

        return (new ProductResource($product->load('category')))
            ->additional(['message' => 'Produk diperbarui.'])
            ->response();
    }

    public function publish(Product $product): JsonResponse
    {
        Gate::authorize('publish', $product);

        $published = $product->publish();

        return (new ProductResource($product->load('category')))
            ->additional(['message' => $published ? 'Produk diterbitkan.' : 'Produk sudah terbit.'])
            ->response();
    }

    public function archive(Product $product): JsonResponse
    {
        Gate::authorize('archive', $product);

        $product->archive();

        return (new ProductResource($product->load('category')))
            ->additional(['message' => 'Produk diarsipkan.'])
            ->response();
    }

    public function stats(Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $daily = DownloadStatsDaily::query()
            ->where('product_id', $product->getKey())
            ->with('version:id,version')
            ->orderByDesc('date')
            ->limit(60)
            ->get();

        return response()->json([
            'data' => [
                'downloads_count' => $product->downloads_count,
                'versions_count' => $product->versions()->count(),
                'daily' => $daily->map(fn (DownloadStatsDaily $row): array => [
                    'date' => $row->date->toDateString(),
                    'version' => $row->version?->version,
                    'count' => $row->count,
                ])->all(),
            ],
        ]);
    }

    private function recordUsage(User $user, Product $product): void
    {
        if ($user->hasUnlimitedUploads()) {
            return;
        }

        CreditLedger::query()->create([
            'user_id' => $user->getKey(),
            'delta' => -1,
            'type' => CreditLedger::TYPE_USAGE,
            'balance_after' => (int) DeveloperProfile::query()
                ->where('user_id', $user->getKey())
                ->value('upload_credits'),
            'description' => 'Upload produk: '.$product->title,
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'produk';
        $slug = $base;
        $suffix = 1;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
