<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke pembeli setelah kredit upload bertambah.
 */
class CreditPurchased extends Notification
{
    public function __construct(public Order $order, public int $quantity, public bool $unlimited = false) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'credit_purchased',
            'message' => $this->unlimited
                ? 'Paket upload tanpa batas sudah aktif.'
                : $this->quantity.' slot upload berhasil ditambahkan.',
            'url' => '/kelola',
            'actor' => null,
            'subject' => [
                'type' => 'order',
                'order_no' => $this->order->order_no,
            ],
        ];
    }
}
