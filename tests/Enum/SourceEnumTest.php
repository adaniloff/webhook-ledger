<?php

namespace App\Tests\Enum;

use App\Enum\SourceEnum;
use PHPUnit\Framework\TestCase;

final class SourceEnumTest extends TestCase
{
    public function testGithubSafety(): void
    {
        // Arrange
        $raw = json_encode(['hello' => 'world']);
        $headers = ['X-Hub-Signature-256' => ['sha256='.$hash = hash('sha256', $raw)]];

        // Act
        // Assert
        $this->assertFalse(SourceEnum::GITHUB->isSafe($headers,
            hash('sha256', json_encode(['Hello' => 'World'])),
        ));
        $this->assertFalse(SourceEnum::GITHUB->isSafe(['X-Hub-Signature-256' => [
            'sha256='.sha1($raw),
        ]], $hash));
        $this->assertTrue(SourceEnum::GITHUB->isSafe($headers, $hash));
    }
}
