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
            self::STRIPE => is_array($decoded = json_decode($raw, true)) && is_string($decoded['id'] ?? null)
                ? $decoded['id']
                : null,
            self::GITHUB => ((string) ($headers['x-github-delivery'][0] ?? $headers['X-GitHub-Delivery'][0] ?? '')) ?: null,
        };
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    public function checkSignature(array $headers, string $hmac): bool
    {
        return match ($this) {
            self::STRIPE => $this->checkStripe(headers: $headers, hmac: $hmac),
            self::GITHUB => $this->checkGithub(headers: $headers, hmac: $hmac),
        };
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    public function parseHmac(array $headers, string $raw, string $secret): string
    {
        $data = '0';

        if (self::GITHUB === $this) {
            $data = $raw;
        }

        if (self::STRIPE === $this) {
            $header = $headers['stripe-signature'][0] ?? $headers['Stripe-Signature'][0] ?? '';

            foreach (explode(',', $header) as $part) {
                if (str_starts_with($part, 't=')) {
                    $data = substr($part, 2).'.'.$raw;
                }
            }
        }

        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    private function checkStripe(array $headers, string $hmac): bool
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

        foreach ($signatures as $signature) {
            if (hash_equals($signature, $hmac)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    private function checkGithub(array $headers, string $hmac): bool
    {
        return ($h = $headers['x-hub-signature-256'] ?? $headers['X-Hub-Signature-256'] ?? null)
                        && hash_equals($h[0] ?? '', "sha256=$hmac");
    }
}
