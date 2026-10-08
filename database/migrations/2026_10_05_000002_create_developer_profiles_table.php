<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('studio_name', 100);
            $table->string('slug', 120)->unique();
            $table->text('bio')->nullable();
            $table->string('website')->nullable();
            $table->unsignedInteger('upload_credits')->default(0);
            $table->boolean('unlimited_uploads')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_profiles');
    }
};
