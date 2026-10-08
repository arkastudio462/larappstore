<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    private const STATUSES = [
        Order::STATUS_PENDING,
        Order::STATUS_PAID,
        Order::STATUS_FAILED,
        Order::STATUS_EXPIRED,
        Order::STATUS_CANCELLED,
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with(['user', 'items'])
            ->latest('id');

        $status = $request->string('status')->toString();

        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        return $this->paginated($query->paginate(20), OrderResource::class);
    }
}
