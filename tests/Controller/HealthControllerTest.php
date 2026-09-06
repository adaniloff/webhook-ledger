<?php

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Monolog\Handler\TestHandler;
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
        $this->logger->hasErrorThatContains('Error, the connection is closed');
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
        $this->logger->hasErrorThatContains('Error, the connection is closed');
    }

    private function setupDBConnection(bool $connected): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isConnected'])
            ->getMock();
        $connection->expects($this->any())
            ->method('isConnected')
            ->willReturn($connected);
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
