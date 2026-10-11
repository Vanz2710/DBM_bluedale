<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\ContactCategory;
use App\Models\ContactIndustry;
use App\Models\ContactStatus;
use App\Models\ContactType;
use App\Models\Deal;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\ToDo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Clearly fictional demo data for screen recordings — local use only.
 *
 * Creates four demo logins (username-based) and ~15 "(Demo)" contacts with
 * deals, to-dos and follow-ups split across the two regular users so the
 * per-user ownership scoping is visible. Safe to re-run: users are upserted
 * by username, and contacts that already exist are left untouched.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'DemoPass2026!';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoDataSeeder refuses to run in production.');

            return;
        }

        $this->call([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            $users = $this->seedUsers();
            $this->seedContacts($users['demo_alex'], $users['demo_sam']);
        });

        $this->command?->info('Demo logins (username / password '.self::PASSWORD.'):');
        $this->command?->table(
            ['Username', 'Name', 'Email', 'Role'],
            [
                ['demo_superadmin', 'Demo Superadmin', 'superadmin@example.test', 'super-admin'],
                ['demo_admin',      'Demo Admin',      'admin@example.test',      'admin'],
                ['demo_alex',       'Alex Example',    'alex@example.test',       'user'],
                ['demo_sam',        'Sam Sample',      'sam@example.test',        'user'],
            ]
        );
    }

    /** @return array<string, User> keyed by username */
    private function seedUsers(): array
    {
        $defs = [
            ['demo_superadmin', 'Demo Superadmin', 'superadmin@example.test', 'super-admin', 'super_admin', 'Demo Director'],
            ['demo_admin',      'Demo Admin',      'admin@example.test',      'admin',       'staff',       'Demo Office Manager'],
            ['demo_alex',       'Alex Example',    'alex@example.test',       'user',        'staff',       'Demo Sales Executive'],
            ['demo_sam',        'Sam Sample',      'sam@example.test',        'user',        'staff',       'Demo Sales Executive'],
        ];

        $users = [];
        foreach ($defs as [$username, $name, $email, $spatieRole, $deptRole, $jobTitle]) {
            $user = User::withTrashed()->firstOrNew(['username' => $username]);
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => self::PASSWORD,
                'job_title' => $jobTitle,
                'phone' => null,
                'role' => $deptRole,
                'email_verified_at' => now(),
                'is_approved' => true,
                'approved_at' => now(),
                'blocked_at' => null,
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'lockout_level' => 0,
                'permanently_locked' => false,
                'deleted_at' => null,
            ])->save();

            $user->syncRoles([$spatieRole]);
            $users[$username] = $user;
        }

        return $users;
    }

    private function seedContacts(User $alex, User $sam): void
    {
        $statusIds = ContactStatus::query()->pluck('id')->all();
        $typeIds = ContactType::query()->pluck('id')->all();
        $categoryIds = ContactCategory::query()->pluck('id')->all();
        $industryIds = ContactIndustry::query()->pluck('id')->all();
        $taskIds = Task::query()->pluck('id')->all();

        $pick = fn (array $ids, int $i) => $ids ? $ids[$i % count($ids)] : null;

        // [company, person in charge, remark]
        $contacts = [
            ['Acme Widgets (Demo)',            'Wile E. Example',   'Interested in billboard package.'],
            ['Globex Trading (Demo)',          'Hank Placeholder',  'Asked for Q4 rate card.'],
            ['Initech Solutions (Demo)',       'Peter Sample',      'Wants a digital advertorial bundle.'],
            ['Umbrella Foods (Demo)',          'Alice Fictional',   'Referral from a trade show.'],
            ['Stark Lighting (Demo)',          'Tony Testcase',     'Comparing three vendors.'],
            ['Wayne Logistics (Demo)',         'Bruce Dummy',       'Needs proposal by month end.'],
            ['Hooli Media (Demo)',             'Gavin Mockup',      'Existing client, renewal due.'],
            ['Pied Piper Studio (Demo)',       'Richard Stub',      'Small budget, high potential.'],
            ['Cyberdyne Retail (Demo)',        'Sarah Fixture',     'Requested a site visit.'],
            ['Soylent Beverages (Demo)',       'Robert Template',   'Seasonal campaign enquiry.'],
            ['Tyrell Interiors (Demo)',        'Rachel Specimen',   'Follow up after call.'],
            ['Wonka Confectionery (Demo)',     'Willy Pretend',     'Interested in social media management.'],
            ['Oceanic Travel (Demo)',          'Jack Notreal',      'Looking at radio slots.'],
            ['Vandelay Imports (Demo)',        'Art Imaginary',     'Cold lead from website form.'],
            ['Bluth Property (Demo)',          'Lucille Madeup',    'Lost to competitor last year; re-engaging.'],
        ];

        $stages = [
            // stage, probability, status
            ['New Lead',       10, 'open'],
            ['Contacted',      25, 'open'],
            ['Quotation Sent', 50, 'open'],
            ['Negotiation',    75, 'open'],
            ['Won',           100, 'won'],
            ['Lost',            0, 'lost'],
        ];
        $actionTypes = ['Call', 'Email', 'Meeting', 'Site Visit', 'Proposal'];
        $today = today();

        foreach ($contacts as $i => [$company, $person, $remark]) {
            // Alternate ownership so each regular user owns roughly half.
            $owner = $i % 2 === 0 ? $alex : $sam;

            $contact = Contact::firstOrCreate(
                ['name' => $company],
                [
                    'address' => (100 + $i).' Example Street, Demo City',
                    'remark' => $remark,
                    'lead_source' => $i % 3 === 0 ? 'Website' : ($i % 3 === 1 ? 'Referral' : 'Walk-in'),
                    'user_id' => $owner->id,
                    'status_id' => $pick($statusIds, $i),
                    'type_id' => $pick($typeIds, $i),
                    'category_id' => $pick($categoryIds, $i),
                    'industry_id' => $pick($industryIds, $i),
                ]
            );

            if (! $contact->wasRecentlyCreated) {
                continue;
            }

            $slug = strtolower(preg_replace('/[^a-z]+/i', '.', trim($person)));
            $contact->incharges()->create([
                'name' => $person,
                'email' => "{$slug}@example.test",
                'phone_mobile' => sprintf('+1-555-01%02d', $i),
                'phone_office' => null,
            ]);

            [$stage, $probability, $status] = $stages[$i % count($stages)];
            Deal::create([
                'contact_id' => $contact->id,
                'user_id' => $owner->id,
                'title' => "{$company} – advertising package",
                'stage' => $stage,
                'value' => 2500 + ($i * 1750),
                'probability' => $probability,
                'expected_close_date' => $today->copy()->addDays(7 + $i * 4),
                'status' => $status,
                'lost_reason' => $status === 'lost' ? 'Demo: chose a cheaper option' : null,
                'notes' => 'Demo deal – fictional data.',
            ]);

            // One overdue/completed to-do and one upcoming to-do per contact.
            $past = ToDo::create([
                'contact_id' => $contact->id,
                'user_id' => $owner->id,
                'task_id' => $pick($taskIds, $i),
                'todo_date' => $today->copy()->subDays(3 + $i),
                'date_created' => $today->copy()->subDays(10 + $i),
                'todo_remark' => "Intro call with {$person}",
                'completion_status' => $i % 3 === 0 ? 'pending' : 'completed',
                'completed_at' => $i % 3 === 0 ? null : $today->copy()->subDays(2 + $i),
            ]);

            $upcoming = ToDo::create([
                'contact_id' => $contact->id,
                'user_id' => $owner->id,
                'task_id' => $pick($taskIds, $i + 1),
                'todo_date' => $today->copy()->addDays(1 + $i),
                'date_created' => $today->copy()->subDays(1),
                'todo_remark' => 'Send updated quotation',
                'completion_status' => 'pending',
                'completed_at' => null,
            ]);

            FollowUp::create([
                'todo_id' => $past->id,
                'followup_date' => $today->copy()->subDays(1 + ($i % 4)),
                'action_type' => $actionTypes[$i % count($actionTypes)],
                'note' => 'Demo follow-up – discussed requirements.',
                'completion_status' => 'completed',
                'completed_at' => $today->copy()->subDays(1 + ($i % 4)),
            ]);

            if ($i % 2 === 1) {
                FollowUp::create([
                    'todo_id' => $upcoming->id,
                    'followup_date' => $today->copy()->addDays(2 + $i),
                    'action_type' => $actionTypes[($i + 2) % count($actionTypes)],
                    'note' => 'Demo follow-up – check on quotation.',
                    'completion_status' => 'pending',
                    'completed_at' => null,
                ]);
            }
        }
    }
}
