<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Personal',      'code' => 'PER', 'color' => '#7C3AED'],
            ['name' => 'Revenue',       'code' => 'REV', 'color' => '#059669'],
            ['name' => 'Editorial',     'code' => 'EDI', 'color' => '#EA580C'],
            ['name' => 'Finance',       'code' => 'FIN', 'color' => '#2563EB'],
            ['name' => 'KL The Guide',  'code' => 'KLG', 'color' => '#0891B2'],
            ['name' => 'Social Media',  'code' => 'SOC', 'color' => '#E11D48'],
            ['name' => 'IT',            'code' => 'IT',  'color' => '#4F46E5'],
            ['name' => 'Marketing',     'code' => 'MKT', 'color' => '#D97706'],
            ['name' => 'Others',        'code' => 'OTH', 'color' => '#64748B'],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['code' => $dept['code']],
                ['name' => $dept['name'], 'color' => $dept['color']]
            );
        }
    }
}
