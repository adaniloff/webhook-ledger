<?php

namespace App\Enum;

enum SourceEnum: string
{
    case STRIPE = 'stripe';
    case GITHUB = 'github';

    /**
     * @param array<string, list<string|null>> $headers
     */
    public function externalEventId(array $headers, string $raw): ?string
    {
        return match ($this) {
            // self::STRIPE => null, // (($decoded = ((array) json_decode($raw)) ?? [])['id']) ?: null,
            self::GITHUB => ((string) ($headers['x-github-delivery'][0] ?? $headers['X-GitHub-Delivery'][0] ?? '')) ?: null,
            default => null,
        };
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    public function isSafe(array $headers, string $hmac): bool
    {
        return match ($this) {
            // self::STRIPE => false, // not implemented
            self::GITHUB => ($h = $headers['x-hub-signature-256'] ?? $headers['X-Hub-Signature-256'] ?? null)
                && hash_equals($h[0] ?? '', "sha256=$hmac"),
            default => false,
        };
    }
}
