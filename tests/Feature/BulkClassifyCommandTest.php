<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ClassifyTicket;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BulkClassifyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_shows_tickets_without_processing(): void
    {
        Ticket::factory(5)->create(['category' => null]);

        $this->artisan('tickets:bulk-classify --dry-run')
            ->expectsOutput('DRY RUN MODE - No tickets will be actually classified')
            ->expectsOutput('Found 5 tickets that would be processed:')
            ->assertSuccessful();
    }

    public function test_bulk_classify_processes_unclassified_tickets(): void
    {
        Queue::fake();
        
        // Create tickets that need classification
        Ticket::factory(3)->create(['category' => null]);
        
        // Create tickets that are already classified
        Ticket::factory(2)->create(['category' => 'technical']);

        $this->artisan('tickets:bulk-classify')
            ->expectsOutput('Found 3 tickets to classify.')
            ->expectsQuestion('Do you want to proceed?', true)
            ->assertSuccessful();

        Queue::assertPushed(ClassifyTicket::class, 3);
    }

    public function test_force_option_processes_all_tickets(): void
    {
        Queue::fake();
        
        Ticket::factory(5)->create(['category' => 'technical']);

        $this->artisan('tickets:bulk-classify --force')
            ->expectsOutput('Found 5 tickets to classify.')
            ->expectsQuestion('Do you want to proceed?', true)
            ->assertSuccessful();

        Queue::assertPushed(ClassifyTicket::class, 5);
    }

    public function test_batch_size_option_works(): void
    {
        Queue::fake();
        
        Ticket::factory(15)->create(['category' => null]);

        $this->artisan('tickets:bulk-classify --batch-size=5')
            ->expectsOutput('Batch size: 5')
            ->expectsQuestion('Do you want to proceed?', true)
            ->assertSuccessful();

        Queue::assertPushed(ClassifyTicket::class, 15);
    }

    public function test_no_tickets_to_classify_message(): void
    {
        // All tickets are already classified
        Ticket::factory(3)->create(['category' => 'technical']);

        $this->artisan('tickets:bulk-classify')
            ->expectsOutput('No tickets found that need classification.')
            ->assertSuccessful();
    }

    public function test_rate_limit_configuration(): void
    {
        Ticket::factory(5)->create(['category' => null]);

        $this->artisan('tickets:bulk-classify --rate-limit=30 --dry-run')
            ->expectsOutputToContain('30/minute')
            ->assertSuccessful();
    }
}