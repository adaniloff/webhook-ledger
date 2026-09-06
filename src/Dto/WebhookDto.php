<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class WebhookDto
{
    public function __construct(
        #[Assert\NotBlank]
        public string $external_event_id,
    ) {
    }
}
