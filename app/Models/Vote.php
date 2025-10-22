<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'joke_id',
        'user_id',
        'rating',
    ];

    /**
     * Get the user that owns the vote
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the joke that was voted on
     */
    public function joke()
    {
        return $this->belongsTo(Joke::class);
    }
}
