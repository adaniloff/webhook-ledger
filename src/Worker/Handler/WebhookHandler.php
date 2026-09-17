<?php

declare(strict_types=1);

namespace App\Worker\Handler;

use App\Webhook\Adapter\GithubAdapter;
use App\Webhook\Adapter\StripeAdapter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use WebhookLedger\Application\Worker\Message\ProcessWebhookEvent;
use WebhookLedger\Domain\Contract\WebhookEntryInterface;
use WebhookLedger\Domain\Contract\WebhookLedgerProjectionInterface;

#[AsMessageHandler]
final class WebhookHandler
{
    public function __construct(
        private LoggerInterface $webhookLogger,
        private WebhookLedgerProjectionInterface $repository,
    ) {
    }

    public function __invoke(ProcessWebhookEvent $message): void
    {
        $this->webhookLogger->debug(sprintf('Handling message %s', $message->uuid));

        $webhook = $this->repository->findOneBy(['uuid' => $message->uuid]);
        match ($webhook?->getSource()) {
            StripeAdapter::NAME => $this->stripe(webhook: $webhook),
            GithubAdapter::NAME => $this->github(webhook: $webhook),
            null => $this->webhookLogger->warning(sprintf('Entity not found for uuid: %s', $message->uuid)),
            default => $this->webhookLogger->warning(sprintf('Unknown source for uuid: %s', $message->uuid)),
        };
    }

    private function stripe(WebhookEntryInterface $webhook): void
    {
        $payload = json_decode($webhook->getPayload(), true);
        $type = is_array($payload) && is_string($payload['type'] ?? null) ? $payload['type'] : null;

        if ('payment_intent.succeeded' !== $type) {
            $this->webhookLogger->debug('<stripe event ignored> '.$webhook->getUuid().' ('.($type ?? 'unknown').')');

            return;
        }

        $this->webhookLogger->info('<payment_intent.succeeded> '.$webhook->getUuid());
    }

    private function github(WebhookEntryInterface $webhook): void
    {
        /** @phpstan-ignore-next-line */
        $event = $webhook->getHeaders()['X-Github-Event'] ?? $webhook->getHeaders()['x-github-event'] ?? [];
        $event = json_encode($event);

        $this->webhookLogger->info('<event> '.$event);
    }
}
