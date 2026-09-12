<?php

namespace App\Tests\Factory;

use App\Entity\WebhookEntity;
use App\Enum\SourceEnum;
use App\Enum\StatusEnum;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<WebhookEntity>
 */
final class WebhookEntityFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
    }

    public static function class(): string
    {
        return WebhookEntity::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        $headers = '{
         "content-type": "application/json",
         "x-number": "AC347D212341XR",
        }';

        return [
            'attempts' => self::faker()->randomNumber(),
            'external_event_id' => self::faker()->text(255),
            'headers' => $headers,
            'payload' => self::faker()->text(),
            'received_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'signature_valid' => self::faker()->boolean(),
            'source' => self::faker()->randomElement(SourceEnum::cases()),
            'status' => self::faker()->randomElement(StatusEnum::cases()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'uuid' => Uuid::v7(),
            'version' => self::faker()->randomNumber(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(WebhookEntity $webhookEntity): void {})
        ;
    }
}
