<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\AccountAuthentication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(private AccountAuthentication $accounts) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $account = $this->accounts->authenticate($validated['email'], $validated['password']);
        if ($account === null) {
            throw ValidationException::withMessages(['email' => ['Email atau password salah, atau akun tidak aktif.']])->status(401);
        }
        $role = $this->accounts->role($account);

        $expiresAt = now()->addDays(30);
        $token = $account->createToken(
            $validated['device_name'] ?? 'mobile-app',
            ['*'],
            $expiresAt,
        );

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
                'user' => [
                    'id' => $account->getKey(),
                    'name' => $account->name,
                    'email' => $account->email,
                    'role' => $role,
                ],
            ],
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $account = $request->user();
        $role = $this->accounts->role($account);

        return response()->json([
            'data' => [
                'id' => $account->getKey(),
                'name' => $account->name,
                'email' => $account->email,
                'role' => $role,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $currentToken = $request->user()->currentAccessToken();

        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();
        }

        return response()->json(['message' => 'Berhasil keluar.']);
    }
}
