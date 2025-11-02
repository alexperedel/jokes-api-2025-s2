<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use App\Models\Vote;
use App\Models\Joke;
use App\Models\User;
use App\Responses\ApiResponse;

class VoteController extends Controller
{
    use AuthorizesRequests;

    /**
     * Vote on a joke (1=like, -1=dislike, 0=remove).
     * 
     * @param Request $request
     * @param string $joke_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function vote(Request $request, string $joke_id) {
        $user = Auth::user();

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'in:-1,0,1'],
        ]);

        $joke = Joke::find($joke_id);

        if ($joke === null) {
            return ApiResponse::error(null, "Joke not found", 404);
        }

        $existingVote = Vote::where('user_id', $user->id)
                    ->where('joke_id', $joke_id)
                    ->first();

        if ($validated['rating'] === 0) {
            if ($existingVote === null) {
                return ApiResponse::error(null, "No vote to remove", 404);
            }
            $this->authorize('delete', $existingVote);
            $existingVote->delete();
            return ApiResponse::success(null, "Vote removed", 200);
        }

        if ($existingVote !== null) {
            $this->authorize('update', $existingVote);
            $existingVote->update(['rating' => $validated['rating']]);
            return ApiResponse::success($existingVote, "Rating Updated", 200);
        }

        if ($existingVote === null) {
            $this->authorize("create", Vote::class);

            $vote = Vote::create([
                "user_id"=> $user->id,
                "joke_id"=> $joke_id,
                "rating"=> $validated['rating'],
            ]);

            $message = $validated['rating'] === 1 ? "Liked" : "Disliked";
            return ApiResponse::success($vote, $message, 201);  
        }
    }

    /**
     * Clear all votes for a specific user.
     * 
     * @param string $user_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearUserVotes(string $user_id) {
        $user = User::find($user_id);

        if ($user === null) {
            return ApiResponse::error(null, "User not found", 404);
        }

        $this->authorize('clearUser', Vote::class);

        $currentUser = auth()->user();

        if ($currentUser->hasRole('admin')) {
            if (!($user->hasRole('staff') || $user->hasRole('client'))) {
                return ApiResponse::error(null, "Cannot clear votes for this user", 403);
            }
        }

        $votes = Vote::where('user_id', $user_id)->get();
        if (count($votes) === 0) {
            return ApiResponse::error(null, "No votes found for this user", 404);
        }

        foreach ($votes as $vote) {
            $vote->delete();
        }

        return ApiResponse::success(null,'Votes Deleted', 200);
    }

    /**
     * Reset all votes in the system.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetAllVotes() {
        $this->authorize('resetAll', Vote::class);

        $votes = Vote::all();
        if (count($votes) === 0) {
            return ApiResponse::error(null, "No votes to reset", 404);
        }

        foreach ($votes as $vote) {
            $vote->delete();
        }

        return ApiResponse::success(null,'Votes reset', 200);
    }
}