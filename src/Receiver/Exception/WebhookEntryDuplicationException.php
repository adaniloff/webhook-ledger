<?php

namespace App\Receiver\Exception;

use Symfony\Component\Uid\Uuid;

final class WebhookEntryDuplicationException extends \RuntimeException
{
    public function __construct(private Uuid|string $uuid, \Throwable $previous)
    {
        parent::__construct(previous: $previous);
    }

    public function getIdentifier(): string
    {
        return (string) $this->uuid;
    }
}
