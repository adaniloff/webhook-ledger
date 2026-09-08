<?php

namespace App\Worker\Listener;

use App\Repository\WebhookEntityRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

final readonly class HandlerListener
{
    public function __construct(private WebhookEntityRepository $repository)
    {
    }

    #[AsEventListener]
    public function onDispatched(WorkerMessageReceivedEvent $event): void
    {
        /* @phpstan-ignore-next-line */
        $this->repository->markDispatched(uuid: $event->getEnvelope()->getMessage()->uuid);
    }

    #[AsEventListener]
    public function onSucceeded(WorkerMessageHandledEvent $event): void
    {
        /* @phpstan-ignore-next-line */
        $this->repository->markSucceeded(uuid: $event->getEnvelope()->getMessage()->uuid);
    }

    #[AsEventListener]
    public function onFailure(WorkerMessageFailedEvent $event): void
    {
        if (!$event->willRetry()) {
            return;
        }
        $this->repository->markFailed(
            /* @phpstan-ignore-next-line */
            uuid: $event->getEnvelope()->getMessage()->uuid,
            error: $event->getThrowable()->__toString(),
        );
    }

    #[AsEventListener]
    public function onDead(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }
        $this->repository->markDead(
            /* @phpstan-ignore-next-line */
            uuid: $event->getEnvelope()->getMessage()->uuid,
            error: $event->getThrowable()->__toString(),
        );
    }
}
