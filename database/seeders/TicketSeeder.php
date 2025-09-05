<?php

/**
 * TODO: Ticket Seeder
 * 
 * Requirements from specification:
 * - TicketSeeder inserts ≥ 25 tickets, some with notes, across mixed subjects/bodies
 * - Use Factory + Faker for realistic data generation
 */

namespace Database\Seeders;

use App\Models\Ticket;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        // Total 35
        
        // 15 regular tickets
        Ticket::factory(15)->create();

        // 8 tickets with notes
        Ticket::factory(8)->withNote()->create();

        // 12 classified tickets (some with notes)
        Ticket::factory(7)->classified()->create();
        Ticket::factory(5)->classified()->withNote()->create();

        $this->command->info('Created ' . Ticket::count() . ' tickets.');
    }
}
