<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductPurchaseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $purchases = $request->user()->purchases()
            ->with(['product.category', 'product.developer'])
            ->latest('id')
            ->paginate(15);

        return $this->paginated($purchases, ProductPurchaseResource::class);
    }
}
