<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap;
use RuntimeException;

class MidtransGateway implements PaymentGateway
{
    public function __construct()
    {
        MidtransConfig::$serverKey = (string) config('services.midtrans.server_key');
        MidtransConfig::$clientKey = (string) config('services.midtrans.client_key');
        MidtransConfig::$isProduction = (bool) config('services.midtrans.is_production');
        MidtransConfig::$isSanitized = true;
        MidtransConfig::$is3ds = true;
    }

    /**
     * @return array{token: string, redirect_url: string|null}
     */
    public function createTransaction(Order $order): array
    {
        if (MidtransConfig::$serverKey === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum dikonfigurasi.');
        }

        $order->loadMissing(['user', 'items']);

        $transaction = Snap::createTransaction([
            'transaction_details' => [
                'order_id' => $order->order_no,
                'gross_amount' => $order->gross_amount,
            ],
            'customer_details' => [
                'first_name' => $order->user->name,
                'email' => $order->user->email,
            ],
            'item_details' => $order->items->map(fn ($item): array => [
                'id' => (string) $item->id,
                'price' => $item->unit_price,
                'quantity' => $item->quantity,
                'name' => mb_substr($item->name, 0, 50),
            ])->all(),
        ]);

        return [
            'token' => (string) $transaction->token,
            'redirect_url' => isset($transaction->redirect_url) ? (string) $transaction->redirect_url : null,
        ];
    }

    /**
     * Midtrans menandatangani notifikasi dengan
     * `sha512(order_id + status_code + gross_amount + server_key)`.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyNotification(array $payload): bool
    {
        $serverKey = (string) config('services.midtrans.server_key');
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signature = (string) ($payload['signature_key'] ?? '');

        if ($serverKey === '' || $signature === '' || $orderId === '' || $statusCode === '' || $grossAmount === '') {
            return false;
        }

        return hash_equals(hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey), $signature);
    }
}
