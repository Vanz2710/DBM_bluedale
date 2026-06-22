<?php

namespace Database\Seeders;

use App\Models\EmailAudienceGroup;
use App\Models\EmailTag;
use Illuminate\Database\Seeder;

class EmailAudienceSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name' => 'Lead',   'color' => '#f59e0b'],
            ['name' => 'Client', 'color' => '#0f766e'],
            ['name' => 'VIP',    'color' => '#7c3aed'],
        ];

        foreach ($tags as $tag) {
            EmailTag::firstOrCreate(['name' => $tag['name']], ['color' => $tag['color']]);
        }

        $groups = [
            ['name' => 'All Contacts',     'type' => 'dynamic', 'filters' => [],                    'description' => 'Everyone in the contact book.'],
            ['name' => 'Leads',            'type' => 'dynamic', 'filters' => ['tag' => 'Lead'],     'description' => 'Contacts tagged as leads.'],
            ['name' => 'Existing Clients', 'type' => 'dynamic', 'filters' => ['tag' => 'Client'],   'description' => 'Contacts tagged as clients.'],
            ['name' => 'VIP Clients',      'type' => 'dynamic', 'filters' => ['tag' => 'VIP'],      'description' => 'Your most important contacts.'],
            ['name' => 'Inactive Clients', 'type' => 'dynamic', 'filters' => ['activity' => 'none'],'description' => 'Contacts who never opened an email.'],
        ];

        foreach ($groups as $group) {
            EmailAudienceGroup::firstOrCreate(
                ['name' => $group['name']],
                [
                    'type'        => $group['type'],
                    'filters'     => $group['filters'],
                    'description' => $group['description'],
                    'is_system'   => true,
                ]
            );
        }
    }
}
