<?php

namespace App\Services\Ai;

/**
 * AI assistant for answering payee questions and explaining commission results.
 *
 * This is a wired stub: with no API key configured it returns a clear
 * "not configured" message; when `services.ai.key` is set, a driver
 * (e.g. Anthropic) can be slotted in here without touching callers. Kept behind
 * an interface so the rest of the app depends on the capability, not a vendor.
 */
class AiAssistant
{
    public function isConfigured(): bool
    {
        return ! empty(config('services.ai.key'));
    }

    /**
     * Answer a payee/admin question. Returns a stub notice until a provider key
     * is configured (see docs/INTEGRATIONS.md).
     */
    public function answer(string $question, array $context = []): string
    {
        if (! $this->isConfigured()) {
            return 'AI assistant is not configured. Add an API key (services.ai.key) to enable answering payee questions.';
        }

        // A provider call (e.g. Anthropic Messages API) would go here, grounded
        // in $context (the payee's released credits/rewards + calc logs).
        return 'AI provider configured — implement the driver call in AiAssistant::answer().';
    }
}
