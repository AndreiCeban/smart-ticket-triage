<?php

declare(strict_types=1);

/**
 * TODO: AI Ticket Classification Service
 * 
 * Requirements from specification:
 * - Use openai-php/laravel inside App\Services\TicketClassifier
 * - System prompt must ask for JSON with keys category, explanation, confidence
 * - Store all three values
 * - If OPENAI_CLASSIFY_ENABLED=false, return random category & dummy explanation/confidence
 */

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class TicketClassifier
{
    public function classify(Ticket $ticket): array
    {
        if (!config('app.openai_classify_enabled', env('OPENAI_CLASSIFY_ENABLED', true))) {
            return $this->getFakeClassification();
        }

        try {
            $response = OpenAI::chat()->create([
                'model' => config('openai.model', 'gpt-3.5-turbo'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => "Subject: {$ticket->subject}\n\nBody: {$ticket->body}",
                    ],
                ],
                'max_tokens' => 200,
                'temperature' => 0.1,
            ]);

            $content = $response->choices[0]->message->content ?? '';
            
            if (empty($content)) {
                throw new \Exception('Empty response from OpenAI');
            }

            $result = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from OpenAI: ' . json_last_error_msg());
            }

            if (!$this->isValidClassification($result)) {
                throw new \Exception('Classification result missing required fields');
            }

            return $this->normalizeClassification($result);
        } catch (\Exception $e) {
            Log::error('OpenAI classification failed', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getFakeClassification();
        }
    }

    private function getSystemPrompt(): string
    {
        $categories = collect(Ticket::CATEGORIES)->map(fn($label, $key) => "- {$key}: {$label}")->implode("\n");

        return "You are a support ticket classifier. Analyze the given ticket and respond with a JSON object containing exactly these keys:

- category: one of these categories: {$categories}
- explanation: a brief explanation (max 100 chars) of why you chose this category
- confidence: a decimal between 0.0 and 1.0 representing your confidence in the classification

Example response:
{\"category\": \"technical\", \"explanation\": \"User reporting login issues with specific error message\", \"confidence\": 0.85}

Respond only with valid JSON, no additional text.";
    }

    private function isValidClassification(array $result): bool
    {
        return isset($result['category'], $result['explanation'], $result['confidence']);
    }

    private function normalizeClassification(array $result): array
    {
        if (!array_key_exists($result['category'], Ticket::CATEGORIES)) {
            $result['category'] = 'general';
        }

        // Ensure confidence is between 0 and 1
        $result['confidence'] = max(0, min(1, (float) $result['confidence']));

        $result['explanation'] = substr(trim($result['explanation']), 0, 100);

        return $result;
    }

    private function getFakeClassification(): array
    {
        $categories = array_keys(Ticket::CATEGORIES);
        $category = $categories[array_rand($categories)];

        return [
            'category' => $category,
            'explanation' => 'Auto-classified (AI disabled)',
            'confidence' => round(mt_rand(60, 95) / 100, 2),
        ];
    }
}
