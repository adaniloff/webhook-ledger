<?php

namespace App\Tests\Receiver\Service;

use App\Enum\SourceEnum;
use App\Enum\StatusEnum;
use App\Receiver\Dto\WebhookDto;
use App\Receiver\Exception\WebhookEntryDuplicationException;
use App\Receiver\Exception\WebhookNotFoundException;
use App\Receiver\Exception\WebhookNotReplayableException;
use App\Receiver\Exception\WebhookOutdatedException;
use App\Receiver\Service\Receiver;
use App\Tests\Factory\WebhookEntityFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ReceiverTest extends KernelTestCase
{
    public function testHappyCapturePath(): void
    {
        // Arrange
        $container = self::getContainer();
        WebhookEntityFactory::assert()->count(0);

        // Act
        $uuid = $container->get(Receiver::class)->capture(SourceEnum::GITHUB, new WebhookDto(
            external_event_id: 'some-id',
            payload: '{"some-payload": false}',
            headers: [],
            signature_valid: true,
        ), payloadValid: true);

        // Assert
        WebhookEntityFactory::assert()
                ->count(1)
                ->exists(['uuid' => $uuid]);
        $this->assertSame(1, $this->countMessengerMessages());
    }

    public function testCaptureWithInvalidPayloadDoesNotDispatch(): void
    {
        // Arrange
        $container = self::getContainer();
        WebhookEntityFactory::assert()->count(0);

        // Act
        $uuid = $container->get(Receiver::class)->capture(SourceEnum::GITHUB, new WebhookDto(
            external_event_id: 'some-id',
            payload: '{"some-payload": false}',
            headers: [],
            signature_valid: true,
        ), payloadValid: false);

        // Assert
        WebhookEntityFactory::assert()
                ->count(1)
                ->exists(['uuid' => $uuid]);
        $this->assertSame(0, $this->countMessengerMessages());
    }

    public function testBrokenCapturePathAtomicity(): void
    {
        // Arrange
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException());

        $container = self::getContainer();
        $container->set(MessageBusInterface::class, $bus);

        WebhookEntityFactory::assert()->count(0);

        // Act
        try {
            $container->get(Receiver::class)->capture(SourceEnum::GITHUB, new WebhookDto(
                external_event_id: 'some-id',
                payload: '{"some-payload": false}',
                headers: [],
                signature_valid: true,
            ), payloadValid: true);
        } catch (\Throwable) {
            // Assert
            WebhookEntityFactory::assert()->count(0);

            return;
        }

        $this->fail('An exception should have been thrown.');
    }

    public function testCaptureOnDuplicateDoesNotDispatch(): void
    {
        // Arrange
        WebhookEntityFactory::assert()->count(0);
        $container = self::getContainer();
        $uuid = $container->get(Receiver::class)->capture(SourceEnum::GITHUB, $dto = new WebhookDto(
            external_event_id: 'some-id',
            payload: '{"some-payload": false}',
            headers: [],
            signature_valid: true,
        ), payloadValid: true);

        // Act
        try {
            $container->get(Receiver::class)->capture(SourceEnum::GITHUB, $dto, payloadValid: true);
        } catch (WebhookEntryDuplicationException $e) {
            // Assert
            $this->assertEquals($uuid, $e->getIdentifier());
            WebhookEntityFactory::assert()
                ->count(1)
                ->exists(['uuid' => $uuid]);
            $this->assertSame(1, $this->countMessengerMessages());

            return;
        }

        $this->fail('A WebhookEntryDuplicationException should have been thrown.');
    }

    public function testHappyReplayPath(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne([
            'status' => StatusEnum::DEAD,
            'signature_valid' => true,
            'version' => 1,
        ]);

        // Act
        self::getContainer()->get(Receiver::class)->replay(uuid: $webhook->getUuid(), version: 1);

        // Assert
        WebhookEntityFactory::assert()->exists([
            'uuid' => $webhook->getUuid(),
            'status' => StatusEnum::RECEIVED,
            'version' => 2,
        ]);
        $this->assertSame(1, $this->countMessengerMessages());
    }

    public function testReplayThrowsNotFound(): void
    {
        $this->expectException(WebhookNotFoundException::class);
        self::getContainer()->get(Receiver::class)->replay(uuid: (string) Uuid::v7(), version: 1);
    }

    public function testReplayThrowsNotReplayableWhenStatusIsInvalid(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne([
            'status' => StatusEnum::FAILED,
            'signature_valid' => true,
            'version' => 1,
        ]);

        // Assert
        $this->expectException(WebhookNotReplayableException::class);

        // Act
        try {
            self::getContainer()->get(Receiver::class)->replay(uuid: $webhook->getUuid(), version: 1);
        } finally {
            WebhookEntityFactory::assert()->exists([
                'uuid' => $webhook->getUuid(),
                'status' => StatusEnum::FAILED,
                'version' => 1,
            ]);
            $this->assertSame(0, $this->countMessengerMessages());
        }
    }

    public function testReplayThrowsNotReplayableWhenSignatureInvalid(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne([
            'status' => StatusEnum::DEAD,
            'signature_valid' => false,
            'version' => 1,
        ]);

        // Assert
        $this->expectException(WebhookNotReplayableException::class);

        // Act
        try {
            self::getContainer()->get(Receiver::class)->replay(uuid: $webhook->getUuid(), version: 1);
        } finally {
            WebhookEntityFactory::assert()->exists([
                'uuid' => $webhook->getUuid(),
                'status' => StatusEnum::DEAD,
                'version' => 1,
            ]);
            $this->assertSame(0, $this->countMessengerMessages());
        }
    }

    public function testReplayThrowsOutdatedOnStaleVersion(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne([
            'status' => StatusEnum::DEAD,
            'signature_valid' => true,
            'version' => 1,
        ])->_disableAutoRefresh(); // Doctrine/Foundry incompatible optimistic-lock failure issue

        // Assert
        $this->expectException(WebhookOutdatedException::class);

        // Act
        try {
            self::getContainer()->get(Receiver::class)->replay(uuid: $webhook->getUuid(), version: 999);
        } finally {
            WebhookEntityFactory::assert()->exists([
                'uuid' => $webhook->getUuid(),
                'status' => StatusEnum::DEAD,
                'version' => 1,
            ]);
            $this->assertSame(0, $this->countMessengerMessages());
        }
    }

    private function countMessengerMessages(): int
    {
        return (int) self::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM messenger_messages');
    }
}
