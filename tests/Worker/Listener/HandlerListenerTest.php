<?php

namespace App\Tests\Worker\Listener;

use App\Enum\StatusEnum;
use App\Tests\Factory\WebhookEntityFactory;
use App\Worker\Listener\HandlerListener;
use App\Worker\Message\ProcessWebhookEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class HandlerListenerTest extends KernelTestCase
{
    private HandlerListener $listener;

    public function setUp(): void
    {
        $this->listener = self::getContainer()->get(HandlerListener::class);
    }

    public function testOnDispatched(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne(['status' => StatusEnum::RECEIVED, 'attempts' => 0]);
        $event = new WorkerMessageReceivedEvent($this->envelope($webhook->getUuid()), 'async');

        // Act
        $this->listener->onDispatched($event);

        // Assert
        WebhookEntityFactory::assert()->exists([
            'uuid' => $webhook->getUuid(),
            'status' => StatusEnum::DISPATCHED,
            'attempts' => 1,
        ]);
    }

    public function testOnSucceeded(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne(['status' => StatusEnum::DISPATCHED, 'attempts' => 1]);
        $event = new WorkerMessageHandledEvent($this->envelope($webhook->getUuid()), 'async');

        // Act
        $this->listener->onSucceeded($event);

        // Assert
        WebhookEntityFactory::assert()->exists([
            'uuid' => $webhook->getUuid(),
            'status' => StatusEnum::SUCCEEDED,
            'attempts' => 1,
        ]);
    }

    public function testOnFailureWhenWillRetry(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne(['status' => StatusEnum::DISPATCHED, 'last_error' => null]);
        $event = new WorkerMessageFailedEvent(
            $this->envelope($webhook->getUuid()),
            'async',
            new \RuntimeException($expectedException = 'boom'),
        );
        $event->setForRetry();

        // Act
        $this->listener->onFailure($event);

        // Assert
        $entity = WebhookEntityFactory::find(['uuid' => $webhook->getUuid()]);
        $this->assertSame(StatusEnum::FAILED, $entity->getStatus());
        $this->assertStringContainsString($expectedException, (string) $entity->getLastError());
    }

    public function testOnFailureDoesNothingWhenWillNotRetry(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne(['status' => StatusEnum::DISPATCHED, 'last_error' => null]);
        $event = new WorkerMessageFailedEvent($this->envelope($webhook->getUuid()), 'async', new \RuntimeException());

        // Act
        $this->listener->onFailure($event);

        // Assert
        WebhookEntityFactory::assert()->exists([
            'uuid' => $webhook->getUuid(),
            'status' => StatusEnum::DISPATCHED,
            'last_error' => null,
        ]);
    }

    public function testOnDeadWhenWillRetry(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne(['status' => StatusEnum::DISPATCHED, 'last_error' => null]);
        $event = new WorkerMessageFailedEvent(
            $this->envelope($webhook->getUuid()),
            'async',
            new \RuntimeException($expectedException = 'dead'),
        );

        // Act
        $this->listener->onDead($event);

        // Assert
        $entity = WebhookEntityFactory::find(['uuid' => $webhook->getUuid()]);
        $this->assertSame(StatusEnum::DEAD, $entity->getStatus());
        $this->assertStringContainsString($expectedException, (string) $entity->getLastError());
    }

    public function testOnDeadDoesNothingWhenWillRetry(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne(['status' => StatusEnum::DISPATCHED, 'last_error' => null]);
        $event = new WorkerMessageFailedEvent($this->envelope($webhook->getUuid()), 'async', new \RuntimeException());
        $event->setForRetry();

        // Act
        $this->listener->onDead($event);

        // Assert
        WebhookEntityFactory::assert()->exists([
            'uuid' => $webhook->getUuid(),
            'status' => StatusEnum::DISPATCHED,
            'last_error' => null,
        ]);
    }

    private function envelope(string $uuid): Envelope
    {
        return new Envelope(new ProcessWebhookEvent(uuid: $uuid));
    }
}
