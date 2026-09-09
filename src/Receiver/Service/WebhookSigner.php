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
        $hmac = $this->hash(raw: $raw, headers: $headers, source: $source);

        return $source->checkSignature(headers: $headers, hmac: $hmac);
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    public function hash(string $raw, array $headers, SourceEnum $source): string
    {
        $secret = match ($source) {
            SourceEnum::STRIPE => $this->stripe,
            SourceEnum::GITHUB => $this->github,
        };

        return $source->parseHmac(raw: $raw, headers: $headers, secret: $secret);
    }
}
