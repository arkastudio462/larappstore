<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('developerProfile')
            ->latest('id');

        if ($search = trim($request->string('q')->toString())) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $role = $request->string('role')->toString();

        if (in_array($role, [User::ROLE_USER, User::ROLE_DEVELOPER, User::ROLE_ADMIN], true)) {
            $query->where('role', $role);
        }

        return $this->paginated($query->paginate(20), UserResource::class);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if ($request->has('role')) {
            Gate::authorize('assignRole', $user);
            $user->forceFill(['role' => $request->string('role')->toString()])->save();
        }

        if ($request->has('banned')) {
            Gate::authorize('ban', $user);
            $user->forceFill([
                'banned_at' => $request->boolean('banned') ? now() : null,
            ])->save();
        }

        return (new UserResource($user->refresh()->load('developerProfile')))
            ->additional(['message' => 'Pengguna diperbarui.'])
            ->response();
    }
}
