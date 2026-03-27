<?php

namespace Romansh\LaravelCreemAgent\Agent;

use Illuminate\Support\Str;
use RuntimeException;

class LlmCommandParser implements ParsesAgentMessages
{
    private const ALLOWED_INTENTS = [
        'switch_store',
        'query_subscriptions',
        'query_customers',
        'query_transactions',
        'query_products',
        'status',
        'run_heartbeat',
        'cancel_subscription',
        'create_checkout',
        'help',
        'unknown',
    ];

    public function parse(string $message): array
    {
        $responseText = IntentClassifierAgent::make()
            ->prompt($message)
            ->text;

        $decoded = json_decode($this->extractJson($responseText), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('LLM parser returned invalid JSON.');
        }

        return $this->normalize($decoded, $message);
    }

    private function extractJson(string $text): string
    {
        $trimmed = trim($text);

        if (Str::startsWith($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }

    private function normalize(array $decoded, string $message): array
    {
        $intent = strtolower((string) ($decoded['intent'] ?? 'unknown'));

        if (! in_array($intent, self::ALLOWED_INTENTS, true)) {
            $intent = 'unknown';
        }

        $normalized = [
            'intent' => $intent,
            'store' => $this->nullableString($decoded['store'] ?? null),
            'status' => $this->normalizeStatus($decoded['status'] ?? null),
            'id' => $this->nullableString($decoded['id'] ?? null),
            'product_id' => $this->nullableString($decoded['product_id'] ?? null),
            'message' => trim($message),
        ];

        if ($intent === 'cancel_subscription' && ! Str::startsWith((string) $normalized['id'], 'sub_')) {
            $normalized['intent'] = 'unknown';
        }

        if ($intent === 'create_checkout' && ! Str::startsWith((string) $normalized['product_id'], 'prod_')) {
            $normalized['intent'] = 'unknown';
        }

        if ($intent === 'switch_store' && empty($normalized['store'])) {
            $normalized['intent'] = 'unknown';
        }

        return $normalized;
    }

    private function normalizeStatus(mixed $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        return str_replace([' ', '-'], '_', strtolower(trim((string) $status)));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' || strtolower($string) === 'null' ? null : $string;
    }
}