<?php

namespace App\Tests\Repository;

use App\Entity\WebhookEntity;
use App\Enum\SourceEnum;
use App\Enum\StatusEnum;
use App\Infrastructure\Doctrine\WebhookCriteria;
use App\Repository\WebhookLedgerRepository;
use App\Tests\Factory\WebhookEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;
use Zenstruck\Foundry\Persistence\Proxy;

#[ResetDatabase]
final class WebhookLedgerRepositoryTest extends KernelTestCase
{
    private WebhookLedgerRepository $repository;

    public function setUp(): void
    {
        $this->repository = static::getContainer()->get(WebhookLedgerRepository::class);
    }

    public function testCountThrough(): void
    {
        // Arrange
        $stripeReceived = WebhookEntityFactory::createOne(['source' => SourceEnum::STRIPE, 'status' => StatusEnum::RECEIVED]);
        WebhookEntityFactory::createOne(['source' => SourceEnum::STRIPE, 'status' => StatusEnum::DEAD]);
        WebhookEntityFactory::createOne(['source' => SourceEnum::GITHUB, 'status' => StatusEnum::RECEIVED]);

        // Act
        // Assert
        $this->assertSame(3, $this->repository->countBy(new WebhookCriteria()));
        $this->assertSame(2, $this->repository->countBy(new WebhookCriteria(source: SourceEnum::STRIPE)));
        $this->assertSame(1, $this->repository->countBy(new WebhookCriteria(source: SourceEnum::STRIPE, status: StatusEnum::RECEIVED)));
    }

    public function testPaginate(): void
    {
        // Arrange
        $stripeReceived = WebhookEntityFactory::createOne(['source' => SourceEnum::STRIPE, 'status' => StatusEnum::RECEIVED]);
        WebhookEntityFactory::createOne(['source' => SourceEnum::STRIPE, 'status' => StatusEnum::DEAD]);
        WebhookEntityFactory::createOne(['source' => SourceEnum::GITHUB, 'status' => StatusEnum::RECEIVED]);

        // Act
        // Assert
        $this->assertCount(3, $this->repository->paginate(new WebhookCriteria(page: 1, limit: 10)));
        $this->assertCount(2, $this->repository->paginate(new WebhookCriteria(source: SourceEnum::STRIPE, page: 1, limit: 10)));

        $result = $this->repository->paginate(new WebhookCriteria(source: SourceEnum::STRIPE, status: StatusEnum::RECEIVED, page: 1, limit: 10));
        $this->assertCount(1, $result);
        $this->assertSame((string) $stripeReceived->getUuid(), (string) $result[0]->getUuid());
    }

    public function testPaginateOnMultiplePages(): void
    {
        // Arrange
        /** @var Proxy<WebhookEntity>[] $webhooks */
        $webhooks = WebhookEntityFactory::createMany(number: 25);
        usort($webhooks, fn ($a, $b) => $b->getUpdatedAt() <=> $a->getUpdatedAt() ?: $b->getId() <=> $a->getId());
        $expectedFirstPage = array_slice($webhooks, 0, 20);
        $expectedSecondPage = array_slice($webhooks, 20, 5);

        // Act
        $firstPage = $this->repository->paginate(new WebhookCriteria(page: 1, limit: 20));
        $secondPage = $this->repository->paginate(new WebhookCriteria(page: 2, limit: 20));

        // Assert
        $this->assertCount(20, $firstPage);
        $this->assertCount(5, $secondPage);
        $this->assertSame(
            array_map(static fn (WebhookEntity $w) => (string) $w->getUuid(), $expectedFirstPage),
            array_map(static fn (WebhookEntity $w) => (string) $w->getUuid(), $firstPage),
        );
        $this->assertSame(
            array_map(static fn (WebhookEntity $w) => (string) $w->getUuid(), $expectedSecondPage),
            array_map(static fn (WebhookEntity $w) => (string) $w->getUuid(), $secondPage),
        );
    }

    public function testPaginateWithCollisionKeepsSameOrder(): void
    {
        // Arrange
        $sameInstant = new \DateTimeImmutable('2024-01-01 12:00:00');
        $webhooks = [];
        for ($i = 0; $i < 5; ++$i) {
            $webhooks[] = WebhookEntityFactory::createOne(['updated_at' => $sameInstant]);
        }
        $expected = array_reverse($webhooks);

        // Act
        $firstPage = $this->repository->paginate(new WebhookCriteria(page: 1, limit: 3));
        $secondPage = $this->repository->paginate(new WebhookCriteria(page: 2, limit: 3));

        // Assert
        $this->assertSame(
            array_map(static fn (WebhookEntity $w) => (string) $w->getUuid(), array_slice($expected, 0, 3)),
            array_map(static fn (WebhookEntity $w) => (string) $w->getUuid(), $firstPage),
        );
        $this->assertSame(
            array_map(static fn (WebhookEntity $w) => (string) $w->getUuid(), array_slice($expected, 3, 2)),
            array_map(static fn (WebhookEntity $w) => (string) $w->getUuid(), $secondPage),
        );
    }
}
