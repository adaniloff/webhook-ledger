<?php

namespace App\Tests\Worker\Handler;

use App\Enum\SourceEnum;
use App\Tests\Factory\WebhookEntityFactory;
use App\Worker\Handler\WebhookHandler;
use App\Worker\Message\ProcessWebhookEvent;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class WebhookHandlerTest extends KernelTestCase
{
    private WebhookHandler $handler;
    private TestHandler $logger;

    public function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        // WebhookHandler is wired to the "webhook" logger channel (monolog.logger.webhook),
        // which "test.log.handler" does not capture: that one only decorates the default channel.
        $this->logger = new TestHandler();
        $container->set('monolog.logger.webhook', new Logger('webhook', [$this->logger]));

        $this->handler = $container->get(WebhookHandler::class);
    }

    public function testInvokeHandlesGithubSource(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne([
            'source' => SourceEnum::GITHUB,
            'headers' => ['x-github-event' => ['push']],
        ]);

        // Act
        ($this->handler)(new ProcessWebhookEvent(uuid: $webhook->getUuid()));

        // Assert
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: sprintf('Handling message %s', $webhook->getUuid()), level: Level::Debug),
        );
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: '<event> '.json_encode(['push']), level: Level::Info),
        );
    }

    public function testInvokeHandlesStripeSource(): void
    {
        // Arrange
        $webhook = WebhookEntityFactory::createOne(['source' => SourceEnum::STRIPE]);

        // Act
        ($this->handler)(new ProcessWebhookEvent(uuid: $webhook->getUuid()));

        // Assert
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: sprintf('Handling message %s', $webhook->getUuid()), level: Level::Debug),
        );
        $this->assertTrue(
            $this->logger->hasRecordThatContains(
                message: '<stripe event ignored>',
                level: Level::Debug,
            ),
        );
    }

    public function testInvokeDoesNothingWhenEntityNotFound(): void
    {
        // Arrange
        $uuid = (string) Uuid::v7();

        // Act
        ($this->handler)(new ProcessWebhookEvent(uuid: $uuid));

        // Assert
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: sprintf('Handling message %s', $uuid), level: Level::Debug),
        );
        $this->assertFalse($this->logger->hasRecords(Level::Info));
        $this->assertTrue(
            $this->logger->hasRecordThatContains(message: sprintf('Entity not found for uuid: %s', $uuid), level: Level::Warning),
        );
    }
}
