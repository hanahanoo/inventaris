<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi login gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->role !== 'pegawai') {
            Auth::logout();

            return response()->json([
                'success' => false,
                'message' => 'Akses aplikasi ini hanya untuk pegawai.',
            ], 403);
        }

        if ($user->is_banned) {
            Auth::logout();

            return response()->json([
                'success' => false,
                'message' => 'Akun pegawai ini sedang diblokir.',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login success',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->transformUser($user),
        ]);
    }

    public function logout()
    {
        $user = Auth::user();

        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'logout success'
        ]);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        if ($user->role !== 'pegawai') {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'Akses aplikasi ini hanya untuk pegawai.',
            ], 403);
        }

        if ($user->is_banned) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'Akun pegawai ini sedang diblokir.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile fetched successfully',
            'user' => $this->transformUser($user),
        ]);
    }

    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
