<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;
use App\Policies\RolePolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     * 
     * Source: https://laravel.com/docs/11.x/authorization#registering-policies
     */
    public function boot(): void
    {
        // Register RolePolicy for Spatie's Role model
        // Spatie Role model lives in vendor, so it won't be auto-discovered
        // Coudn't test Roles without adding this to boot
        // Source: https://spatie.be/docs/laravel-permission/v6/basic-usage/role-permissions
        // Source: https://laravel.com/docs/12.x/authorization
        Gate::policy(Role::class, RolePolicy::class);
    }
}
