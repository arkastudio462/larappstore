<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\CreditLedger;
use App\Models\DeveloperProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeveloperController extends Controller
{
    /**
     * Upgrade user → developer: gratis, instan, tanpa verifikasi.
     *
     * Pemberian kredit memakai `firstOrCreate` pada `developer_profiles`
     * (unique per user) sehingga permintaan ganda tidak menggandakan bonus.
     */
    public function upgrade(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === User::ROLE_DEVELOPER) {
            return response()->json([
                'message' => 'Kamu sudah developer.',
                'data' => ['user' => $this->resourceFor($user)],
            ]);
        }

        if ($user->role === User::ROLE_ADMIN) {
            return response()->json([
                'message' => 'Akun admin sudah memiliki seluruh hak developer.',
                'data' => ['user' => $this->resourceFor($user)],
            ]);
        }

        $profile = DeveloperProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'studio_name' => Str::limit($user->name, 100, ''),
                'slug' => $this->uniqueStudioSlug($user),
                'upload_credits' => 1,
            ],
        );

        if ($profile->wasRecentlyCreated) {
            CreditLedger::query()->create([
                'user_id' => $user->id,
                'delta' => 1,
                'type' => CreditLedger::TYPE_SIGNUP_BONUS,
                'balance_after' => 1,
                'description' => 'Bonus 1 slot upload saat pertama kali menjadi developer',
            ]);
        }

        if ($user->role !== User::ROLE_DEVELOPER) {
            $user->forceFill(['role' => User::ROLE_DEVELOPER])->save();
        }

        return response()->json([
            'message' => 'Selamat, kamu sekarang developer. Kamu dapat 1 slot upload gratis.',
            'data' => ['user' => $this->resourceFor($user->refresh())],
        ]);
    }

    private function resourceFor(User $user): UserResource
    {
        return new UserResource($user->load('developerProfile'));
    }

    private function uniqueStudioSlug(User $user): string
    {
        $base = Str::slug($user->username);

        if ($base === '') {
            $base = 'studio-'.$user->id;
        }

        $slug = $base;
        $suffix = 1;

        while (DeveloperProfile::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
