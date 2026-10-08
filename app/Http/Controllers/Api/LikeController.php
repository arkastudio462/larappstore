<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Like\ToggleLikeRequest;
use App\Models\Post;
use App\Notifications\LikeReceived;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LikeController extends Controller
{
    /**
     * Toggle suka pada konten polimorfik.
     *
     * Baris like dibuat lewat `insertOrIgnore` (satu statement atomik yang
     * dijamin unique) dan penghitung hanya disentuh bila barisnya benar-benar
     * berubah — permintaan ganda tetap idempoten.
     */
    public function toggle(ToggleLikeRequest $request): JsonResponse
    {
        $viewer = $request->user();
        $class = ToggleLikeRequest::TYPES[$request->string('likeable_type')->toString()];

        /** @var Model $likeable */
        $likeable = $class::query()->findOrFail($request->integer('likeable_id'));

        if ($likeable instanceof Post) {
            Gate::authorize('view', $likeable);
        }

        $type = $likeable->getMorphClass();
        $id = $likeable->getKey();

        $scope = fn () => DB::table('likes')
            ->where('user_id', $viewer->id)
            ->where('likeable_type', $type)
            ->where('likeable_id', $id);

        if ($scope()->exists()) {
            $removed = $scope()->delete();

            if ($removed > 0) {
                $this->adjustCount($class, $id, -1);
            }

            $liked = false;
        } else {
            try {
                $inserted = $scope()->insertOrIgnore([
                    'user_id' => $viewer->id,
                    'likeable_type' => $type,
                    'likeable_id' => $id,
                    'created_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                $inserted = 0;
            }

            if ($inserted > 0) {
                $this->adjustCount($class, $id, 1);

                if ($likeable instanceof Post && $likeable->user !== null && ! $likeable->user->is($viewer)) {
                    $likeable->user->notify(new LikeReceived($viewer, $likeable));
                }
            }

            $liked = true;
        }

        return response()->json([
            'message' => $liked ? 'Postingan disukai.' : 'Suka dibatalkan.',
            'data' => [
                'is_liked' => $liked,
                'likes_count' => $class::query()->whereKey($id)->value('likes_count'),
            ],
        ]);
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function adjustCount(string $class, int|string $id, int $delta): void
    {
        if ($delta > 0) {
            $class::query()->whereKey($id)->increment('likes_count');

            return;
        }

        $class::query()
            ->whereKey($id)
            ->where('likes_count', '>', 0)
            ->decrement('likes_count');
    }
}
