<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke developer saat produk berbayarnya dibeli orang lain.
 */
class PaymentSucceeded extends Notification
{
    public function __construct(public Product $product, public User $buyer) {}

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
            'type' => 'payment_succeeded',
            'message' => $this->buyer->name.' membeli '.$this->product->title.'.',
            'url' => '/kelola/products',
            'actor' => [
                'id' => $this->buyer->id,
                'username' => $this->buyer->username,
                'name' => $this->buyer->name,
                'avatar_path' => $this->buyer->avatar_path,
            ],
            'subject' => [
                'type' => 'product',
                'id' => $this->product->id,
                'slug' => $this->product->slug,
            ],
        ];
    }
}
