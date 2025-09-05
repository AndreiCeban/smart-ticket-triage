<?php

declare(strict_types=1);

/**
 * TODO: Queued Ticket Classification Job
 * 
 * Requirements from specification:
 * - Queued Job (ClassifyTicket) calls TicketClassifier
 * - If a user has already changed category, keep the manual value but still update explanation & confidence
 */

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\TicketClassifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClassifyTicket implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket
    ) {}

    public function handle(TicketClassifier $classifier): void
    {
        $result = $classifier->classify($this->ticket);

        $updateData = [
            'explanation' => $result['explanation'],
            'confidence' => $result['confidence'],
        ];

        if (!$this->ticket->manually_categorized) {
            $updateData['category'] = $result['category'];
        }

        $this->ticket->update($updateData);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Ticket classification job failed', [
            'ticket_id' => $this->ticket->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    /**
     * The maximum number of attempts for this job.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;
}
