<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(PaymentGateway::class, fn (): PaymentGateway => new class implements PaymentGateway
        {
            public function createTransaction(Order $order): array
            {
                return [
                    'token' => 'snap-'.$order->order_no,
                    'redirect_url' => 'https://app.sandbox.midtrans.test/'.$order->order_no,
                ];
            }

            public function verifyNotification(array $payload): bool
            {
                return false;
            }
        });
    }

    public function test_store_creates_a_pending_upload_slot_order_with_quantity_pricing(): void
    {
        $developer = $this->developer();

        $this->actingAs($developer)
            ->postJson('/api/orders', ['type' => Order::TYPE_UPLOAD_SLOTS, 'quantity' => 3])
            ->assertCreated()
            ->assertJsonPath('data.type', Order::TYPE_UPLOAD_SLOTS)
            ->assertJsonPath('data.status', Order::STATUS_PENDING)
            ->assertJsonPath('data.gross_amount', 45000)
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.items.0.unit_price', 15000)
            ->assertJsonPath('data.items.0.subtotal', 45000)
            ->assertJsonPath('payment.token', fn ($token) => str_starts_with($token, 'snap-'));

        $this->assertDatabaseHas('orders', [
            'user_id' => $developer->id,
            'type' => Order::TYPE_UPLOAD_SLOTS,
            'status' => Order::STATUS_PENDING,
            'gross_amount' => 45000,
        ]);
        $this->assertDatabaseHas('order_items', ['type' => OrderItem::TYPE_UPLOAD_SLOTS]);
    }

    public function test_store_creates_an_unlimited_order(): void
    {
        $this->actingAs($this->developer())
            ->postJson('/api/orders', ['type' => Order::TYPE_UNLIMITED_UPLOAD])
            ->assertCreated()
            ->assertJsonPath('data.gross_amount', 150000)
            ->assertJsonPath('data.items.0.type', OrderItem::TYPE_UNLIMITED);
    }

    public function test_store_requires_a_quantity_for_slot_orders(): void
    {
        $this->actingAs($this->developer())
            ->postJson('/api/orders', ['type' => Order::TYPE_UPLOAD_SLOTS])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity')
            ->assertJsonPath('errors.quantity.0', 'Tentukan dulu berapa slot yang ingin dibeli.');
    }

    public function test_store_limits_the_quantity(): void
    {
        $this->actingAs($this->developer())
            ->postJson('/api/orders', ['type' => Order::TYPE_UPLOAD_SLOTS, 'quantity' => 100])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_store_forbids_credit_purchases_by_regular_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/orders', ['type' => Order::TYPE_UNLIMITED_UPLOAD])
            ->assertForbidden()
            ->assertJsonPath('message', 'Hanya developer yang bisa membeli slot upload.');
    }

    public function test_store_lets_a_regular_user_buy_a_product(): void
    {
        $product = Product::factory()->paid(25000)->create(['slug' => 'berbayar']);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)
            ->postJson('/api/orders', ['type' => Order::TYPE_APP_PURCHASE, 'product_id' => $product->id])
            ->assertCreated()
            ->assertJsonPath('data.gross_amount', 25000)
            ->assertJsonPath('data.items.0.type', OrderItem::TYPE_APP)
            ->assertJsonPath('data.items.0.product_id', $product->id);
    }

    public function test_store_rejects_buying_a_free_product(): void
    {
        $product = Product::factory()->create(['slug' => 'gratis', 'price' => 0]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/orders', ['type' => Order::TYPE_APP_PURCHASE, 'product_id' => $product->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Produk ini gratis, tidak perlu dibeli.');
    }

    public function test_store_rejects_buying_a_product_twice(): void
    {
        $product = Product::factory()->paid(25000)->create(['slug' => 'berbayar']);
        $buyer = User::factory()->create();
        $buyer->purchases()->create(['product_id' => $product->id]);

        $this->actingAs($buyer)
            ->postJson('/api/orders', ['type' => Order::TYPE_APP_PURCHASE, 'product_id' => $product->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kamu sudah memiliki produk ini.');
    }

    public function test_store_requires_a_product_for_app_purchase(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/orders', ['type' => Order::TYPE_APP_PURCHASE])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/orders', ['type' => Order::TYPE_UNLIMITED_UPLOAD])->assertUnauthorized();
    }

    public function test_check_returns_the_owners_order(): void
    {
        $developer = $this->developer();
        $order = $developer->orders()->create([
            'order_no' => 'INV-260101-AAAAAA',
            'type' => Order::TYPE_UPLOAD_SLOTS,
            'status' => Order::STATUS_PENDING,
            'gross_amount' => 15000,
        ]);

        $this->actingAs($developer)
            ->postJson("/api/orders/{$order->order_no}/check")
            ->assertOk()
            ->assertJsonPath('data.order_no', 'INV-260101-AAAAAA')
            ->assertJsonPath('data.status', Order::STATUS_PENDING);
    }

    public function test_check_hides_orders_belonging_to_other_users(): void
    {
        $other = $this->developer();
        $order = $other->orders()->create([
            'order_no' => 'INV-260101-BBBBBB',
            'type' => Order::TYPE_UPLOAD_SLOTS,
            'status' => Order::STATUS_PENDING,
            'gross_amount' => 15000,
        ]);

        $this->actingAs($this->developer())
            ->postJson("/api/orders/{$order->order_no}/check")
            ->assertNotFound();
    }

    private function developer(): User
    {
        $user = User::factory()->developer()->create();

        $user->developerProfile()->create([
            'studio_name' => 'Studio '.$user->id,
            'slug' => 'studio-'.$user->id,
            'upload_credits' => 0,
        ]);

        return $user;
    }
}
