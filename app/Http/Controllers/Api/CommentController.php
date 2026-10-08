<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function index(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);

        $comments = $post->comments()
            ->with(['user', 'replies.user'])
            ->paginate(20);

        return $this->paginated($comments, CommentResource::class);
    }

    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);

        $comment = $request->user()->comments()->create([
            'post_id' => $post->id,
            'parent_id' => $request->input('parent_id'),
            'body' => $request->string('body')->toString(),
        ]);

        Post::query()
            ->whereKey($post->id)
            ->increment('comments_count');

        $this->notifyRecipients($request->user(), $post, $comment);

        return (new CommentResource($comment->load('user')))
            ->additional(['message' => 'Komentar ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $comment->purge();

        return response()->json(['message' => 'Komentar dihapus.']);
    }

    /**
     * Beri tahu author postingan, dan author komentar yang dibalas bila
     * berbeda. Pelaku sendiri tidak diberi notifikasi.
     */
    private function notifyRecipients(User $actor, Post $post, Comment $comment): void
    {
        $recipients = collect([$post->user]);

        if ($comment->parent_id !== null) {
            $recipients->push($comment->parent?->user);
        }

        $recipients
            ->filter()
            ->unique('id')
            ->reject(fn (User $user): bool => $user->is($actor))
            ->each(fn (User $user) => $user->notify(new CommentReceived($actor, $comment)));
    }
}
