<?php

namespace Database\Seeders;

use App\Models\ContactArea;
use App\Models\ContactCategory;
use App\Models\ContactIndustry;
use App\Models\ContactStatus;
use App\Models\ContactType;
use App\Models\Task;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'Active',
            'Inactive',
            'Prospect',
            'Lead',
            'Churned',
            'On Hold',
        ];

        $types = [
            'Client',
            'Prospect',
            'Partner',
            'Vendor',
            'Referral',
            'Internal',
        ];

        $categories = [
            'Hot',
            'Warm',
            'Cold',
            'VIP',
            'At Risk',
        ];

        $industries = [
            'Education',
            'Government',
            'Healthcare',
            'Hospitality',
            'Legal',
            'Manufacturing',
            'Media & Publishing',
            'Non-Profit',
            'Real Estate',
            'Retail',
            'Technology',
            'Finance & Banking',
            'Construction',
            'Logistics & Transport',
            'Other',
        ];

        $areas = [
            'Luzon',
            'Visayas',
            'Mindanao',
            'Metro Manila',
            'Central Luzon',
            'CALABARZON',
            'Ilocos Region',
            'Cagayan Valley',
            'Cordillera (CAR)',
            'MIMAROPA',
            'Bicol Region',
            'Western Visayas',
            'Central Visayas',
            'Eastern Visayas',
            'Zamboanga Peninsula',
            'Northern Mindanao',
            'Davao Region',
            'SOCCSKSARGEN',
            'Caraga',
            'BARMM',
        ];

        $tasks = [
            'Call',
            'Email',
            'Meeting',
            'Follow-Up',
            'Demo',
            'Proposal',
            'Site Visit',
            'Contract Review',
            'Quotation',
            'Onboarding',
            'Support',
            'Check-In',
        ];

        foreach ($statuses as $name) {
            ContactStatus::firstOrCreate(['name' => $name]);
        }

        foreach ($types as $name) {
            ContactType::firstOrCreate(['name' => $name]);
        }

        foreach ($categories as $name) {
            ContactCategory::firstOrCreate(['name' => $name]);
        }

        foreach ($industries as $name) {
            ContactIndustry::firstOrCreate(['name' => $name]);
        }

        foreach ($areas as $name) {
            ContactArea::firstOrCreate(['name' => $name]);
        }

        foreach ($tasks as $name) {
            Task::firstOrCreate(['name' => $name]);
        }
    }
}
