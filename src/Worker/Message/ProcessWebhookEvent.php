<?php

namespace App\Worker\Message;

final readonly class ProcessWebhookEvent
{
    public function __construct(public string $uuid)
    {
    }
}
