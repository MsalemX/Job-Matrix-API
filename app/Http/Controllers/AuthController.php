<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

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

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid login details',
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
            'message' => 'Logged out',
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
                'assignedTasks' => function ($q) {
                    $q->whereHas('project', function ($q2) {
                        $q2->where('visibility', 'public');
                    });
                },
            ]),
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
                'assignedTasks' => function ($q) {
                    $q->whereHas('project', function ($q2) {
                        $q2->where('visibility', 'public');
                    });
                },
            ]),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'bio' => 'nullable|string',
            'avatar' => 'nullable|image|max:2048', // تم التغيير من string إلى image
            'skills' => 'nullable|array',
            'skills.*' => 'string',
        ]);

        // منطق رفع وحفظ الصورة
        if ($request->hasFile('avatar')) {
            // حفظ الصورة في مجلد public/avatars
            $path = $request->file('avatar')->store('avatars', 'public');
            // تخزين الرابط الكامل للصورة في قاعدة البيانات
            $validated['avatar'] = asset('storage/'.$path);
        }

        // تحديث بيانات المستخدم الأساسية
        $userFields = array_intersect_key($validated, array_flip(['name', 'email', 'username']));
        if (! empty($userFields)) {
            $user->update($userFields);
        }

        // التأكد من وجود بروفايل
        $profile = $user->profile ?? $user->profile()->create();

        // تحديث بيانات البروفايل (البيو والرابط الجديد للصورة)
        $profileFields = array_intersect_key($validated, array_flip(['bio', 'avatar']));
        if (! empty($profileFields)) {
            $profile->update($profileFields);
        }

        // استبدال المهارات إذا تم توفيرها
        if (array_key_exists('skills', $validated)) {
            $profile->skills()->delete();
            $skills = $validated['skills'] ?? [];
            foreach ($skills as $skillName) {
                if (is_string($skillName) && trim($skillName) !== '') {
                    $profile->skills()->create(['name' => $skillName]);
                }
            }
        }

        return response()->json([
            'message' => 'Profile updated',
            'user' => $user->load(['profile.skills']), // تحميل البيانات الجديدة
        ]);
    }

    public function deactivateAccount(Request $request)
    {
        $user = $request->user();

        // mark user as inactive and set deactivated timestamp
        $user->update([
            'active' => false,
            'updated_at' => now(),
        ]);

        // set deactivated_at on users table if column exists
        if (Schema::hasColumn('users', 'deactivated_at')) {
            $user->forceFill(['deactivated_at' => now()])->save();
        }

        // revoke all tokens to immediately block access
        $user->tokens()->delete();

        return response()->json(['message' => 'Account deactivated']);
    }

    /**
     * Return tasks assigned to the authenticated user.
     */
    public function myTasks(Request $request)
    {
        $user = $request->user();

        $tasks = $user->assignedTasks()
            ->with(['project', 'section', 'attachments'])
            ->latest()
            ->get();

        return response()->json(['tasks' => $tasks]);
    }
}
