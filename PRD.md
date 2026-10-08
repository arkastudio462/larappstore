# PRD — LarAppStore

Toko aplikasi bergaya Play Store/App Store dengan elemen sosial media (feed, like, komentar, follow).

Status: **Rencana siap eksekusi**
Tanggal: 4 Oktober 2026

---

## 1. Ringkasan Produk

LarAppStore adalah platform distribusi konten digital (aplikasi, game, software, file) tempat developer mengunggah dan menjual produknya, sekaligus berfungsi seperti sosial media: semua pengguna bisa memposting teks/gambar, saling menyukai, berkomentar, dan mengikuti pengguna/developer lain.

**Prinsip inti:**

- Tanpa pramoderasi — postingan dan unggahan aplikasi langsung dipublikasikan atau disimpan sebagai draft oleh author.
- Admin hanya berwenang **menghapus** konten (soft delete), bukan menyetujuinya.
- Developer monetisasi: 1 upload gratis, selanjutnya berbayar via Midtrans.
- Infrastruktur: **Supabase** (database PostgreSQL) dan **Cloudflare R2** (penyimpanan S3-compatible).

---

## 2. Keputusan Teknis

| Aspek | Keputusan |
|---|---|
| Backend | Laravel 13 (`laravel/laravel:^13.x`), PHP 8.3–8.5 |
| Frontend | Vue 3 SPA — vue-router 5, Pinia 4, axios, Tailwind CSS 4, Vite 8, lucide-vue-next, chart.js |
| Database | Development: SQLite lokal → Production: **Supabase (PostgreSQL)** via driver bawaan Laravel `pgsql` |
| Penyimpanan | **Cloudflare R2** (S3-compatible) via `league/flysystem-aws-s3-v3`, disk `r2` |
| Auth | Session cookie (`auth:web`), SPA satu domain. Tanpa Sanctum di fase 1 |
| Pembayaran | Midtrans Snap (`midtrans/midtrans-php`) |
| Bahasa UI | Bahasa Indonesia |
| Deploy | Shared hosting / cPanel, di depannya Cloudflare DNS/CDN |

### Dependensi

###
# Basis URL publik objek R2 untuk browser (biasanya sama dengan R2_URL)
VITE_MEDIA_URL="https://pub-85fc955824e4421087e553cd6bf00ef2.r2.dev"

# Cloudflare R2 (S3-compatible)
R2_ACCESS_KEY_ID=c269b976a468fffd7c6b99aafad52983
R2_SECRET_ACCESS_KEY=098c4315668dc4d10cc26220283a235ac8ac68794822f0125936a138a723a3ab
R2_BUCKET=appfeed
R2_ENDPOINT=https://f6453feed86a5c7f445539ff97894ddd.r2.cloudflarestorage.com
R2_URL=https://pub-85fc955824e4421087e553cd6bf00ef2.r2.dev

# Midtrans
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_MERCHANT_ID=G771649207
MIDTRANS_CLIENT_KEY=SB-Mid-client-XKXSsGJy3UEJfWey
MIDTRANS_SERVER_KEY=SB-Mid-server-z6TJU9co9gqtv0edTJanoYKG

```
composer require league/flysystem-aws-s3-v3 midtrans/midtrans-php
npm i vue vue-router pinia axios lucide-vue-next chart.js @vitejs/plugin-vue
```

### Konfigurasi Supabase

**PostgreSQL** (`config/database.php`, koneksi `pgsql`) — kredensial diambil dari
Dashboard Supabase → *Project Settings* → *Database*:

```php
'pgsql' => [
    'driver'      => 'pgsql',
    'host'        => env('DB_HOST'),          // db.<PROJECT_REF>.supabase.co
    'port'        => env('DB_PORT', '5432'),
    'database'    => env('DB_DATABASE'),      // postgres
    'username'    => env('DB_USERNAME'),      // postgres
    'password'    => env('DB_PASSWORD'),
    'search_path' => 'public',
    'sslmode'     => env('DB_SSLMODE', 'require'),
],
```

`.env` produksi: `DB_CONNECTION=pgsql`. Development lokal bisa tetap
`DB_CONNECTION=sqlite` (tes PHPUnit memakai SQLite in-memory).

**R2** (`config/filesystems.php`, disk `r2`):

```php
'r2' => [
    'driver'   => 's3',
    'key'      => env('R2_ACCESS_KEY_ID'),
    'secret'   => env('R2_SECRET_ACCESS_KEY'),
    'bucket'   => env('R2_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'), // https://{ACCOUNT_ID}.r2.cloudflarestorage.com
    'url'      => env('R2_URL'),       // https://files.domain.com (custom domain, public access)
    'throw'    => true,
],
```

---

## 3. Role & Hak Akses

| Role | Capaian | Hak |
|---|---|---|
| `user` | Mendaftar (default) | Posting teks/gambar, like, komentar, follow, beli produk, unduh produk gratis, ulasan |
| `developer` | Upgrade dari dashboard — **gratis & instan** | Semua hak user + unggah produk, kelola versi, beli slot upload, lihat statistik sendiri |
| `admin` | Ditentukan manual | Semua hak + **menghapus** postingan/komentar/produk/ulasan, kelola kategori & koleksi, ban user, lihat orders & laporan, dashboard statistik |

**Upgrade user → developer:** gratis, instan, tanpa verifikasi. Saat upgrade dibuat `developer_profiles` dengan `upload_credits = 1` dan baris `credit_ledger` tipe `signup_bonus`.

**Tidak ada antrean moderasi.** Semua konten terbit otomatis (`published`) atau `draft` sesuai pilihan author.

---

## 4. Skema Database

Semua harus kompatibel SQLite (dev/tes) dan PostgreSQL Supabase (produksi). Tanpa fitur khusus MySQL.

### 4.1 Identitas & Sosial

**`users`**
`id`, `username` (unique), `name`, `email` (unique), `password`, `role` enum(`user`,`developer`,`admin`), `bio`, `avatar_path`, `email_verified_at`, `followers_count`, `following_count`, `posts_count`, `remember_token`, timestamps, soft deletes

**`developer_profiles`**
`id`, `user_id` (unique), `studio_name`, `slug` (unique), `bio`, `website`, **`upload_credits`** (int, default 0), **`unlimited_uploads`** (bool, default false), timestamps

**`posts`**
`id`, `user_id`, `type` enum(`text`,`image`), `body` (text), `media` (JSON — maksimum 4 path R2), `status` enum(`published`,`draft`), `published_at`, `likes_count`, `comments_count`, timestamps, soft deletes

**`likes`**
`id`, `user_id`, `likeable_type`, `likeable_id`, `created_at`; unique(`user_id`,`likeable_type`,`likeable_id`)

**`comments`**
`id`, `user_id`, `post_id`, `parent_id` (nullable, balasan 1 level), `body`, timestamps, soft deletes; `comments_count` disimpan di `posts`

**`follows`**
`id`, `follower_id`, `followable_type`, `followable_id`, `created_at`; unique(`follower_id`,`followable_type`,`followable_id`)

**`notifications`**
Tabel bawaan Laravel (channel database). Tipe: `LikeReceived`, `CommentReceived`, `FollowedYou`, `PaymentSucceeded`, `PurchaseCompleted`, `CreditPurchased`

**`feed_items`** — activity stream untuk timeline
`id`, `actor_id`, `subject_type` (nama kelas penuh: `App\Models\Post`, `App\Models\Product`, `App\Models\ProductVersion`, agar konsisten dengan kolom polimorfik lain dan relasi `subject()` Eloquent langsung bekerja tanpa morph map), `subject_id`, `created_at`
Diisi saat: post publish, produk publish pertama kali, versi baru publish. Memungkinkan feed tercampur (postingan + rilis aplikasi) dipaginasi dengan **1 query cursor**.

**`reports`** — pelaporan konten oleh user (pengganti pramoderasi)
`id`, `reporter_id`, `reportable_type`, `reportable_id`, `reason`, `status` enum(`open`,`resolved`,`dismissed`), `resolved_by`, timestamps

### 4.2 Katalog & Konten

**`categories`**
`id`, `parent_id` (nullable, hierarkis), `name`, `slug` (unique), `icon`, `position`, `is_active`, timestamps

**`products`**
`id`, `developer_id` (users), `category_id`, `type` enum(`aplikasi`,`game`,`software`,`file`,`lainnya`), `title`, `slug` (unique), `summary`, `description` (longtext), `icon_path`, `banner_path`, `price` (unsigned int, 0 = gratis), `currency` (default `IDR`), `status` enum(`draft`,`published`,`archived`), `is_featured`, `published_at`, `downloads_count`, timestamps, soft deletes

**`product_screenshots`**
`id`, `product_id`, `path`, `position`

**`product_versions`**
`id`, `product_id`, `version`, `file_path`, `file_size`, `file_type` (apk/ipa/exe/zip/pdf/dll), `checksum_sha256`, `changelog`, `download_count`, `status` enum(`draft`,`published`), `is_latest`, `published_at`, timestamps

**`reviews`**
`id`, `product_id`, `user_id`, `rating` (tinyint 1–5), `body`, `status` enum(`published`,`hidden`), timestamps; unique(`product_id`,`user_id`)

**`collections`**
`id`, `name`, `slug` (unique), `description`, `cover_path`, `position`, `is_active`, timestamps

**`collection_items`**
`id`, `collection_id`, `product_id`, `position`

**`product_search`** — indeks teks penuh
Salinan `product_id`, `title`, `summary`, `description`. Di PostgreSQL kolom `tsv tsvector` dihitung oleh generated column + index GIN; di SQLite berupa virtual table FTS5. Sinkronisasi lewat Eloquent observer (reindex saat produk disimpan/dihapus).

### 4.3 Pembayaran & Monetisasi

**`orders`**
`id`, `order_no` (unique), `user_id`, `type` enum(`app_purchase`,`upload_slots`,`unlimited_upload`), `status` enum(`pending`,`paid`,`failed`,`expired`,`cancelled`), `gross_amount`, `payment_method`, `paid_at`, timestamps

**`order_items`**
`id`, `order_id`, `type`, `product_id` (nullable), `quantity`, `unit_price`, `name`

**`product_purchases`**
`id`, `user_id`, `product_id`, `order_id`, `created_at`; unique(`user_id`,`product_id`) → akses unduh produk berbayar

**`credit_ledger`** — audit kredit upload (riwayat mutasi, bukan hanya saldo)
`id`, `user_id`, `delta`, `type` enum(`signup_bonus`,`purchase`,`usage`), `balance_after`, `order_id` (nullable), `description`, `created_at`

**`download_stats_daily`**
`id`, `product_id`, `version_id`, `date`, `count`; unique(`product_id`,`version_id`,`date`)

---

## 5. Skema Harga

| Item | Harga | Keterangan |
|---|---|---|
| Upload pertama developer | **Gratis** | Diberikan saat upgrade ke developer (`upload_credits = 1`) |
| Slot upload tambahan | **Rp15.000 / aplikasi** | Boleh dibeli banyak dalam 1 transaksi (qty × Rp15.000) |
| Unlimited upload lifetime | **Rp150.000** | Sekali bayar, `unlimited_uploads = true` selamanya |
| Produk berbayar | Sesuai `products.price` | Potongan ke developer — **fase lanjutan** (belum ada payout) |

**Pembayaran selalu dilakukan sebelum upload.** Form beli kredit → Midtrans Snap → webhook sukses → tambah kredit / set unlimited + ledger + notifikasi.

**Konsumsi kredit** dilakukan saat produk dibuat (`POST /developer/products`) dengan satu statement atomik yang hanya cocok selama saldo masih positif:

```sql
UPDATE developer_profiles SET upload_credits = upload_credits - 1
WHERE user_id = ? AND upload_credits > 0
```

Cek `rowCount`: 0 → respons 402, arahkan ke halaman beli kredit. Bila `unlimited_uploads = true`, lewati pengurangan.

---

## 6. Integrasi Cloudflare — Desain Kunci

### 6.1 Upload file besar (R2 presigned)

Berkas APK/EXE/ZIP bisa ratusan MB dan melebihi `upload_max_filesize` shared hosting, sehingga **browser mengunggah langsung ke R2**:

1. `POST /api/uploads/presign` — validasi ekstensi, ukuran, mime → kembalikan presigned **PUT** URL (`Storage::temporaryUrl()`) + key
2. Browser `PUT` ke R2 (XHR, ada progress bar)
3. `POST /api/uploads/complete` — verifikasi objek (`headObject`, ukuran, checksum) → baru baris DB dibuat

### 6.2 Serving file

- Produk gratis → URL publik custom domain `files.domain.com`
- Produk berbayar → presigned **GET** berumur ~5 menit setelah pengecekan `product_purchases`

### 6.3 Transaksi & idempotensi

PostgreSQL Supabase mendukung `BEGIN/COMMIT` penuh sehingga `DB::transaction()`
aman dipakai. Operasi yang menyentuh uang & kredit tetap ditulis idempoten
agar retry webhook Midtrans berbahaya:

- Alur pembayaran & kredit memakai **state machine idempoten**: update status hanya bila masih `pending`, unique constraint sebagai pengaman, `firstOrCreate` untuk `product_purchases`
- Notifikasi webhook Midtrans diverifikasi signature-nya lalu diproses idempoten (retry aman)
- Pengurangan kredit & toggle follow tetap berupa statement atomik bersyarat (`WHERE ... > 0`, `insertOrIgnore`)

### 6.4 Efisiensi query & cache

- Hitung unduhan di **file cache** (per produk/versi/tanggal), di-flush ke `download_stats_daily` tiap 5 menit oleh scheduled job `stats:flush`
- Notifikasi hanya bersifat **1:1** (like, komentar, follow, pembayaran) — rilis aplikasi cukup muncul di feed, **tanpa fan-out** ke seluruh follower
- `CACHE_STORE=file`; halaman publik di-cache 60–300 detik dengan invalidation saat data berubah
- Target ≤ 4 query tak-tercache per request (tiap query menyeberangi jaringan ke Supabase)

### 6.5 Pencarian teks penuh

Produksi memakai full-text **PostgreSQL**: kolom ter-generate `tsv tsvector`
pada tabel `product_search` + index GIN, query `@@ to_tsquery('simple', ...)`
dengan ranking `ts_rank` menurun. Development & tes memakai **FTS5** SQLite
(`MATCH` + `bm25()` menaik). `ProductSearchService` memilih keduanya berdasarkan
driver, dan sinkronisasi index di kedua driver dilakukan via Eloquent observer.

### 6.6 Cron shared hosting

Satu entri di cron cPanel:

```
* * * * * php /home/user/app/artisan schedule:run
```

Menjalankan: `queue:run`, `stats:flush`, `session:gc`.

---

## 7. Peta API (`routes/api.php`)

### Publik (guest)

```
GET    /api/home                          ringkasan feed + koleksi + kategori
GET    /api/feed                          cursor pagination, ?scope=all|following&type=all|post|product
GET    /api/categories
GET    /api/products                      filter: category, type, sort, price, page
GET    /api/products/{slug}
GET    /api/products/{slug}/versions
GET    /api/search?q=                     FTS5
GET    /api/collections/{slug}
GET    /api/users/{username}              profil publik
GET    /api/posts/{id}
POST   /api/products/{slug}/download      gratis (guest) → redirect presigned URL
```

### Auth (session)

```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
PUT    /api/auth/me

POST   /api/posts                          buat (published|draft)
PUT    /api/posts/{id}
DELETE /api/posts/{id}                     hanya author / admin (soft delete)
POST   /api/posts/{id}/publish             draft → published (+ feed_items)
POST   /api/likes                          toggle like (polymorphic)
GET    /api/posts/{id}/comments
POST   /api/posts/{id}/comments
DELETE /api/comments/{id}
POST   /api/users/{username}/follow        toggle follow
GET    /api/notifications
POST   /api/notifications/read-all

POST   /api/products/{slug}/reviews
PUT    /api/reviews/{id}
```

### Pembayaran

```
POST   /api/orders                         tipe: app_purchase | upload_slots | unlimited_upload
POST   /api/orders/{order_no}/check        polling status
POST   /api/payments/midtrans/notification webhook server-to-server (signature diverifikasi)
GET    /api/me/purchases
```

### Developer (`role:developer|admin`)

```
POST   /api/developer/upgrade              gratis & instan
GET    /api/developer/overview             kredit, unlimited, statistik
GET    /api/developer/orders

POST   /api/uploads/presign
POST   /api/uploads/complete

GET    /api/developer/products
POST   /api/developer/products             konsumsi kredit di sini
PUT    /api/developer/products/{id}
POST   /api/developer/products/{id}/publish|archive
POST   /api/developer/products/{id}/versions
POST   /api/developer/products/{id}/versions/{v}/publish
GET    /api/developer/products/{id}/stats
```

### Admin (`role:admin`)

```
DELETE /api/admin/posts/{id}
DELETE /api/admin/comments/{id}
DELETE /api/admin/products/{id}
DELETE /api/admin/reviews/{id}
GET    /api/admin/reports                  daftar laporan konten
POST   /api/admin/reports/{id}/resolve
CRUD   /api/admin/categories
CRUD   /api/admin/collections
GET    /api/admin/users
PATCH  /api/admin/users/{id}               ubah role / ban
GET    /api/admin/orders
GET    /api/admin/stats                    dashboard statistik
```

---

## 8. Halaman Vue (vue-router)

**Publik**

- `/` — feed beranda: composer postingan, tab **Untuk Anda / Mengikuti**, filter postingan vs aplikasi, kartu postingan (like, komentar), kartu rilis aplikasi
- `/postingan/{id}` — detail postingan + thread komentar
- `/produk/{slug}` — detail produk: ikon, banner, carousel screenshot, tab Tentang/Versi/Ulasan, tombol unduh/beli, rating
- `/kategori/{slug}`, `/cari`, `/koleksi/{slug}`
- `/u/{username}` — profil publik: tab Postingan | Aplikasi | Pengikut, jumlah pengikut/mengikuti

**Auth** — `/masuk`, `/daftar`

**Akun** — `/akun/profil`, `/akun/notifikasi`, `/akun/pembelian`, `/akun/ulasan`, `/akun/mengikuti`

**Developer**

- `/kelola` — dashboard: kredit upload, unlimited, grafik unduhan
- `/kelola/produk`, `/kelola/produk/baru` — form + upload presigned dengan progress bar
- `/kelola/versi` — rilis versi (draft/published)
- `/kelola/beli-kredit` — pilih qty slot (Rp15.000 × n) atau unlimited (Rp150.000)
- `/akun/developer` — tombol upgrade jadi developer (untuk user biasa)

**Admin**

- `/admin` — laporan konten, aksi hapus cepat
- `/admin/kategori`, `/admin/koleksi`, `/admin/pengguna`, `/admin/pesanan`, `/admin/statistik`

**Struktur kode**

```
resources/js/
  app.js, App.vue
  router/index.js            # guard berbasis role
  stores/ auth.js, feed.js, catalog.js
  pages/                     # seperti daftar di atas
  components/ ui/, layout/ (Topbar, FeedCard, NotificationBell), product/ (Card, Carousel, RatingBar)
  lib/api.js                 # axios + XSRF cookie
```

`routes/web.php` → satu view `app` + `fallback` catch-all. Routing riwayat SPA sudah ditangani `.htaccess` bawaan Laravel.

---

## 9. Milestone

| # | Milestone | Deliverable |
|---|---|---|
| 0 | Fondasi | Scaffold Laravel 13, instal dependensi, konfigurasi Supabase & R2, **spike de-risiko: migrasi + pencarian teks penuh + presigned URL ke layanan asli** |
| 1 | Data | Seluruh migrasi, model + relasi, seeder (kategori, admin, produk demo), service pencarian FTS5 |
| 2 | Auth & sosial dasar | Register/login session, upgrade developer, profil publik, follow |
| 3 | Feed & postingan | Composer teks/gambar→R2, timeline cursor, like, komentar, notifikasi in-app |
| 4 | Katalog aplikasi | Halaman produk, pencarian FTS5, versi, unduh (gratis/berbayar), ulasan |
| 5 | Portal developer | Kredit + ledger, beli slot/unlimited (Midtrans), upload presigned, publish/draft |
| 6 | Pembayaran | Snap + webhook idempoten, riwayat pembelian, notifikasi |
| 7 | Admin | Hapus konten, laporan user, kategori/koleksi, pengguna, orders, statistik |
| 8 | Analitik & deploy | Penghitung unduhan teragregasi + grafik; `npm run build`, upload cPanel, cron, `.env` produksi, migrasi lokal→Supabase, custom domain R2, Midtrans production |

**Verifikasi setiap milestone:**

```sh
vendor/bin/pint
php artisan test
npm run build
```

---

## 10. Risiko & Mitigasi

| # | Risiko | Mitigasi |
|---|---|---|
| 1 | Latensi koneksi jarak jauh ke Supabase (TLS, per query) | Cache file agresif, budget ≤4 query tak-tercache/halaman; `CACHE_STORE=file` |
| 2 | Perbedaan perilaku SQLite (dev/tes) vs PostgreSQL (produksi) | Migrasi & `ProductSearchService` dibuat driver-aware; PHPUnit tetap jalan di SQLite |
| 3 | Kuota free tier Supabase (500 MB) | Agregasi statistik harian, opsional upgrade plan |
| 4 | Retry webhook Midtrans memproses event ganda | State machine idempoten: atomic UPDATE + unique constraint + `credit_ledger` |
| 5 | Upload file besar di shared hosting (limit PHP) | Presigned langsung ke R2 dari browser |
| 6 | Kredensial Supabase bocor ke repo | Rahasia hanya di `.env`; `.env.example` tanpa nilai; `config:cache` di server |
| 7 | Tidak ada pramoderasi → konten berbahaya | Fitur laporan user + admin bisa hapus kapan saja (soft delete) |

---

## 11. Asumsi

1. Upgrade user → developer **gratis dan instan**, tanpa verifikasi.
2. Pembelian slot upload **boleh qty lebih dari 1** dalam satu transaksi.
3. Komentar dan postingan **tanpa pramoderasi**; admin memperoleh hak hapus + menerima laporan user.
4. **Tanpa keranjang belanja** — pembelian produk langsung per item.
5. Belum ada **payout/split pembayaran ke developer** — potongan penjualan produk adalah fase lanjutan.
6. Notifikasi **hanya in-app** (lonceng), tanpa email (tanpa konfigurasi SMTP).
7. Kredensial Supabase (host, database, user, password) dan Cloudflare R2 (access key) sudah tersedia di `.env`.
