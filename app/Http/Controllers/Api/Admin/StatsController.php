<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'users' => User::query()->count(),
                'developers' => User::query()->where('role', User::ROLE_DEVELOPER)->count(),
                'banned' => User::query()->whereNotNull('banned_at')->count(),
                'new_users_7d' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
                'posts' => Post::query()->count(),
                'comments' => Comment::query()->count(),
                'products' => Product::query()->count(),
                'published_products' => Product::query()->published()->count(),
                'reviews' => Review::query()->count(),
                'downloads' => (int) Product::query()->sum('downloads_count'),
                'orders' => Order::query()->count(),
                'paid_orders' => Order::query()->where('status', Order::STATUS_PAID)->count(),
                'revenue' => (int) Order::query()->where('status', Order::STATUS_PAID)->sum('gross_amount'),
                'open_reports' => Report::query()->where('status', Report::STATUS_OPEN)->count(),
            ],
        ]);
    }
}
