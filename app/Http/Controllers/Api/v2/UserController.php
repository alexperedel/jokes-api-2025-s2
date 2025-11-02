<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Responses\ApiResponse;

class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get all users with pagination.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $this->authorize('viewAny', User::class);

        $users = User::with('roles')
            ->orderBy('name')
            ->paginate(15);

        return ApiResponse::success($users, "Users retrieved");
    }

    /**
     * Get a single user with roles.
     * 
     * @param string $id User ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        $user = User::with('roles')->find($id);

        if ($user === null) {
            return ApiResponse::error(null, "User not found", 404);
        }

        $this->authorize('view', $user);

        return ApiResponse::success($user, "User retrieved");
    }

    /**
     * Create a new user with role assignment.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate input
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:client,staff,admin'],
        ];
        $validated = $request->validate($rules);

        // Authorize with specific role
        $this->authorize('createWithRole', [User::class, $validated['role']]);

        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Assign role
        $user->assignRole($validated['role']);

        // Send email verification
        $user->sendEmailVerificationNotification();

        // source: https://stackoverflow.com/questions/26005994/laravel-with-method-versus-load-method
        // Load roles for response
        $user->load('roles');

        return ApiResponse::success($user, 'User created successfully. Verification email sent.', 201);
    }

    /**
     * Update user name and email.
     * 
     * @param Request $request
     * @param string $id User ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);

        if ($user === null) {
            return ApiResponse::error(null, "User not found", 404);
        }

        $this->authorize('update', $user);

        // Validate input
        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ];
        $validated = $request->validate($rules);

        // Update user
        $user->update($validated);

        // Load roles for response
        $user->load('roles');

        return ApiResponse::success($user, 'User updated successfully', 200);
    }

    /**
     * Soft delete a user.
     * 
     * @param string $id User ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        $user = User::find($id);

        if ($user === null) {
            return ApiResponse::error(null, "User not found", 404);
        }

        $this->authorize('delete', $user);

        // Soft delete
        $user->delete();

        return ApiResponse::success(null, 'User deleted successfully', 200);
    }

    /**
     * Search users by name or email.
     * 
     * @param string $keyword
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(string $keyword)
    {
        $this->authorize('search', User::class);

        // Search by name or email
        $users = User::with('roles')
            ->where(function ($query) use ($keyword) {
                $query->where('name', 'like', "%{$keyword}%")
                      ->orWhere('email', 'like', "%{$keyword}%");
            })
            ->orderBy('name')
            ->paginate(15);

        if ($users->isEmpty()) {
            return ApiResponse::error(null, "No users found", 404);
        }

        return ApiResponse::success($users, "Users found", 200);
    }


    /**
     * Assign a role to user.
     * 
     * @param Request $request
     * @param string $id User ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRole(Request $request, string $id)
    {
        $user = User::find($id);

        if ($user === null) {
            return ApiResponse::error(null, "User not found", 404);
        }

        // Validate role
        $rules = [
            'role' => ['required', 'string', 'in:client,staff,admin'],
        ];
        $validated = $request->validate($rules);

        // Authorize with policy (checks both user.assign.role and user.add.{role} permissions)
        $this->authorize('assignRole', [$user, $validated['role']]);

        // Remove all existing roles and assign new one
        $user->syncRoles([$validated['role']]);

        // Load roles for response
        $user->load('roles');

        return ApiResponse::success($user, 'Role assigned successfully', 200);
    }

    /**
     * Get all soft-deleted users.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function trash()
    {
        $this->authorize('viewTrashed', User::class);

        $users = User::onlyTrashed()
            ->with('roles')
            ->orderBy('deleted_at', 'desc')
            ->paginate(15);

        if ($users->isEmpty()) {
            return ApiResponse::error(null, "No soft deleted users found", 404);
        }

        return ApiResponse::success($users, "Soft deleted users retrieved");
    }

    /**
     * Restore a soft-deleted user.
     * 
     * @param string $id User ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function restoreOne(string $id)
    {
        $user = User::onlyTrashed()->find($id);

        if ($user === null) {
            return ApiResponse::error(null, "User not found in trash", 404);
        }

        $this->authorize('restore', $user);

        // Restore user
        $user->restore();

        // Load roles for response
        $user->load('roles');

        return ApiResponse::success($user, 'User restored successfully', 200);
    }

    /**
     * Restore all soft-deleted users.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function restoreAll()
    {
        $this->authorize('restoreAll', User::class);

        $users = User::onlyTrashed()->get();

        if (count($users) === 0) {
            return ApiResponse::error(null, "No soft deleted users found", 404);
        }

        foreach ($users as $user) {
            // Check if authorized to restore this specific user
            if (auth()->user()->can('restore', $user)) {
                $user->restore();
            }
        }

        return ApiResponse::success(null, "Soft deleted users restored");
    }

    /**
     * Permanently delete a user from trash.
     * 
     * @param string $id User ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function emptyOne(string $id)
    {
        $user = User::onlyTrashed()->find($id);

        if ($user === null) {
            return ApiResponse::error(null, "User not found in trash", 404);
        }

        $this->authorize('forceDelete', $user);

        // Permanently delete
        $user->forceDelete();

        return ApiResponse::success(null, "User permanently deleted", 200);
    }

    /**
     * Permanently delete all users from trash.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function emptyAll()
    {
        $this->authorize('emptyTrash', User::class);

        $users = User::onlyTrashed()->get();

        if (count($users) === 0) {
            return ApiResponse::error(null, "No soft deleted users found", 404);
        }

        foreach ($users as $user) {
            // Check if authorized to force delete this specific user
            if (auth()->user()->can('forceDelete', $user)) {
                $user->forceDelete();
            }
        }

        return ApiResponse::success(null, "Soft deleted users permanently removed");
    }

}