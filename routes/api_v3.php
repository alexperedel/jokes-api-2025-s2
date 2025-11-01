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
 * API Version 3
 * 
 * All routes follow patterns:
 * - GET    /{resource}                  - List all
 * - POST   /{resource}                  - Create new
 * - GET    /{resource}/{id}             - Show one
 * - PUT    /{resource}/{id}             - Update
 * - DELETE /{resource}/{id}             - Delete
 * - GET    /{resource}/search/{keyword} - Search
 * - GET    /{resource}/trash            - View deleted
 * - POST   /{resource}/trash/restore    - Restore all
 * - DELETE /{resource}/trash/empty      - Delete all permanently
 * - POST   /{resource}/trash/{id}       - Restore one
 * - DELETE /{resource}/trash/{id}       - Delete one permanently
 */

/**
 * Authentication Routes
 */
Route::prefix('auth')->group(function () {
    // Public routes
    Route::post('register', [AuthControllerV1::class, 'register']);
    Route::post('login', [AuthControllerV1::class, 'login']);
    Route::post('password/forgot', [AuthControllerV2::class, 'forgotPassword']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('profile', [AuthControllerV1::class, 'profile']);
        Route::post('logout', [AuthControllerV1::class, 'logout']);
        Route::put('password/reset', [AuthControllerV2::class, 'resetPassword']);
        Route::put('password/reset/{user_id}', [AuthControllerV2::class, 'resetPasswordForUser']);
        Route::delete('logout/user/{user_id}', [AuthControllerV2::class, 'forceLogoutUser']);
        Route::delete('logout/role/{role}', [AuthControllerV2::class, 'forceLogoutRole']);
    });
});

/**
 * Profile Routes
 */
Route::prefix('profile')->middleware('auth:sanctum')->group(function () {
    Route::put('/', [ProfileControllerV2::class, 'update']);
    Route::delete('/', [ProfileControllerV2::class, 'destroy']);
});

/**
 * User Routes
 */
Route::prefix('users')->middleware('auth:sanctum')->group(function () {
    Route::get('search/{keyword}', [UserControllerV2::class, 'search']);
    Route::get('trash', [UserControllerV2::class, 'trash']);
    Route::post('trash/restore', [UserControllerV2::class, 'restoreAll']);
    Route::delete('trash/empty', [UserControllerV2::class, 'emptyAll']);
    Route::post('trash/{id}', [UserControllerV2::class, 'restoreOne']);
    Route::delete('trash/{id}', [UserControllerV2::class, 'emptyOne']);
    Route::post('{id}/assign-role', [UserControllerV2::class, 'assignRole']);
    Route::apiResource('/', UserControllerV2::class)->parameters(['' => 'user']);
});

/**
 * Category Routes
 */
Route::prefix('categories')->middleware('auth:sanctum')->group(function () {
    Route::get('search/{keyword}', [CategoryControllerV2::class, 'search']);
    Route::get('trash', [CategoryControllerV2::class, 'trash']);
    Route::post('trash/restore', [CategoryControllerV2::class, 'recoverAll']);
    Route::delete('trash/empty', [CategoryControllerV2::class, 'removeAll']);
    Route::post('trash/{id}', [CategoryControllerV2::class, 'recoverOne']);
    Route::delete('trash/{id}', [CategoryControllerV2::class, 'removeOne']);
    Route::apiResource('/', CategoryControllerV2::class)->parameters(['' => 'category']);
});

/**
 * Joke Routes
 */
Route::prefix('jokes')->group(function () {
    // Public route
    Route::get('random', [JokeControllerV2::class, 'random']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('search/{keyword}', [JokeControllerV2::class, 'search']);
        Route::get('trash', [JokeControllerV2::class, 'trash']);
        Route::post('trash/restore', [JokeControllerV2::class, 'recoverAll']);
        Route::delete('trash/empty', [JokeControllerV2::class, 'emptyAll']);
        Route::post('trash/{id}', [JokeControllerV2::class, 'recoverOne']);
        Route::delete('trash/{id}', [JokeControllerV2::class, 'emptyOne']);
        Route::apiResource('/', JokeControllerV2::class)->parameters(['' => 'joke']);
    });
});

/**
 * Vote Routes
 */
Route::prefix('votes')->middleware('auth:sanctum')->group(function () {
    Route::post('joke/{joke_id}', [VoteControllerV2::class, 'vote']);
    Route::delete('user/{user_id}', [VoteControllerV2::class, 'clearUserVotes']);
    Route::delete('reset', [VoteControllerV2::class, 'resetAllVotes']);
});
