<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Factory\WebhookEntryFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use WebhookLedger\Domain\Enum\StatusEnum;
use WebhookLedger\Infrastructure\Doctrine\Entity\WebhookEntry;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class WebhookReplayControllerTest extends WebTestCase
{
    public function testReplayReturns404WhenWebhookNotFound(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->jsonRequest(method: 'POST', uri: '/webhook/replay/'.Uuid::v7().'?version=1');

        // Assert
        $this->assertResponseStatusCodeSame(404);
    }

    public function testReplayReturns409WhenWebhookNotReplayable(): void
    {
        // Arrange
        $client = static::createClient();
        $webhook = WebhookEntryFactory::createOne([
            'status' => StatusEnum::FAILED,
            'signature_valid' => true,
            'version' => 1,
        ]);

        // Act
        $client->jsonRequest(method: 'POST', uri: '/webhook/replay/'.$webhook->getUuid().'?version=1');

        // Assert
        $this->assertResponseStatusCodeSame(409);
    }

    public function testReplayReturns409WhenWebhookOutdated(): void
    {
        // Arrange
        $client = static::createClient();
        $uuid = WebhookEntryFactory::createOne(['status' => StatusEnum::DEAD, 'signature_valid' => true])->getUuid();

        //
        // Doctrine override version number set through Foundry
        // --> must update or insert through Doctrine directly
        //
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $em->getClassMetadata(WebhookEntry::class);
        $rowCount = $em->getConnection()
            ->executeStatement("UPDATE {$metadata->getTableName()} SET version = 5");
        $this->assertEquals(1, $rowCount);

        // Act
        $client->jsonRequest(method: 'POST', uri: '/webhook/replay/'.$uuid.'?version=2');

        // Assert
        $this->assertResponseStatusCodeSame(409);
    }

    public function testReplayReturns202OnSuccess(): void
    {
        // Arrange
        $client = static::createClient();
        $webhook = WebhookEntryFactory::createOne([
            'status' => StatusEnum::DEAD,
            'signature_valid' => true,
            'version' => 1,
        ]);

        // Act
        $client->jsonRequest(method: 'POST', uri: '/webhook/replay/'.$webhook->getUuid().'?version=1');

        // Assert
        $this->assertResponseStatusCodeSame(202);
        $this->assertSame((string) $webhook->getUuid(), $client->getResponse()->headers->get('X-Evt-Id'));
    }
}
