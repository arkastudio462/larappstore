<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // SQLite menolak ADD COLUMN NOT NULL tanpa default ketika tabel sudah
            // berisi baris, jadi kolom dibuat nullable lebih dulu lalu di-backfill.
            // Kewajiban isi username dijaga pada level aplikasi (validasi form).
            $table->string('username', 50)->nullable()->unique();
            $table->string('role', 20)->default('user');
            $table->string('bio', 500)->nullable();
            $table->string('avatar_path')->nullable();
            $table->unsignedBigInteger('followers_count')->default(0);
            $table->unsignedBigInteger('following_count')->default(0);
            $table->unsignedBigInteger('posts_count')->default(0);
            $table->softDeletes();

            $table->index('role');
        });

        DB::table('users')
            ->whereNull('username')
            ->update(['username' => DB::raw("'user' || id")]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username',
                'role',
                'bio',
                'avatar_path',
                'followers_count',
                'following_count',
                'posts_count',
                'deleted_at',
            ]);
        });
    }
};
