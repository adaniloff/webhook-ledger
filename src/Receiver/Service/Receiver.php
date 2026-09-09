<?php

namespace App\Receiver\Service;

use App\Enum\SourceEnum;
use App\Receiver\Dto\WebhookDto;
use App\Receiver\Exception\WebhookNotFoundException;
use App\Receiver\Exception\WebhookNotReplayableException;
use App\Repository\WebhookEntityRepository;
use App\Worker\Message\ProcessWebhookEvent;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final readonly class Receiver
{
    public function __construct(
        private Connection $conn,
        private MessageBusInterface $bus,
        private WebhookEntityRepository $repository,
        private WebhookSigner $signer,
    ) {
    }

    /**
     * @param array<string, list<string|null>> $headers
     */
    public function sign(SourceEnum $source, array $headers, string $raw): bool
    {
        return $this->signer->verify(source: $source, headers: $headers, raw: $raw);
    }

    public function capture(SourceEnum $source, WebhookDto $dto): Uuid
    {
        return $this->conn->transactional(function () use ($source, $dto): Uuid {
            $uuid = $this->repository->receive(source: $source, dto: $dto);

            if ($dto->signature_valid) {
                $this->bus->dispatch(new ProcessWebhookEvent(uuid: $uuid->toRfc4122()));
            }

            return $uuid;
        });
    }

    public function replay(Uuid|string $uuid, int $version): void
    {
        $this->conn->transactional(function () use ($uuid, $version): void {
            if (!$entity = $this->repository->findOneBy(['uuid' => $uuid])) {
                throw new WebhookNotFoundException(uuid: $uuid);
            }
            if (!$entity->getStatus()?->canReplay() || !$entity->isSignatureValid()) {
                throw new WebhookNotReplayableException(uuid: $uuid);
            }
            $this->repository->replay(entity: $entity, version: $version);
            $this->bus->dispatch(new ProcessWebhookEvent(uuid: $uuid));
        });
    }
}
