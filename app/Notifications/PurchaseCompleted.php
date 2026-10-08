<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke pembeli setelah produk berbayar berhasil dibeli.
 */
class PurchaseCompleted extends Notification
{
    public function __construct(public Product $product, public Order $order) {}

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
            'type' => 'purchase_completed',
            'message' => 'Pembelian '.$this->product->title.' berhasil. Kamu bisa mengunduhnya sekarang.',
            'url' => '/produk/'.$this->product->slug,
            'actor' => null,
            'subject' => [
                'type' => 'product',
                'id' => $this->product->id,
                'slug' => $this->product->slug,
            ],
        ];
    }
}
