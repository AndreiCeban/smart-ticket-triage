<?php

declare(strict_types=1);

/**
 * TODO: Bulk Classify Tickets Command
 * 
 * Requirements from specification:
 * - Artisan command for bulk ticket classification
 * - Rate-limit calls per minute to prevent API quota exhaustion
 * - Process unclassified tickets in batches
 */

namespace App\Console\Commands;

use App\Jobs\ClassifyTicket;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BulkClassifyTickets extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tickets:bulk-classify
                            {--batch-size=10 : Number of tickets to process in each batch}
                            {--rate-limit=60 : Maximum API calls per minute}
                            {--delay=1 : Delay between batches in seconds}
                            {--force : Force classification of already classified tickets}
                            {--dry-run : Show what would be processed without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Bulk classify tickets with rate limiting to prevent API quota exhaustion';

    private const CACHE_KEY = 'bulk_classify_rate_limit';
    private int $rateLimitPerMinute;
    private int $batchSize;
    private int $delaySeconds;
    private bool $force;
    private bool $dryRun;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->initializeOptions();
        
        if ($this->dryRun) {
            return $this->performDryRun();
        }

        $tickets = $this->getTicketsToClassify();
        
        if ($tickets->isEmpty()) {
            $this->info('No tickets found that need classification.');
            return Command::SUCCESS;
        }

        $this->info("Found {$tickets->count()} tickets to classify.");
        $this->info("Rate limit: {$this->rateLimitPerMinute} calls/minute");
        $this->info("Batch size: {$this->batchSize}");
        $this->info("Delay between batches: {$this->delaySeconds} seconds");
        
        if (!$this->confirm('Do you want to proceed?')) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        return $this->processTickets($tickets);
    }

    private function initializeOptions(): void
    {
        $this->batchSize = max(1, (int) $this->option('batch-size'));
        $this->rateLimitPerMinute = max(1, (int) $this->option('rate-limit'));
        $this->delaySeconds = max(0, (int) $this->option('delay'));
        $this->force = (bool) $this->option('force');
        $this->dryRun = (bool) $this->option('dry-run');
    }

    private function getTicketsToClassify()
    {
        $query = Ticket::query();
        
        if (!$this->force) {
            // Only classify tickets that haven't been classified yet
            $query->whereNull('category')
                  ->orWhere('confidence', '<', 0.5); // Re-classify low confidence tickets
        }

        return $query->orderBy('created_at')
                    ->get();
    }

    private function performDryRun(): int
    {
        $tickets = $this->getTicketsToClassify();
        
        $this->info("DRY RUN MODE - No tickets will be actually classified");
        $this->info("Found {$tickets->count()} tickets that would be processed:");
        
        $batches = $tickets->chunk($this->batchSize);
        $totalBatches = $batches->count();
        $estimatedTime = (int) (($totalBatches * $this->delaySeconds) + 
                        ceil($tickets->count() / $this->rateLimitPerMinute * 60));
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total tickets', $tickets->count()],
                ['Batch size', $this->batchSize],
                ['Number of batches', $totalBatches],
                ['Rate limit', "{$this->rateLimitPerMinute}/minute"],
                ['Delay between batches', "{$this->delaySeconds}s"],
                ['Estimated time', $this->formatDuration($estimatedTime)],
            ]
        );

        // Show sample tickets
        $this->info("\nSample tickets to be classified:");
        $sampleTickets = $tickets->take(5);
        
        foreach ($sampleTickets as $ticket) {
            $this->line("- {$ticket->id}: " . substr($ticket->subject, 0, 50) . '...');
        }
        
        if ($tickets->count() > 5) {
            $this->line("... and " . ($tickets->count() - 5) . " more tickets");
        }

        return Command::SUCCESS;
    }

    private function processTickets($tickets): int
    {
        $progressBar = $this->output->createProgressBar($tickets->count());
        $progressBar->setFormat('verbose');
        $progressBar->start();

        $batches = $tickets->chunk($this->batchSize);
        $processed = 0;
        $errors = 0;

        foreach ($batches as $batchIndex => $batch) {
            if (!$this->checkRateLimit($batch->count())) {
                $this->newLine(2);
                $this->warn('Rate limit would be exceeded. Waiting...');
                $this->waitForRateLimit();
            }

            foreach ($batch as $ticket) {
                try {
                    $this->dispatchClassificationJob($ticket);
                    $this->incrementRateLimit();
                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Failed to dispatch classification job', [
                        'ticket_id' => $ticket->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                $progressBar->advance();
            }

            // Add delay between batches to prevent overwhelming the system
            if ($batchIndex < $batches->count() - 1 && $this->delaySeconds > 0) {
                sleep($this->delaySeconds);
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Bulk classification completed!");
        $this->info("Processed: {$processed} tickets");
        
        if ($errors > 0) {
            $this->warn("Errors: {$errors} tickets failed to queue");
        }

        return Command::SUCCESS;
    }

    private function checkRateLimit(int $requestCount): bool
    {
        $currentCount = Cache::get(self::CACHE_KEY, 0);
        return ($currentCount + $requestCount) <= $this->rateLimitPerMinute;
    }

    private function incrementRateLimit(): void
    {
        $currentCount = Cache::get(self::CACHE_KEY, 0);
        Cache::put(self::CACHE_KEY, $currentCount + 1, now()->addMinute());
    }

    private function waitForRateLimit(): void
    {
        $waitTime = 60; // Wait for the rate limit window to reset
        $this->info("Waiting {$waitTime} seconds for rate limit to reset...");
        
        $progressBar = $this->output->createProgressBar($waitTime);
        for ($i = 0; $i < $waitTime; $i++) {
            sleep(1);
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        // Clear the rate limit cache
        Cache::forget(self::CACHE_KEY);
    }

    private function dispatchClassificationJob(Ticket $ticket): void
    {
        ClassifyTicket::dispatch($ticket);
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($minutes < 60) {
            return $remainingSeconds > 0 ? "{$minutes}m {$remainingSeconds}s" : "{$minutes}m";
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return $remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}m" : "{$hours}h";
    }
}
