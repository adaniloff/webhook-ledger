<?php

declare(strict_types=1);

namespace App\Webhook\Adapter;

use WebhookLedger\Domain\Contract\SourceAdapterInterface;

final readonly class GithubAdapter implements SourceAdapterInterface
{
    public const NAME = 'github';

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
        return ((string) ($headers['x-github-delivery'][0] ?? $headers['X-GitHub-Delivery'][0] ?? '')) ?: null;
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    public function verify(array $headers, string $raw): bool
    {
        $header = $headers['x-hub-signature-256'] ?? $headers['X-Hub-Signature-256'] ?? null;
        if (null === $header) {
            return false;
        }

        $hmac = hash_hmac('sha256', $raw, $this->secret);

        return hash_equals($header[0] ?? '', "sha256=$hmac");
    }
}
