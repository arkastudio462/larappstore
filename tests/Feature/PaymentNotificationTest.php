<?php

namespace Tests\Feature;

use App\Models\CreditLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\CreditPurchased;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.midtrans.server_key' => self::SERVER_KEY]);
    }

    public function test_notification_rejects_an_invalid_signature(): void
    {
        $order = $this->order();

        $this->postJson('/api/payments/midtrans/notification', $this->payload($order, signature: 'palsu'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Tanda tangan notifikasi tidak valid.');

        $this->assertSame(Order::STATUS_PENDING, $order->refresh()->status);
        $this->assertDatabaseCount('credit_ledger', 0);
    }

    public function test_notification_returns_404_for_an_unknown_order(): void
    {
        $payload = [
            'order_id' => 'INV-TIDAK-ADA',
            'status_code' => '200',
            'gross_amount' => '15000.00',
            'transaction_status' => 'settlement',
        ];
        $payload['signature_key'] = $this->signature($payload);

        $this->postJson('/api/payments/midtrans/notification', $payload)->assertNotFound();
    }

    public function test_settlement_fulfills_the_order_and_grants_credits(): void
    {
        Notification::fake();

        $user = $this->developer(credits: 0);
        $order = $this->order($user, quantity: 2);

        $this->postJson('/api/payments/midtrans/notification', $this->payload($order, [
            'payment_type' => 'bank_transfer',
        ]))->assertOk();

        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);
        $this->assertSame('bank_transfer', $order->refresh()->payment_method);
        $this->assertSame(2, $user->developerProfile->refresh()->upload_credits);
        $this->assertDatabaseHas('credit_ledger', ['order_id' => $order->id, 'delta' => 2]);
        Notification::assertSentTo($user, CreditPurchased::class);
    }

    public function test_capture_with_accepted_fraud_status_fulfills_the_order(): void
    {
        $order = $this->order($this->developer());

        $this->postJson('/api/payments/midtrans/notification', $this->payload($order, [
            'transaction_status' => 'capture',
            'fraud_status' => 'accept',
        ]))->assertOk();

        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);
    }

    public function test_capture_with_challenge_fraud_status_is_left_pending(): void
    {
        $order = $this->order($this->developer());

        $this->postJson('/api/payments/midtrans/notification', $this->payload($order, [
            'transaction_status' => 'capture',
            'fraud_status' => 'challenge',
        ]))->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->refresh()->status);
        $this->assertDatabaseCount('credit_ledger', 0);
    }

    public function test_failed_statuses_update_the_order_without_granting_credits(): void
    {
        $user = $this->developer();

        foreach ([
            'deny' => Order::STATUS_FAILED,
            'cancel' => Order::STATUS_CANCELLED,
            'expire' => Order::STATUS_EXPIRED,
        ] as $transactionStatus => $expected) {
            $order = $this->order($user, suffix: $transactionStatus);

            $this->postJson('/api/payments/midtrans/notification', $this->payload($order, [
                'transaction_status' => $transactionStatus,
            ]))->assertOk();

            $this->assertSame($expected, $order->refresh()->status);
        }

        $this->assertDatabaseCount('credit_ledger', 0);
    }

    public function test_repeated_settlement_notifications_do_not_double_grant(): void
    {
        Notification::fake();

        $user = $this->developer(credits: 0);
        $order = $this->order($user, quantity: 3);
        $payload = $this->payload($order);

        $this->postJson('/api/payments/midtrans/notification', $payload)->assertOk();
        $this->postJson('/api/payments/midtrans/notification', $payload)->assertOk();

        $this->assertSame(3, $user->developerProfile->refresh()->upload_credits);
        $this->assertSame(1, CreditLedger::query()->where('order_id', $order->id)->count());
        Notification::assertSentToTimes($user, CreditPurchased::class, 1);
    }

    public function test_pending_notification_leaves_the_order_untouched(): void
    {
        $order = $this->order($this->developer());

        $this->postJson('/api/payments/midtrans/notification', $this->payload($order, [
            'transaction_status' => 'pending',
        ]))->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->refresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Order $order, array $overrides = [], string $signature = 'auto'): array
    {
        $payload = array_merge([
            'order_id' => $order->order_no,
            'status_code' => '200',
            'gross_amount' => number_format($order->gross_amount, 2, '.', ''),
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ], $overrides);

        $payload['signature_key'] = $signature === 'auto' ? $this->signature($payload) : $signature;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signature(array $payload): string
    {
        return hash('sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].self::SERVER_KEY
        );
    }

    private function order(?User $user = null, int $quantity = 1, string $suffix = 'a'): Order
    {
        $user ??= $this->developer();
        $unitPrice = (int) config('developer.upload_slot_price');

        $order = $user->orders()->create([
            'order_no' => 'INV-260101-'.strtoupper($suffix),
            'type' => Order::TYPE_UPLOAD_SLOTS,
            'status' => Order::STATUS_PENDING,
            'gross_amount' => $quantity * $unitPrice,
        ]);

        $order->items()->create([
            'type' => OrderItem::TYPE_UPLOAD_SLOTS,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'name' => 'Slot upload aplikasi',
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
