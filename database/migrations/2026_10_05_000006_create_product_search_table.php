<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Indeks teks penuh untuk pencarian produk.
     *
     * Tabel ini menyimpan salinan teksnya sendiri (bukan external content)
     * agar sinkronisasi cukup dilakukan di tingkat aplikasi lewat observer.
     * Kolom indeks dibuat berbeda per driver:
     *
     * - pgsql: kolom ter-generate `tsv tsvector` + index GIN, dihitung
     *   otomatis oleh PostgreSQL sehingga tidak perlu ditulis ulang aplikasi.
     * - sqlite: virtual table FTS5, karena SQLite tidak punya kolom
     *   ter-generate bertipe tsvector.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE TABLE IF NOT EXISTS product_search ('.
                'product_id bigint, '.
                'title text, '.
                'summary text, '.
                'description text, '.
                'tsv tsvector GENERATED ALWAYS AS ('.
                "setweight(to_tsvector('simple', coalesce(title, '')), 'A') || ".
                "setweight(to_tsvector('simple', coalesce(summary, '')), 'B') || ".
                "setweight(to_tsvector('simple', coalesce(description, '')), 'C')".
                ') STORED)'
            );

            DB::statement(
                'CREATE INDEX IF NOT EXISTS product_search_tsv_idx '.
                'ON product_search USING GIN (tsv)'
            );

            return;
        }

        DB::statement(
            'CREATE VIRTUAL TABLE IF NOT EXISTS product_search USING fts5('.
            'product_id UNINDEXED, title, summary, description, '.
            "tokenize='unicode61 remove_diacritics 2'"
            .')'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS product_search');
    }
};
