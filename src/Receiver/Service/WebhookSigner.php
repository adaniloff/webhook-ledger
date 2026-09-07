<?php

namespace App\Receiver\Service;

use App\Enum\SourceEnum;

final readonly class WebhookSigner
{
    public function __construct(private string $stripe, private string $github)
    {
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    public function verify(SourceEnum $source, array $headers, string $raw): bool
    {
        $hmac = $this->hash(raw: $raw, source: $source);

        return $source->isSafe(headers: $headers, hmac: $hmac);
    }

    public function hash(string $raw, SourceEnum $source): string
    {
        $secret = match ($source) {
            SourceEnum::STRIPE => $this->stripe,
            SourceEnum::GITHUB => $this->github,
        };

        return hash_hmac('sha256', $raw, $secret);
    }
}
