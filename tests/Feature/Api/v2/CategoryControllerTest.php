<?php

use App\Models\Category;
use App\Models\Joke;
use App\Models\User;
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
    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('staff');
    $this->client = User::factory()->create(['email_verified_at' => now()]);
    $this->client->assignRole('client');
});

// BROWSE Tests
test('staff can browse all categories', function () {
    // Arrange
    Category::factory(3)->create();

    // Act
    // actingAs() authenticates user with Sanctum for API testing
    // Source: https://laravel.com/docs/11.x/sanctum#testing
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/categories');

    // Assert
    $response->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJson(['success' => true, 'message' => 'Categories retrieved']);
});

test('unauthenticated user cannot browse categories', function () {
    // Act
    $response = $this->getJson('/api/' . API_VER . '/categories');
    
    // Assert
    $response->assertStatus(401);
});

// READ Tests
test('staff can view single category', function () {
    // Arrange
    $category = Category::factory()->create();

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/categories/' . $category->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Category retrieved'])
        ->assertJsonStructure(['data' => ['category', 'jokes']]);
});

test('returns 404 when category not found', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/categories/9999');

    // Assert
    $response->assertStatus(404)
        ->assertJson(['success' => false, 'message' => 'Category not found']);
});

// CREATE Tests
test('staff can create category', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/' . API_VER . '/categories', [
            'title' => 'Test Category',
            'description' => 'Test Description',
        ]);

    // Assert
    $response->assertStatus(201)
        ->assertJson(['success' => true, 'message' => 'Category created']);
});

test('client cannot create category', function () {
    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->postJson('/api/' . API_VER . '/categories', [
            'title' => 'Test Category',
            'description' => 'Test Description',
        ]);

    // Assert
    $response->assertStatus(403);
});

test('validation fails for invalid category data', function () {
    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/' . API_VER . '/categories', [
            'title' => 'ab', // Too short
            'description' => 'short', // Too short
        ]);

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'description']);
});

// UPDATE Tests
test('staff can update category', function () {
    // Arrange
    $category = Category::factory()->create();

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->putJson('/api/' . API_VER . '/categories/' . $category->id, [
            'title' => 'Updated Title',
            'description' => 'Updated Description',
        ]);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Category updated']);
});

test('client cannot update category', function () {
    // Arrange
    $category = Category::factory()->create();

    // Act
    $response = $this->actingAs($this->client, 'sanctum')
        ->putJson('/api/' . API_VER . '/categories/' . $category->id, [
            'title' => 'Updated Title',
        ]);

    // Assert
    $response->assertStatus(403);
});

// DELETE Tests
test('staff can delete category', function () {
    // Arrange
    $category = Category::factory()->create();

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->deleteJson('/api/' . API_VER . '/categories/' . $category->id);

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Category deleted']);
    
    $this->assertSoftDeleted('categories', ['id' => $category->id]);
});

// SEARCH Tests
test('staff can search categories', function () {
    // Arrange
    Category::factory()->create(['title' => 'Programming Jokes']);
    Category::factory()->create(['title' => 'Dad Jokes']);

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/categories/search/Programming');

    // Assert
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

// TRASH Tests
test('staff can view trash', function () {
    // Arrange
    $category = Category::factory()->create();
    $category->delete();

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/' . API_VER . '/categories/trash');

    // Assert
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('staff can recover deleted category', function () {
    // Arrange
    $category = Category::factory()->create();
    $category->delete();

    // Act
    $response = $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/' . API_VER . '/categories/trash/' . $category->id . '/recover');

    // Assert
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Category recovered']);
});
