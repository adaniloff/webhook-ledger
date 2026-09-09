<?php

namespace App\Tests\Receiver\Service;

use App\Enum\SourceEnum;
use App\Receiver\Service\WebhookSigner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WebhookSignerTest extends KernelTestCase
{
    private const RAW = '{"hello":"world"}';
    private const STRIPE_TIMESTAMP = '1700000000';

    private const GITHUB_HMAC = '10066aedabff202f367db78e6566bcf43f58fb0487094e612a059b2b9285f1bf';
    private const STRIPE_HMAC = '0f0d33b178464a7bdf94e223e0c4ac2be96f2cbb13987c3985cd7947dbed574b';

    private WebhookSigner $signer;

    public function setUp(): void
    {
        self::bootKernel();
        $this->signer = self::getContainer()->get(WebhookSigner::class);
    }

    public function testHashGithubProducesExpectedHmac(): void
    {
        $this->assertSame(
            self::GITHUB_HMAC,
            $this->signer->hash(raw: self::RAW, headers: [], source: SourceEnum::GITHUB),
        );
    }

    public function testVerifyGithubAcceptsValidSignature(): void
    {
        $headers = ['x-hub-signature-256' => ['sha256='.self::GITHUB_HMAC]];

        $this->assertTrue($this->signer->verify(source: SourceEnum::GITHUB, headers: $headers, raw: self::RAW));
    }

    public function testVerifyGithubRejectsWrongSignature(): void
    {
        $headers = ['x-hub-signature-256' => ['sha256=2f62c40a-c72e-416a-86be-4f39713de6f9']];

        $this->assertFalse($this->signer->verify(source: SourceEnum::GITHUB, headers: $headers, raw: self::RAW));
    }

    public function testHashStripeProducesExpectedHmac(): void
    {
        $headers = ['stripe-signature' => ['t='.self::STRIPE_TIMESTAMP]];

        $this->assertSame(
            self::STRIPE_HMAC,
            $this->signer->hash(raw: self::RAW, headers: $headers, source: SourceEnum::STRIPE),
        );
    }

    public function testVerifyStripeAcceptsValidSignature(): void
    {
        $timestamp = (string) time();
        $hmac = hash_hmac('sha256', $timestamp.'.'.self::RAW, 'stripe-test');
        $headers = ['stripe-signature' => ["t=$timestamp,v1=$hmac"]];

        $this->assertTrue($this->signer->verify(source: SourceEnum::STRIPE, headers: $headers, raw: self::RAW));
    }

    public function testVerifyStripeAcceptsValidSignatureAmongMultipleSchemes(): void
    {
        $timestamp = (string) time();
        $hmac = hash_hmac('sha256', $timestamp.'.'.self::RAW, 'stripe-test');
        $headers = ['stripe-signature' => ["t=$timestamp,v0=deadbeef,v1=$hmac"]];

        $this->assertTrue($this->signer->verify(source: SourceEnum::STRIPE, headers: $headers, raw: self::RAW));
    }

    public function testVerifyStripeRejectsExpiredTimestamp(): void
    {
        $timestamp = (string) (time() - 301);
        $hmac = hash_hmac('sha256', $timestamp.'.'.self::RAW, 'stripe-test');
        $headers = ['stripe-signature' => ["t=$timestamp,v1=$hmac"]];

        $this->assertFalse($this->signer->verify(source: SourceEnum::STRIPE, headers: $headers, raw: self::RAW));
    }
}
