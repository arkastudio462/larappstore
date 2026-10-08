<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class OrderController extends Controller
{
    /**
     * Buat pesanan lalu minta token pembayaran dari gerbang.
     *
     * Baris pesanan bertipe `pending`; kredit/produk baru diberikan setelah
     * pembayaran dikonfirmasi `OrderFulfillmentService` (PRD §5).
     */
    public function store(StoreOrderRequest $request, PaymentGateway $gateway): JsonResponse
    {
        $user = $request->user();
        $type = $request->string('type')->toString();

        if ($type !== Order::TYPE_APP_PURCHASE && ! $user->isDeveloper()) {
            return response()->json([
                'message' => 'Hanya developer yang bisa membeli slot upload.',
            ], 403);
        }

        $line = $this->lineItem($request, $user, $type);

        if ($line instanceof JsonResponse) {
            return $line;
        }

        $order = DB::transaction(function () use ($user, $type, $line): Order {
            $order = Order::query()->create([
                'order_no' => $this->orderNumber(),
                'user_id' => $user->getKey(),
                'type' => $type,
                'status' => Order::STATUS_PENDING,
                'gross_amount' => $line['quantity'] * $line['unit_price'],
            ]);

            $order->items()->create($line);

            return $order;
        });

        try {
            $payment = $gateway->createTransaction($order->load('items'));
        } catch (Throwable $exception) {
            report($exception);

            $order->forceFill(['status' => Order::STATUS_FAILED])->save();

            return response()->json([
                'message' => 'Gerbang pembayaran belum siap. Coba lagi nanti atau hubungi admin.',
            ], 503);
        }

        return (new OrderResource($order->load('items')))
            ->additional([
                'message' => 'Pesanan dibuat. Lanjutkan pembayaran.',
                'payment' => $payment,
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function check(Request $request, string $orderNo): JsonResponse
    {
        $order = Order::query()
            ->where('order_no', $orderNo)
            ->where('user_id', $request->user()->getKey())
            ->with('items')
            ->first();

        abort_if($order === null, 404, 'Pesanan tidak ditemukan.');

        return (new OrderResource($order))->response();
    }

    /**
     * Susun satu baris pesanan sesuai tipe. Mengembalikan `JsonResponse` bila
     * permintaan tidak bisa dilanjutkan (mis. produk gratis / sudah dibeli).
     *
     * @return array{type: string, name: string, quantity: int, unit_price: int, product_id: int|null}|JsonResponse
     */
    private function lineItem(StoreOrderRequest $request, User $user, string $type): array|JsonResponse
    {
        if ($type === Order::TYPE_APP_PURCHASE) {
            $product = Product::query()->published()->findOrFail($request->integer('product_id'));

            if ($product->isFree()) {
                return response()->json(['message' => 'Produk ini gratis, tidak perlu dibeli.'], 422);
            }

            if ($user->purchases()->where('product_id', $product->getKey())->exists()) {
                return response()->json(['message' => 'Kamu sudah memiliki produk ini.'], 422);
            }

            return [
                'type' => OrderItem::TYPE_APP,
                'name' => $product->title,
                'quantity' => 1,
                'unit_price' => $product->price,
                'product_id' => $product->getKey(),
            ];
        }

        if ($type === Order::TYPE_UPLOAD_SLOTS) {
            return [
                'type' => OrderItem::TYPE_UPLOAD_SLOTS,
                'name' => 'Slot upload aplikasi',
                'quantity' => $request->integer('quantity'),
                'unit_price' => (int) config('developer.upload_slot_price'),
                'product_id' => null,
            ];
        }

        return [
            'type' => OrderItem::TYPE_UNLIMITED,
            'name' => 'Paket upload tanpa batas',
            'quantity' => 1,
            'unit_price' => (int) config('developer.unlimited_price'),
            'product_id' => null,
        ];
    }

    private function orderNumber(): string
    {
        do {
            $number = 'INV-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('order_no', $number)->exists());

        return $number;
    }
}
