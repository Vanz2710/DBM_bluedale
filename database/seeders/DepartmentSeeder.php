<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Finance',               'code' => 'FIN', 'color' => '#3B82F6', 'icon' => '💰'],
            ['name' => 'Admin',                  'code' => 'ADM', 'color' => '#8B5CF6', 'icon' => '🗂️'],
            ['name' => 'HR',                     'code' => 'HR',  'color' => '#10B981', 'icon' => '👥'],
            ['name' => 'Contractors / Vendors',  'code' => 'CON', 'color' => '#F59E0B', 'icon' => '🔧'],
            ['name' => 'Statutory',              'code' => 'STA', 'color' => '#EF4444', 'icon' => '⚖️'],
            ['name' => 'Project',                'code' => 'PRJ', 'color' => '#06B6D4', 'icon' => '🚀'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['code' => $dept['code']], $dept);
        }
    }
}
