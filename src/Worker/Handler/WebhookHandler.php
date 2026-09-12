<?php

namespace App\Worker\Handler;

use App\Entity\WebhookEntity;
use App\Enum\SourceEnum;
use App\Repository\WebhookEntityRepository;
use App\Worker\Message\ProcessWebhookEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class WebhookHandler
{
    public function __construct(
        private LoggerInterface $webhookLogger,
        private WebhookEntityRepository $repository,
    ) {
    }

    public function __invoke(ProcessWebhookEvent $message): void
    {
        $this->webhookLogger->debug(sprintf('Handling message %s', $message->uuid));

        $webhook = $this->repository->findOneBy(['uuid' => $message->uuid]);
        match ($webhook?->getSource()) {
            SourceEnum::STRIPE => $this->stripe(webhook: $webhook),
            SourceEnum::GITHUB => $this->github(webhook: $webhook),
            null => $this->webhookLogger->warning(sprintf('Entity not found for uuid: %s', $message->uuid)),
        };
    }

    private function stripe(WebhookEntity $webhook): void
    {
        $payload = json_decode($webhook->getPayload() ?? '', true);
        $type = is_array($payload) && is_string($payload['type'] ?? null) ? $payload['type'] : null;

        if ('payment_intent.succeeded' !== $type) {
            $this->webhookLogger->debug('<stripe event ignored> '.$webhook->getUuid().' ('.($type ?? 'unknown').')');

            return;
        }

        $this->webhookLogger->info('<payment_intent.succeeded> '.$webhook->getUuid());
    }

    private function github(WebhookEntity $webhook): void
    {
        /** @phpstan-ignore-next-line */
        $event = $webhook->getHeaders()['X-Github-Event'] ?? $webhook->getHeaders()['x-github-event'] ?? [];
        $event = json_encode($event);

        $this->webhookLogger->info('<event> '.$event);
    }
}
