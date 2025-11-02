<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Email verification check using Laravel's built-in hasVerifiedEmail method
        // Source: https://medium.com/@joshuaadedoyin2/laravel-email-verification-for-apis-a-step-by-step-guide-4e231bf14370
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        // Check if user has specific permission using Spatie Permission package
        // Source: https://spatie.be/docs/laravel-permission/v6/basic-usage/basic-usage
        return $user->can('category.browse');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        return $user->can('category.show.any');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('category.add');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool
    {
        return $user->can('category.edit.any');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user): bool
    {
        return $user->can('category.delete.any');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restoreOne(User $user): bool
    {
        return $user->can('category.trash.recover.one');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user): bool
    {
        return $user->can('category.trash.remove.one');
    }

    /**
     * Determine whether the user can view soft-deleted categories.
     */
    public function viewTrashed(User $user): bool
    {
        return $user->can('category.trash.view');
    }

    public function restoreAll(User $user): bool
    {
        return $user->can('category.trash.recover.all');
    }

    public function emptyTrash(User $user): bool
    {
        return $user->can('category.trash.empty.all');
    }
}
