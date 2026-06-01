<?php

namespace Database\Seeders;

use App\Models\ContactCategory;
use App\Models\ContactIndustry;
use App\Models\ContactStatus;
use App\Models\ContactType;
use App\Models\ForecastProduct;
use App\Models\ForecastResult;
use App\Models\ForecastType;
use App\Models\Task;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'Agency',
            'BP Distribution',
            'Client',
            'Existing',
            'KLTG - Sabah',
            'KLTG Existing',
            'KLTG Potential',
            'KLTG Raw',
            'KV4L Existing',
            'KV4L Potential',
            'KV4L Raw',
            'Potential',
            'Project',
            'Raw',
            'Raw New',
            'Supplier',
        ];

        $types = [
            'A1',
            'A2',
            'A3',
            'Contract',
            'on going',
            'reject',
        ];

        $categories = [
            'Banner ads',
            'Billboard',
            'Digital advertorial',
            'E-catalogue',
            'Enquiry',
            'FB Sponsored ads',
            'JKR Signage',
            'Lamp post bunting',
            'Newspaper',
            'Others',
            'Project - Outdoor',
            'Radio',
            'Social media management',
            'TG - CHEMS',
            'TG - JHTG',
            'TG - KLTG',
            'TG - KNTG',
            'TG - KSTG',
            'TG - KV4L',
            'TG - MKTG',
            'TG - SBTG',
            'TG - TBTG',
            'TG - TWTG',
            'Temp board',
            'Travel Guide',
            'Travel guide- Others',
            'Website',
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

        $forecastProducts = [
            'Billboard',
            'Temp Board',
            'Socmed management',
            'Facebook',
            'Travel Guide (Project)',
            'Website Dev',
            'Newspaper',
            'Radio',
            'KLTG',
            'KV4L',
            'e-Catalogue',
            'Bunting',
        ];

        $forecastTypes = ['A1', 'A2', 'PL', 'UL'];

        $forecastResults = [
            ['id' => 1, 'name' => 'Confirmed'],
            ['id' => 2, 'name' => 'Rejected'],
            ['id' => 3, 'name' => 'Pending'],
            ['id' => 100, 'name' => 'No Result'],
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

        foreach ($tasks as $name) {
            Task::firstOrCreate(['name' => $name]);
        }

        foreach ($forecastProducts as $name) {
            ForecastProduct::firstOrCreate(['name' => $name]);
        }

        foreach ($forecastTypes as $name) {
            ForecastType::firstOrCreate(['name' => $name]);
        }

        foreach ($forecastResults as $result) {
            ForecastResult::updateOrCreate(['id' => $result['id']], ['name' => $result['name']]);
        }
    }
}
