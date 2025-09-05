<?php

declare(strict_types=1);

/**
 * TODO: OpenAI API Rate Limiter Service
 * 
 * Requirements from specification:
 * - Rate-limit calls per minute to prevent API quota exhaustion
 * - Professional implementation using Laravel's rate limiting
 * - Configurable limits and backoff strategies
 * - Integration with OpenAI classification service
 */

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class OpenAIRateLimiter
{
    private const RATE_LIMIT_KEY = 'openai_classification';
    private const DEFAULT_RATE_LIMIT = 30; // requests per minute
    private const DEFAULT_DECAY_MINUTES = 1;
    private const BACKOFF_MULTIPLIER = 2;
    private const MAX_BACKOFF_MINUTES = 60;

    public function __construct(
        private int $rateLimit = self::DEFAULT_RATE_LIMIT,
        private int $decayMinutes = self::DEFAULT_DECAY_MINUTES
    ) {
        // Only use config values if not explicitly provided
        if ($rateLimit === self::DEFAULT_RATE_LIMIT) {
            $this->rateLimit = (int) config('openai.rate_limit', $this->rateLimit);
        }
        if ($decayMinutes === self::DEFAULT_DECAY_MINUTES) {
            $this->decayMinutes = (int) config('openai.decay_minutes', $this->decayMinutes);
        }
    }

    /**
     * Check if we can make an API call without exceeding rate limits
     */
    public function canMakeRequest(): bool
    {
        return !RateLimiter::tooManyAttempts(
            self::RATE_LIMIT_KEY,
            $this->rateLimit
        );
    }

    /**
     * Attempt to make an API call with rate limiting
     * Returns true if successful, false if rate limited
     */
    public function attempt(callable $callback): mixed
    {
        if (!$this->canMakeRequest()) {
            $this->logRateLimitHit();
            return false;
        }

        $result = RateLimiter::attempt(
            self::RATE_LIMIT_KEY,
            $this->rateLimit,
            $callback,
            $this->decayMinutes * 60 // Convert to seconds
        );

        if ($result === false) {
            $this->logRateLimitHit();
        }

        return $result;
    }

    /**
     * Get the number of remaining attempts
     */
    public function remainingAttempts(): int
    {
        return RateLimiter::remaining(
            self::RATE_LIMIT_KEY,
            $this->rateLimit
        );
    }

    /**
     * Get the number of attempts made
     */
    public function attempts(): int
    {
        return RateLimiter::attempts(self::RATE_LIMIT_KEY);
    }

    /**
     * Get the time until the rate limit resets (in seconds)
     */
    public function availableIn(): int
    {
        return RateLimiter::availableIn(self::RATE_LIMIT_KEY);
    }

    /**
     * Clear the rate limit (useful for testing or manual reset)
     */
    public function clear(): void
    {
        RateLimiter::clear(self::RATE_LIMIT_KEY);
    }

    /**
     * Get rate limit status information
     */
    public function getStatus(): array
    {
        return [
            'can_make_request' => $this->canMakeRequest(),
            'remaining_attempts' => $this->remainingAttempts(),
            'attempts_made' => $this->attempts(),
            'rate_limit' => $this->rateLimit,
            'available_in_seconds' => $this->availableIn(),
            'decay_minutes' => $this->decayMinutes,
        ];
    }

    /**
     * Calculate backoff delay for retries
     */
    public function getBackoffDelay(): int
    {
        $attempts = $this->attempts();
        
        // If no attempts made, no backoff needed
        if ($attempts === 0) {
            return 0;
        }
        
        $backoffMinutes = min(
            $this->decayMinutes * (self::BACKOFF_MULTIPLIER ** $attempts),
            self::MAX_BACKOFF_MINUTES
        );

        return $backoffMinutes * 60; // Convert to seconds
    }

    /**
     * Check if we should use exponential backoff
     */
    public function shouldUseBackoff(): bool
    {
        return $this->attempts() > 0 && !$this->canMakeRequest();
    }

    /**
     * Log when rate limit is hit
     */
    private function logRateLimitHit(): void
    {
        Log::warning('OpenAI API rate limit exceeded', [
            'rate_limit' => $this->rateLimit,
            'attempts_made' => $this->attempts(),
            'available_in_seconds' => $this->availableIn(),
            'remaining_attempts' => $this->remainingAttempts(),
        ]);
    }

    /**
     * Create a new instance with custom rate limits
     */
    public static function withLimits(int $rateLimit, int $decayMinutes = 1): self
    {
        return new self($rateLimit, $decayMinutes);
    }

    /**
     * Create a conservative rate limiter (lower limits)
     */
    public static function conservative(): self
    {
        return new self(15, 1); // 15 requests per minute
    }

    /**
     * Create an aggressive rate limiter (higher limits)
     */
    public static function aggressive(): self
    {
        return new self(60, 1); // 60 requests per minute
    }

    /**
     * Get the current rate limit
     */
    public function getRateLimit(): int
    {
        return $this->rateLimit;
    }

    /**
     * Get the current decay minutes
     */
    public function getDecayMinutes(): int
    {
        return $this->decayMinutes;
    }
}
