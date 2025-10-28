<?php

namespace App\Http\Controllers\Api\v1;

use App\Responses\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    use AuthorizesRequests;
    public function update(Request $request)
    {

        // Validate
        $rules = [
            'name' => ['required', 'string', 'max:255',],
            'email' => ['required', 'string', 'email', 'max:255','unique'],
        ];
        $validated = $request->validate($rules);

        $user = User::find($request->user_id);

        if ($user === null) {
            return ApiResponse::error($user, "User not found", 404);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        return ApiResponse::success($user, 'Profile updated', 200);
    }

}
