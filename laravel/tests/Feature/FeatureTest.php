<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_features_list_is_returned(): void
    {
        Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => true]);
        Feature::create(['name' => 'Dark Mode', 'key' => 'dark-mode', 'enabled' => false]);

        $response = $this->getJson('/api/features');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonStructure([
            '*' => ['id', 'name', 'key', 'enabled'],
        ]);
    }

    public function test_feature_can_be_assigned_to_user(): void
    {
        $feature = Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => true]);
        $user = User::factory()->create();

        $response = $this->postJson("/api/features/{$feature->id}/assign/{$user->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('feature_user', [
            'feature_id' => $feature->id,
            'user_id'    => $user->id,
        ]);
    }

    public function test_beta_route_is_accessible_when_user_has_feature(): void
    {
        $feature = Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => true]);
        $user = User::factory()->create();
        $feature->users()->attach($user->id);

        $response = $this->getJson("/api/beta?user_id={$user->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Welcome to the beta!']);
    }

    public function test_beta_route_is_blocked_when_user_lacks_feature(): void
    {
        Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => true]);
        $user = User::factory()->create();

        $response = $this->getJson("/api/beta?user_id={$user->id}");

        $response->assertStatus(403);
    }

    public function test_beta_route_is_blocked_with_no_user(): void
    {
        Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => true]);

        $response = $this->getJson('/api/beta');

        $response->assertStatus(403);
    }

    public function test_beta_route_is_blocked_when_feature_globally_disabled_even_if_assigned(): void
    {
        $feature = Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => false]);
        $user = User::factory()->create();
        $feature->users()->attach($user->id);

        $response = $this->getJson("/api/beta?user_id={$user->id}");

        $response->assertStatus(403);
    }

    public function test_beta_route_returns_404_when_feature_does_not_exist(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/beta?user_id={$user->id}");

        $response->assertStatus(404);
    }

    public function test_beta_route_is_blocked_for_unknown_user(): void
    {
        Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => true]);

        $response = $this->getJson('/api/beta?user_id=999999');

        $response->assertStatus(403);
    }

    public function test_assign_returns_404_for_missing_feature(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson("/api/features/999999/assign/{$user->id}");

        $response->assertStatus(404);
    }

    public function test_assign_returns_404_for_missing_user(): void
    {
        $feature = Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => true]);

        $response = $this->postJson("/api/features/{$feature->id}/assign/999999");

        $response->assertStatus(404);
    }

    public function test_assigning_same_user_twice_is_idempotent(): void
    {
        $feature = Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => true]);
        $user = User::factory()->create();

        $this->postJson("/api/features/{$feature->id}/assign/{$user->id}")->assertStatus(200);
        $this->postJson("/api/features/{$feature->id}/assign/{$user->id}")->assertStatus(200);

        $this->assertDatabaseCount('feature_user', 1);
    }

    public function test_feature_can_be_assigned_while_globally_disabled(): void
    {
        // Allows pre-assigning limited testers before general release.
        $feature = Feature::create(['name' => 'Dark Mode', 'key' => 'dark-mode', 'enabled' => false]);
        $user = User::factory()->create();

        $response = $this->postJson("/api/features/{$feature->id}/assign/{$user->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('feature_user', [
            'feature_id' => $feature->id,
            'user_id'    => $user->id,
        ]);
    }
}
