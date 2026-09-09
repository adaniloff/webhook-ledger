<?php

namespace App\Tests\Repository;

use App\Enum\SourceEnum;
use App\Enum\StatusEnum;
use App\Receiver\Dto\WebhookDto;
use App\Receiver\Exception\WebhookEntryDuplicationException;
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
                'attempts' => 1,
                'version' => 1,
                'signature_valid' => true,
                'payload' => '{"id":"some-external-id"}',
            ]);

        $event = $this->repository->findOneBy(['external_event_id' => 'some-external-id']);
        $this->assertSame(['content-type' => 'application/json'], $event->getHeaders());
    }

    public function testReceiveButConstraintFailure(): void
    {
        // Arrange
        $dto = new WebhookDto(
            external_event_id: 'some-external-id',
            payload: '{"id":"some-external-id"}',
            headers: ['content-type' => 'application/json'],
            signature_valid: true,
        );

        // Assert
        $this->expectException(WebhookEntryDuplicationException::class);

        // Act
        WebhookEntityFactory::assert()->empty();
        $this->repository->receive(SourceEnum::STRIPE, $dto);
        $this->repository->receive(SourceEnum::STRIPE, $dto);
    }

    public function testReceiveButConstraintFailureCarriesExistingIdentifier(): void
    {
        // Arrange
        WebhookEntityFactory::assert()->empty();
        $uuid = $this->repository->receive(SourceEnum::STRIPE, $dto = new WebhookDto(
            external_event_id: 'some-external-id',
            payload: '{"id":"some-external-id"}',
            headers: ['content-type' => 'application/json'],
            signature_valid: true,
        ));

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

    public function testMarkDispatchedSetsStatusAndIncrementsAttempts(): void
    {
    }

    public function testMarkSucceededSetsStatusWithoutIncrementingAttempts(): void
    {
    }

    public function testMarkFailedSetsStatusAndLastError(): void
    {
    }

    public function testMarkDeadSetsStatusAndLastError(): void
    {
    }

    public function testMarkIsNoopWhenAlreadyAtTargetStatus(): void
    {
    }
}
