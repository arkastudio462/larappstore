<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\UserResource;
use App\Models\Product;
use App\Models\User;
use App\Notifications\FollowedYou;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function show(Request $request, string $username): JsonResponse
    {
        return (new UserResource($this->resolveUser($username)->load('developerProfile')))->response();
    }

    public function posts(Request $request, string $username): JsonResponse
    {
        $user = $this->resolveUser($username);

        $query = $user->posts()->with('user')->latest('published_at');

        if (! $this->canSeeDrafts($request, $user)) {
            $query->published();
        }

        return $this->paginated($query->paginate(15), PostResource::class);
    }

    public function products(Request $request, string $username): JsonResponse
    {
        $user = $this->resolveUser($username);

        $query = Product::query()
            ->where('developer_id', $user->id)
            ->with(['category', 'developer'])
            ->latest('published_at');

        if (! $this->canSeeDrafts($request, $user)) {
            $query->published();
        }

        return $this->paginated($query->paginate(15), ProductResource::class);
    }

    public function followers(Request $request, string $username): JsonResponse
    {
        $user = $this->resolveUser($username);

        return $this->paginated(
            $user->followers()->withExists('followedByViewer as is_following')->orderByDesc('id')->paginate(15),
            UserResource::class,
        );
    }

    public function following(Request $request, string $username): JsonResponse
    {
        $user = $this->resolveUser($username);

        return $this->paginated(
            $user->following()->withExists('followedByViewer as is_following')->orderByDesc('id')->paginate(15),
            UserResource::class,
        );
    }

    /**
     * Toggle mengikuti pengguna.
     *
     * Relasi dibuat lewat `insertOrIgnore` (satu statement atomik yang
     * dijamin unique) dan penghitung hanya disentuh bila barisnya
     * benar-benar berubah — permintaan ganda tetap idempoten.
     */
    public function follow(Request $request, string $username): JsonResponse
    {
        $target = $this->resolveUser($username);
        $viewer = $request->user();

        if ($target->is($viewer)) {
            return response()->json([
                'message' => 'Kamu tidak bisa mengikuti diri sendiri.',
            ], 422);
        }

        $scope = fn () => DB::table('follows')
            ->where('follower_id', $viewer->id)
            ->where('followable_type', User::class)
            ->where('followable_id', $target->id);

        if ($scope()->exists()) {
            $deleted = $scope()->delete();

            if ($deleted > 0) {
                User::whereKey($target->id)->where('followers_count', '>', 0)->decrement('followers_count');
                User::whereKey($viewer->id)->where('following_count', '>', 0)->decrement('following_count');
            }

            $isFollowing = false;
        } else {
            try {
                $inserted = $scope()->insertOrIgnore([
                    'follower_id' => $viewer->id,
                    'followable_type' => User::class,
                    'followable_id' => $target->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                $inserted = 0;
            }

            if ($inserted > 0) {
                User::whereKey($target->id)->increment('followers_count');
                User::whereKey($viewer->id)->increment('following_count');

                $target->notify(new FollowedYou($viewer));
            }

            $isFollowing = true;
        }

        $target->refresh();

        return response()->json([
            'message' => $isFollowing ? 'Mengikuti '.$target->username.'.' : 'Berhenti mengikuti '.$target->username.'.',
            'data' => [
                'is_following' => $isFollowing,
                'followers_count' => $target->followers_count,
            ],
        ]);
    }

    private function canSeeDrafts(Request $request, User $owner): bool
    {
        $viewer = $request->user();

        return $viewer !== null && ($viewer->is($owner) || $viewer->isAdmin());
    }

    private function resolveUser(string $username): User
    {
        $user = User::query()
            ->where('username', $username)
            ->withExists('followedByViewer as is_following')
            ->first();

        abort_if($user === null, 404, 'Pengguna tidak ditemukan.');

        return $user;
    }
}
