<?php

declare(strict_types=1);

namespace App\Tests\Webhook\Adapter;

use App\Webhook\Adapter\GithubAdapter;
use PHPUnit\Framework\TestCase;

final class GithubAdapterTest extends TestCase
{
    private const RAW = '{"hello":"world"}';
    private const SECRET = 'github-test';

    private GithubAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new GithubAdapter(self::SECRET);
    }

    public function testVerifyAcceptsValidSignature(): void
    {
        $hmac = hash_hmac('sha256', self::RAW, self::SECRET);
        $headers = ['x-hub-signature-256' => ['sha256='.$hmac]];

        $this->assertTrue($this->adapter->verify($headers, self::RAW));
    }

    public function testVerifyRejectsWrongSignature(): void
    {
        $headers = ['x-hub-signature-256' => ['sha256='.hash('sha256', 'not-the-secret')]];

        $this->assertFalse($this->adapter->verify($headers, self::RAW));
    }

    public function testVerifyRejectsMissingHeader(): void
    {
        $this->assertFalse($this->adapter->verify([], self::RAW));
    }
}
