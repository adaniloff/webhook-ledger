<?php

namespace App\Tests\Controller;

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
        // Act
        $this->client->request(method: 'POST', uri: '/webhook/stripe', content: $payload = '{"processed":true}');

        // Assert
        $this->logger->hasRecordThatContains(message: 'REQUEST BODY', level: Level::Info);
        $this->logger->hasRecordThatContains(message: $payload, level: Level::Info);

        // response ...
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(202);
        $this->assertEmpty($this->client->getResponse()->getContent());
    }
}
