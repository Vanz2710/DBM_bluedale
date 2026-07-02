<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\ContactType;
use App\Models\Forecast;
use App\Models\PerformanceTarget;
use App\Models\SocialMediaPackage;
use App\Models\SocialMediaReminder;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminLookupMergeTest extends TestCase
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

    public function test_delete_is_blocked_while_a_status_is_still_in_use(): void
    {
        $this->actingAdmin();

        $status = ContactStatus::create(['name' => 'KV4L']);
        Contact::create(['name' => 'Some Client', 'status_id' => $status->id]);

        $this->deleteJson("/api/v1/admin/statuses/{$status->id}")->assertStatus(409);
    }

    public function test_merge_into_existing_status_reassigns_contacts_and_forecasts(): void
    {
        $user = $this->actingAdmin();

        $keep   = ContactStatus::create(['name' => 'TG - Project']);
        $mergeA = ContactStatus::create(['name' => 'KV4L']);
        $mergeB = ContactStatus::create(['name' => 'JHTG']);

        $contact = Contact::create(['name' => 'Acme Media', 'status_id' => $mergeA->id]);
        $forecast = Forecast::create([
            'contact_id'        => $contact->id,
            'user_id'           => $user->id,
            'contact_status_id' => $mergeB->id,
            'amount'            => 1000,
            'forecast_date'     => '2026-07-01',
        ]);

        $this->postJson('/api/v1/admin/statuses/merge', [
            'keep_id'   => $keep->id,
            'merge_ids' => [$mergeA->id, $mergeB->id],
        ])->assertOk()
            ->assertJsonPath('merged', 2)
            ->assertJsonPath('data.id', $keep->id);

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'status_id' => $keep->id]);
        $this->assertDatabaseHas('forecasts', ['id' => $forecast->id, 'contact_status_id' => $keep->id]);
        $this->assertDatabaseMissing('contact_statuses', ['id' => $mergeA->id]);
        $this->assertDatabaseMissing('contact_statuses', ['id' => $mergeB->id]);
    }

    public function test_merge_with_new_name_creates_a_fresh_type_and_reassigns_contacts(): void
    {
        $this->actingAdmin();

        $typeA = ContactType::create(['name' => 'KLTG - Sabah']);
        $typeB = ContactType::create(['name' => 'KLTG Existing']);
        $contactA = Contact::create(['name' => 'Client A', 'type_id' => $typeA->id]);
        $contactB = Contact::create(['name' => 'Client B', 'type_id' => $typeB->id]);

        $res = $this->postJson('/api/v1/admin/types/merge', [
            'new_name'  => 'KLTG - Beyond',
            'merge_ids' => [$typeA->id, $typeB->id],
        ])->assertOk()
            ->assertJsonPath('merged', 2);

        $newId = $res->json('data.id');

        $this->assertDatabaseHas('contact_types', ['id' => $newId, 'name' => 'KLTG - Beyond']);
        $this->assertDatabaseHas('contacts', ['id' => $contactA->id, 'type_id' => $newId]);
        $this->assertDatabaseHas('contacts', ['id' => $contactB->id, 'type_id' => $newId]);
        $this->assertDatabaseMissing('contact_types', ['id' => $typeA->id]);
        $this->assertDatabaseMissing('contact_types', ['id' => $typeB->id]);
    }

    public function test_merge_packages_reassigns_the_string_based_reminder_column(): void
    {
        $this->actingAdmin();

        $basic = SocialMediaPackage::create(['name' => 'Basic']);
        $pro   = SocialMediaPackage::create(['name' => 'Pro']);

        $reminder1 = SocialMediaReminder::create(['company_name' => 'Co A', 'package' => 'Basic', 'month' => '2026-07']);
        $reminder2 = SocialMediaReminder::create(['company_name' => 'Co B', 'package' => 'Pro', 'month' => '2026-07']);

        $this->postJson('/api/v1/admin/packages/merge', [
            'new_name'  => 'Standard',
            'merge_ids' => [$basic->id, $pro->id],
        ])->assertOk()->assertJsonPath('merged', 2);

        $this->assertDatabaseHas('social_media_reminders', ['id' => $reminder1->id, 'package' => 'Standard']);
        $this->assertDatabaseHas('social_media_reminders', ['id' => $reminder2->id, 'package' => 'Standard']);
        $this->assertDatabaseMissing('social_media_packages', ['name' => 'Basic']);
        $this->assertDatabaseMissing('social_media_packages', ['name' => 'Pro']);
    }

    public function test_merging_tasks_with_conflicting_performance_targets_fails_cleanly(): void
    {
        $user = $this->actingAdmin();

        $taskA = Task::create(['name' => 'Call']);
        $taskB = Task::create(['name' => 'Email']);

        PerformanceTarget::create(['user_id' => $user->id, 'task_id' => $taskA->id, 'weekly_target' => 5]);
        PerformanceTarget::create(['user_id' => $user->id, 'task_id' => $taskB->id, 'weekly_target' => 3]);

        $this->postJson('/api/v1/admin/tasks/merge', [
            'keep_id'   => $taskA->id,
            'merge_ids' => [$taskB->id],
        ])->assertStatus(422)->assertJsonValidationErrors('merge_ids');

        // Transaction must roll back — nothing should have been deleted or reassigned.
        $this->assertDatabaseHas('tasks', ['id' => $taskB->id]);
        $this->assertDatabaseHas('performance_targets', ['user_id' => $user->id, 'task_id' => $taskA->id]);
        $this->assertDatabaseHas('performance_targets', ['user_id' => $user->id, 'task_id' => $taskB->id]);
    }
}
