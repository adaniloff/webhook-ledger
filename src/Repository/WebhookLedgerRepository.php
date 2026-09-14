<?php

namespace App\Repository;

use App\Domain\WebhookLedgerRepositoryInterface;
use App\Entity\WebhookEntity;
use App\Enum\StatusEnum;
use App\Infrastructure\Doctrine\WebhookCriteria;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebhookEntity>
 */
final class WebhookLedgerRepository extends ServiceEntityRepository implements WebhookLedgerRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookEntity::class);
    }

    /**
     * @return list<WebhookEntity>
     */
    public function paginate(WebhookCriteria $criteria = new WebhookCriteria()): array
    {
        /** @var list<WebhookEntity> $result */
        $result = $this->createQueryBuilder('w')
            ->addCriteria($criteria->build(paginated: true))
            ->orderBy('w.updated_at', 'DESC')
            ->addOrderBy('w.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countBy(WebhookCriteria $criteria = new WebhookCriteria()): int
    {
        return (int) $this->createQueryBuilder('w')
            ->addCriteria($criteria->build(paginated: false))
            ->select('COUNT(w.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        /** @var list<array{status: StatusEnum, count: string}> $rows */
        $rows = $this->createQueryBuilder('w')
            ->select('w.status AS status', 'COUNT(w.id) AS count')
            ->groupBy('w.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']->value] = (int) $row['count'];
        }

        return $counts;
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?WebhookEntity
    {
        return parent::findOneBy($criteria, $orderBy);
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return parent::findBy($criteria, $orderBy, $limit, $offset);
    }
}
