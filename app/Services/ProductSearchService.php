<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indeks pencarian produk berbasis teks penuh.
 *
 * Sinkronisasi dilakukan di tingkat aplikasi (lewat ProductObserver) alih-alih
 * trigger database, supaya perilakunya identik di SQLite lokal maupun
 * PostgreSQL Supabase. Pada PostgreSQL kolom `tsv` dihitung oleh kolom
 * ter-generate sehingga reindex cukup menyalin teks sumbernya saja.
 */
class ProductSearchService
{
    /**
     * Susun klausa MATCH yang aman dari karakter khusus sintaks FTS5.
     *
     * Setiap kata dikutip dan diberi operator prefix agar pengguna bisa
     * mengetik sebagian dari nama aplikasi.
     */
    public function toMatchQuery(string $query): string
    {
        $terms = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $parts = array_map(
            fn (string $term): string => '"'.str_replace('"', '""', $term).'"*',
            array_slice($terms, 0, 8),
        );

        return implode(' AND ', $parts);
    }

    /**
     * Susun klausa to_tsquery yang aman dari karakter khusus sintaks tsquery.
     *
     * Hanya huruf dan angka yang dipertahankan; sisanya dipecah menjadi kata
     * terpisah. Tiap kata diberi operator prefix `:*` agar pengguna bisa
     * mengetik sebagian dari nama aplikasi.
     */
    public function toTsQuery(string $query): string
    {
        $normalized = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $query) ?? '');
        $terms = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $parts = array_map(
            fn (string $term): string => $term.':*',
            array_slice($terms, 0, 8),
        );

        return implode(' & ', $parts);
    }

    /**
     * Tulis ulang baris indeks untuk satu produk.
     */
    public function reindex(Product $product): void
    {
        $this->reindexMany([$product]);
    }

    /**
     * Tulis ulang indeks untuk banyak produk dalam dua statement saja.
     *
     * Dipakai oleh seeder dan impor massal, yang mem-bypass event Eloquent
     * sehingga observer tidak ikut terpicu. Pada PostgreSQL kolom `tsv` ikut
     * diperbarui otomatis oleh generated column, jadi cukup tulis teksnya.
     *
     * @param  iterable<int, Product>  $products
     */
    public function reindexMany(iterable $products): void
    {
        $ids = [];
        $rows = [];

        foreach ($products as $product) {
            $ids[] = $product->getKey();
            $rows[] = [
                'product_id' => $product->getKey(),
                'title' => $product->title,
                'summary' => (string) $product->summary,
                'description' => trim(strip_tags((string) $product->description)),
            ];
        }

        if ($rows === []) {
            return;
        }

        DB::table('product_search')->whereIn('product_id', $ids)->delete();
        DB::table('product_search')->insert($rows);
    }

    public function remove(int|string|null $productId): void
    {
        if ($productId === null) {
            return;
        }

        DB::table('product_search')->where('product_id', $productId)->delete();
    }

    /**
     * Cari produk terbit sesuai peringkat relevansi.
     *
     * @return Collection<int, Product>
     */
    public function search(string $query, int $limit = 20): Collection
    {
        $ids = DB::getDriverName() === 'pgsql'
            ? $this->searchPostgres($query, $limit)
            : $this->searchSqlite($query, $limit);

        if ($ids->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->published()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return $ids
            ->map(fn (int $id) => $products->get($id))
            ->filter()
            ->take($limit)
            ->values();
    }

    /**
     * Pencarian FTS5 pada SQLite: skor bm25 terendah = paling relevan.
     *
     * @return Collection<int, int|string>
     */
    private function searchSqlite(string $query, int $limit): Collection
    {
        $match = $this->toMatchQuery($query);

        if ($match === '') {
            return collect();
        }

        return DB::table('product_search')
            ->select('product_id')
            ->selectRaw('bm25(product_search) AS relevance')
            ->whereRaw('product_search MATCH ?', [$match])
            ->orderBy('relevance')
            ->limit($limit * 2)
            ->pluck('product_id');
    }

    /**
     * Pencarian full-text pada PostgreSQL: skor ts_rank tertinggi = paling relevan.
     *
     * @return Collection<int, int|string>
     */
    private function searchPostgres(string $query, int $limit): Collection
    {
        $tsQuery = $this->toTsQuery($query);

        if ($tsQuery === '') {
            return collect();
        }

        $rows = DB::select(
            'SELECT product_id '.
            'FROM product_search, to_tsquery(\'simple\', ?) AS query '.
            'WHERE tsv @@ query '.
            'ORDER BY ts_rank(tsv, query) DESC '.
            'LIMIT ?',
            [$tsQuery, $limit * 2],
        );

        return collect($rows)->pluck('product_id');
    }
}
