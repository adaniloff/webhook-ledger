<?php

declare(strict_types=1);

namespace App\Webhook\Adapter;

use WebhookLedger\Domain\Contract\SourceAdapterInterface;

final readonly class StripeAdapter implements SourceAdapterInterface
{
    public const NAME = 'stripe';

    public function __construct(private string $secret)
    {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    public function externalEventId(array $headers, string $raw): ?string
    {
        return is_array($decoded = json_decode($raw, true)) && is_string($decoded['id'] ?? null)
            ? $decoded['id']
            : null;
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    public function verify(array $headers, string $raw): bool
    {
        $header = $headers['stripe-signature'][0] ?? $headers['Stripe-Signature'][0] ?? null;
        if (null === $header) {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            if ('t' === $key) {
                $timestamp = $value;
            } elseif ('v1' === $key && null !== $value) {
                $signatures[] = $value;
            }
        }

        if (null === $timestamp || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $hmac = hash_hmac('sha256', $timestamp.'.'.$raw, $this->secret);

        foreach ($signatures as $signature) {
            if (hash_equals($signature, $hmac)) {
                return true;
            }
        }

        return false;
    }
}
