<?php

namespace App\Tests\Receiver\Service;

use App\Enum\SourceEnum;
use App\Receiver\Dto\WebhookDto;
use App\Receiver\Exception\WebhookEntryDuplicationException;
use App\Receiver\Service\Receiver;
use App\Tests\Factory\WebhookEntityFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class ReceiverTest extends KernelTestCase
{
    // context: it was working 1 or 2 days before
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
        ));

        // Assert
        WebhookEntityFactory::assert()
                ->count(1)
                ->exists(['uuid' => $uuid]);
        $this->assertSame(1, $this->countMessengerMessages());
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
            ));
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
        ));

        // Act
        try {
            $container->get(Receiver::class)->capture(SourceEnum::GITHUB, $dto);
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

    private function countMessengerMessages(): int
    {
        return (int) self::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM messenger_messages');
    }
}
