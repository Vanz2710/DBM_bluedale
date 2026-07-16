<?php

namespace Database\Seeders;

use App\Models\DeptTask;
use App\Models\DeptTaskComment;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DeptTaskSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::today();

        // dept_id => name mapping (from live DB)
        // 1:Finance  7:Personal  8:Revenue  9:Editorial  10:KL The Guide
        // 11:Social Media  12:IT  13:Marketing  14:Others

        // user_id mapping (picked active ones)
        // 1:Super Admin  2:vance  4:Lebron James  6:Anna  13:Kadir  14:Production  16:marketing

        $tasks = [
            // ── REVENUE ─────────────────────────────────────────────────────────
            [
                'title'         => 'Q3 sales pipeline review',
                'description'   => 'Go through all open deals above RM 50k and update forecast probabilities before board meeting.',
                'department_id' => 8,
                'assigned_to'   => 2,
                'created_by'    => 1,
                'priority'      => 'critical',
                'status'        => 'in_progress',
                'due_date'      => $now->copy()->addDays(1),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Follow up with Petronas account',
                'description'   => 'Confirm budget sign-off for the annual advertising package.',
                'department_id' => 8,
                'assigned_to'   => 4,
                'created_by'    => 1,
                'priority'      => 'high',
                'status'        => 'pending',
                'due_date'      => $now->copy()->addDays(3),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Update CRM contacts for Penang region',
                'department_id' => 8,
                'assigned_to'   => 2,
                'created_by'    => 2,
                'priority'      => 'medium',
                'status'        => 'completed',
                'due_date'      => $now->copy()->subDays(3),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Prepare Q2 revenue summary deck',
                'description'   => 'Slides for the management presentation. Use YoY comparison format.',
                'department_id' => 8,
                'assigned_to'   => 1,
                'created_by'    => 1,
                'priority'      => 'high',
                'status'        => 'completed',
                'due_date'      => $now->copy()->subDays(1),
                'board_position'=> 0,
            ],

            // ── EDITORIAL ───────────────────────────────────────────────────────
            [
                'title'         => 'Write cover story — July issue',
                'description'   => 'Feature: "The Rise of Local Luxury Brands". Min 2500 words, 3 interviews.',
                'department_id' => 9,
                'assigned_to'   => 6,
                'created_by'    => 1,
                'priority'      => 'critical',
                'status'        => 'in_progress',
                'due_date'      => $now->copy()->addDays(5),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Proof-read June supplement',
                'department_id' => 9,
                'assigned_to'   => 6,
                'created_by'    => 6,
                'priority'      => 'high',
                'status'        => 'pending',
                'due_date'      => $now->copy()->addDays(2),
                'board_position'=> 1,
            ],
            [
                'title'         => 'Source photography for F&B section',
                'description'   => 'Need at least 8 high-res images. Coordinate with freelancer.',
                'department_id' => 9,
                'assigned_to'   => 14,
                'created_by'    => 6,
                'priority'      => 'medium',
                'status'        => 'completed',
                'due_date'      => $now->copy()->subDays(5),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Archive May issue digital edition',
                'department_id' => 9,
                'assigned_to'   => 14,
                'created_by'    => 1,
                'priority'      => 'low',
                'status'        => 'completed',
                'due_date'      => $now->copy()->subDays(10),
                'board_position'=> 1,
            ],

            // ── SOCIAL MEDIA ────────────────────────────────────────────────────
            [
                'title'         => 'Schedule Instagram posts for next 2 weeks',
                'description'   => 'Use approved content calendar. Stories at 9am, Feed at 12pm.',
                'department_id' => 11,
                'assigned_to'   => 16,
                'created_by'    => 1,
                'priority'      => 'high',
                'status'        => 'in_progress',
                'due_date'      => $now->copy()->addDays(1),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Reply to brand collaboration DMs',
                'department_id' => 11,
                'assigned_to'   => 16,
                'created_by'    => 16,
                'priority'      => 'medium',
                'status'        => 'pending',
                'due_date'      => $now->copy(),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Run analytics report — June engagement',
                'description'   => 'Export from Meta Business Suite. Include reach, saves, link clicks.',
                'department_id' => 11,
                'assigned_to'   => 16,
                'created_by'    => 1,
                'priority'      => 'medium',
                'status'        => 'pending',
                'due_date'      => $now->copy()->addDays(7),
                'board_position'=> 1,
            ],
            [
                'title'         => 'Create Reel for product launch',
                'description'   => '15-second teaser. Brand colors, upbeat music. Due before press release.',
                'department_id' => 11,
                'assigned_to'   => 16,
                'created_by'    => 16,
                'priority'      => 'critical',
                'status'        => 'pending',
                'due_date'      => $now->copy()->subDays(2),  // overdue
                'board_position'=> 2,
            ],

            // ── MARKETING ───────────────────────────────────────────────────────
            [
                'title'         => 'Review July ad placements',
                'description'   => 'Confirm placements with all print and digital partners by EOD Friday.',
                'department_id' => 13,
                'assigned_to'   => 13,
                'created_by'    => 1,
                'priority'      => 'high',
                'status'        => 'in_progress',
                'due_date'      => $now->copy()->addDays(4),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Coordinate with events team for roadshow',
                'department_id' => 13,
                'assigned_to'   => 13,
                'created_by'    => 13,
                'priority'      => 'medium',
                'status'        => 'completed',
                'due_date'      => $now->copy()->addDays(6),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Update media kit 2026',
                'description'   => 'New rate card, circulation numbers, demographic breakdown.',
                'department_id' => 13,
                'assigned_to'   => 16,
                'created_by'    => 1,
                'priority'      => 'high',
                'status'        => 'pending',
                'due_date'      => $now->copy()->subDays(4),  // overdue
                'board_position'=> 0,
            ],
            [
                'title'         => 'Prepare awards submission — Best Lifestyle Brand',
                'department_id' => 13,
                'assigned_to'   => 13,
                'created_by'    => 1,
                'priority'      => 'medium',
                'status'        => 'cancelled',
                'due_date'      => $now->copy()->subDays(7),
                'board_position'=> 0,
            ],

            // ── FINANCE ─────────────────────────────────────────────────────────
            [
                'title'         => 'Process June invoices',
                'description'   => 'All outstanding invoices must be keyed in before month-end close.',
                'department_id' => 1,
                'assigned_to'   => 1,
                'created_by'    => 1,
                'priority'      => 'critical',
                'status'        => 'pending',
                'due_date'      => $now->copy()->addDays(2),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Reconcile petty cash — June',
                'department_id' => 1,
                'assigned_to'   => 1,
                'created_by'    => 1,
                'priority'      => 'medium',
                'status'        => 'in_progress',
                'due_date'      => $now->copy()->addDays(3),
                'board_position'=> 1,
            ],
            [
                'title'         => 'Submit SST return',
                'description'   => 'Deadline is 30 June. Ensure all taxable supplies are captured.',
                'department_id' => 1,
                'assigned_to'   => 1,
                'created_by'    => 1,
                'priority'      => 'critical',
                'status'        => 'pending',
                'due_date'      => $now->copy()->subDays(3),  // overdue
                'board_position'=> 1,
            ],
            [
                'title'         => 'Annual audit prep — document request list',
                'department_id' => 1,
                'assigned_to'   => 1,
                'created_by'    => 1,
                'priority'      => 'high',
                'status'        => 'completed',
                'due_date'      => $now->copy()->addDays(10),
                'board_position'=> 0,
            ],

            // ── IT ──────────────────────────────────────────────────────────────
            [
                'title'         => 'Upgrade CRM server SSL certificate',
                'description'   => 'Current cert expires in 12 days. Auto-renewal failed — manual action needed.',
                'department_id' => 12,
                'assigned_to'   => 2,
                'created_by'    => 1,
                'priority'      => 'critical',
                'status'        => 'in_progress',
                'due_date'      => $now->copy()->addDays(2),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Set up new staff laptop — Kadir',
                'department_id' => 12,
                'assigned_to'   => 2,
                'created_by'    => 1,
                'priority'      => 'medium',
                'status'        => 'completed',
                'due_date'      => $now->copy()->subDays(2),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Weekly server backup check',
                'department_id' => 12,
                'assigned_to'   => 2,
                'created_by'    => 2,
                'priority'      => 'high',
                'status'        => 'pending',
                'due_date'      => $now->copy()->addDays(1),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Fix email delivery issue — bounce rate spike',
                'description'   => 'Check MX records and spam filter config. Affects campaign sends.',
                'department_id' => 12,
                'assigned_to'   => 2,
                'created_by'    => 1,
                'priority'      => 'critical',
                'status'        => 'pending',
                'due_date'      => $now->copy()->subDays(1),  // overdue
                'board_position'=> 1,
            ],

            // ── KL THE GUIDE ────────────────────────────────────────────────────
            [
                'title'         => 'Finalize restaurant listings — Q3 edition',
                'description'   => 'New entries: 12 F&B, 3 rooftop bars. Remove permanently closed listings.',
                'department_id' => 10,
                'assigned_to'   => 6,
                'created_by'    => 1,
                'priority'      => 'high',
                'status'        => 'in_progress',
                'due_date'      => $now->copy()->addDays(6),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Coordinate map layout with printer',
                'department_id' => 10,
                'assigned_to'   => 14,
                'created_by'    => 6,
                'priority'      => 'medium',
                'status'        => 'pending',
                'due_date'      => $now->copy()->addDays(8),
                'board_position'=> 0,
            ],

            // ── PERSONAL ────────────────────────────────────────────────────────
            [
                'title'         => 'Prepare talking points for board meeting',
                'description'   => 'Cover: YTD revenue, editorial calendar, headcount plan.',
                'department_id' => 7,
                'assigned_to'   => 1,
                'created_by'    => 1,
                'priority'      => 'critical',
                'status'        => 'pending',
                'due_date'      => $now->copy()->addDays(1),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Sign off on HR policy update',
                'department_id' => 7,
                'assigned_to'   => 1,
                'created_by'    => 1,
                'priority'      => 'high',
                'status'        => 'pending',
                'due_date'      => $now->copy()->addDays(3),
                'board_position'=> 1,
            ],

            // ── OTHERS ──────────────────────────────────────────────────────────
            [
                'title'         => 'Arrange office pantry restocking',
                'department_id' => 14,
                'assigned_to'   => 4,
                'created_by'    => 1,
                'priority'      => 'low',
                'status'        => 'completed',
                'due_date'      => $now->copy()->subDays(4),
                'board_position'=> 0,
            ],
            [
                'title'         => 'Book venue for team lunch',
                'department_id' => 14,
                'assigned_to'   => 6,
                'created_by'    => 1,
                'priority'      => 'low',
                'status'        => 'pending',
                'due_date'      => $now->copy()->addDays(5),
                'board_position'=> 0,
            ],
        ];

        // firstOrCreate keyed on title + assignee + department so re-running this
        // seeder does NOT duplicate rows (a plain create() previously did).
        foreach ($tasks as $data) {
            DeptTask::firstOrCreate(
                [
                    'title'         => $data['title'],
                    'assigned_to'   => $data['assigned_to'],
                    'department_id' => $data['department_id'],
                ],
                $data
            );
        }

        // Add a couple of sample comments on the first two tasks so the detail panel is populated
        $first  = DeptTask::where('title', 'Q3 sales pipeline review')->first();
        $second = DeptTask::where('title', 'Write cover story — July issue')->first();

        if ($first) {
            DeptTaskComment::firstOrCreate([
                'task_id' => $first->id,
                'user_id' => 1,
                'comment' => 'Deals above RM 50k updated. Still waiting on 3 responses from the Klang Valley team.',
            ]);
            DeptTaskComment::firstOrCreate([
                'task_id' => $first->id,
                'user_id' => 2,
                'comment' => 'Penang pipeline looks good — 2 hot leads to bump to 80%.',
            ]);
        }

        if ($second) {
            DeptTaskComment::firstOrCreate([
                'task_id' => $second->id,
                'user_id' => 6,
                'comment' => 'Interview with Dato Razif confirmed for Thursday 10am.',
            ]);
        }
    }
}
