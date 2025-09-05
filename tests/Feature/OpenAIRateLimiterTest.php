<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\OpenAIRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OpenAIRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear any existing rate limit data
        RateLimiter::clear('openai_classification');
    }

    public function test_can_check_if_request_is_allowed(): void
    {
        $rateLimiter = new OpenAIRateLimiter(2, 1); // 2 requests per minute
        
        $this->assertTrue($rateLimiter->canMakeRequest());
        
        // Make 2 requests
        $rateLimiter->attempt(fn() => true);
        $rateLimiter->attempt(fn() => true);
        
        $this->assertFalse($rateLimiter->canMakeRequest());
    }

    public function test_tracks_remaining_attempts_correctly(): void
    {
        $rateLimiter = new OpenAIRateLimiter(3, 1);
        
        $this->assertEquals(3, $rateLimiter->remainingAttempts());
        
        $rateLimiter->attempt(fn() => true);
        $this->assertEquals(2, $rateLimiter->remainingAttempts());
        
        $rateLimiter->attempt(fn() => true);
        $this->assertEquals(1, $rateLimiter->remainingAttempts());
        
        $rateLimiter->attempt(fn() => true);
        $this->assertEquals(0, $rateLimiter->remainingAttempts());
    }

    public function test_executes_callback_when_within_rate_limit(): void
    {
        $rateLimiter = new OpenAIRateLimiter(1, 1);
        $executed = false;
        
        $result = $rateLimiter->attempt(function () use (&$executed) {
            $executed = true;
            return 'success';
        });
        
        $this->assertEquals('success', $result);
        $this->assertTrue($executed);
    }

    public function test_returns_false_when_rate_limit_exceeded(): void
    {
        $rateLimiter = new OpenAIRateLimiter(1, 1);
        
        // First request should succeed
        $result1 = $rateLimiter->attempt(fn() => 'first');
        $this->assertEquals('first', $result1);
        
        // Second request should be rate limited
        $result2 = $rateLimiter->attempt(fn() => 'second');
        $this->assertFalse($result2);
    }

    public function test_provides_accurate_status_information(): void
    {
        $rateLimiter = new OpenAIRateLimiter(2, 1);
        
        $status = $rateLimiter->getStatus();
        $this->assertArrayHasKey('can_make_request', $status);
        $this->assertArrayHasKey('remaining_attempts', $status);
        $this->assertArrayHasKey('attempts_made', $status);
        $this->assertArrayHasKey('rate_limit', $status);
        $this->assertArrayHasKey('available_in_seconds', $status);
        $this->assertArrayHasKey('decay_minutes', $status);
        
        $this->assertTrue($status['can_make_request']);
        $this->assertEquals(2, $status['remaining_attempts']);
        $this->assertEquals(0, $status['attempts_made']);
        $this->assertEquals(2, $status['rate_limit']);
        $this->assertEquals(1, $status['decay_minutes']);
    }

    public function test_can_be_cleared(): void
    {
        $rateLimiter = new OpenAIRateLimiter(1, 1);
        
        // Exhaust the rate limit
        $rateLimiter->attempt(fn() => true);
        $this->assertFalse($rateLimiter->canMakeRequest());
        
        // Clear and check it's reset
        $rateLimiter->clear();
        $this->assertTrue($rateLimiter->canMakeRequest());
    }

    public function test_can_create_instances_with_custom_limits(): void
    {
        $conservative = OpenAIRateLimiter::conservative();
        $this->assertEquals(15, $conservative->getRateLimit());
        
        $aggressive = OpenAIRateLimiter::aggressive();
        $this->assertEquals(60, $aggressive->getRateLimit());
        
        $custom = OpenAIRateLimiter::withLimits(10, 2);
        $this->assertEquals(10, $custom->getRateLimit());
        $this->assertEquals(2, $custom->getDecayMinutes());
    }

    public function test_calculates_backoff_delay_correctly(): void
    {
        $rateLimiter = new OpenAIRateLimiter(1, 1);
        
        // No attempts yet, should be 0
        $this->assertEquals(0, $rateLimiter->getBackoffDelay());
        
        // After one attempt, should have some delay
        $rateLimiter->attempt(fn() => true);
        $delay = $rateLimiter->getBackoffDelay();
        $this->assertGreaterThan(0, $delay);
        $this->assertLessThanOrEqual(120, $delay); // Max 120 seconds (2 minutes)
    }

    public function test_determines_when_to_use_backoff(): void
    {
        $rateLimiter = new OpenAIRateLimiter(1, 1);
        
        $this->assertFalse($rateLimiter->shouldUseBackoff());
        
        $rateLimiter->attempt(fn() => true);
        $this->assertTrue($rateLimiter->shouldUseBackoff());
    }
}
