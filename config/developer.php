<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Harga Monetisasi Developer
    |--------------------------------------------------------------------------
    |
    | Seluruh nilai dalam rupiah penuh (bukan sen). Upload pertama gratis lewat
    | `upload_credits = 1` saat upgrade (PRD §5).
    |
    */

    'upload_slot_price' => (int) env('DEVELOPER_UPLOAD_SLOT_PRICE', 15000),

    'unlimited_price' => (int) env('DEVELOPER_UNLIMITED_PRICE', 150000),

    'gateway' => env('PAYMENT_GATEWAY', 'midtrans'),

];
