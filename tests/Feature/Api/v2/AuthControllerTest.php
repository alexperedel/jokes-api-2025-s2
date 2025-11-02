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
    
    // Create test users with different roles
    $this->testPassword = 'password123';
    
    $this->existingUser = User::factory()->create([
        'email' => 'existing@example.com',
        'password' => Hash::make($this->testPassword),
        'email_verified_at' => now(),
    ]);
    $this->existingUser->assignRole('client');
});

// ============================================================================
// REGISTER TESTS
// ============================================================================

test('user can register with valid data', function () {
    // Arrange
    $userData = [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    // Act
    // postJson() sends JSON POST request without authentication
    // Source: https://laravel.com/docs/11.x/http-tests#making-requests
    $response = $this->postJson('/api/' . API_VER . '/auth/register', $userData);

    // Assert
    // assertJsonStructure() validates response JSON structure without exact values
    // Source: https://laravel.com/docs/11.x/http-tests#assert-json-structure
    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'User successfully created',
        ])
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email'],
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'newuser@example.com',
        'name' => 'New User',
    ]);
});

test('registration fails with duplicate email', function () {
    // Arrange
    $userData = [
        'name' => 'Duplicate User',
        'email' => $this->existingUser->email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/register', $userData);

    // Assert
    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Registration details error',
        ]);
});

test('registration fails with short password', function () {
    // Arrange
    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => '12345',
        'password_confirmation' => '12345',
    ];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/register', $userData);

    // Assert
    $response->assertStatus(401)
        ->assertJsonPath('data.error.password.0', 'The password field must be at least 6 characters.');
});

test('registration fails when password confirmation does not match', function () {
    // Arrange
    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'differentpassword',
    ];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/register', $userData);

    // Assert
    $response->assertStatus(401)
        ->assertJsonPath('data.error.password.0', 'The password field confirmation does not match.');
});

test('registration fails with missing required fields', function () {
    // Arrange
    $userData = [
        'email' => 'test@example.com',
    ];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/register', $userData);

    // Assert
    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Registration details error',
        ]);
});

// ============================================================================
// LOGIN TESTS
// ============================================================================

test('user can login with valid credentials', function () {
    // Arrange
    $credentials = [
        'email' => $this->existingUser->email,
        'password' => $this->testPassword,
    ];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/login', $credentials);

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Login successful',
        ])
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email'],
            ],
        ]);
});

test('login fails with invalid password', function () {
    // Arrange
    $credentials = [
        'email' => $this->existingUser->email,
        'password' => 'wrongpassword',
    ];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/login', $credentials);

    // Assert
    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid credentials',
        ]);
});

test('login fails with non-existent email', function () {
    // Arrange
    $credentials = [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/login', $credentials);

    // Assert
    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid credentials',
        ]);
});

test('login fails with invalid email format', function () {
    // Arrange
    $credentials = [
        'email' => 'not-an-email',
        'password' => 'password123',
    ];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/login', $credentials);

    // Assert
    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid credentials',
        ]);
});

test('login fails with missing credentials', function () {
    // Arrange
    $credentials = [];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/login', $credentials);

    // Assert
    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid credentials',
        ]);
});

// ============================================================================
// PROFILE TESTS
// ============================================================================

test('authenticated user can view profile', function () {
    // Arrange
    // (user created in beforeEach)

    // Act
    $response = $this->actingAs($this->existingUser, 'sanctum')
        ->getJson('/api/' . API_VER . '/auth/profile');

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'User profile request successful',
        ])
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
            ],
        ])
        ->assertJsonPath('data.user.email', $this->existingUser->email);
});

test('unauthenticated user cannot view profile', function () {
    // Arrange
    // (no authentication)

    // Act
    $response = $this->getJson('/api/' . API_VER . '/auth/profile');

    // Assert
    $response->assertStatus(401);
});

// ============================================================================
// LOGOUT TESTS
// ============================================================================

test('authenticated user can logout', function () {
    // Arrange
    $token = $this->existingUser->createToken('TestToken')->plainTextToken;
    
    // Act
    $response = $this->actingAs($this->existingUser, 'sanctum')
        ->postJson('/api/' . API_VER . '/auth/logout');

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Logout successful',
        ]);

    // Verify tokens are deleted
    expect($this->existingUser->tokens()->count())->toBe(0);
});

test('logout deletes all user tokens', function () {
    // Arrange
    $this->existingUser->createToken('Token1');
    $this->existingUser->createToken('Token2');
    $this->existingUser->createToken('Token3');
    
    expect($this->existingUser->tokens()->count())->toBe(3);

    // Act
    $response = $this->actingAs($this->existingUser, 'sanctum')
        ->postJson('/api/' . API_VER . '/auth/logout');

    // Assert
    $response->assertStatus(200);
    expect($this->existingUser->tokens()->count())->toBe(0);
});

test('unauthenticated user cannot logout', function () {
    // Arrange
    // (no authentication)

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/logout');

    // Assert
    $response->assertStatus(401);
});

// ============================================================================
// FORGOT PASSWORD TESTS
// ============================================================================

test('forgot password returns success for existing email', function () {
    // Arrange
    $data = [
        'email' => $this->existingUser->email,
    ];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/password/forgot', $data);

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'If that email exists, a password reset link has been sent',
        ]);
});

test('forgot password returns success for non-existent email', function () {
    // Arrange
    $data = [
        'email' => 'nonexistent@example.com',
    ];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/password/forgot', $data);

    // Assert
    // Should return success to prevent email enumeration
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'If that email exists, a password reset link has been sent',
        ]);
});

test('forgot password fails with invalid email format', function () {
    // Arrange
    $data = [
        'email' => 'not-an-email',
    ];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/password/forgot', $data);

    // Assert
    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Validation error',
        ]);
});

test('forgot password fails with missing email', function () {
    // Arrange
    $data = [];

    // Act
    $response = $this->postJson('/api/' . API_VER . '/auth/password/forgot', $data);

    // Assert
    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Validation error',
        ]);
});

// ============================================================================
// RESET PASSWORD TESTS (V2 specific)
// ============================================================================

test('authenticated user can reset their own password', function () {
    // Arrange
    $data = [
        'current_password' => $this->testPassword,
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'newpassword123',
    ];

    // Act
    $response = $this->actingAs($this->existingUser, 'sanctum')
        ->putJson('/api/' . API_VER . '/auth/password/reset', $data);

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Password reset successful. Please login again.',
        ]);

    // Verify all tokens are deleted after password reset
    expect($this->existingUser->tokens()->count())->toBe(0);

    // Verify new password works
    $this->assertTrue(Hash::check('newpassword123', $this->existingUser->fresh()->password));
});

test('password reset fails with incorrect current password', function () {
    // Arrange
    $data = [
        'current_password' => 'wrongpassword',
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'newpassword123',
    ];

    // Act
    $response = $this->actingAs($this->existingUser, 'sanctum')
        ->putJson('/api/' . API_VER . '/auth/password/reset', $data);

    // Assert
    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Current password is incorrect',
        ]);
});

test('password reset fails when new password same as current', function () {
    // Arrange
    $data = [
        'current_password' => $this->testPassword,
        'new_password' => $this->testPassword,
        'new_password_confirmation' => $this->testPassword,
    ];

    // Act
    $response = $this->actingAs($this->existingUser, 'sanctum')
        ->putJson('/api/' . API_VER . '/auth/password/reset', $data);

    // Assert
    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'New password must be different from current password',
        ]);
});

test('password reset fails when new password confirmation does not match', function () {
    // Arrange
    $data = [
        'current_password' => $this->testPassword,
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'differentpassword',
    ];

    // Act
    $response = $this->actingAs($this->existingUser, 'sanctum')
        ->putJson('/api/' . API_VER . '/auth/password/reset', $data);

    // Assert
    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Validation error',
        ]);
});

test('password reset fails when password too short', function () {
    // Arrange
    $data = [
        'current_password' => $this->testPassword,
        'new_password' => '12345',
        'new_password_confirmation' => '12345',
    ];

    // Act
    $response = $this->actingAs($this->existingUser, 'sanctum')
        ->putJson('/api/' . API_VER . '/auth/password/reset', $data);

    // Assert
    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Validation error',
        ]);
});

test('unauthenticated user cannot reset password', function () {
    // Arrange
    $data = [
        'current_password' => 'password123',
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'newpassword123',
    ];

    // Act
    $response = $this->putJson('/api/' . API_VER . '/auth/password/reset', $data);

    // Assert
    $response->assertStatus(401);
});

// ============================================================================
// RESET PASSWORD FOR USER TESTS (V2 specific - admin/staff can reset others)
// ============================================================================

test('admin can send password reset link to staff user', function () {
    // Arrange
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    
    $staffUser = User::factory()->create(['email_verified_at' => now()]);
    $staffUser->assignRole('staff');

    // Act
    $response = $this->actingAs($admin, 'sanctum')
        ->putJson('/api/' . API_VER . '/auth/password/reset/' . $staffUser->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);
});

// ============================================================================
// FORCE LOGOUT USER TESTS (V2 specific - admin/staff can logout others)
// ============================================================================

test('admin can force logout a staff user', function () {
    // Arrange
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    
    $staffUser = User::factory()->create(['email_verified_at' => now()]);
    $staffUser->assignRole('staff');
    $staffUser->createToken('TestToken');
    
    expect($staffUser->tokens()->count())->toBe(1);

    // Act
    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/auth/logout/user/' . $staffUser->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'User has been logged out',
        ]);
    
    expect($staffUser->tokens()->count())->toBe(0);
});

// ============================================================================
// FORCE LOGOUT ROLE TESTS (V2 specific - admin can logout all users of a role)
// ============================================================================

test('admin can force logout all client users', function () {
    // Arrange
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    
    $client1 = User::factory()->create(['email_verified_at' => now()]);
    $client1->assignRole('client');
    $client1->createToken('Token1');
    
    $client2 = User::factory()->create(['email_verified_at' => now()]);
    $client2->assignRole('client');
    $client2->createToken('Token2');
    
    expect($client1->tokens()->count())->toBe(1);
    expect($client2->tokens()->count())->toBe(1);

    // Act
    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/auth/logout/role/client');

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'All client users have been logged out',
        ]);
    
    expect($client1->tokens()->count())->toBe(0);
    expect($client2->tokens()->count())->toBe(0);
});
