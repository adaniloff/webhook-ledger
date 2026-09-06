<?php

namespace App\Tests\Controller;

use App\Dto\WebhookDto;
use App\Enum\SourceEnum;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WebhookControllerTest extends WebTestCase
{
    private TestHandler $logger;
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
        $this->logger = self::getContainer()->get('test.log.handler');
    }

    public function testHookLogsAndReturns202(): void
    {
        // Arrange
        $dto = new WebhookDto(external_event_id: '72dae7da-40a7-4744-b8a2-beb413579c40');
        // Act
        $this->client->jsonRequest(
            method: 'POST',
            uri: '/webhook/stripe',
            parameters: $payload = [
                'external_event_id' => $dto->external_event_id,
            ],
        );

        // Assert
        // response ...
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(202);
        $this->assertEmpty($this->client->getResponse()->getContent());
        // logs ...
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: 'REQUEST BODY', level: Level::Debug),
        );
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: 'source: '.SourceEnum::STRIPE->value, level: Level::Debug),
        );
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: 'payload: '.serialize($dto), level: Level::Debug),
        );
    }
}
