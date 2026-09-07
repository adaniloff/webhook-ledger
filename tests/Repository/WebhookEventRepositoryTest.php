<?php

namespace App\Tests\Repository;

use App\Dto\WebhookDto;
use App\Enum\SourceEnum;
use App\Enum\StatusEnum;
use App\Exception\WebhookEventDuplicationException;
use App\Repository\WebhookEventRepository;
use App\Tests\Factory\WebhookEventFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class WebhookEventRepositoryTest extends KernelTestCase
{
    private WebhookEventRepository $repository;

    public function setUp(): void
    {
        $this->repository = static::getContainer()->get(WebhookEventRepository::class);
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
        WebhookEventFactory::assert()->empty();
        $this->repository->receive(SourceEnum::STRIPE, $dto);

        // Assert
        WebhookEventFactory::assert()
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

        // headers is a JSON column: Foundry's array-equality criteria doesn't reliably
        // match it against the stored JSON, so it's checked via a direct read instead.
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
        $this->expectException(WebhookEventDuplicationException::class);

        // Act
        WebhookEventFactory::assert()->empty();
        $this->repository->receive(SourceEnum::STRIPE, $dto);
        $this->repository->receive(SourceEnum::STRIPE, $dto);
    }
}
