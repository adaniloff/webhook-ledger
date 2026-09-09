<?php

namespace App\Tests\Controller;

use App\Enum\SourceEnum;
use App\Receiver\Service\WebhookSigner;
use App\Tests\Factory\WebhookEntityFactory;
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
    private WebhookSigner $signer;

    public function setUp(): void
    {
        $this->client = static::createClient();
        $this->logger = self::getContainer()->get('test.log.handler');
        $this->signer = self::getContainer()->get(WebhookSigner::class);
    }

    public function testHookReturns202(): void
    {
        // Arrange
        $payload = [
            'some_things' => 'dae7da-40a7-4744-b8a2-beb413579c40',
        ];
        $raw = json_encode($payload, \JSON_PRESERVE_ZERO_FRACTION);
        WebhookEntityFactory::assert()->count(0);

        // Act
        $this->client->jsonRequest(
            method: 'POST',
            uri: '/webhook/github',
            parameters: $payload,
            server: [
                'HTTP_X_GitHub_Delivery' => 'helloword!',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$this->signer->hash(raw: $raw, headers: [], source: SourceEnum::GITHUB),
            ],
        );

        // Assert
        // response ...
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(202);
        $this->assertEmpty($this->client->getResponse()->getContent());

        // storage...
        WebhookEntityFactory::assert()
            ->count(1)
            ->exists(['signature_valid' => true])
        ;

        // logs ...
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: 'REQUEST BODY', level: Level::Debug),
        );
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: 'source: '.SourceEnum::GITHUB->value, level: Level::Debug),
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
        WebhookEntityFactory::assert()->count(0);

        // Act
        $this->client->jsonRequest(
            method: 'POST',
            uri: '/webhook/github',
            parameters: $payload,
            server: [
                'HTTP_X_GitHub_Delivery' => 'helloword!',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$this->signer->hash(raw: $raw, headers: [], source: SourceEnum::STRIPE),
            ],
        );

        // Assert
        // response ...
        $this->assertResponseStatusCodeSame(401);

        // storage...
        WebhookEntityFactory::assert()
            ->count(1)
            ->exists(['signature_valid' => false])
        ;
    }

    public function testHookReturns422WithEmptyPayload(): void
    {
        // Arrange
        WebhookEntityFactory::assert()->count(0);

        // Act
        $this->client->jsonRequest(
            method: 'POST',
            uri: '/webhook/stripe',
            parameters: [],
        );

        // Assert
        // response ...
        $this->assertResponseStatusCodeSame(422);
        $response = json_decode($this->client->getResponse()->getContent(), associative: true);
        $this->assertSame('Invalid and/or missing fields.', $response['error']);
        $this->assertArrayHasKey('external_event_id', $response['fields']);

        // storage...
        WebhookEntityFactory::assert()->count(0);
    }

    public function testHookReturns422WithEmptyContent(): void
    {
        // Arrange
        WebhookEntityFactory::assert()->count(0);

        // Act
        $this->client->request(
            method: 'POST',
            uri: '/webhook/github',
            server: [
                'HTTP_X_GitHub_Delivery' => 'helloword!',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '',
        );

        // Assert
        // response ...
        $this->assertResponseStatusCodeSame(422);
        $response = json_decode($this->client->getResponse()->getContent(), associative: true);
        $this->assertSame('Invalid and/or missing fields.', $response['error']);
        $this->assertArrayHasKey('payload', $response['fields']);
        $this->assertArrayNotHasKey('external_event_id', $response['fields']);

        // storage...
        WebhookEntityFactory::assert()->count(0);
    }

    public function testHook202Idempotency(): void
    {
        // Arrange
        $payload = [
            'some_things' => 'dae7da-40a7-4744-b8a2-beb413579c40',
        ];
        $raw = json_encode($payload, \JSON_PRESERVE_ZERO_FRACTION);
        WebhookEntityFactory::assert()->count(0);

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
                    'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$this->signer->hash(raw: $raw, headers: [], source: SourceEnum::GITHUB),
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
        WebhookEntityFactory::assert()
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
        WebhookEntityFactory::assert()->count(0);

        // Act
        $count = 0;
        do {
            $this->client->jsonRequest(
                method: 'POST',
                uri: '/webhook/github',
                parameters: $payload,
                server: [
                    'HTTP_X_GitHub_Delivery' => 'helloword!',
                    'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$this->signer->hash(raw: $raw, headers: [], source: SourceEnum::STRIPE),
                ],
            );
        } while (++$count < 5);

        // Assert
        // response ...
        $this->assertResponseStatusCodeSame(401);

        // storage...
        WebhookEntityFactory::assert()
            ->count(1)
            ->exists(['signature_valid' => false])
        ;
    }
}
