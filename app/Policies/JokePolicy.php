<?php

namespace App\Policies;

use App\Models\Joke;
use App\Models\User;

class JokePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        return $user->can('joke.browse');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        return $user->can('joke.show.any');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('joke.add');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Joke $joke): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->can('joke.edit.any')) {
            return true;
        }

        if ($user->can('joke.edit.own') && $joke->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Joke $joke): bool
    {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->can('joke.delete.any')) {
            return true;
        }

        if ($user->can('joke.delete.own') && $joke->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restoreOne(User $user): bool
    {
        return $user->can('joke.trash.recover.one');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user): bool
    {
        return $user->can('joke.trash.remove.one');
    }

    /**
     * Determine whether the user can view soft-deleted categories.
     */
    public function viewTrashed(User $user): bool
    {
        return $user->can('joke.trash.view');
    }

    public function restoreAll(User $user): bool
    {
        return $user->can('joke.trash.recover.all');
    }

    public function emptyTrash(User $user): bool
    {
        return $user->can('joke.trash.empty.all');
    }
}
