<?php

use App\Models\Category;
use App\Models\Joke;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

const API_VER = 'v2';

uses(RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('staff');
    $this->client = User::factory()->create(['email_verified_at' => now()]);
    $this->client->assignRole('client');
    $this->category = Category::factory()->create();
});

// BROWSE Tests
test('staff can browse all jokes', function () {
    // Arrange
    Joke::factory(3)->create(['user_id' => $this->staff->id]);

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/jokes');

    // Assert
    $response->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJson(['success' => true, 'message' => 'Jokes retrieved']);
});

test('unauthenticated user cannot browse jokes', function () {
    // Act
    $response = $this->getJson('/api/' . API_VER . '/jokes');
    
    // Assert
    $response->assertStatus(401); // Unauthorized
});

// READ Tests
test('staff can view single joke', function () {
    // Arrange
    $joke = Joke::factory()->create(['user_id' => $this->staff->id]);

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/jokes/' . $joke->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Joke retrieved']);
});

test('returns 404 when joke not found', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/jokes/9999');

    // Assert
    $response->assertStatus(404)
        ->assertJson(['success' => false, 'message' => 'Joke not found']);
});

// CREATE Tests
test('client can create joke', function () {
    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->postJson('/api/' . API_VER . '/jokes', [
            'title' => 'Test Joke',
            'content' => 'This is a test joke content',
            'categories' => [$this->category->id],
        ]);

    // Assert
    $response->assertStatus(201)
        ->assertJson(['success' => true, 'message' => 'Joke created']);
});

test('validation fails for invalid joke data', function () {
    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->postJson('/api/' . API_VER . '/jokes', [
            'title' => 'ab', // Too short
            'content' => 'sh', // Too short
            'categories' => [],
        ]);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'content', 'categories']);
});

// UPDATE Tests
test('user can update own joke', function () {
    // Arrange
    $joke = Joke::factory()->create(['user_id' => $this->client->id]);

    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->putJson('/api/' . API_VER . '/jokes/' . $joke->id, [
            'title' => 'Updated Joke',
            'content' => 'Updated joke content',
            'categories' => [$this->category->id],
        ]);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Joke updated']);
});

test('client cannot update others joke', function () {
    // Arrange
    $joke = Joke::factory()->create(['user_id' => $this->staff->id]);

    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->putJson('/api/' . API_VER . '/jokes/' . $joke->id, [
            'title' => 'Updated Joke',
            'content' => 'Updated content',
            'categories' => [$this->category->id],
        ]);

    // Assert
    $response->assertStatus(403); // Forbidden
});

test('staff can update any joke', function () {
    // Arrange
    $joke = Joke::factory()->create(['user_id' => $this->client->id]);

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->putJson('/api/' . API_VER . '/jokes/' . $joke->id, [
            'title' => 'Staff Updated Joke',
            'content' => 'Staff updated content',
            'categories' => [$this->category->id],
        ]);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Joke updated']);
});

// DELETE Tests
test('user can delete own joke', function () {
    // Arrange
    $joke = Joke::factory()->create(['user_id' => $this->client->id]);

    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/jokes/' . $joke->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Joke deleted']);
    
    $this->assertSoftDeleted('jokes', ['id' => $joke->id]);
});

test('staff can delete any joke', function () {
    // Arrange
    $joke = Joke::factory()->create(['user_id' => $this->client->id]);

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/jokes/' . $joke->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Joke deleted']);
});

// SEARCH Tests
test('staff can search jokes', function () {
    // Arrange
    Joke::factory()->create(['title' => 'Programming Joke', 'user_id' => $this->staff->id]);
    Joke::factory()->create(['title' => 'Dad Joke', 'user_id' => $this->staff->id]);

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/jokes/search/Programming');

    // Assert
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

// RANDOM Tests
test('can get random joke', function () {
    // Arrange
    $category = Category::factory()->create(['title' => 'General']);
    $joke = Joke::factory()->create(['user_id' => $this->staff->id]);
    $joke->categories()->attach($category->id);

    // Act
    $response = $this->getJson('/api/' . API_VER . '/jokes/random');

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Random joke retrieved']);
});

// TRASH Tests (using superuser since staff/admin don't have joke trash permissions)
test('superuser can view trash', function () {
    // Arrange
    $superuser = User::factory()->create(['email_verified_at' => now()]);
    $superuser->assignRole('superuser');
    $joke = Joke::factory()->create(['user_id' => $superuser->id]);
    $joke->delete();

    // Act
    $response = $this->actingAs($superuser, 'sanctum')
        ->getJson('/api/' . API_VER . '/jokes/trash');

    // Assert
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('superuser can recover deleted joke', function () {
    // Arrange
    $superuser = User::factory()->create(['email_verified_at' => now()]);
    $superuser->assignRole('superuser');
    $joke = Joke::factory()->create(['user_id' => $superuser->id]);
    $joke->delete();

    // Act
    $response = $this->actingAs($superuser, 'sanctum')
        ->postJson('/api/' . API_VER . '/jokes/trash/' . $joke->id . '/recover');

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Joke recovered']);
});

test('superuser can permanently delete joke from trash', function () {
    // Arrange
    $superuser = User::factory()->create(['email_verified_at' => now()]);
    $superuser->assignRole('superuser');
    $joke = Joke::factory()->create(['user_id' => $superuser->id]);
    $joke->delete();

    // Act
    $response = $this->actingAs($superuser, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/jokes/trash/' . $joke->id . '/remove');

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Joke permanently removed']);
    
    $this->assertDatabaseMissing('jokes', ['id' => $joke->id]);
});
