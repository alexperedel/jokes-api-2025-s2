<?php

namespace App\Policies;

use App\Models\Vote;
use App\Models\User;

class VotePolicy
{
    /**
     * Determine whether the user can vote.
     */
    public function create(User $user) {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        return $user->can('vote.add');
    }

    public function update(User $user, Vote $vote) {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        if ($vote->user_id != $user->id) {
            return false;
        }

        return $user->can('vote.edit.own');
    }

    public function delete(User $user, Vote $vote) {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }

        if ($vote->user_id != $user->id) {
            return false;
        }

        return $user->can('vote.delete.own');
    }

    public function clearUser(User $user) {
        return $user->can('vote.clear.user');
    }

    public function resetAll(User $user) {
        return $user->can('vote.reset.all');
    }
}