<?php

namespace App\Domain;

use App\Entity\WebhookEntity;
use App\Infrastructure\Doctrine\WebhookCriteria;

interface WebhookLedgerRepositoryInterface
{
    /**
     * @return array<string, int>
     */
    public function countByStatus(): array;

    /**
     * @return list<WebhookEntity>
     */
    public function paginate(WebhookCriteria $criteria = new WebhookCriteria()): array;

    public function countBy(WebhookCriteria $criteria = new WebhookCriteria()): int;

    /**
     * @param array<string, mixed>       $criteria
     * @param array<string, string>|null $orderBy
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?WebhookEntity;

    /**
     * @param array<string, mixed>       $criteria
     * @param array<string, string>|null $orderBy
     *
     * @return list<WebhookEntity>
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;
}
