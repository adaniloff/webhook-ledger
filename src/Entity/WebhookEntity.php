<?php

namespace App\Entity;

use App\Enum\SourceEnum;
use App\Enum\StatusEnum;
use App\Repository\WebhookEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebhookEntityRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_source_external_event_id', columns: ['source', 'external_event_id'])]
class WebhookEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID)]
    private ?string $uuid = null;

    #[ORM\Column(enumType: SourceEnum::class)]
    private ?SourceEnum $source = null;

    #[ORM\Column(length: 255)]
    private ?string $external_event_id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $payload = null;

    #[ORM\Column(type: Types::JSONB)]
    private mixed $headers = null;

    #[ORM\Column]
    private ?bool $signature_valid = null;

    #[ORM\Column(enumType: StatusEnum::class)]
    private ?StatusEnum $status = null;

    #[ORM\Column]
    private ?int $attempts = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $last_error = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $received_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\Column]
    private ?int $version = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getSource(): ?SourceEnum
    {
        return $this->source;
    }

    public function setSource(SourceEnum $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getExternalEventId(): ?string
    {
        return $this->external_event_id;
    }

    public function setExternalEventId(string $external_event_id): static
    {
        $this->external_event_id = $external_event_id;

        return $this;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getHeaders(): mixed
    {
        return $this->headers;
    }

    public function setHeaders(mixed $headers): static
    {
        $this->headers = $headers;

        return $this;
    }

    public function isSignatureValid(): ?bool
    {
        return $this->signature_valid;
    }

    public function setSignatureValid(bool $signature_valid): static
    {
        $this->signature_valid = $signature_valid;

        return $this;
    }

    public function getStatus(): ?StatusEnum
    {
        return $this->status;
    }

    public function setStatus(StatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAttempts(): ?int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): static
    {
        $this->attempts = $attempts;

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->last_error;
    }

    public function setLastError(?string $last_error): static
    {
        $this->last_error = $last_error;

        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->received_at;
    }

    public function setReceivedAt(\DateTimeImmutable $received_at): static
    {
        $this->received_at = $received_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;

        return $this;
    }
}
