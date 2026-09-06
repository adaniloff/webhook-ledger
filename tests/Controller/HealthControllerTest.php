<?php

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    private TestHandler $logger;
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
        $this->logger = self::getContainer()->get('test.log.handler');
    }

    public function testHealthCheckReturns204(): void
    {
        // Arrange
        $this->setupDBConnection(connected: true);
        //
        // Act
        $this->client->request(method: 'GET', uri: '/health');

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(204);
        $this->assertEmpty($this->client->getResponse()->getContent());
        $this->assertFalse(
            $this->logger->hasRecordThatContains(message: 'Error, the connection is closed', level: Level::Error),
        );
    }

    public function testHealthCheckReturns503(): void
    {
        // Arrange
        $this->setupDBConnection(connected: false);

        // Act
        $this->client->request(method: 'GET', uri: '/health');

        // Assert
        $this->assertResponseStatusCodeSame(503);
        $this->assertEmpty($this->client->getResponse()->getContent());
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: 'Error, the connection is closed', level: Level::Error),
        );
    }

    private function setupDBConnection(bool $connected): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDatabase'])
            ->getMock();
        $connection->expects($this->any())
            ->method('getDatabase')
            ->willReturn($connected ? 'test-app' : null);
        $mock = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'clear'])
            ->getMock();
        $mock->expects($this->any())
            ->method('getConnection')
            ->willReturn($connection);
        static::getContainer()->set('doctrine.orm.default_entity_manager', $mock);
    }
}
