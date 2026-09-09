<?php

namespace App\Repository;

use App\Entity\WebhookEntity;
use App\Enum\SourceEnum;
use App\Enum\StatusEnum;
use App\Receiver\Dto\WebhookDto;
use App\Receiver\Exception\WebhookEntryDuplicationException;
use App\Receiver\Exception\WebhookOutdatedException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<WebhookEntity>
 */
final class WebhookEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookEntity::class);
    }

    public function receive(SourceEnum $source, WebhookDto $dto, int $attempts = 1, int $version = 1): Uuid
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $em = $this->getEntityManager();
        $metadata = $em->getClassMetadata(WebhookEntity::class);
        $table = $metadata->getTableName();

        $metaId = $metadata->getColumnName('id');
        $metaUuid = $metadata->getColumnName('uuid');
        $metaSource = $metadata->getColumnName('source');
        $metaExternalEventId = $metadata->getColumnName('external_event_id');
        $metaPayload = $metadata->getColumnName('payload');
        $metaHeaders = $metadata->getColumnName('headers');
        $metaSignatureValid = $metadata->getColumnName('signature_valid');
        $metaStatus = $metadata->getColumnName('status');
        $metaAttempts = $metadata->getColumnName('attempts');
        $metaReceivedAt = $metadata->getColumnName('received_at');
        $metaUpdatedAt = $metadata->getColumnName('updated_at');
        $metaVersion = $metadata->getColumnName('version');

        $query = "
            INSERT INTO $table (
            $metaId,
            $metaUuid,
            $metaSource,
            $metaExternalEventId,
            $metaPayload,
            $metaHeaders,
            $metaSignatureValid,
            $metaStatus,
            $metaAttempts,
            $metaReceivedAt,
            $metaUpdatedAt,
            $metaVersion
            ) VALUES (null, ?,?,?,?,?,?,?,?,?,?,?)
        ";

        try {
            $em->getConnection()->executeStatement($query, [
                $uuid = Uuid::v7(),
                $source->value,
                $dto->external_event_id,
                $dto->payload,
                json_encode($dto->headers),
                $dto->signature_valid ? '1' : '0',
                StatusEnum::RECEIVED->value,
                $attempts,
                $now,
                $now,
                $version,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            /** @var Uuid $uuid */
            $uuid = $em->getConnection()
                ->executeQuery("SELECT $metaUuid FROM $table WHERE source = ? AND external_event_id = ?", [
                    $source->value,
                    $dto->external_event_id,
                ])->fetchOne();
            throw new WebhookEntryDuplicationException(uuid: (string) $uuid, previous: $e);
        }

        return $uuid;
    }

    public function replay(WebhookEntity $entity, int $version): void
    {
        $em = $this->getEntityManager();

        try {
            $entity->setStatus(StatusEnum::RECEIVED);
            $entity->setUpdatedAt($now = new \DateTimeImmutable());
            $entity->setReceivedAt($now);
            $em->lock($entity, LockMode::OPTIMISTIC, $version);
            $em->flush();
        } catch (OptimisticLockException $e) {
            throw new WebhookOutdatedException(uuid: (string) $entity->getUuid(), outdatedVersion: $version, previous: $e);
        }
    }

    public function markDispatched(string $uuid): void
    {
        $this->mark(uuid: $uuid, status: StatusEnum::DISPATCHED, incrementAttempts: true);
    }

    public function markSucceeded(string $uuid): void
    {
        $this->mark(uuid: $uuid, status: StatusEnum::SUCCEEDED);
    }

    public function markFailed(string $uuid, string $error): void
    {
        $this->mark(uuid: $uuid, status: StatusEnum::FAILED, error: $error);
    }

    public function markDead(string $uuid, string $error): void
    {
        $this->mark(uuid: $uuid, status: StatusEnum::DEAD, error: $error);
    }

    private function mark(
        string $uuid,
        StatusEnum $status,
        bool $incrementAttempts = false,
        ?string $error = null,
    ): void {
        $em = $this->getEntityManager();
        $metadata = $em->getClassMetadata(WebhookEntity::class);
        $table = $metadata->getTableName();

        $metaId = $metadata->getColumnName('id');
        $metaUuid = $metadata->getColumnName('uuid');
        $count = (int) $incrementAttempts;

        $em->getConnection()->executeStatement(
            "UPDATE $table SET status = :status,
               attempts = attempts + $count,
               updated_at = :now,
               last_error = :last_error
             WHERE $metaUuid = :uuid AND status != :status",
            [
                'uuid' => Uuid::fromString($uuid),
                'status' => $status->value,
                'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'last_error' => $error,
            ],
        );
    }
}
