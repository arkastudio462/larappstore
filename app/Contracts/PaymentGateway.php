<?php

namespace App\Contracts;

use App\Models\Order;

/**
 * Batas sistem ke gerbang pembayaran. Implementasi konkret saat ini adalah
 * Midtrans; kontrak ini membuat alur pesanan mudah diuji tanpa memanggil
 * layanan asli (PRD §11).
 */
interface PaymentGateway
{
    /**
     * Buat transaksi di gerbang pembayaran dan kembalikan token yang dipakai
     * frontend untuk membuka halaman pembayaran.
     *
     * @return array{token: string, redirect_url: string|null}
     */
    public function createTransaction(Order $order): array;

    /**
     * Verifikasi keaslian notifikasi server-to-server memakai tanda tangan
     * yang hanya bisa dibuat gerbang pembayaran (PRD §6.3).
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyNotification(array $payload): bool;
}
