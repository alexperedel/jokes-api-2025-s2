<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use AuthorizesRequests;
    
    /**
     * Browse - Get all roles.
     * 
     * Permission: admin and superuser only
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $this->authorize('viewAny', Role::class);

        // Source: https://spatie.be/docs/laravel-permission/v6/basic-usage/role-permissions
        // Source: https://laracasts.com/discuss/channels/laravel/get-all-permissions-from-role
        $roles = Role::with('permissions')->get();
        
        return ApiResponse::success($roles, "Roles retrieved");
    }

    /**
     * Read - Get a single role.
     * 
     * Permission: admin and superuser only
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        $role = Role::with('permissions')->find($id);

        if ($role === null) {
            return ApiResponse::error(null, "Role not found", 404);
        }

        $this->authorize('view', $role);

        return ApiResponse::success($role, "Role retrieved");
    }

    /**
     * Add - Create a new role.
     * 
     * Permission: admin and superuser only
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', Role::class);

        // Validate request data
        // Source: https://laravel.com/docs/11.x/validation#available-validation-rules
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        // Source: https://spatie.be/docs/laravel-permission/v6/basic-usage/role-permissions
        // Source: https://laracasts.com/discuss/channels/laravel/laravel-permission-whats-the-utility-of-the-guard-name
        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

        // Attach permissions if provided
        // Source: https://spatie.be/docs/laravel-permission/v6/basic-usage/role-permissions#assigning-permissions
        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return ApiResponse::success(
            $role->load('permissions'),
            "Role created",
            201
        );
    }

    /**
     * Edit - Update an existing role.
     * 
     * Permission: admin and superuser only
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id)
    {
        $role = Role::find($id);

        if ($role === null) {
            return ApiResponse::error(null, "Role not found", 404);
        }

        $this->authorize('update', $role);

        // Prevent editing superuser role
        if ($role->name === 'superuser') {
            return ApiResponse::error(
                null,
                "Cannot edit superuser role",
                403
            );
        }

        // Validate request data
        // Source: https://laravel.com/docs/11.x/validation#available-validation-rules
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255', "unique:roles,name,{$id}"],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }

        // Source: https://spatie.be/docs/laravel-permission/v6/basic-usage/role-permissions#sync-permissions
        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return ApiResponse::success(
            $role->load('permissions'),
            "Role updated"
        );
    }

    /**
     * Delete - Remove a role.
     * 
     * Permission: superuser only
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        $role = Role::find($id);

        if ($role === null) {
            return ApiResponse::error(null, "Role not found", 404);
        }

        $this->authorize('delete', $role);

        if ($role->name === 'superuser') {
            return ApiResponse::error(
                null,
                "Cannot delete superuser role",
                403
            );
        }

        // Check if role has users
        // Source: https://spatie.be/docs/laravel-permission/v6/basic-usage/role-permissions#eloquent
        $userCount = $role->users()->count();
        
        if ($userCount > 0) {
            return ApiResponse::error(
                null,
                "Cannot delete role with {$userCount} assigned user(s)",
                // Source: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/409
                409
            );
        }

        $role->delete();

        return ApiResponse::success(
            null,
            "Role deleted"
        );
    }

    /**
     * Search - Find roles by keyword in name.
     * 
     * Permission: admin and superuser only
     *
     * @param string $keyword
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(string $keyword)
    {
        $this->authorize('viewAny', Role::class);

        // Source: https://laravel.com/docs/11.x/queries#where-clauses
        $roles = Role::with('permissions')
            ->where('name', 'like', "%{$keyword}%")
            ->get();

        if ($roles->isEmpty()) {
            return ApiResponse::error(
                null,
                "No roles found matching '{$keyword}'",
                404
            );
        }

        return ApiResponse::success($roles, "Found {$roles->count()} role(s)");
    }
}
