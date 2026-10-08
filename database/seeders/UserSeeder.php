<?php

namespace Database\Seeders;

use App\Models\DeveloperProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@larappstore.test'],
            [
                'username' => 'admin',
                'name' => 'Administrator',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
            ],
        );

        $developer = User::query()->firstOrCreate(
            ['email' => 'developer@larappstore.test'],
            [
                'username' => 'studioarunika',
                'name' => 'Studio Arunika',
                'password' => 'password',
                'role' => User::ROLE_DEVELOPER,
            ],
        );

        DeveloperProfile::query()->firstOrCreate(
            ['user_id' => $developer->id],
            [
                'studio_name' => 'Studio Arunika',
                'slug' => 'studio-arunika',
                'bio' => 'Membuat aplikasi ringan untuk kebutuhan sehari-hari.',
                'upload_credits' => 1,
            ],
        );

        User::query()->firstOrCreate(
            ['email' => 'user@larappstore.test'],
            [
                'username' => 'budi',
                'name' => 'Budi Santoso',
                'password' => 'password',
                'role' => User::ROLE_USER,
            ],
        );
    }
}
