<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name')->toString(),
            'username' => Str::lower($request->string('username')->toString()),
            'email' => Str::lower($request->string('email')->toString()),
            'password' => $request->string('password')->toString(),
        ]);

        $user->refresh();

        Auth::login($user, $request->boolean('remember'));

        return $this->respondWithUser($user, 201, 'Pendaftaran berhasil.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = [
            'email' => Str::lower($request->string('email')->toString()),
            'password' => $request->string('password')->toString(),
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 422);
        }

        $user = Auth::user();

        if ($user->isBanned()) {
            Auth::logout();
            $request->session()->invalidate();

            return response()->json([
                'message' => 'Akun kamu sedang diblokir. Hubungi admin.',
            ], 422);
        }

        $user->load('developerProfile');

        return $this->respondWithUser($user, 200, 'Berhasil masuk.');
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Berhasil keluar.',
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        return $this->respondWithUser($request->user()->load('developerProfile'));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $payload = $request->safe()->only(['name', 'username', 'email', 'bio', 'password']);

        if (array_key_exists('username', $payload)) {
            $payload['username'] = Str::lower($payload['username']);
        }

        if (array_key_exists('email', $payload)) {
            $payload['email'] = Str::lower($payload['email']);
        }

        if (empty($payload['password'] ?? null)) {
            unset($payload['password']);
        }

        if ($request->hasFile('avatar')) {
            $payload['avatar_path'] = $request->file('avatar')->store('avatars', 'r2');
        }

        $user->update($payload);

        return $this->respondWithUser($user->refresh()->load('developerProfile'), 200, 'Profil diperbarui.');
    }

    private function respondWithUser(User $user, int $status = 200, string $message = 'OK'): JsonResponse
    {
        return (new UserResource($user))
            ->additional(['message' => $message])
            ->response()
            ->setStatusCode($status);
    }
}
