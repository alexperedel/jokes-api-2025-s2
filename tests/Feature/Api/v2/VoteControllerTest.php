<?php

use App\Models\Joke;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;

const API_VER = 'v2';

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions
    (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
    
    // Create test users
    $this->client = User::factory()->create(['email_verified_at' => now()]);
    $this->client->assignRole('client');
    
    $this->anotherClient = User::factory()->create(['email_verified_at' => now()]);
    $this->anotherClient->assignRole('client');
    
    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');
    
    $this->superuser = User::factory()->create(['email_verified_at' => now()]);
    $this->superuser->assignRole('superuser');
    
    // Create test joke
    $this->joke = Joke::factory()->create(['user_id' => $this->client->id]);
});

// ============================================================================
// VOTE TESTS - Create/Add Vote
// ============================================================================

test('authenticated user can upvote a joke', function () {
    // Arrange
    $data = ['rating' => 1];

    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->postJson('/api/' . API_VER . '/jokes/' . $this->joke->id . '/vote', $data);

    // Assert
    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Liked',
        ])
        ->assertJsonStructure([
            'data' => ['id', 'user_id', 'joke_id', 'rating'],
        ]);

    $this->assertDatabaseHas('votes', [
        'user_id' => $this->client->id,
        'joke_id' => $this->joke->id,
        'rating' => 1,
    ]);
});

test('vote fails with invalid rating', function () {
    // Arrange
    $data = ['rating' => 5];

    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->postJson('/api/' . API_VER . '/jokes/' . $this->joke->id . '/vote', $data);

    // Assert
    $response->assertStatus(422);
});

test('vote fails for non-existent joke', function () {
    // Arrange
    $data = ['rating' => 1];

    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->postJson('/api/' . API_VER . '/jokes/99999/vote', $data);

    // Assert
    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Joke not found',
        ]);
});

test('unauthenticated user cannot vote', function () {
    // Arrange
    $data = ['rating' => 1];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/jokes/' . $this->joke->id . '/vote', $data);

    // Assert
    $response->assertStatus(401);
});

// ============================================================================
// VOTE TESTS - Update Vote
// ============================================================================

test('user can change their vote', function () {
    // Arrange
    $vote = Vote::create([
        'user_id' => $this->client->id,
        'joke_id' => $this->joke->id,
        'rating' => 1,
    ]);
    $data = ['rating' => -1];

    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->postJson('/api/' . API_VER . '/jokes/' . $this->joke->id . '/vote', $data);

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Rating Updated',
        ]);

    $this->assertDatabaseHas('votes', [
        'id' => $vote->id,
        'rating' => -1,
    ]);
});

// ============================================================================
// VOTE TESTS - Delete Vote (rating = 0)
// ============================================================================

test('user can remove their vote by setting rating to 0', function () {
    // Arrange
    $vote = Vote::create([
        'user_id' => $this->client->id,
        'joke_id' => $this->joke->id,
        'rating' => 1,
    ]);
    $data = ['rating' => 0];

    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->postJson('/api/' . API_VER . '/jokes/' . $this->joke->id . '/vote', $data);

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Vote removed',
        ]);

    $this->assertDatabaseMissing('votes', [
        'id' => $vote->id,
    ]);
});

// ============================================================================
// CLEAR USER VOTES TESTS
// ============================================================================

test('admin can clear all votes for a client user', function () {
    // Arrange
    $joke1 = Joke::factory()->create(['user_id' => $this->client->id]);
    $joke2 = Joke::factory()->create(['user_id' => $this->client->id]);
    
    Vote::create(['user_id' => $this->anotherClient->id, 'joke_id' => $joke1->id, 'rating' => 1]);
    Vote::create(['user_id' => $this->anotherClient->id, 'joke_id' => $joke2->id, 'rating' => -1]);
    
    expect(Vote::where('user_id', $this->anotherClient->id)->count())->toBe(2);

    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/votes/user/' . $this->anotherClient->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Votes Deleted',
        ]);
    
    expect(Vote::where('user_id', $this->anotherClient->id)->count())->toBe(0);
});

// ============================================================================
// RESET ALL VOTES TESTS
// ============================================================================

test('superuser can reset all votes in system', function () {
    // Arrange
    $joke1 = Joke::factory()->create(['user_id' => $this->client->id]);
    $joke2 = Joke::factory()->create(['user_id' => $this->client->id]);
    
    Vote::create(['user_id' => $this->client->id, 'joke_id' => $joke1->id, 'rating' => 1]);
    Vote::create(['user_id' => $this->anotherClient->id, 'joke_id' => $joke2->id, 'rating' => -1]);
    Vote::create(['user_id' => $this->admin->id, 'joke_id' => $joke1->id, 'rating' => 1]);
    
    expect(Vote::count())->toBe(3);

    // Act
    $response = $this->actingAs($this->superuser, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/votes/reset');

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Votes reset',
        ]);
    
    expect(Vote::count())->toBe(0);
});
