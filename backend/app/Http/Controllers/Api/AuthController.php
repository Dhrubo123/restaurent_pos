<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthController extends Controller
{
    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['token' => $request->session()->token()]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        try {
            $authenticated = Auth::attempt([...$credentials, 'active' => true], $request->boolean('remember'));
        } catch (RuntimeException $exception) {
            report($exception);
            $authenticated = false;
        }

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => ['The supplied credentials are invalid.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['data' => $this->userPayload($request), 'token' => $request->session()->token()]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($request)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out.']);
    }

    private function userPayload(Request $request): array
    {
        $user = $request->user()->load('roles.permissions', 'restaurant');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'restaurant' => $user->restaurant?->only(['id', 'name', 'timezone', 'currency_code']),
            'roles' => $user->roles->pluck('key')->values(),
            'permissions' => $user->roles
                ->flatMap(fn ($role) => $role->permissions)
                ->pluck('key')
                ->unique()
                ->values(),
        ];
    }
}
