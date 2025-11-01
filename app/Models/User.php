<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'city',
        'state',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the jokes created by this user
     */
    public function jokes()
    {
        return $this->hasMany(Joke::class);
    }

    /**
     * Get the votes made by this user
     */
    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * Boot method to handle cascade deletes
     * 
     * source: https://laracasts.com/discuss/channels/laravel/laravel-soft-delete-cascade
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function($user) {
            // When user is deleted (soft or force):
            // - Soft delete all jokes by this user
            // - Hard delete all votes by this user
            $user->jokes()->delete();
            $user->votes()->delete();
        });
    }
}
