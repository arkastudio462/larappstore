<?php

namespace App\Services;

use App\Models\CreditLedger;
use App\Models\DeveloperProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\User;
use App\Notifications\CreditPurchased;
use App\Notifications\PaymentSucceeded;
use App\Notifications\PurchaseCompleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menyelesaikan pesanan yang sudah dibayar.
 *
 * Dirancang idempoten untuk webhook yang bisa terkirim ulang: baris pesanan
 * dikunci di dalam transaksi dan status hanya berpindah dari `pending` ke
 * `paid`, sementara `product_purchases` dibuat dengan `firstOrCreate`
 * (PRD §6.3). Kredit tidak mungkin bertambah dua kali.
 */
class OrderFulfillmentService
{
    /**
     * @return bool true bila pesanan baru saja diselesaikan; false bila sudah pernah.
     */
    public function fulfill(Order $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->isPaid()) {
                return false;
            }

            $locked->forceFill([
                'status' => Order::STATUS_PAID,
                'paid_at' => now(),
            ])->save();

            $user = User::query()->findOrFail($locked->user_id);

            match ($locked->type) {
                Order::TYPE_APP_PURCHASE => $this->grantProductPurchase($locked, $user),
                Order::TYPE_UNLIMITED_UPLOAD => $this->grantUnlimited($locked, $user),
                default => $this->grantUploadSlots($locked, $user),
            };

            return true;
        });
    }

    private function grantUploadSlots(Order $order, User $user): void
    {
        $quantity = max(1, (int) $order->items()->sum('quantity'));
        $profile = $this->profileFor($user);

        $profile->increment('upload_credits', $quantity);
        $profile->refresh();

        CreditLedger::query()->create([
            'user_id' => $user->id,
            'delta' => $quantity,
            'type' => CreditLedger::TYPE_PURCHASE,
            'balance_after' => $profile->upload_credits,
            'order_id' => $order->id,
            'description' => $quantity.' slot upload dibeli',
        ]);

        $user->notify(new CreditPurchased($order, $quantity));
    }

    private function grantUnlimited(Order $order, User $user): void
    {
        $profile = $this->profileFor($user);
        $profile->forceFill(['unlimited_uploads' => true])->save();

        CreditLedger::query()->create([
            'user_id' => $user->id,
            'delta' => 0,
            'type' => CreditLedger::TYPE_PURCHASE,
            'balance_after' => $profile->upload_credits,
            'order_id' => $order->id,
            'description' => 'Paket upload tanpa batas aktif',
        ]);

        $user->notify(new CreditPurchased($order, 0, unlimited: true));
    }

    private function grantProductPurchase(Order $order, User $user): void
    {
        $productId = $order->items()->whereNotNull('product_id')->value('product_id');

        if ($productId === null) {
            return;
        }

        $product = Product::query()->withTrashed()->find($productId);

        if ($product === null) {
            return;
        }

        ProductPurchase::query()->firstOrCreate(
            ['user_id' => $user->id, 'product_id' => $product->id],
            ['order_id' => $order->id],
        );

        $user->notify(new PurchaseCompleted($product, $order));

        $developer = $product->developer;

        if ($developer !== null && ! $developer->is($user)) {
            $developer->notify(new PaymentSucceeded($product, $user));
        }
    }

    private function profileFor(User $user): DeveloperProfile
    {
        return DeveloperProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'studio_name' => Str::limit($user->name, 100, ''),
                'slug' => 'studio-'.$user->id,
                'upload_credits' => 0,
            ],
        );
    }
}
