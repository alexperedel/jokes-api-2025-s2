<?php

namespace App\Http\Controllers\Api\v2;

use App\Responses\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;


/**
 * API Version 2 - AuthController
 */
class AuthController extends Controller
{
    /**
     * Register a User
     *
     * Provide registration capability to the client app
     *
     * Registration requires:
     * - name
     * - valid email address
     * - password (min 6 character)
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function register(Request $request): JsonResponse
    {
        //  check https://laravel.com/docs/12.x/validation#rule-email
        $validator = Validator::make(
            $request->all(),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users',],
                'password' => ['required', 'string', 'min:6', 'confirmed',],
                'password_confirmation' => ['required', 'string', 'min:6',],
            ]
        );

        if ($validator->fails()) {
            return ApiResponse::error(
                ['error' => $validator->errors()],
                'Registration details error',
                401
            );
        }

        $user = User::create([
            'name' => $validator->validated()['name'],
            'email' => $validator->validated()['email'],
            'password' => Hash::make(
                $validator->validated()['password']
            ),
        ]);

        $token = $user->createToken('MyAppToken')->plainTextToken;

        return ApiResponse::success(
            [
                'token' => $token,
                'user' => $user,
            ],
            'User successfully created',
            201
        );
    }

    /**
     * User Login
     *
     * Attempt to log the user in using email
     * and password based authentication.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        // Alternative using string based validation rules
        // $validator = Validator::make($request->all(), [
        //     'email' => 'required|string|email|max:255',
        //     'password' => 'required|string|min:6',
        // ]);
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255',],
            'password' => ['required', 'string',],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                [
                    'error' => $validator->errors()
                ],
                'Invalid credentials',
                401
            );
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::error(
                [],
                'Invalid credentials',
                401);
        }

        $user = Auth::user();
        $token = $user->createToken('MyAppToken')->plainTextToken;

        return ApiResponse::success(
            [
                'token' => $token,
                'user' => $user,
            ],
            'Login successful'
        );
    }

    /**
     * User Profile API
     *
     * Provide the user's profile information, including:
     * - name,
     * - email,
     * - email verified,
     * - created at, and
     * - updated at.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profile(Request $request): JsonResponse
    {
        return ApiResponse::success(
            [
                'user' => $request->user(),
            ],
            'User profile request successful'
        );
    }

    /**
     * User Logout
     *
     * Log user out of system, cleaning token and session details.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return ApiResponse::success(
            [],
            'Logout successful'
        );
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                ['error' => $validator->errors()],
                'Validation error',
                400
            );
        }

        $validated = $validator->validated();

        // Use helper to send reset link
        $result = $this->sendPasswordResetLink($validated['email']);

        // Always returns success
        return ApiResponse::success(
            null,
            'If that email exists, a password reset link has been sent',
            200
        );
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
            'new_password_confirmation' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                [
                    'error' => $validator->errors()
                ],
        'Validation error',
            400
            );
        }

        $validated = $validator->validated();
        $user = auth()->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return ApiResponse::error(
                null,
                'Current password is incorrect',
                401
            );
        }

        if (Hash::check($validated['new_password'], $user->password)) {
            return ApiResponse::error(
                null,
                'New password must be different from current password',
                400
            );
        } 
        
        $user->update([
            'password' => Hash::make($validated['new_password'])
        ]);

        $user->tokens()->delete();

        return ApiResponse::success(
        null,
        'Password reset successful. Please login again.',
            200
        );
    }

    public function resetPasswordForUser(Request $request, string $user_id): JsonResponse
    {
        $targetUser = User::find($user_id);
        
        if ($targetUser === null) {
            return ApiResponse::error(null, 'User not found', 404);
        }

        $currentUser = auth()->user();

        if (!$currentUser->can('auth.reset.password.others')) {
            return ApiResponse::error(null, 'Unauthorized', 403);
        }

        // Role-based restrictions
        if ($currentUser->hasRole('staff')) {
            if (!$targetUser->hasRole('client')) {
                return ApiResponse::error(
                    null,
                    'Cannot reset password for this user',
                    403
                );
            }
        }
        elseif ($currentUser->hasRole('admin')) {
            if ($targetUser->hasRole('admin') || $targetUser->hasRole('superuser')) {
                return ApiResponse::error(
                    null,
                    'Cannot reset password for this user',
                    403
                );
            }
        }

        // Use helper to send reset link
        $result = $this->sendPasswordResetLink($targetUser->email);

        if ($result['success']) {
            return ApiResponse::success(null, $result['message'], 200);
        }

        return ApiResponse::error(null, $result['message'], 500);
    }

    public function forceLogoutUser(Request $request, string $user_id): JsonResponse
    {
        $targetUser = User::find($user_id);

        if ($targetUser === null) {
            return ApiResponse::error(null, 'User not found', 404);
        }

        $currentUser = auth()->user();

        if (!$currentUser->can('auth.force.logout.others')) {
            return ApiResponse::error(null, 'Unauthorized', 403);
        }

        if ($currentUser->hasRole('staff')) {
            if (!$targetUser->hasRole('client')) {
                return ApiResponse::error(
                    null,
                    'Cannot logout this user',
                    403
                );
            }
        }
        elseif ($currentUser->hasRole('admin')) {
            if ($targetUser->hasRole('admin') || $targetUser->hasRole('superuser')) {
                return ApiResponse::error(
                    null,
                    'Cannot logout this user',
                    403
                );
            }
        }

        $targetUser->tokens()->delete();

        return ApiResponse::success(
            null,
            "User has been logged out",
            200
        );

    }

    public function forceLogoutRole(Request $request, string $role): JsonResponse
    {
        $validRoles = ['client', 'staff', 'admin', 'superuser'];

        if (!in_array($role, $validRoles)) {
            return ApiResponse::error(
                null,
                'Invalid role',
                400
            );
        }

        $currentUser = auth()->user();

        if (!$currentUser->can('auth.force.logout.others')) {
            return ApiResponse::error(null, 'Unauthorized', 403);
        }

        if ($currentUser->hasRole('staff')) {
            if ($role !== 'client') {
                return ApiResponse::error(
                    null,
                    'Cannot logout this role',
                    403
                );
            }
        }
        elseif ($currentUser->hasRole('admin')) {
            if ($role === 'admin' || $role === 'superuser') {
                return ApiResponse::error(
                    null,
                    'Cannot logout this role',
                    403
                );
            }
        }

        $users = User::role($role)->get();

        if (count($users) === 0) {
            return ApiResponse::error(
                null,
                "No users found with role {$role}",
                404
            );
        }

        foreach ($users as $user) {
            $user->tokens()->delete();
        }

        return ApiResponse::success(
            null,
            "All {$role} users have been logged out",
            200
        );

    }

    /**
     * Helper method to send password reset link
     * Just handles the email sending - caller handles authorization
     * 
     * @param string $email
     * @return array ['success' => bool, 'message' => string]
     */
    private function sendPasswordResetLink(string $email): array
    {
        $status = Password::sendResetLink(['email' => $email]);

        if ($status === Password::RESET_LINK_SENT) {
            return [
                'success' => true,
                'message' => "Password reset link sent to {$email}"
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to send password reset link'
        ];
    }

}
