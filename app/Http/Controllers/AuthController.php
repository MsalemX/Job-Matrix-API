<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();
        
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        // Create profile for the user
        $user->profile()->create();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('profile'),
        ]);
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['login'])
                    ->orWhere('username', $validated['login'])
                    ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid login details'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('profile'),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out'
        ]);
    }

    public function profile(\Illuminate\Http\Request $request)
    {
        return response()->json([
            'user' => $request->user()->load([
                'profile.skills',
                'projectParticipants.project' => function ($query) {
                    $query->where('visibility', 'public');
                },
                'assignedTasks'
            ])
        ]);
    }

    public function showProfile(User $user)
    {
        return response()->json([
            'user' => $user->load([
                'profile.skills',
                'projectParticipants.project' => function ($query) {
                    $query->where('visibility', 'public');
                },
                'assignedTasks'
            ])
        ]);
    }
}
