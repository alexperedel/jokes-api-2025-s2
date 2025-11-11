<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

const API_VER = 'v2';

// RefreshDatabase resets database after each test to ensure test isolation
// Source: https://laravel.com/docs/11.x/database-testing#resetting-the-database-after-each-test
uses(RefreshDatabase::class);

// Pest beforeEach hook runs before each test case for setup
// Used to seed roles and create authenticated test users
// Source: https://pestphp.com/docs/hooks#beforeeach
beforeEach(function () {
    (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
    
    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');
    
    $this->superuser = User::factory()->create(['email_verified_at' => now()]);
    $this->superuser->assignRole('superuser');
    
    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('staff');
});

// BROWSE Tests
test('admin can browse all roles', function () {
    // Act
    // actingAs() authenticates user with Sanctum for API testing
    // Source: https://laravel.com/docs/11.x/sanctum#testing
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/' . API_VER . '/roles');

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Roles retrieved'])
        ->assertJsonStructure(['data' => [['id', 'name', 'guard_name', 'permissions']]]);
});

test('staff cannot browse roles', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/roles');

    // Assert
    $response->assertStatus(403);
});

// READ Tests
test('admin can view single role', function () {
    // Arrange
    $role = Role::where('name', 'staff')->first();

    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/' . API_VER . '/roles/' . $role->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Role retrieved'])
        ->assertJsonStructure(['data' => ['id', 'name', 'guard_name', 'permissions']]);
});

// CREATE Tests
test('admin can create role with permissions', function () {
    // Arrange
    $permissions = ['joke.browse', 'joke.show.any'];

    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/' . API_VER . '/roles', [
            'name' => 'reviewer',
            'permissions' => $permissions,
        ]);

    // Assert
    $response->assertStatus(201);
    
    $role = Role::where('name', 'reviewer')->first();
    expect($role->permissions->pluck('name')->toArray())->toEqual($permissions);
});

test('staff cannot create role', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/' . API_VER . '/roles', [
            'name' => 'newrole',
        ]);

    // Assert
    $response->assertStatus(403);
});

test('validation fails for duplicate role name', function () {
    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/' . API_VER . '/roles', [
            'name' => 'admin', // Already exists
        ]);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

// UPDATE Tests
test('admin can update role', function () {
    // Arrange
    $role = Role::create(['name' => 'tester', 'guard_name' => 'web']);

    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson('/api/' . API_VER . '/roles/' . $role->id, [
            'name' => 'quality_assurance',
        ]);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Role updated']);
    
    expect(Role::where('name', 'quality_assurance')->exists())->toBeTrue();
});

test('cannot update superuser role', function () {
    // Arrange
    $role = Role::where('name', 'superuser')->first();

    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->putJson('/api/' . API_VER . '/roles/' . $role->id, [
            'name' => 'hacked',
        ]);

    // Assert
    $response->assertStatus(403)
        ->assertJson(['success' => false, 'message' => 'Cannot edit superuser role']);
});

// DELETE Tests
test('superuser can delete role', function () {
    // Arrange
    $role = Role::create(['name' => 'temp_delete', 'guard_name' => 'web']);

    // Act
    $response = $this->actingAs($this->superuser, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/roles/' . $role->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Role deleted']);
    
    expect(Role::where('name', 'temp_delete')->exists())->toBeFalse();
});

test('admin cannot delete role', function () {
    // Arrange
    $role = Role::create(['name' => 'protected', 'guard_name' => 'web']);

    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/roles/' . $role->id);

    // Assert
    $response->assertStatus(403);
});

test('cannot delete superuser role', function () {
    // Arrange
    $role = Role::where('name', 'superuser')->first();

    // Act
    $response = $this->actingAs($this->superuser, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/roles/' . $role->id);

    // Assert
    $response->assertStatus(403)
        ->assertJson(['success' => false, 'message' => 'Cannot delete superuser role']);
});

test('cannot delete role with assigned users', function () {
    // Arrange
    $role = Role::create(['name' => 'in_use', 'guard_name' => 'web']);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    // Act
    $response = $this->actingAs($this->superuser, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/roles/' . $role->id);

    // Assert
    $response->assertStatus(409)
        ->assertJsonFragment(['message' => 'Cannot delete role with 1 assigned user(s)']);
});

// SEARCH Tests
test('admin can search roles by name', function () {
    // Arrange
    Role::create(['name' => 'content_moderator', 'guard_name' => 'web']);

    // Act
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/' . API_VER . '/roles/search/content');

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonFragment(['name' => 'content_moderator']);
});
