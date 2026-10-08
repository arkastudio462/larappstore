<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductScreenshot;
use App\Models\ProductVersion;
use App\Models\User;
use App\Services\ProductSearchService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    /**
     * @var array<int, array{title: string, category: string, type: string, price: int, summary: string}>
     */
    private const PRODUCTS = [
        [
            'title' => 'Catatan Kilat',
            'category' => 'Catatan',
            'type' => 'aplikasi',
            'price' => 0,
            'summary' => 'Catatan cepat dengan sinkronisasi dan pengelompokan topik.',
        ],
        [
            'title' => 'Dompet Pintar',
            'category' => 'Anggaran',
            'type' => 'aplikasi',
            'price' => 0,
            'summary' => 'Pelacakan pengeluaran harian dengan grafik sederhana.',
        ],
        [
            'title' => 'Puzzle Batu Nisan',
            'category' => 'Puzzle',
            'type' => 'game',
            'price' => 15000,
            'summary' => 'Puzzle logika 200 tingkat dengan mode luring penuh.',
        ],
        [
            'title' => 'Balap Kota',
            'category' => 'Balapan',
            'type' => 'game',
            'price' => 0,
            'summary' => 'Balapan arkade dengan lintasan kota.',
        ],
        [
            'title' => 'Kamus Bahasa Cepat',
            'category' => 'Bahasa',
            'type' => 'aplikasi',
            'price' => 0,
            'summary' => 'Kamus dua arah yang bekerja tanpa koneksi internet.',
        ],
        [
            'title' => 'Konverter Arsip',
            'category' => 'Utilitas',
            'type' => 'software',
            'price' => 25000,
            'summary' => 'Konversi massal dokumen ke PDF dari desktop.',
        ],
        [
            'title' => 'Paket Ikon Nordik',
            'category' => 'Desain',
            'type' => 'file',
            'price' => 35000,
            'summary' => '500 ikon vektor siap pakai untuk antarmuka gelap.',
        ],
        [
            'title' => 'Pemutar Vinyl Mini',
            'category' => 'Pemutar Musik',
            'type' => 'aplikasi',
            'price' => 0,
            'summary' => 'Pemutar musik bergaya piringan hitam yang ringan.',
        ],
    ];

    private const DOWNLOAD_FLOOR = 50;

    private const DOWNLOAD_CEILING = 9000;

    public function run(): void
    {
        $developer = User::query()->where('email', 'developer@larappstore.test')->first();

        if ($developer === null) {
            $this->command?->warn('UserSeeder belum dijalankan, data demo dilewati.');

            return;
        }

        $categorySlug = Category::query()->pluck('id', 'slug');
        $total = count(self::PRODUCTS);
        $now = now();
        $productRows = [];

        foreach (self::PRODUCTS as $index => $definition) {
            $categoryId = $categorySlug->get(Str::slug($definition['category']));

            if ($categoryId === null) {
                continue;
            }

            $productRows[] = [
                'index' => $index,
                'slug' => Str::slug($definition['title']),
                'row' => [
                    'developer_id' => $developer->id,
                    'category_id' => $categoryId,
                    'type' => $definition['type'],
                    'title' => $definition['title'],
                    'slug' => Str::slug($definition['title']),
                    'summary' => $definition['summary'],
                    'description' => $this->describe($definition['summary']),
                    'icon_path' => "products/demo/icon-{$index}.png",
                    'banner_path' => "products/demo/banner-{$index}.jpg",
                    'price' => $definition['price'],
                    'currency' => 'IDR',
                    'status' => Product::STATUS_PUBLISHED,
                    'is_featured' => $index < 3,
                    'published_at' => $now->copy()->subDays($total - $index),
                    'downloads_count' => random_int(self::DOWNLOAD_FLOOR, self::DOWNLOAD_CEILING),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];
        }

        $existing = Product::query()
            ->whereIn('slug', array_column($productRows, 'slug'))
            ->pluck('slug')
            ->all();

        $missing = array_values(array_filter(
            $productRows,
            fn (array $item): bool => ! in_array($item['slug'], $existing, true),
        ));

        if ($missing !== []) {
            Product::query()->insert(array_column($missing, 'row'));
        }

        $productId = Product::query()
            ->whereIn('slug', array_column($productRows, 'slug'))
            ->pluck('id', 'slug');

        $this->seedScreenshots($productRows, $productId);
        $this->seedVersions($productRows, $productId, $total, $now);

        // Bulk insert melewati event Eloquent, jadi indeks FTS diisi manual.
        app(ProductSearchService::class)->reindexMany(
            Product::query()->whereIn('slug', array_column($productRows, 'slug'))->get()
        );
    }

    private function describe(string $summary): string
    {
        return implode("\n\n", [
            $summary,
            'Dikembangkan oleh Studio Arunika dan diuji pada berbagai perangkat sebelum dirilis.',
            'Versi awal sudah mendukung pembaruan otomatis, mode gelap, dan ekspor data.',
        ]);
    }

    /**
     * @param  array<int, array{index: int, slug: string, row: array<string, mixed>}>  $productRows
     * @param  Collection<string, int>  $productId
     */
    private function seedScreenshots(array $productRows, $productId): void
    {
        $existing = ProductScreenshot::query()
            ->whereIn('product_id', $productId->values())
            ->count();

        if ($existing > 0) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($productRows as $item) {
            $id = $productId->get($item['slug']);

            if ($id === null) {
                continue;
            }

            foreach ([1, 2, 3] as $position) {
                $rows[] = [
                    'product_id' => $id,
                    'path' => "products/demo/{$item['index']}/screenshot-{$position}.jpg",
                    'position' => $position,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            ProductScreenshot::query()->insert($rows);
        }
    }

    /**
     * @param  array<int, array{index: int, slug: string, row: array<string, mixed>}>  $productRows
     * @param  Collection<string, int>  $productId
     */
    private function seedVersions(array $productRows, $productId, int $total, $now): void
    {
        if (ProductVersion::query()->whereIn('product_id', $productId->values())->exists()) {
            return;
        }

        $rows = [];

        foreach ($productRows as $item) {
            $id = $productId->get($item['slug']);

            if ($id === null) {
                continue;
            }

            $index = $item['index'];

            $rows[] = [
                'product_id' => $id,
                'version' => '1.'.($index % 5).'.0',
                'file_path' => "products/demo/{$index}/release.apk",
                'file_size' => random_int(4_000_000, 60_000_000),
                'file_type' => 'apk',
                'checksum_sha256' => hash('sha256', 'demo-'.$index),
                'changelog' => 'Rilis awal.',
                'download_count' => random_int(10, 500),
                'status' => ProductVersion::STATUS_PUBLISHED,
                'is_latest' => true,
                'published_at' => $now->copy()->subDays($total - $index),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            ProductVersion::query()->insert($rows);
        }
    }
}
