<?php

declare(strict_types=1);

namespace App\Tests\Webhook\Adapter;

use App\Webhook\Adapter\StripeAdapter;
use PHPUnit\Framework\TestCase;

final class StripeAdapterTest extends TestCase
{
    private const RAW = '{"hello":"world"}';
    private const SECRET = 'stripe-test';

    private StripeAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new StripeAdapter(self::SECRET);
    }

    public function testVerifyAcceptsValidSignature(): void
    {
        $timestamp = (string) time();
        $hmac = hash_hmac('sha256', $timestamp.'.'.self::RAW, self::SECRET);
        $headers = ['stripe-signature' => ["t=$timestamp,v1=$hmac"]];

        $this->assertTrue($this->adapter->verify($headers, self::RAW));
    }

    public function testVerifyAcceptsValidSignatureAmongMultipleSchemes(): void
    {
        $timestamp = (string) time();
        $hmac = hash_hmac('sha256', $timestamp.'.'.self::RAW, self::SECRET);
        $headers = ['stripe-signature' => ["t=$timestamp,v0=deadbeef,v1=$hmac"]];

        $this->assertTrue($this->adapter->verify($headers, self::RAW));
    }

    public function testVerifyRejectsWrongSignature(): void
    {
        $timestamp = (string) time();
        $hmac = hash_hmac('sha256', $timestamp.'.'.self::RAW, self::SECRET);
        $headers = ['stripe-signature' => ["t=$timestamp,v1=$hmac"]];

        $this->assertFalse($this->adapter->verify($headers, '{"hello":"someone-else"}'));
    }

    public function testVerifyRejectsWrongScheme(): void
    {
        $timestamp = (string) time();
        $hmac = hash_hmac('sha256', $timestamp.'.'.self::RAW, self::SECRET);
        $headers = ['stripe-signature' => ["t=$timestamp,v0=$hmac"]];

        $this->assertFalse($this->adapter->verify($headers, self::RAW));
    }

    public function testVerifyRejectsExpiredTimestamp(): void
    {
        $timestamp = (string) (time() - 301);
        $hmac = hash_hmac('sha256', $timestamp.'.'.self::RAW, self::SECRET);
        $headers = ['stripe-signature' => ["t=$timestamp,v1=$hmac"]];

        $this->assertFalse($this->adapter->verify($headers, self::RAW));
    }

    public function testVerifyRejectsMissingHeader(): void
    {
        $this->assertFalse($this->adapter->verify([], self::RAW));
    }
}
