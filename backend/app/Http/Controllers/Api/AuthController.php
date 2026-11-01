<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\DriverResource;
use App\Http\Resources\RiderResource;
use App\Models\Driver;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function riderLogin(LoginRequest $request): JsonResponse
    {
        return $this->issueToken($request, Rider::class, 'rider');
    }

    public function driverLogin(LoginRequest $request): JsonResponse
    {
        return $this->issueToken($request, Driver::class, 'driver');
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profile($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['message' => 'Logged out.']]);
    }

    /** @param class-string<Rider|Driver> $model */
    private function issueToken(LoginRequest $request, string $model, string $role): JsonResponse
    {
        $user = $model::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            // Same message for unknown email and wrong password to avoid account enumeration.
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $lifetime = config('sanctum.expiration');
        $token = $user->createToken("{$role}-web", [$role], $lifetime ? now()->addMinutes($lifetime) : null);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
                ...$this->profile($user),
            ],
        ]);
    }

    /** @return array{role: string, user: mixed} */
    private function profile(Rider|Driver $user): array
    {
        return $user instanceof Driver
            ? ['role' => 'driver', 'user' => new DriverResource($user)]
            : ['role' => 'rider', 'user' => new RiderResource($user)];
    }
}
