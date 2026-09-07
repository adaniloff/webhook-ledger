<?php

namespace App\Repository;

use App\Dto\WebhookDto;
use App\Entity\WebhookEvent;
use App\Enum\SourceEnum;
use App\Enum\StatusEnum;
use App\Exception\WebhookEventDuplicationException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<WebhookEvent>
 */
final class WebhookEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookEvent::class);
    }

    public function receive(SourceEnum $source, WebhookDto $dto, int $attempts = 1, int $version = 1): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $em = $this->getEntityManager();
        $metadata = $em->getClassMetadata(WebhookEvent::class);
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
            $affectedRows = $em->getConnection()->executeStatement($query, [
                Uuid::v7(),
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
            throw new WebhookEventDuplicationException(previous: $e);
        }
    }
}
