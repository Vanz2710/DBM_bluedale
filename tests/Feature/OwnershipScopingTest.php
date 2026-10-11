<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Forecast;
use App\Models\ForecastProduct;
use App\Models\ForecastType;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Deals, projects and forecasts are private to their owner (admins see all).
 * A regular user must not be able to read, edit or delete another user's
 * record by guessing its id — the list endpoints already scope by owner,
 * so the single-record endpoints have to as well.
 *
 * Hidden records return 404 (not 403), matching ForecastController::abortIfHidden,
 * so the response doesn't confirm the record exists.
 */
class OwnershipScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $alex;
    private User $sam;
    private Contact $samsContact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->alex = $this->userWithRole('user');
        $this->sam  = $this->userWithRole('user');

        $this->samsContact = Contact::create(['name' => 'Globex Trading', 'user_id' => $this->sam->id]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function samsDeal(): Deal
    {
        return Deal::create([
            'contact_id' => $this->samsContact->id,
            'user_id'    => $this->sam->id,
            'title'      => "Sam's deal",
            'stage'      => 'Negotiation',
            'value'      => 50000,
            'status'     => 'open',
        ]);
    }

    private function samsProject(): Project
    {
        return Project::create([
            'contact_id'        => $this->samsContact->id,
            'user_id'           => $this->sam->id,
            'project_name'      => "Sam's project",
            'project_startdate' => '2026-01-01',
            'project_enddate'   => '2026-06-30',
        ]);
    }

    // ── Deals ────────────────────────────────────────────────────────────────

    public function test_deal_list_only_shows_own_deals(): void
    {
        $this->samsDeal();
        Sanctum::actingAs($this->alex);

        $this->getJson('/api/v1/deals')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_cannot_view_another_users_deal(): void
    {
        $deal = $this->samsDeal();
        Sanctum::actingAs($this->alex);

        $this->getJson("/api/v1/deals/{$deal->id}")->assertNotFound();
    }

    public function test_user_cannot_update_another_users_deal(): void
    {
        $deal = $this->samsDeal();
        Sanctum::actingAs($this->alex);

        $this->putJson("/api/v1/deals/{$deal->id}", [
            'title'      => 'Hijacked',
            'stage'      => 'Lost',
            'contact_id' => $this->samsContact->id,
            'status'     => 'lost',
        ])->assertNotFound();

        $this->assertDatabaseHas('deals', ['id' => $deal->id, 'title' => "Sam's deal", 'status' => 'open']);
    }

    public function test_user_cannot_delete_another_users_deal(): void
    {
        $deal = $this->samsDeal();
        Sanctum::actingAs($this->alex);

        $this->deleteJson("/api/v1/deals/{$deal->id}")->assertNotFound();

        $this->assertDatabaseHas('deals', ['id' => $deal->id]);
    }

    public function test_admin_can_view_any_deal(): void
    {
        $deal = $this->samsDeal();
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->getJson("/api/v1/deals/{$deal->id}")
            ->assertOk()
            ->assertJsonPath('data.user_id', $this->sam->id);
    }

    // ── Projects ─────────────────────────────────────────────────────────────

    public function test_user_cannot_view_another_users_project(): void
    {
        $project = $this->samsProject();
        Sanctum::actingAs($this->alex);

        $this->getJson("/api/v1/projects/{$project->id}")->assertNotFound();
    }

    public function test_user_cannot_update_another_users_project(): void
    {
        $project = $this->samsProject();
        Sanctum::actingAs($this->alex);

        $this->putJson("/api/v1/projects/{$project->id}", [
            'project_name'      => 'Hijacked',
            'project_startdate' => '2026-01-01',
            'project_enddate'   => '2026-06-30',
            'contact_id'        => $this->samsContact->id,
        ])->assertNotFound();

        // Ownership must not silently move to whoever made the edit.
        $this->assertDatabaseHas('projects', [
            'id'           => $project->id,
            'project_name' => "Sam's project",
            'user_id'      => $this->sam->id,
        ]);
    }

    public function test_user_cannot_delete_another_users_project(): void
    {
        $project = $this->samsProject();
        Sanctum::actingAs($this->alex);

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    // ── Forecasts (reference: already scoped via abortIfHidden) ──────────────

    public function test_user_cannot_view_another_users_forecast(): void
    {
        $forecast = Forecast::create([
            'contact_id'       => $this->samsContact->id,
            'user_id'          => $this->sam->id,
            'product_id'       => ForecastProduct::create(['name' => 'Billboard'])->id,
            'forecast_type_id' => ForecastType::create(['name' => 'A1'])->id,
            'amount'           => 25000,
            'forecast_date'    => '2026-06-15',
        ]);
        Sanctum::actingAs($this->alex);

        $this->getJson("/api/v1/forecasts/{$forecast->id}")->assertNotFound();
    }
}
