<?php

namespace App\Tests\Repository;

use App\Entity\WebhookEntity;
use App\Enum\SourceEnum;
use App\Enum\StatusEnum;
use App\Receiver\Dto\WebhookDto;
use App\Receiver\Exception\WebhookEntryDuplicationException;
use App\Receiver\Exception\WebhookOutdatedException;
use App\Repository\WebhookEntityRepository;
use App\Tests\Factory\WebhookEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class WebhookEntityRepositoryTest extends KernelTestCase
{
    private WebhookEntityRepository $repository;

    public function setUp(): void
    {
        $this->repository = static::getContainer()->get(WebhookEntityRepository::class);
    }

    public function testReceiveOnceSucceed(): void
    {
        // Arrange
        $dto = new WebhookDto(
            external_event_id: 'some-external-id',
            payload: '{"id":"some-external-id"}',
            headers: ['content-type' => 'application/json'],
            signature_valid: true,
        );

        // Act
        WebhookEntityFactory::assert()->empty();
        $this->repository->receive(SourceEnum::STRIPE, $dto);

        // Assert
        WebhookEntityFactory::assert()
            ->count(1)
            ->exists(criteria: [
                'external_event_id' => 'some-external-id',
                'source' => SourceEnum::STRIPE,
                'status' => StatusEnum::RECEIVED,
                'attempts' => 0,
                'version' => 1,
                'signature_valid' => true,
                'payload' => '{"id":"some-external-id"}',
            ]);

        $event = $this->repository->findOneBy(['external_event_id' => 'some-external-id']);
        $this->assertSame(['content-type' => 'application/json'], $event->getHeaders());
    }

    public function testReceiveAlreadyExistingThrowsDuplicationException(): void
    {
        // Arrange
        $dto = new WebhookDto(
            external_event_id: 'some-external-id',
            payload: '{"id":"some-external-id"}',
            headers: ['content-type' => 'application/json'],
            signature_valid: true,
        );

        WebhookEntityFactory::assert()->empty();
        $uuid = $this->repository->receive(SourceEnum::STRIPE, $dto);
        WebhookEntityFactory::assert()->count(1);

        try {
            // Act
            $this->repository->receive(SourceEnum::STRIPE, $dto);
        } catch (WebhookEntryDuplicationException $e) {
            // Assert
            $this->assertSame((string) $uuid, $e->getIdentifier());
            WebhookEntityFactory::assert()->count(1);

            return;
        }

        $this->fail('Expected WebhookEntryDuplicationException to be thrown.');
    }

    public function testMarkDispatched(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne([
            'status' => StatusEnum::RECEIVED,
            'attempts' => $attempts = random_int(0, 15),
        ]);

        // Act
        $this->repository->markDispatched(uuid: $webhook->getUuid());

        // Assert
        WebhookEntityFactory::assert()->exists([
            'uuid' => $webhook->getUuid(),
            'status' => StatusEnum::DISPATCHED,
            'attempts' => ++$attempts,
        ]);
    }

    public function testMarkSucceeded(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne([
            'status' => StatusEnum::RECEIVED,
            'attempts' => $attempts = random_int(0, 15),
        ]);

        // Act
        $this->repository->markSucceeded(uuid: $webhook->getUuid());

        // Assert
        WebhookEntityFactory::assert()->exists([
            'uuid' => $webhook->getUuid(),
            'status' => StatusEnum::SUCCEEDED,
            'attempts' => $attempts,
        ]);
    }

    public function testMarkFailed(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne(['status' => StatusEnum::DISPATCHED, 'last_error' => null]);

        // Act
        $this->repository->markFailed(uuid: $webhook->getUuid(), error: $errorMessage = 'boom');

        // Assert
        WebhookEntityFactory::assert()->exists([
            'uuid' => $webhook->getUuid(),
            'status' => StatusEnum::FAILED,
            'last_error' => $errorMessage,
        ]);
    }

    public function testMarkDead(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne(['status' => StatusEnum::DISPATCHED, 'last_error' => null]);

        // Act
        $this->repository->markDead(uuid: $webhook->getUuid(), error: $errorMessage = 'dead-boom');

        // Assert
        WebhookEntityFactory::assert()->exists([
            'uuid' => $webhook->getUuid(),
            'status' => StatusEnum::DEAD,
            'last_error' => $errorMessage,
        ]);
    }

    public function testMarkIsNoopWhenSameStatus(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne([
            'status' => StatusEnum::DISPATCHED,
            'attempts' => $attempts = random_int(0, 13),
        ]);

        // Act
        $this->repository->markDispatched(uuid: $webhook->getUuid());

        // Assert
        WebhookEntityFactory::assert()->exists([
            'uuid' => $webhook->getUuid(),
            'status' => StatusEnum::DISPATCHED,
            'attempts' => $attempts,
        ]);
    }

    public function testReplayBumpVersion(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne([
            'status' => StatusEnum::DEAD,
            'version' => $version = 1,
        ]);

        // Act
        $this->repository->replay(entity: $webhook->_real(), version: $version);

        // Assert
        WebhookEntityFactory::assert()->exists([
            'uuid' => $webhook->getUuid(),
            'status' => StatusEnum::RECEIVED,
            'version' => ++$version,
        ]);
    }

    public function testReplayThrowsOutdatedExceptionOnStaleVersion(): void
    {
        // Arrange
        $uuid = WebhookEntityFactory::createOne(['status' => StatusEnum::DEAD])->getUuid();

        //
        // Doctrine override version number set through Foundry
        // --> must update or insert through Doctrine directly
        //
        $em = self::getContainer()->get($this->repository::class)->getEntityManager();
        $metadata = $em->getClassMetadata(WebhookEntity::class);
        $rowCount = $em->getConnection()
            ->executeStatement("UPDATE {$metadata->getTableName()} SET version = 2");
        $this->assertEquals(1, $rowCount);

        // Act
        try {
            $this->repository->replay(
                entity: $webhook = $this->repository->findOneBy(['uuid' => $uuid]),
                version: 1,
            );
        } catch (WebhookOutdatedException $e) {
            // Assert
            $this->assertEquals(1, $e->getOutdatedVersion());
            $this->assertEquals((string) $webhook->getUuid(), $e->getIdentifier());
            WebhookEntityFactory::assert()->exists([
                'uuid' => $webhook->getUuid(),
                'status' => StatusEnum::DEAD,
                'version' => 2,
            ]);

            return;
        }

        $this->fail('A WebhookOutdatedException should have been thrown.');
    }
}
