<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\ToDo;
use App\Models\FollowUp;
use App\Models\KpiTarget;

class PredictiveInsightsSeeder extends Seeder
{
    public function run(): void
    {
        $today   = Carbon::today();
        $userIds = DB::table('users')->pluck('id')->toArray();
        $user1   = $userIds[0];
        $user2   = count($userIds) > 1 ? $userIds[1] : $user1;

        // ── 1. Additional contacts ─────────────────────────────────────────
        $contactDefs = [
            ['name' => 'Acme Media Corp',       'status_id' => 3,  'type_id' => 1, 'industry_id' => 7,  'area_id' => 4,  'user_id' => $user1, 'lead_source' => 'Referral'],
            ['name' => 'Golden Gate Retail',     'status_id' => 12, 'type_id' => 2, 'industry_id' => 10, 'area_id' => 5,  'user_id' => $user1, 'lead_source' => 'Cold Call'],
            ['name' => 'BlueStar Healthcare',    'status_id' => 3,  'type_id' => 1, 'industry_id' => 3,  'area_id' => 4,  'user_id' => $user2, 'lead_source' => 'Website'],
            ['name' => 'Pinnacle Finance',       'status_id' => 4,  'type_id' => 2, 'industry_id' => 12, 'area_id' => 4,  'user_id' => $user1, 'lead_source' => 'Exhibition'],
            ['name' => 'Summit Tech Solutions',  'status_id' => 12, 'type_id' => 1, 'industry_id' => 11, 'area_id' => 6,  'user_id' => $user2, 'lead_source' => 'Referral'],
            ['name' => 'Verde Construction',     'status_id' => 12, 'type_id' => 3, 'industry_id' => 13, 'area_id' => 2,  'user_id' => $user1, 'lead_source' => 'LinkedIn'],
            ['name' => 'Horizon Logistics',      'status_id' => 3,  'type_id' => 1, 'industry_id' => 14, 'area_id' => 3,  'user_id' => $user2, 'lead_source' => 'Cold Call'],
            ['name' => 'Sunrise Education',      'status_id' => 4,  'type_id' => 2, 'industry_id' => 1,  'area_id' => 7,  'user_id' => $user1, 'lead_source' => 'Referral'],
            ['name' => 'Pacific Real Estate',    'status_id' => 3,  'type_id' => 1, 'industry_id' => 9,  'area_id' => 4,  'user_id' => $user2, 'lead_source' => 'Exhibition'],
            ['name' => 'Metro Government Dept',  'status_id' => 13, 'type_id' => 4, 'industry_id' => 2,  'area_id' => 4,  'user_id' => $user1, 'lead_source' => 'Tender'],
            ['name' => 'Lakeview Hospitality',   'status_id' => 12, 'type_id' => 2, 'industry_id' => 4,  'area_id' => 8,  'user_id' => $user2, 'lead_source' => 'Website'],
            ['name' => 'IronCore Manufacturing', 'status_id' => 3,  'type_id' => 1, 'industry_id' => 6,  'area_id' => 5,  'user_id' => $user1, 'lead_source' => 'Referral'],
            ['name' => 'Apex Legal Services',    'status_id' => 4,  'type_id' => 1, 'industry_id' => 5,  'area_id' => 4,  'user_id' => $user2, 'lead_source' => 'Cold Call'],
            ['name' => 'NorthStar Non-Profit',   'status_id' => 12, 'type_id' => 3, 'industry_id' => 8,  'area_id' => 1,  'user_id' => $user1, 'lead_source' => 'Referral'],
            ['name' => 'Eastern Tech Hub',       'status_id' => 12, 'type_id' => 2, 'industry_id' => 11, 'area_id' => 3,  'user_id' => $user2, 'lead_source' => 'LinkedIn'],
            ['name' => 'Crown Retail Group',     'status_id' => 3,  'type_id' => 1, 'industry_id' => 10, 'area_id' => 6,  'user_id' => $user1, 'lead_source' => 'Exhibition'],
            ['name' => 'Bayview Finance',        'status_id' => 3,  'type_id' => 2, 'industry_id' => 12, 'area_id' => 4,  'user_id' => $user2, 'lead_source' => 'Referral'],
            ['name' => 'Island Media Works',     'status_id' => 12, 'type_id' => 1, 'industry_id' => 7,  'area_id' => 2,  'user_id' => $user1, 'lead_source' => 'Website'],
            ['name' => 'GreenPath Energy',       'status_id' => 12, 'type_id' => 3, 'industry_id' => 15, 'area_id' => 1,  'user_id' => $user2, 'lead_source' => 'Cold Call'],
            ['name' => 'National Health Coop',   'status_id' => 4,  'type_id' => 1, 'industry_id' => 3,  'area_id' => 4,  'user_id' => $user1, 'lead_source' => 'Exhibition'],
        ];

        $contactIds = [];
        foreach ($contactDefs as $def) {
            $daysAgo = rand(7, 90);
            $c = Contact::create(array_merge($def, [
                'category_id' => rand(1, 10),
                'address'     => fake()->address(),
                'created_at'  => $today->copy()->subDays($daysAgo),
                'updated_at'  => $today->copy()->subDays($daysAgo),
            ]));
            $contactIds[] = $c->id;
        }

        // Include pre-existing contacts too
        $allContactIds = Contact::pluck('id')->toArray();

        // ── 2. KPI Targets ─────────────────────────────────────────────────
        $metrics = [
            'new_contacts'        => 10,
            'todos_completed'     => 20,
            'followups_completed' => 15,
            'projects_created'    => 3,
            'deals_created'       => 5,
            'deals_won'           => 2,
            'won_deal_value'      => 50000,
        ];
        foreach ($userIds as $uid) {
            foreach ($metrics as $metric => $target) {
                KpiTarget::updateOrCreate(
                    ['user_id' => $uid, 'metric' => $metric],
                    ['target_value' => $target]
                );
            }
        }

        // ── 3. Deals ───────────────────────────────────────────────────────
        $stages = ['Lead', 'Qualified', 'Proposal', 'Negotiation', 'Closing'];

        // Won deals (historical — spread over past 3 months)
        $wonDeals = [
            ['value' => 45000,  'days_ago' => 75, 'contact' => $allContactIds[0]],
            ['value' => 28500,  'days_ago' => 60, 'contact' => $allContactIds[1] ?? $allContactIds[0]],
            ['value' => 120000, 'days_ago' => 45, 'contact' => $allContactIds[2] ?? $allContactIds[0]],
            ['value' => 15000,  'days_ago' => 30, 'contact' => $allContactIds[3] ?? $allContactIds[0]],
            ['value' => 67000,  'days_ago' => 20, 'contact' => $allContactIds[4] ?? $allContactIds[0]],
            ['value' => 33000,  'days_ago' => 14, 'contact' => $allContactIds[5] ?? $allContactIds[0]],
            ['value' => 89000,  'days_ago' => 7,  'contact' => $allContactIds[0]],
        ];
        foreach ($wonDeals as $i => $d) {
            $wonAt = $today->copy()->subDays($d['days_ago']);
            Deal::create([
                'contact_id'          => $d['contact'],
                'user_id'             => $i % 2 === 0 ? $user1 : $user2,
                'title'               => 'Deal #' . ($i + 1) . ' - Won',
                'stage'               => 'Closing',
                'value'               => $d['value'],
                'probability'         => 100,
                'expected_close_date' => $wonAt->toDateString(),
                'status'              => 'won',
                'created_at'          => $wonAt->copy()->subDays(rand(14, 30)),
                'updated_at'          => $wonAt,
            ]);
        }

        // Lost deals
        $lostDeals = [
            ['value' => 22000, 'days_ago' => 50, 'contact' => $allContactIds[0]],
            ['value' => 41000, 'days_ago' => 35, 'contact' => $allContactIds[1] ?? $allContactIds[0]],
            ['value' => 9500,  'days_ago' => 18, 'contact' => $allContactIds[2] ?? $allContactIds[0]],
        ];
        foreach ($lostDeals as $i => $d) {
            $lostAt = $today->copy()->subDays($d['days_ago']);
            Deal::create([
                'contact_id'          => $d['contact'],
                'user_id'             => $i % 2 === 0 ? $user1 : $user2,
                'title'               => 'Deal #' . ($i + 1) . ' - Lost',
                'stage'               => 'Negotiation',
                'value'               => $d['value'],
                'probability'         => 0,
                'expected_close_date' => $lostAt->toDateString(),
                'status'              => 'lost',
                'lost_reason'         => 'Budget constraints',
                'created_at'          => $lostAt->copy()->subDays(rand(10, 25)),
                'updated_at'          => $lostAt,
            ]);
        }

        // Open deals with close dates in the next 1–12 weeks (what the forecast chart shows)
        $openDeals = [
            ['value' => 55000,  'prob' => 80, 'stage' => 'Closing',     'close_weeks' => 1],
            ['value' => 38000,  'prob' => 65, 'stage' => 'Negotiation', 'close_weeks' => 1],
            ['value' => 120000, 'prob' => 40, 'stage' => 'Proposal',    'close_weeks' => 2],
            ['value' => 75000,  'prob' => 70, 'stage' => 'Closing',     'close_weeks' => 2],
            ['value' => 22000,  'prob' => 50, 'stage' => 'Negotiation', 'close_weeks' => 3],
            ['value' => 48000,  'prob' => 30, 'stage' => 'Proposal',    'close_weeks' => 3],
            ['value' => 200000, 'prob' => 20, 'stage' => 'Qualified',   'close_weeks' => 4],
            ['value' => 15000,  'prob' => 90, 'stage' => 'Closing',     'close_weeks' => 4],
            ['value' => 62000,  'prob' => 55, 'stage' => 'Negotiation', 'close_weeks' => 5],
            ['value' => 35000,  'prob' => 45, 'stage' => 'Proposal',    'close_weeks' => 5],
            ['value' => 90000,  'prob' => 35, 'stage' => 'Proposal',    'close_weeks' => 6],
            ['value' => 28000,  'prob' => 75, 'stage' => 'Closing',     'close_weeks' => 6],
            ['value' => 180000, 'prob' => 15, 'stage' => 'Lead',        'close_weeks' => 8],
            ['value' => 44000,  'prob' => 60, 'stage' => 'Negotiation', 'close_weeks' => 10],
            ['value' => 95000,  'prob' => 25, 'stage' => 'Qualified',   'close_weeks' => 12],
        ];
        $cIdx = 0;
        foreach ($openDeals as $i => $d) {
            $closeDate  = $today->copy()->addWeeks($d['close_weeks']);
            $contactIdx = $cIdx % count($allContactIds);
            Deal::create([
                'contact_id'          => $allContactIds[$contactIdx],
                'user_id'             => $i % 2 === 0 ? $user1 : $user2,
                'title'               => $d['stage'] . ' — ' . $d['value'] . ' deal',
                'stage'               => $d['stage'],
                'value'               => $d['value'],
                'probability'         => $d['prob'],
                'expected_close_date' => $closeDate->toDateString(),
                'status'              => 'open',
                'created_at'          => $today->copy()->subDays(rand(5, 45)),
                'updated_at'          => $today->copy()->subDays(rand(0, 4)),
            ]);
            $cIdx++;
        }

        // ── 4. ToDos & FollowUps (past 8 weeks for trend) ─────────────────
        // Task IDs — grab whatever exists in the tasks table
        $taskIds = DB::table('tasks')->pluck('id')->toArray();
        if (empty($taskIds)) {
            $taskIds = [null];
        }

        // We want a rising trend: more activity in recent weeks
        // Week -8 → -1 (relative to current week start)
        $weeklyTodoCounts   = [3, 4, 5, 6, 7, 8, 9, 11];
        $weeklyFollowCounts = [2, 3, 4, 5, 5, 6, 7, 9];

        for ($w = 0; $w < 8; $w++) {
            $weekStart = $today->copy()->startOfWeek()->subWeeks(7 - $w);

            // Todos for this week
            for ($t = 0; $t < $weeklyTodoCounts[$w]; $t++) {
                $doneAt    = $weekStart->copy()->addDays(rand(0, 6))->addHours(rand(8, 17));
                $uid       = $t % 2 === 0 ? $user1 : $user2;
                $contactId = $allContactIds[rand(0, count($allContactIds) - 1)];
                $taskId    = $taskIds[array_rand($taskIds)];

                $todo = ToDo::create([
                    'contact_id'        => $contactId,
                    'user_id'           => $uid,
                    'task_id'           => $taskId,
                    'todo_date'         => $doneAt->toDateString(),
                    'date_created'      => $doneAt->copy()->subDays(rand(1, 5))->toDateString(),
                    'todo_remark'       => 'Seeded activity todo',
                    'completion_status' => 'completed',
                    'completed_at'      => $doneAt,
                    'created_at'        => $doneAt->copy()->subDays(rand(1, 5)),
                    'updated_at'        => $doneAt,
                ]);

                // Some todos get a follow-up
                if ($t < $weeklyFollowCounts[$w]) {
                    FollowUp::create([
                        'todo_id'           => $todo->id,
                        'followup_date'     => $doneAt->toDateString(),
                        'action_type'       => ['Call', 'Email', 'Meeting', 'WhatsApp'][rand(0, 3)],
                        'note'              => 'Seeded follow-up',
                        'completion_status' => 'completed',
                        'completed_at'      => $doneAt->copy()->addHours(rand(1, 4)),
                        'created_at'        => $doneAt,
                        'updated_at'        => $doneAt->copy()->addHours(rand(1, 4)),
                    ]);
                }
            }
        }

        // A few pending todos (for overdue risk)
        for ($i = 0; $i < 5; $i++) {
            $dueDate   = $today->copy()->subDays(rand(1, 14));
            $uid       = $i % 2 === 0 ? $user1 : $user2;
            $contactId = $allContactIds[rand(0, count($allContactIds) - 1)];
            ToDo::create([
                'contact_id'        => $contactId,
                'user_id'           => $uid,
                'task_id'           => $taskIds[array_rand($taskIds)],
                'todo_date'         => $dueDate->toDateString(),
                'date_created'      => $dueDate->copy()->subDays(3)->toDateString(),
                'todo_remark'       => 'Pending overdue todo',
                'completion_status' => 'pending',
                'completed_at'      => null,
                'created_at'        => $dueDate->copy()->subDays(3),
                'updated_at'        => $dueDate->copy()->subDays(3),
            ]);
        }

        $this->command->info('PredictiveInsights seeded:');
        $this->command->info('  Contacts: +' . count($contactDefs));
        $this->command->info('  Deals: ' . (count($wonDeals) + count($lostDeals) + count($openDeals)) . ' (' . count($openDeals) . ' open, ' . count($wonDeals) . ' won, ' . count($lostDeals) . ' lost)');
        $this->command->info('  KPI Targets: ' . count($userIds) . ' users × ' . count($metrics) . ' metrics');
        $this->command->info('  Todos (8 weeks): ~' . array_sum($weeklyTodoCounts));
        $this->command->info('  FollowUps (8 weeks): ~' . array_sum($weeklyFollowCounts));
        $this->command->info('  Overdue pending todos: 5');
    }
}
