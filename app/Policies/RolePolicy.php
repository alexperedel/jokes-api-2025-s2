<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     * 
     * Permission: admin and superuser only
     * Source: https://spatie.be/docs/laravel-permission/v6/basic-usage/role-permissions
     */
    public function viewAny(User $user): bool
    {
        // Source: https://medium.com/@joshuaadedoyin2/laravel-email-verification-for-apis-a-step-by-step-guide-4e231bf14370
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        return $user->hasRole(['admin', 'superuser']);
    }

    /**
     * Determine whether the user can view a single role.
     * 
     * Permission: admin and superuser only
     */
    public function view(User $user, Role $role): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        return $user->hasRole(['admin', 'superuser']);
    }

    /**
     * Determine whether the user can create roles.
     * 
     * Permission: admin and superuser only
     */
    public function create(User $user): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        return $user->hasRole(['admin', 'superuser']);
    }

    /**
     * Determine whether the user can update roles.
     * 
     * Permission: admin and superuser only
     */
    public function update(User $user, Role $role): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        return $user->hasRole(['admin', 'superuser']);
    }

    /**
     * Determine whether the user can delete roles.
     * 
     * Permission: superuser only
     */
    public function delete(User $user, Role $role): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        return $user->hasRole('superuser');
    }
}
