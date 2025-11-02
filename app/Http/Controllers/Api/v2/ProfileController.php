<?php

namespace App\Http\Controllers\Api\v2;

use App\Responses\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Update own profile
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check permission
        if (!$user->can('profile.edit.own')) {
            return ApiResponse::error(null, 'Unauthorized', 403);
        }

        // Validate - email must be unique except for current user
        // Validator::make() for manual validation with more control than $request->validate()
        // Source: https://laravel.com/docs/11.x/validation#rule-unique
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                ['error' => $validator->errors()],
                'Validation error',
                400
            );
        }

        $validated = $validator->validated();

        // If email changed, reset verification
        if ($user->email !== $validated['email']) {
            $user->email_verified_at = null;
        }

        // Update user
        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        return ApiResponse::success(
            ['user' => $user],
            'Profile updated successfully',
            200
        );
    }

    /**
     * Delete own account
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check permission
        if (!$user->can('profile.delete.own')) {
            return ApiResponse::error(null, 'Unauthorized', 403);
        }

        // Validate password
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                ['error' => $validator->errors()],
                'Validation error',
                400
            );
        }

        $validated = $validator->validated();

        // Verify password
        if (!Hash::check($validated['password'], $user->password)) {
            return ApiResponse::error(
                null,
                'Incorrect password',
                401
            );
        }

        // Delete all tokens
        $user->tokens()->delete();

        // Delete user
        $user->delete();

        return ApiResponse::success(
            null,
            'Account deleted successfully',
            200
        );
    }
}
