<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FooControllerTest extends WebTestCase
{
    public function testIndexReturnsGreeting(): void
    {
        $client = static::createClient();
        $client->request('GET', '/foo');

        $this->assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString(
            '{"message":"Hello from FooController"}',
            $client->getResponse()->getContent(),
        );
    }

    public function testShowReturnsPersonalizedGreeting(): void
    {
        $client = static::createClient();
        $client->request('GET', '/foo/World');

        $this->assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString(
            '{"message":"Hello, World!"}',
            $client->getResponse()->getContent(),
        );
    }
}
