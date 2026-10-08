<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditLedgerResource;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperPortalController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user()->load('developerProfile');

        return response()->json([
            'data' => [
                'credits' => $user->uploadCredits(),
                'unlimited_uploads' => $user->hasUnlimitedUploads(),
                'products_count' => $user->products()->count(),
                'published_count' => $user->products()->published()->count(),
                'downloads_count' => (int) $user->products()->sum('downloads_count'),
                'prices' => [
                    'upload_slot' => (int) config('developer.upload_slot_price'),
                    'unlimited' => (int) config('developer.unlimited_price'),
                ],
                'ledger' => CreditLedgerResource::collection(
                    $user->creditLedger()->latest('id')->limit(20)->get(),
                )->resolve($request),
            ],
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()->with('items')->latest('id')->paginate(15);

        return $this->paginated($orders, OrderResource::class);
    }
}
