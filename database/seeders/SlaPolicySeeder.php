<?php

namespace Database\Seeders;

use App\Models\SlaPolicy;
use Illuminate\Database\Seeder;

class SlaPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            ['policy_name' => 'Critical Response', 'priority' => 'Critical', 'response_time_minutes' => 60, 'resolution_time_minutes' => 240, 'warning_threshold_percent' => 80, 'status' => 'active'],
            ['policy_name' => 'High Priority', 'priority' => 'High', 'response_time_minutes' => 120, 'resolution_time_minutes' => 480, 'warning_threshold_percent' => 80, 'status' => 'active'],
            ['policy_name' => 'Medium Standard', 'priority' => 'Medium', 'response_time_minutes' => 480, 'resolution_time_minutes' => 2880, 'warning_threshold_percent' => 75, 'status' => 'active'],
            ['policy_name' => 'Low Priority', 'priority' => 'Low', 'response_time_minutes' => 1440, 'resolution_time_minutes' => 7200, 'warning_threshold_percent' => 70, 'status' => 'active'],
        ];

        foreach ($policies as $policy) {
            SlaPolicy::firstOrCreate(['policy_name' => $policy['policy_name']], $policy);
        }
    }
}
