<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Factory\WebhookEntryFactory;
use Doctrine\DBAL\Connection;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class WebhookControllerTest extends WebTestCase
{
    private TestHandler $logger;
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
        $this->logger = self::getContainer()->get('test.log.handler');
    }

    public function testHookReturns202(): void
    {
        // Arrange
        $payload = [
            'some_things' => 'dae7da-40a7-4744-b8a2-beb413579c40',
        ];
        $raw = json_encode($payload, \JSON_PRESERVE_ZERO_FRACTION);
        WebhookEntryFactory::assert()->count(0);

        // Act
        $this->client->jsonRequest(
            method: 'POST',
            uri: '/webhook/github',
            parameters: $payload,
            server: [
                'HTTP_X_GitHub_Delivery' => 'helloword!',
                'HTTP_X_HUB_SIGNATURE_256' => $this->githubSignature($raw),
            ],
        );

        // Assert
        // response ...
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(202);
        $this->assertEmpty($this->client->getResponse()->getContent());

        // storage...
        WebhookEntryFactory::assert()
            ->count(1)
            ->exists(['signature_valid' => true])
        ;

        // logs ...
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: 'REQUEST BODY', level: Level::Debug),
        );
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: 'source: github', level: Level::Debug),
        );
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: 'payload: '.json_encode($payload), level: Level::Debug),
        );
    }

    public function testHookReturns401OnWrongSignature(): void
    {
        // Arrange
        $payload = [
            'some_things' => 'dae7da-40a7-4744-b8a2-beb413579c40',
        ];
        $raw = json_encode($payload, \JSON_PRESERVE_ZERO_FRACTION);
        WebhookEntryFactory::assert()->count(0);

        // Act
        $this->client->jsonRequest(
            method: 'POST',
            uri: '/webhook/github',
            parameters: $payload,
            server: [
                'HTTP_X_GitHub_Delivery' => 'helloword!',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, 'not-the-right-secret'),
            ],
        );

        // Assert
        // response ...
        $this->assertResponseStatusCodeSame(401);

        // storage...
        WebhookEntryFactory::assert()
            ->count(1)
            ->exists(['signature_valid' => false])
        ;
    }

    public function testHookReturns422WithValidSignatureButWrongPayloadDoesNotDispatch(): void
    {
        // Arrange
        $payload = [
            'some_things' => 'dae7da-40a7-4744-b8a2-beb413579c40',
        ];
        $raw = json_encode($payload, \JSON_PRESERVE_ZERO_FRACTION);
        WebhookEntryFactory::assert()->count(0);

        // Act
        $this->client->request(
            method: 'POST',
            uri: '/webhook/github',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $this->githubSignature($raw),
            ],
            content: $raw,
        );

        // Assert
        // response ...
        $this->assertResponseStatusCodeSame(422);
        $response = json_decode($this->client->getResponse()->getContent(), associative: true);
        $this->assertArrayHasKey('external_event_id', $response['fields']);

        // storage...
        WebhookEntryFactory::assert()
            ->count(1)
            ->exists(['signature_valid' => true])
        ;
        $this->assertSame(0, $this->countMessengerMessages());
    }

    public function testHook202Idempotency(): void
    {
        // Arrange
        $payload = [
            'some_things' => 'dae7da-40a7-4744-b8a2-beb413579c40',
        ];
        $raw = json_encode($payload, \JSON_PRESERVE_ZERO_FRACTION);
        WebhookEntryFactory::assert()->count(0);

        // Act
        $count = 0;
        $eventId = null;
        do {
            $this->client->jsonRequest(
                method: 'POST',
                uri: '/webhook/github',
                parameters: $payload,
                server: [
                    'HTTP_X_GitHub_Delivery' => 'helloword!',
                    'HTTP_X_HUB_SIGNATURE_256' => $this->githubSignature($raw),
                ],
            );
            $eventId ??= $this->client->getResponse()->headers->get('X-Evt-Id');
        } while (++$count < 5);

        // Assert
        // response ...
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(202);
        $this->assertEmpty($this->client->getResponse()->getContent());
        $this->assertSame($eventId, $this->client->getResponse()->headers->get('X-Evt-Id'));

        // storage...
        WebhookEntryFactory::assert()
            ->count(1) // only one line has been persisted
            ->exists(['signature_valid' => true])
        ;
    }

    public function testHook401Idempotency(): void
    {
        // Arrange
        $payload = [
            'some_things' => 'dae7da-40a7-4744-b8a2-beb413579c40',
        ];
        $raw = json_encode($payload, \JSON_PRESERVE_ZERO_FRACTION);
        WebhookEntryFactory::assert()->count(0);

        // Act
        $count = 0;
        do {
            $this->client->jsonRequest(
                method: 'POST',
                uri: '/webhook/github',
                parameters: $payload,
                server: [
                    'HTTP_X_GitHub_Delivery' => 'helloword!',
                    'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, 'not-the-right-secret'),
                ],
            );
        } while (++$count < 5);

        // Assert
        // response ...
        $this->assertResponseStatusCodeSame(401);

        // storage...
        WebhookEntryFactory::assert()
            ->count(1)
            ->exists(['signature_valid' => false])
        ;
    }

    public function testHookReturns404OnUnknownSource(): void
    {
        // Act
        $this->client->jsonRequest(method: 'POST', uri: '/webhook/unknown-provider', parameters: ['a' => 'b']);

        // Assert
        $this->assertResponseStatusCodeSame(404);
    }

    private function githubSignature(string $raw): string
    {
        return 'sha256='.hash_hmac('sha256', $raw, (string) $_ENV['GITHUB_WEBHOOK_SECRET']);
    }

    private function countMessengerMessages(): int
    {
        return (int) self::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM messenger_messages');
    }
}
