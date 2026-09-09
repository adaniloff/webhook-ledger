<?php

namespace App\Receiver\Exception;

use Symfony\Component\Uid\Uuid;

final class WebhookOutdatedException extends \RuntimeException
{
    public function __construct(
        private Uuid|string $uuid,
        private int $outdatedVersion,
        \Throwable $previous,
    ) {
        parent::__construct(previous: $previous);
    }

    public function getIdentifier(): string
    {
        return (string) $this->uuid;
    }

    public function getOutdatedVersion(): string
    {
        return (string) $this->outdatedVersion;
    }
}
