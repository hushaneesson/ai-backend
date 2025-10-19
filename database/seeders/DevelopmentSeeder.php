<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Squad;
use App\Models\Sprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'timezone' => 'America/New_York',
            'is_active' => true,
        ]);

        // Create Squad Lead
        $lead = User::create([
            'name' => 'Squad Lead',
            'email' => 'lead@example.com',
            'password' => Hash::make('password'),
            'role' => 'squad_lead',
            'timezone' => 'America/New_York',
            'is_active' => true,
        ]);

        // Create Team Members
        $member1 = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'role' => 'member',
            'timezone' => 'America/New_York',
            'is_active' => true,
        ]);

        $member2 = User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => Hash::make('password'),
            'role' => 'member',
            'timezone' => 'America/New_York',
            'is_active' => true,
        ]);

        // Create Development Squad
        $squad = Squad::create([
            'name' => 'Development Team Alpha',
            'description' => 'Main development squad working on core features',
            'timezone' => 'America/New_York',
            'workdays' => [1, 2, 3, 4, 5], // Monday to Friday
            'sprint_duration_days' => 14,
            'jira_board_id' => 'DEV',
            'project_key' => 'PROJ-001',
            'is_active' => true,
        ]);

        // Attach members to squad
        $squad->members()->attach($lead->id, [
            'role' => 'lead',
            'joined_at' => now()->subDays(90),
            'is_active' => true,
        ]);

        $squad->members()->attach($member1->id, [
            'role' => 'member',
            'joined_at' => now()->subDays(60),
            'is_active' => true,
        ]);

        $squad->members()->attach($member2->id, [
            'role' => 'member',
            'joined_at' => now()->subDays(30),
            'is_active' => true,
        ]);

        // Create Active Sprint
        Sprint::create([
            'squad_id' => $squad->id,
            'name' => 'Sprint 24',
            'description' => 'Q1 2025 Development Sprint',
            'start_date' => now()->startOfWeek(),
            'end_date' => now()->startOfWeek()->addDays(13),
            'status' => 'active',
            'goals' => [
                'Complete attendance tracking feature',
                'Implement leave management',
                'Bug fixes and improvements',
            ],
        ]);

        $this->command->info('Development data seeded successfully!');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Squad Lead: lead@example.com / password');
        $this->command->info('Members: john@example.com, jane@example.com / password');
    }
}
