<?php

namespace App\Worker\Handler;

use App\Worker\Message\ProcessWebhookEvent;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class WebhookHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __invoke(ProcessWebhookEvent $message): void
    {
        $this->logger?->debug(sprintf('Handling message %s', $message->uuid));
    }
}
