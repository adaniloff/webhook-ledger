<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Webhook\Adapter\GithubAdapter;
use App\Webhook\Adapter\StripeAdapter;
use Symfony\Component\Uid\Uuid;
use WebhookLedger\Domain\Enum\StatusEnum;
use WebhookLedger\Infrastructure\Doctrine\Entity\WebhookEntry;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<WebhookEntry>
 */
final class WebhookEntryFactory extends PersistentProxyObjectFactory
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
        return WebhookEntry::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        $headers = [
            'content-type' => 'application/json',
            'x-number' => 'AC347D212341XR',
        ];

        $receivedAt = self::faker()->dateTime();

        return [
            'attempts' => self::faker()->randomNumber(),
            'external_event_id' => self::faker()->text(255),
            'headers' => $headers,
            'payload' => self::faker()->text(),
            'received_at' => \DateTimeImmutable::createFromMutable($receivedAt),
            'signature_valid' => self::faker()->boolean(),
            'source' => self::faker()->randomElement([StripeAdapter::NAME, GithubAdapter::NAME]),
            'status' => self::faker()->randomElement(StatusEnum::cases()),
            'updated_at' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween($receivedAt)),
            'uuid' => (string) Uuid::v7(),
            'version' => self::faker()->randomNumber(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::withoutConstructor()->alwaysForce());
    }
}
