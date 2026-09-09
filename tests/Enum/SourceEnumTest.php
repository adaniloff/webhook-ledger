<?php

namespace App\Tests\Enum;

use App\Enum\SourceEnum;
use PHPUnit\Framework\TestCase;

final class SourceEnumTest extends TestCase
{
    public function testGithubSignature(): void
    {
        // Arrange
        $raw = json_encode(['hello' => 'world']);
        $headers = ['X-Hub-Signature-256' => ['sha256='.$hash = hash('sha256', $raw)]];

        // Act
        // Assert
        $this->assertFalse(SourceEnum::GITHUB->checkSignature($headers,
            hash('sha256', json_encode(['Hello' => 'World'])),
        ));
        $this->assertFalse(SourceEnum::GITHUB->checkSignature(['X-Hub-Signature-256' => [
            'sha256='.sha1($raw),
        ]], $hash));
        $this->assertTrue(SourceEnum::GITHUB->checkSignature($headers, $hash));
    }

    public function testStripeSignature(): void
    {
        // Arrange
        $timestamp = (string) time();
        $hmac = hash('sha256', 'hello-world');

        // Act
        // Assert
        $this->assertFalse(SourceEnum::STRIPE->checkSignature(
            ['stripe-signature' => ["t=$timestamp,v1=$hmac"]],
            hash('sha256', 'another-world'),
        ));
        $this->assertFalse(SourceEnum::STRIPE->checkSignature(
            ['stripe-signature' => ["t=$timestamp,v0=$hmac"]],
            $hmac,
        ));
        $this->assertFalse(SourceEnum::STRIPE->checkSignature(
            ['stripe-signature' => ['t='.(time() - 301).",v1=$hmac"]],
            $hmac,
        ));
        $this->assertTrue(SourceEnum::STRIPE->checkSignature(
            ['stripe-signature' => ["t=$timestamp,v1=$hmac"]],
            $hmac,
        ));
    }
}
