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
            null => null,
        };
    }

    private function stripe(WebhookEntity $webhook): void
    {
        $this->webhookLogger->info('<not implemented yet> '.$webhook->getUuid());
    }

    private function github(WebhookEntity $webhook): void
    {
        /** @phpstan-ignore-next-line */
        $event = $webhook->getHeaders()['X-Github-Event'] ?? $webhook->getHeaders()['x-github-event'] ?? [];
        $event = json_encode($event);

        $this->webhookLogger->info('<event> '.$event);
    }
}
