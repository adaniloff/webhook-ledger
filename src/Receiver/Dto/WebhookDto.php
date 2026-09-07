<?php

namespace App\Receiver\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class WebhookDto
{
    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $external_event_id,
        #[Assert\NotBlank]
        public string $payload,
        public array $headers,
        public bool $signature_valid,
    ) {
    }
}
