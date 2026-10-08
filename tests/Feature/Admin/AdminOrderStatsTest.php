<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_index_lists_orders_with_buyer_and_items(): void
    {
        $buyer = User::factory()->create(['username' => 'budi']);
        $product = Product::factory()->paid(25000)->create();
        $order = $this->order($buyer, Order::STATUS_PAID, $product);
        $this->order(User::factory()->create(), Order::STATUS_PENDING, $product);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/orders?status=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_no', $order->order_no)
            ->assertJsonPath('data.0.user.username', 'budi')
            ->assertJsonPath('data.0.items.0.product_id', $product->id);
    }

    public function test_stats_summarises_the_platform(): void
    {
        User::factory()->developer()->create();
        User::factory()->create(['banned_at' => now()]);
        $buyer = User::factory()->create();

        Product::factory()->create(['downloads_count' => 120]);
        Post::factory()->create();

        $paid = $this->order($buyer, Order::STATUS_PAID, Product::factory()->paid(25000)->create());
        $paid->forceFill(['gross_amount' => 45000])->save();
        $this->order($buyer, Order::STATUS_PENDING, Product::factory()->paid(15000)->create());

        Report::query()->create([
            'reporter_id' => $buyer->id,
            'reportable_type' => Post::class,
            'reportable_id' => 1,
            'reason' => 'Uji',
            'status' => Report::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->admin())->getJson('/api/admin/stats')->assertOk();

        $response
            ->assertJsonPath('data.developers', 1)
            ->assertJsonPath('data.banned', 1)
            ->assertJsonPath('data.orders', 2)
            ->assertJsonPath('data.paid_orders', 1)
            ->assertJsonPath('data.revenue', 45000)
            ->assertJsonPath('data.downloads', 120)
            ->assertJsonPath('data.open_reports', 1);

        $this->assertGreaterThanOrEqual(4, $response->json('data.users'));
    }

    public function test_order_and_stats_routes_require_the_admin_role(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/orders')
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/stats')
            ->assertForbidden();
    }

    private function order(User $buyer, string $status, Product $product): Order
    {
        $order = $buyer->orders()->create([
            'order_no' => 'INV-'.$buyer->id.'-'.$status.'-'.uniqid(),
            'type' => Order::TYPE_APP_PURCHASE,
            'status' => $status,
            'gross_amount' => $product->price,
        ]);

        $order->items()->create([
            'type' => 'app',
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $product->price,
            'name' => $product->title,
        ]);

        return $order;
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
