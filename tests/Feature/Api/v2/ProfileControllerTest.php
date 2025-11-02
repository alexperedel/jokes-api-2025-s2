<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

const API_VER = 'v2';

// RefreshDatabase resets database after each test for isolation
// Source: https://laravel.com/docs/11.x/database-testing#resetting-the-database-after-each-test
uses(RefreshDatabase::class);

// Pest beforeEach hook runs before each test case for setup
// Source: https://pestphp.com/docs/hooks#beforeeach
beforeEach(function () {
    // Seed roles and permissions
    (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
    
    // Create test users
    $this->client = User::factory()->create([
        'email' => 'client@example.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
    ]);
    $this->client->assignRole('client');
    
    $this->staff = User::factory()->create([
        'email' => 'staff@example.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
    ]);
    $this->staff->assignRole('staff');
});

// ============================================================================
// PROFILE UPDATE TESTS
// ============================================================================

test('authenticated user can update own profile', function () {
    // Arrange
    $data = [
        'name' => 'Updated Name',
        'email' => 'newemail@example.com',
    ];

    // Act
    // actingAs() authenticates user with Sanctum guard for API testing
    // Source: https://laravel.com/docs/11.x/sanctum#testing
    $response = $this->actingAs($this->client, 'sanctum')
        ->putJson('/api/' . API_VER . '/profile', $data);

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Profile updated successfully',
        ])
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email'],
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->client->id,
        'name' => 'Updated Name',
        'email' => 'newemail@example.com',
    ]);
});

test('profile update fails with duplicate email', function () {
    // Arrange
    $data = [
        'name' => 'Test User',
        'email' => $this->staff->email,
    ];

    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->putJson('/api/' . API_VER . '/profile', $data);

    // Assert
    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Validation error',
        ]);
});

test('unauthenticated user cannot update profile', function () {
    // Arrange
    $data = [
        'name' => 'Updated Name',
        'email' => 'newemail@example.com',
    ];

    // Act
    $response = $this->putJson('/api/' . API_VER . '/profile', $data);

    // Assert
    $response->assertStatus(401);
});

// ============================================================================
// PROFILE DELETE TESTS
// ============================================================================

test('authenticated user can delete own account with correct password', function () {
    // Arrange
    $data = ['password' => 'password123'];

    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/profile', $data);

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);

    // User should be soft deleted
    $this->assertSoftDeleted('users', [
        'id' => $this->client->id,
    ]);
});

test('account deletion fails with incorrect password', function () {
    // Arrange
    $data = ['password' => 'wrong-password'];

    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/profile', $data);

    // Assert
    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Incorrect password',
        ]);

    // User should still exist
    $this->assertDatabaseHas('users', [
        'id' => $this->client->id,
        'deleted_at' => null,
    ]);
});

test('unauthenticated user cannot delete account', function () {
    // Arrange
    $data = ['password' => 'password123'];

    // Act
    $response = $this->deleteJson('/api/' . API_VER . '/profile', $data);

    // Assert
    $response->assertStatus(401);
});
