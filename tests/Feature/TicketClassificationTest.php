<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ClassifyTicket;
use App\Models\Ticket;
use App\Services\TicketClassifier;
use App\Services\OpenAIRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_classification_preserves_manual_category(): void
    {
        // Create a ticket that has been manually categorized
        $ticket = Ticket::factory()->create([
            'category' => 'billing',
            'manually_categorized' => true,
        ]);

        $originalCategory = $ticket->category;

        // Mock the classifier to return a different category
        $classifier = $this->createMock(TicketClassifier::class);
        $classifier->method('classify')->willReturn([
            'category' => 'technical',
            'explanation' => 'Test explanation',
            'confidence' => 0.95,
        ]);

        $this->app->instance(TicketClassifier::class, $classifier);

        // Run the classification job
        $job = new ClassifyTicket($ticket);
        $job->handle($classifier);

        // Refresh the ticket from database
        $ticket->refresh();

        // Assert that category was NOT changed (manual classification preserved)
        $this->assertEquals($originalCategory, $ticket->category);
        $this->assertTrue($ticket->manually_categorized);

        // But explanation and confidence should be updated
        $this->assertEquals('Test explanation', $ticket->explanation);
        $this->assertEquals(0.95, $ticket->confidence);
    }

    public function test_classification_updates_auto_categorized_ticket(): void
    {
        // Create a ticket that has NOT been manually categorized
        $ticket = Ticket::factory()->create([
            'category' => null,
            'manually_categorized' => false,
        ]);

        // Mock the classifier
        $classifier = $this->createMock(TicketClassifier::class);
        $classifier->method('classify')->willReturn([
            'category' => 'technical',
            'explanation' => 'Test explanation',
            'confidence' => 0.95,
        ]);

        $this->app->instance(TicketClassifier::class, $classifier);

        // Run the classification job
        $job = new ClassifyTicket($ticket);
        $job->handle($classifier);

        // Refresh the ticket from database
        $ticket->refresh();

        // Assert that all fields were updated
        $this->assertEquals('technical', $ticket->category);
        $this->assertEquals('Test explanation', $ticket->explanation);
        $this->assertEquals(0.95, $ticket->confidence);
        $this->assertFalse($ticket->manually_categorized);
    }

    public function test_classify_endpoint_queues_job(): void
    {
        Queue::fake();

        $ticket = Ticket::factory()->create();

        $response = $this->postJson("/api/tickets/{$ticket->id}/classify");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Classification job queued successfully',
                'ticket_id' => $ticket->id,
            ]);

        Queue::assertPushed(ClassifyTicket::class, function ($job) use ($ticket) {
            return $job->ticket->id === $ticket->id;
        });
    }

    public function test_manual_category_update_sets_flag(): void
    {
        $ticket = Ticket::factory()->create([
            'category' => 'general',
            'manually_categorized' => false,
        ]);

        $response = $this->patchJson("/api/tickets/{$ticket->id}", [
            'category' => 'billing',
        ]);

        $response->assertStatus(200);

        $ticket->refresh();
        $this->assertEquals('billing', $ticket->category);
        $this->assertTrue($ticket->manually_categorized);
    }

    public function test_classification_respects_rate_limiting(): void
    {
        $ticket = Ticket::factory()->create([
            'category' => null,
            'manually_categorized' => false,
        ]);

        // Create a rate limiter with very low limit for testing
        $rateLimiter = new OpenAIRateLimiter(1, 1);
        $classifier = new TicketClassifier($rateLimiter);

        // First classification should work
        $result1 = $classifier->classify($ticket);
        $this->assertIsArray($result1);
        $this->assertArrayHasKey('category', $result1);

        // Second classification should be rate limited and return fallback
        $result2 = $classifier->classify($ticket);
        $this->assertIsArray($result2);
        $this->assertArrayHasKey('category', $result2);
        $this->assertEquals('Auto-classified (AI disabled)', $result2['explanation']);
    }

    public function test_rate_limiter_prevents_api_exhaustion(): void
    {
        $rateLimiter = new OpenAIRateLimiter(2, 1);

        // Should allow 2 requests
        $this->assertTrue($rateLimiter->canMakeRequest());
        $rateLimiter->attempt(fn() => true);
        
        $this->assertTrue($rateLimiter->canMakeRequest());
        $rateLimiter->attempt(fn() => true);

        // Third request should be blocked
        $this->assertFalse($rateLimiter->canMakeRequest());
        
        $result = $rateLimiter->attempt(fn() => true);
        $this->assertFalse($result);
    }
}