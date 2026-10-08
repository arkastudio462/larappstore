<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    public function show(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);

        return (new PostResource($this->presentable($post)))->response();
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $request->user()->posts()->create([
            'type' => $request->has('media') ? Post::TYPE_IMAGE : Post::TYPE_TEXT,
            'body' => $request->string('body')->toString(),
            'media' => $request->input('media'),
            'status' => Post::STATUS_DRAFT,
            'published_at' => null,
        ]);

        if ($request->input('status') === Post::STATUS_PUBLISHED) {
            $post->publish();
        }

        return (new PostResource($this->presentable($post)))
            ->additional(['message' => 'Postingan dibuat.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $post->fill($request->safe()->only(['body', 'media']));

        if ($request->has('media')) {
            $post->type = Post::TYPE_IMAGE;
        }

        $post->save();

        return (new PostResource($this->presentable($post)))
            ->additional(['message' => 'Postingan diperbarui.'])
            ->response();
    }

    public function destroy(Post $post): JsonResponse
    {
        Gate::authorize('delete', $post);

        $post->withdrawFromFeed();
        $post->delete();

        return response()->json(['message' => 'Postingan dihapus.']);
    }

    public function publish(Post $post): JsonResponse
    {
        Gate::authorize('publish', $post);

        $published = $post->publish();

        return (new PostResource($this->presentable($post)))
            ->additional(['message' => $published ? 'Postingan diterbitkan.' : 'Postingan sudah terbit.'])
            ->response();
    }

    /**
     * Muat relasi yang dibutuhkan `PostResource` dalam satu tempat agar
     * setiap aksi mengembalikan bentuk yang sama tanpa query berlebih.
     */
    private function presentable(Post $post): Post
    {
        return $post->load('user')->loadExists(['likedByViewer as is_liked']);
    }
}
