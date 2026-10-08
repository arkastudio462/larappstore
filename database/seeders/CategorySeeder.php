<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Kategori unggulan toko beserta anaknya.
     *
     * @var array<string, array<int, string>>
     */
    private const CATEGORIES = [
        'Produktivitas' => ['Kantor', 'Catatan', 'Manajemen Tugas'],
        'Sosial' => ['Kencan', 'Obrolan', 'Jejaring'],
        'Media & Video' => ['Streaming', 'Editor Video', 'Pemutar Musik'],
        'Game' => ['Aksi', 'Puzzle', 'Kasual', 'Balapan'],
        'Pendidikan' => ['Bahasa', 'Kursus Online', 'Kamus'],
        'Keuangan' => ['Perbankan', 'Anggaran', 'Investasi'],
        'Alat' => ['Utilitas', 'Keamanan', 'Produktivitas Sistem'],
        'Kesehatan & Kebugaran' => ['Latihan', 'Meditasi', 'Pelacakan'],
        'Foto & Editor' => ['Kamera', 'Desain', 'Gambar'],
        'Berita & Majalah' => ['Berita Harian', 'Teknologi', 'Olahraga'],
        'Belanja' => ['Marketplace', 'Kupon', 'Bandingkan Harga'],
        'Peta & Navigasi' => ['Transportasi', 'Perjalanan', 'Lokasi'],
    ];

    public function run(): void
    {
        $parentRows = [];
        $parentSlugs = [];

        foreach (array_keys(self::CATEGORIES) as $position => $name) {
            $slug = Str::slug($name);
            $parentSlugs[$slug] = $name;
            $parentRows[] = [
                'name' => $name,
                'slug' => $slug,
                'icon' => $slug,
                'position' => $position,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->insertMissing($parentRows, 'slug');

        $parentId = Category::query()
            ->whereIn('slug', array_keys($parentSlugs))
            ->pluck('id', 'slug');

        $childRows = [];

        foreach (self::CATEGORIES as $parentName => $children) {
            $parent = $parentId->get(Str::slug($parentName));

            if ($parent === null) {
                continue;
            }

            foreach ($children as $position => $child) {
                $childRows[] = [
                    'parent_id' => $parent,
                    'name' => $child,
                    'slug' => Str::slug($child),
                    'position' => $position,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $this->insertMissing($childRows, 'slug');
    }

    /**
     * Sisipkan hanya baris yang belum ada, dalam satu statement per batch.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertMissing(array $rows, string $uniqueColumn): void
    {
        if ($rows === []) {
            return;
        }

        $existing = Category::query()
            ->whereIn($uniqueColumn, array_column($rows, $uniqueColumn))
            ->pluck($uniqueColumn)
            ->all();

        $missing = array_values(array_filter(
            $rows,
            fn (array $row): bool => ! in_array($row[$uniqueColumn], $existing, true),
        ));

        if ($missing !== []) {
            Category::query()->insert($missing);
        }
    }
}
