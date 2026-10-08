<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $publishedOnly = fn ($query) => $query->published();

        $categories = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->active()->withCount(['products' => $publishedOnly])])
            ->withCount(['products' => $publishedOnly])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories)->response();
    }
}
