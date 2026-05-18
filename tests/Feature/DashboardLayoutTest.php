<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_unauthenticated_user_cannot_access_layout(): void
    {
        $this->getJson('/api/v1/user/dashboard-layout')
            ->assertUnauthorized();
    }

    public function test_returns_null_layout_when_none_saved(): void
    {
        Sanctum::actingAs($this->verifiedUser());

        $this->getJson('/api/v1/user/dashboard-layout')
            ->assertOk()
            ->assertJson(['layout' => null]);
    }

    public function test_user_can_save_and_retrieve_layout(): void
    {
        Sanctum::actingAs($this->verifiedUser());

        $layout = [
            ['i' => 'w-1', 'x' => 0, 'y' => 0, 'w' => 8, 'h' => 5, 'type' => 'RevenueChartWidget'],
            ['i' => 'w-2', 'x' => 8, 'y' => 0, 'w' => 4, 'h' => 5, 'type' => 'RecentContactsWidget'],
        ];

        $this->putJson('/api/v1/user/dashboard-layout', ['layout' => $layout])
            ->assertOk()
            ->assertJsonCount(2, 'layout');

        $this->getJson('/api/v1/user/dashboard-layout')
            ->assertOk()
            ->assertJsonPath('layout.0.i', 'w-1')
            ->assertJsonPath('layout.1.type', 'RecentContactsWidget');
    }

    public function test_layout_is_scoped_per_user(): void
    {
        $userA = $this->verifiedUser();
        $userB = $this->verifiedUser();

        $layout = [['i' => 'w-1', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4, 'type' => 'KpiStatsWidget']];

        Sanctum::actingAs($userA);
        $this->putJson('/api/v1/user/dashboard-layout', ['layout' => $layout])->assertOk();

        Sanctum::actingAs($userB);
        $this->getJson('/api/v1/user/dashboard-layout')
            ->assertOk()
            ->assertJson(['layout' => null]);
    }

    public function test_validation_rejects_missing_layout_key(): void
    {
        Sanctum::actingAs($this->verifiedUser());

        $this->putJson('/api/v1/user/dashboard-layout', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['layout']);
    }

    public function test_validation_rejects_non_array_layout(): void
    {
        Sanctum::actingAs($this->verifiedUser());

        $this->putJson('/api/v1/user/dashboard-layout', ['layout' => 'not-an-array'])
            ->assertUnprocessable();
    }

    public function test_validation_rejects_item_with_missing_fields(): void
    {
        Sanctum::actingAs($this->verifiedUser());

        $this->putJson('/api/v1/user/dashboard-layout', [
            'layout' => [['i' => 'w-1']],  // missing x, y, w, h, type
        ])->assertUnprocessable();
    }

    public function test_validation_rejects_out_of_range_column(): void
    {
        Sanctum::actingAs($this->verifiedUser());

        $this->putJson('/api/v1/user/dashboard-layout', [
            'layout' => [['i' => 'w-1', 'x' => 20, 'y' => 0, 'w' => 6, 'h' => 4, 'type' => 'KpiStatsWidget']],
        ])->assertUnprocessable();
    }

    public function test_user_can_overwrite_existing_layout(): void
    {
        $user = $this->verifiedUser();
        Sanctum::actingAs($user);

        $first  = [['i' => 'w-1', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4, 'type' => 'KpiStatsWidget']];
        $second = [['i' => 'w-2', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 5, 'type' => 'RevenueChartWidget']];

        $this->putJson('/api/v1/user/dashboard-layout', ['layout' => $first])->assertOk();
        $this->putJson('/api/v1/user/dashboard-layout', ['layout' => $second])->assertOk();

        $this->getJson('/api/v1/user/dashboard-layout')
            ->assertJsonPath('layout.0.i', 'w-2')
            ->assertJsonCount(1, 'layout');
    }
}
