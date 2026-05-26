<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\ContactType;
use App\Models\Forecast;
use App\Models\ForecastProduct;
use App\Models\ForecastResult;
use App\Models\ForecastType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ForecastContactTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('super-admin');

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_forecast_can_be_created_from_contact_and_loaded_on_contact_detail(): void
    {
        $user = $this->actingAdmin();

        $status = ContactStatus::create(['name' => 'Prospect']);
        $type = ContactType::create(['name' => 'Client']);
        $contact = Contact::create([
            'name' => 'Acme Media',
            'user_id' => $user->id,
            'status_id' => $status->id,
            'type_id' => $type->id,
        ]);
        $product = ForecastProduct::create(['name' => 'Billboard']);
        $forecastType = ForecastType::create(['name' => 'A1']);
        $result = ForecastResult::create(['name' => 'Pending']);

        $this->postJson('/api/v1/forecasts', [
            'contact_id' => $contact->id,
            'product_id' => $product->id,
            'forecast_type_id' => $forecastType->id,
            'result_id' => $result->id,
            'amount' => 25000,
            'forecast_date' => '2026-06-15',
        ])->assertCreated()
            ->assertJsonPath('data.contact_id', $contact->id)
            ->assertJsonPath('data.contact_status_id', $status->id)
            ->assertJsonPath('data.contact_type_id', $type->id);

        $this->getJson("/api/v1/contacts/{$contact->id}")
            ->assertOk()
            ->assertJsonPath('data.forecasts.0.contact_id', $contact->id)
            ->assertJsonPath('data.forecasts.0.product.name', 'Billboard')
            ->assertJsonPath('data.forecasts.0.forecast_type.name', 'A1');
    }

    public function test_contact_merge_moves_forecasts_to_the_kept_contact(): void
    {
        $user = $this->actingAdmin();

        $keep = Contact::create(['name' => 'Kept Company', 'user_id' => $user->id]);
        $merged = Contact::create(['name' => 'Merged Company', 'user_id' => $user->id]);
        $product = ForecastProduct::create(['name' => 'Website Dev']);
        $forecastType = ForecastType::create(['name' => 'PL']);

        $forecast = Forecast::create([
            'contact_id' => $merged->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'forecast_type_id' => $forecastType->id,
            'amount' => 10000,
            'forecast_date' => '2026-07-01',
            'forecast_updatedate' => '2026-05-20',
        ]);

        $this->postJson('/api/v1/contacts/merge', [
            'keep_id' => $keep->id,
            'merge_ids' => [$merged->id],
        ])->assertOk()
            ->assertJsonPath('merged', 1);

        $this->assertDatabaseHas('forecasts', [
            'id' => $forecast->id,
            'contact_id' => $keep->id,
        ]);
        $this->assertDatabaseMissing('contacts', ['id' => $merged->id]);
    }
}
