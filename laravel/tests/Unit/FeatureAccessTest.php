<?php

namespace Tests\Unit;

use App\Models\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_and_assigned_feature_is_accessible(): void
    {
        $feature = Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => true]);
        $user = User::factory()->create();
        $feature->users()->attach($user->id);

        $this->assertTrue($feature->isAccessibleBy($user));
    }

    public function test_enabled_but_unassigned_feature_is_not_accessible(): void
    {
        $feature = Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => true]);
        $user = User::factory()->create();

        $this->assertFalse($feature->isAccessibleBy($user));
    }

    public function test_disabled_but_assigned_feature_is_not_accessible(): void
    {
        $feature = Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => false]);
        $user = User::factory()->create();
        $feature->users()->attach($user->id);

        $this->assertFalse($feature->isAccessibleBy($user));
    }

    public function test_disabled_and_unassigned_feature_is_not_accessible(): void
    {
        $feature = Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => false]);
        $user = User::factory()->create();

        $this->assertFalse($feature->isAccessibleBy($user));
    }

    public function test_user_has_feature_delegates_to_feature_access(): void
    {
        $enabled = Feature::create(['name' => 'Beta', 'key' => 'beta', 'enabled' => true]);
        $disabled = Feature::create(['name' => 'Dark Mode', 'key' => 'dark-mode', 'enabled' => false]);
        $user = User::factory()->create();
        $enabled->users()->attach($user->id);
        $disabled->users()->attach($user->id);

        $this->assertTrue($user->hasFeature('beta'));
        $this->assertFalse($user->hasFeature('dark-mode'));
        $this->assertFalse($user->hasFeature('does-not-exist'));
    }
}
