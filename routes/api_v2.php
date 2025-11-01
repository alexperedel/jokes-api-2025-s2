<?php

use App\Http\Controllers\Api\v1\AuthController as AuthControllerV1;
use App\Http\Controllers\Api\v2\AuthController as AuthControllerV2;
use App\Http\Controllers\Api\v2\UserController as UserControllerV2;
use App\Http\Controllers\Api\v2\CategoryController as CategoryControllerV2;
use App\Http\Controllers\Api\v2\JokeController as JokeControllerV2;
use App\Http\Controllers\Api\v2\ProfileController as ProfileControllerV2;
use App\Http\Controllers\Api\v2\VoteController as VoteControllerV2;
use Illuminate\Support\Facades\Route;

/**
 * API Version 2 Routes
 */

/**
 * User API Routes
 * - Register, Login (no authentication)
 * - Profile, Logout, User details (authentication required)
 */

Route::prefix('auth')
    ->group(function () {
        Route::post('register', [AuthControllerV1::class, 'register']);
        Route::post('login', [AuthControllerV1::class, 'login']);
        
        // Forgot Password
        Route::post('password/forgot', [AuthControllerV2::class, 'forgotPassword'])
            ->name('auth.password.forgot');

        Route::get('profile', [AuthControllerV1::class, 'profile'])
            ->middleware(['auth:sanctum',]);
        Route::post('logout', [AuthControllerV1::class, 'logout'])
            ->middleware(['auth:sanctum',]);

        Route::middleware('auth:sanctum')->group(function () {
            // Password Reset
            Route::put('password/reset', [AuthControllerV2::class, 'resetPassword'])
                ->name('auth.password.reset.own');
                
            Route::put('password/reset/{user_id}', [AuthControllerV2::class, 'resetPasswordForUser'])
                ->name('auth.password.reset.user');

            // Force Logout
            Route::delete('logout/user/{user_id}', [AuthControllerV2::class, 'forceLogoutUser'])
                ->name('auth.logout.user');
                
            Route::delete('logout/role/{role}', [AuthControllerV2::class, 'forceLogoutRole'])
                ->name('auth.logout.role');
    });

    });

/* Profile Routes */
Route::middleware('auth:sanctum')->group(function () {
    Route::put('profile', [ProfileControllerV2::class, 'update'])
        ->name('profile.update');
    Route::delete('profile', [ProfileControllerV2::class, 'destroy'])
        ->name('profile.destroy');
});

/* Categories Routes */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('categories/trash', [CategoryControllerV2::class, 'trash'])
        ->name('categories.trash');

    Route::delete('categories/trash/empty', [CategoryControllerV2::class, 'removeAll'])
        ->name('categories.trash.remove.all');

    Route::post('categories/trash/recover', [CategoryControllerV2::class, 'recoverAll'])
        ->name('categories.trash.recover.all');

    Route::delete('categories/trash/{id}/remove', [CategoryControllerV2::class, 'removeOne'])
        ->name('categories.trash.remove.one');

    Route::post('categories/trash/{id}/recover', [CategoryControllerV2::class, 'recoverOne'])
        ->name('categories.trash.recover.one');

    /** Stop people trying to "GET" admin/categories/trash/1234/delete or similar */
    Route::get('categories/trash/{id}/{method}', [CategoryControllerV2::class, 'trash']);

    Route::get('categories/search/{keyword}', [CategoryControllerV2::class, 'search'])
        ->name('categories.search');

    Route::resource("categories", CategoryControllerV2::class);

});

// User Admin routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('users/search/{keyword}', [UserControllerV2::class, 'search']);
    
    // Trash management routes MUST come before resource routes
    Route::get('users/trash/list', [UserControllerV2::class, 'trash']);
    Route::post('users/restore-all', [UserControllerV2::class, 'restoreAll']);
    Route::delete('users/trash/empty-all', [UserControllerV2::class, 'emptyAll']);
    Route::post('users/trash/{id}/restore', [UserControllerV2::class, 'restoreOne']);
    Route::delete('users/trash/{id}', [UserControllerV2::class, 'emptyOne']);
    
    // Custom action routes
    Route::post('users/{id}/assign-role', [UserControllerV2::class, 'assignRole']);
    Route::post('users/{id}/restore', [UserControllerV2::class, 'restoreOne']);
    
    Route::apiResource('users', UserControllerV2::class);
});

/* Jokes Routes */
// Public route for guests - no authentication required
Route::get('jokes/random', [JokeControllerV2::class, 'random'])
    ->name('jokes.random');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('jokes/trash', [JokeControllerV2::class, 'trash'])
        ->name('jokes.trash');

    Route::delete('jokes/trash/empty', [JokeControllerV2::class, 'emptyAll'])
        ->name('jokes.trash.empty.all');

    Route::post('jokes/trash/recover', [JokeControllerV2::class, 'recoverAll'])
        ->name('jokes.trash.recover.all');

    Route::delete('jokes/trash/{id}/remove', [JokeControllerV2::class, 'emptyOne'])
        ->name('jokes.trash.remove.one');

    Route::post('jokes/trash/{id}/recover', [JokeControllerV2::class, 'recoverOne'])
        ->name('jokes.trash.recover.one');

    /** Stop people trying to "GET" jokes/trash/1234/delete or similar */
    Route::get('jokes/trash/{id}/{method}', [JokeControllerV2::class, 'trash']);

    Route::get('jokes/search/{keyword}', [JokeControllerV2::class, 'search'])
        ->name('jokes.search');

    Route::resource("jokes", JokeControllerV2::class);
});

/* Votes Routes */
Route::middleware('auth:sanctum')->group(function () {
    // Vote on a joke (add, change, or remove vote)
    Route::post('jokes/{joke_id}/vote', [VoteControllerV2::class, 'vote'])
        ->name('votes.vote');

    // Admin: Clear all votes by a specific user (Staff/Client only)
    Route::delete('votes/user/{user_id}', [VoteControllerV2::class, 'clearUserVotes'])
        ->name('votes.clear.user');

    // Superuser: Reset all votes in the system
    Route::delete('votes/reset', [VoteControllerV2::class, 'resetAllVotes'])
        ->name('votes.reset.all');
});
