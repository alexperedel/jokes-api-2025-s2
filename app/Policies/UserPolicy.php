<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can browse all users.
     */
    public function viewAny(User $user): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        return $user->can('user.browse');
    }

    /**
     * Determine whether the user can view a specific user.
     */
    public function view(User $user, User $targetUser): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->id === $targetUser->id) {
            return true;
        }

        return $user->can('user.show.any');
    }

    /**
     * Determine whether the user can create new users.
     */
    public function create(User $user): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        // Has any user creation permission
        return $user->can('user.add.client') || 
               $user->can('user.add.staff') || 
               $user->can('user.add.admin');
    }

    /**
     * Check if user can create another user with specific role.
     * Use this in controller for role-specific validation.
     */
    public function createWithRole(User $user, string $role): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        // Check specific permission for the role being created
        if ($role === 'client') {
            return $user->can('user.add.client');
        }
        
        if ($role === 'staff') {
            return $user->can('user.add.staff');
        }
        
        if ($role === 'admin') {
            return $user->can('user.add.admin');
        }
        
        // Cannot create superuser
        return false;
    }

    /**
     * Determine whether the user can update another user.
     */
    public function update(User $user, User $targetUser): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        // Can always edit own profile
        if ($user->id === $targetUser->id) {
            return $user->can('user.edit.own');
        }

        // Check if can edit any user
        if ($user->can('user.edit.any')) {
            // But cannot edit admins or superusers unless you are superuser
            if ($targetUser->hasRole('admin') || $targetUser->hasRole('superuser')) {
                return $user->hasRole('superuser') && !$targetUser->hasRole('superuser');
            }
            return true;
        }

        // Check if can edit staff specifically
        if ($user->can('user.edit.staff') && $targetUser->hasRole('staff')) {
            return true;
        }

        // Check if can edit clients specifically
        if ($user->can('user.edit.client') && $targetUser->hasRole('client')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete another user.
     */
    public function delete(User $user, User $targetUser): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        // Client can delete own profile
        if ($user->id === $targetUser->id && $targetUser->hasRole('client')) {
            return $user->can('user.delete.own');
        }

        // Check if can delete any user
        if ($user->can('user.delete.any')) {
            // Cannot delete self (already checked above)
            return $user->id !== $targetUser->id;
        }

        // Check if can delete staff specifically
        if ($user->can('user.delete.staff') && $targetUser->hasRole('staff')) {
            return true;
        }

        // Check if can delete clients specifically
        if ($user->can('user.delete.client') && $targetUser->hasRole('client')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore a soft-deleted user.
     */
    public function restore(User $user, User $targetUser): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        if (!$user->can('user.trash.restore')) {
            return false;
        }

        // Apply same restrictions as delete
        // If has delete.any, can restore anyone except themselves
        if ($user->can('user.delete.any')) {
            return $user->id !== $targetUser->id;
        }

        // If can delete staff, can restore staff
        if ($user->can('user.delete.staff') && $targetUser->hasRole('staff')) {
            return true;
        }

        // If can delete clients, can restore clients
        if ($user->can('user.delete.client') && $targetUser->hasRole('client')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete a user.
     */
    public function forceDelete(User $user, User $targetUser): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        // Only superuser with delete.any permission can force delete
        if (!$user->can('user.delete.any')) {
            return false;
        }

        // Cannot force delete self
        return $user->id !== $targetUser->id;
    }

    /**
     * Determine whether the user can search for users.
     */
    public function search(User $user): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        return $user->can('user.search');
    }

    /**
     * Determine whether the user can assign a role to another user.
     */
    public function assignRole(User $user, User $targetUser, string $newRole): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        // Need role assignment permission
        if (!$user->can('user.assign.role')) {
            return false;
        }

        // Cannot change superuser roles
        if ($targetUser->hasRole('superuser')) {
            return false;
        }

        // Cannot assign superuser role
        if ($newRole === 'superuser') {
            return false;
        }

        // Check if user has permission to add the specific role
        // This ensures admins can only assign roles they can create
        $permissionMap = [
            'client' => 'user.add.client',
            'staff' => 'user.add.staff',
            'admin' => 'user.add.admin',
        ];

        if (!isset($permissionMap[$newRole])) {
            return false;
        }

        return $user->can($permissionMap[$newRole]);
    }

    /**
     * Determine whether the user can view trashed users.
     */
    public function viewTrashed(User $user): bool
    {
        return $user->can('user.trash.view');
    }

    /**
     * Determine whether the user can restore all trashed users.
     */
    public function restoreAll(User $user): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        return $user->can('user.trash.restore');
    }

    /**
     * Determine whether the user can permanently delete (force delete) all trashed users.
     */
    public function emptyTrash(User $user): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        // Only superuser can permanently delete
        return $user->can('user.delete.any');
    }
}