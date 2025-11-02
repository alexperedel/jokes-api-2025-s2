<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

const API_VER = 'v2';

uses(RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('staff');
    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');
    $this->client = User::factory()->create(['email_verified_at' => now()]);
    $this->client->assignRole('client');
});

// BROWSE Tests
test('staff can browse all users', function () {
    // Arrange
    User::factory(3)->create(['email_verified_at' => now()]);

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/users');

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Users retrieved']);
});

test('client cannot browse users', function () {
    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->getJson('/api/' . API_VER . '/users');
    
    // Assert
    $response->assertStatus(403); // Forbidden
});

test('unauthenticated user cannot browse users', function () {
    // Act
    $response = $this->getJson('/api/' . API_VER . '/users');
    
    // Assert
    $response->assertStatus(401); // Unauthorized
});

// READ Tests
test('staff can view single user', function () {
    // Arrange
    $user = User::factory()->create(['email_verified_at' => now()]);

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/users/' . $user->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'User retrieved']);
});

test('returns 404 when user not found', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/users/9999');

    // Assert
    $response->assertStatus(404)
        ->assertJson(['success' => false, 'message' => 'User not found']);
});

// CREATE Tests
test('staff can create client user', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/' . API_VER . '/users', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password123',
            'role' => 'client',
        ]);

    // Assert
    $response->assertStatus(201)
        ->assertJson(['success' => true]);
});

test('staff cannot create staff user', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/' . API_VER . '/users', [
            'name' => 'Test Staff',
            'email' => 'teststaff@example.com',
            'password' => 'password123',
            'role' => 'staff',
        ]);

    // Assert
    $response->assertStatus(403); // Forbidden
});

test('admin can create staff user', function () {
    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/' . API_VER . '/users', [
            'name' => 'Test Staff',
            'email' => 'teststaff@example.com',
            'password' => 'password123',
            'role' => 'staff',
        ]);

    // Assert
    $response->assertStatus(201)
        ->assertJson(['success' => true]);
});

test('validation fails for invalid user data', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/' . API_VER . '/users', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123', // Too short
            'role' => 'client',
        ]);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

// UPDATE Tests
test('staff can update client user', function () {
    // Arrange
    $client = User::factory()->create(['email_verified_at' => now()]);
    $client->assignRole('client');

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->putJson('/api/' . API_VER . '/users/' . $client->id, [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'User updated successfully']);
});

test('staff cannot update admin user', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->putJson('/api/' . API_VER . '/users/' . $this->admin->id, [
            'name' => 'Trying to Update Admin',
        ]);

    // Assert
    $response->assertStatus(403); // Forbidden
});

test('admin can update staff user', function () {
    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson('/api/' . API_VER . '/users/' . $this->staff->id, [
            'name' => 'Admin Updated Staff',
        ]);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'User updated successfully']);
});

// DELETE Tests
test('staff can delete client user', function () {
    // Arrange
    $client = User::factory()->create(['email_verified_at' => now()]);
    $client->assignRole('client');

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/users/' . $client->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'User deleted successfully']);
    
    $this->assertSoftDeleted('users', ['id' => $client->id]);
});

test('staff cannot delete admin user', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/users/' . $this->admin->id);

    // Assert
    $response->assertStatus(403); // Forbidden
});

// SEARCH Tests
test('staff can search users', function () {
    // Arrange
    User::factory()->create(['name' => 'John Doe', 'email_verified_at' => now()]);
    User::factory()->create(['name' => 'Jane Smith', 'email_verified_at' => now()]);

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/users/search/John');

    // Assert
    $response->assertStatus(200)
        ->assertJsonFragment(['name' => 'John Doe']);
});

test('search returns 404 when no users found', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/users/search/NonExistentUser');

    // Assert
    $response->assertStatus(404)
        ->assertJson(['success' => false, 'message' => 'No users found']);
});

// ROLE ASSIGNMENT Tests
test('admin can assign role to user', function () {
    // Arrange
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('client');

    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/' . API_VER . '/users/' . $user->id . '/assign-role', [
            'role' => 'staff',
        ]);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Role assigned successfully']);
    
    expect($user->fresh()->hasRole('staff'))->toBeTrue();
});

test('staff cannot assign staff role', function () {
    // Arrange
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('client');

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/' . API_VER . '/users/' . $user->id . '/assign-role', [
            'role' => 'staff',
        ]);

    // Assert
    $response->assertStatus(403); // Forbidden
});

// TRASH Tests
test('admin can view trash', function () {
    // Arrange
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('client');
    $user->delete();

    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/' . API_VER . '/users/trash/list');

    // Assert
    $response->assertStatus(200)
        ->assertJsonFragment(['email' => $user->email]);
});

test('admin can restore deleted user', function () {
    // Arrange
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('client');
    $user->delete();

    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/' . API_VER . '/users/trash/' . $user->id . '/restore');

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'User restored successfully']);
    
    $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
});

test('superuser can permanently delete user from trash', function () {
    // Arrange
    $superuser = User::factory()->create(['email_verified_at' => now()]);
    $superuser->assignRole('superuser');
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('client');
    $user->delete();

    // Act
    $response = $this->actingAs($superuser, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/users/trash/' . $user->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'User permanently deleted']);
    
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});
