<?php

namespace App\Domain;

use App\Entity\WebhookEntity;
use App\Enum\SourceEnum;
use App\Receiver\Dto\WebhookDto;
use Symfony\Component\Uid\Uuid;

interface WebhookLedgerWriterInterface
{
    public function receive(SourceEnum $source, WebhookDto $dto, int $attempts = 0, int $version = 1): Uuid;

    public function replay(WebhookEntity $entity, int $version): void;

    public function markDispatched(string $uuid): void;

    public function markSucceeded(string $uuid): void;

    public function markFailed(string $uuid, string $error): void;

    public function markDead(string $uuid, string $error): void;
}
