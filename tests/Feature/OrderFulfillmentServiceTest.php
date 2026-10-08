<?php

namespace Tests\Feature;

use App\Models\CreditLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\CreditPurchased;
use App\Notifications\PaymentSucceeded;
use App\Notifications\PurchaseCompleted;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderFulfillmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fulfill_grants_slots_and_releases_the_order(): void
    {
        Notification::fake();

        $user = $this->developer(credits: 2);
        $order = $this->creditOrder($user, Order::TYPE_UPLOAD_SLOTS, quantity: 3);

        $this->assertTrue(app(OrderFulfillmentService::class)->fulfill($order));

        $this->assertSame(5, $user->developerProfile->refresh()->upload_credits);
        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);
        $this->assertNotNull($order->refresh()->paid_at);
        $this->assertDatabaseHas('credit_ledger', [
            'user_id' => $user->id,
            'delta' => 3,
            'type' => CreditLedger::TYPE_PURCHASE,
            'balance_after' => 5,
            'order_id' => $order->id,
        ]);
        Notification::assertSentTo($user, CreditPurchased::class);
    }

    public function test_fulfill_is_idempotent_on_repeated_webhooks(): void
    {
        Notification::fake();

        $user = $this->developer(credits: 0);
        $order = $this->creditOrder($user, Order::TYPE_UPLOAD_SLOTS, quantity: 2);
        $service = app(OrderFulfillmentService::class);

        $this->assertTrue($service->fulfill($order));
        $this->assertFalse($service->fulfill($order));
        $this->assertFalse($service->fulfill($order->refresh()));

        $this->assertSame(2, $user->developerProfile->refresh()->upload_credits);
        $this->assertSame(1, CreditLedger::query()->where('order_id', $order->id)->count());
        Notification::assertSentToTimes($user, CreditPurchased::class, 1);
    }

    public function test_fulfill_enables_unlimited_uploads(): void
    {
        $user = $this->developer(credits: 1);
        $order = $this->creditOrder($user, Order::TYPE_UNLIMITED_UPLOAD);

        app(OrderFulfillmentService::class)->fulfill($order);

        $profile = $user->developerProfile->refresh();

        $this->assertTrue($profile->unlimited_uploads);
        $this->assertSame(1, $profile->upload_credits);
        $this->assertDatabaseHas('credit_ledger', [
            'user_id' => $user->id,
            'delta' => 0,
            'type' => CreditLedger::TYPE_PURCHASE,
            'order_id' => $order->id,
        ]);
    }

    public function test_fulfill_records_a_product_purchase_and_notifies_both_parties(): void
    {
        Notification::fake();

        $developer = $this->developer();
        $buyer = User::factory()->create();
        $product = Product::factory()->paid(25000)->for($developer, 'developer')->create();
        $order = $this->productOrder($buyer, $product);

        $this->assertTrue(app(OrderFulfillmentService::class)->fulfill($order));

        $this->assertDatabaseHas('product_purchases', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
        ]);
        Notification::assertSentTo($buyer, PurchaseCompleted::class);
        Notification::assertSentTo($developer, PaymentSucceeded::class);
    }

    public function test_fulfill_does_not_duplicate_a_product_purchase(): void
    {
        Notification::fake();

        $developer = $this->developer();
        $buyer = User::factory()->create();
        $product = Product::factory()->paid(25000)->for($developer, 'developer')->create();
        $order = $this->productOrder($buyer, $product);
        $service = app(OrderFulfillmentService::class);

        $service->fulfill($order);
        $service->fulfill($order->refresh());

        $this->assertDatabaseCount('product_purchases', 1);
        Notification::assertSentToTimes($buyer, PurchaseCompleted::class, 1);
    }

    public function test_fulfill_does_not_notify_the_developer_about_their_own_purchase(): void
    {
        Notification::fake();

        $developer = $this->developer();
        $product = Product::factory()->paid(25000)->for($developer, 'developer')->create();
        $order = $this->productOrder($developer, $product);

        app(OrderFulfillmentService::class)->fulfill($order);

        Notification::assertNotSentTo($developer, PaymentSucceeded::class);
        Notification::assertSentTo($developer, PurchaseCompleted::class);
    }

    private function creditOrder(User $user, string $type, int $quantity = 1): Order
    {
        $unitPrice = $type === Order::TYPE_UPLOAD_SLOTS
            ? (int) config('developer.upload_slot_price')
            : (int) config('developer.unlimited_price');

        $order = $user->orders()->create([
            'order_no' => 'INV-TEST-'.$user->id.'-'.$quantity.'-'.$type,
            'type' => $type,
            'status' => Order::STATUS_PENDING,
            'gross_amount' => $quantity * $unitPrice,
        ]);

        $order->items()->create([
            'type' => $type === Order::TYPE_UPLOAD_SLOTS ? OrderItem::TYPE_UPLOAD_SLOTS : OrderItem::TYPE_UNLIMITED,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'name' => 'Uji',
        ]);

        return $order;
    }

    private function productOrder(User $buyer, Product $product): Order
    {
        $order = $buyer->orders()->create([
            'order_no' => 'INV-APP-'.$buyer->id.'-'.$product->id,
            'type' => Order::TYPE_APP_PURCHASE,
            'status' => Order::STATUS_PENDING,
            'gross_amount' => $product->price,
        ]);

        $order->items()->create([
            'type' => OrderItem::TYPE_APP,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $product->price,
            'name' => $product->title,
        ]);

        return $order;
    }

    private function developer(int $credits = 0): User
    {
        $user = User::factory()->developer()->create();

        $user->developerProfile()->create([
            'studio_name' => 'Studio '.$user->id,
            'slug' => 'studio-'.$user->id,
            'upload_credits' => $credits,
        ]);

        return $user;
    }
}
