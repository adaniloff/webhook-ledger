<?php

namespace App\Tests\Service;

use App\Enum\SourceEnum;
use App\Service\WebhookSigner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WebhookSignerTest extends KernelTestCase
{
    private const RAW = '{"hello":"world"}';

    private const GITHUB_HMAC = '10066aedabff202f367db78e6566bcf43f58fb0487094e612a059b2b9285f1bf';
    private const STRIPE_HMAC = '72d76233af3a96e51f6af7ee463c470ac06b2294642cc79aaa1dae3958a2d4af';

    private WebhookSigner $signer;

    public function setUp(): void
    {
        self::bootKernel();
        $this->signer = self::getContainer()->get(WebhookSigner::class);
    }

    public function testSignGithubProducesExpectedHmac(): void
    {
        $this->assertSame(self::GITHUB_HMAC, $this->signer->sign(raw: self::RAW, source: SourceEnum::GITHUB));
    }

    public function testSignStripeProducesExpectedHmac(): void
    {
        $this->assertSame(self::STRIPE_HMAC, $this->signer->sign(raw: self::RAW, source: SourceEnum::STRIPE));
    }

    public function testVerifyGithubAcceptsValidSignature(): void
    {
        $headers = ['x-hub-signature-256' => ['sha256='.self::GITHUB_HMAC]];

        $this->assertTrue($this->signer->verify(source: SourceEnum::GITHUB, headers: $headers, raw: self::RAW));
    }

    public function testVerifyGithubRejectsWrongSignature(): void
    {
        $headers = ['x-hub-signature-256' => ['sha256='.self::STRIPE_HMAC]];

        $this->assertFalse($this->signer->verify(source: SourceEnum::GITHUB, headers: $headers, raw: self::RAW));
    }

    public function testVerifyGithubRejectsMissingHeader(): void
    {
        $this->assertFalse($this->signer->verify(source: SourceEnum::GITHUB, headers: [], raw: self::RAW));
    }

    public function testVerifyStripeNotYetImplementedReturnsFalse(): void
    {
        $headers = ['stripe-signature' => ['t=1,v1='.self::STRIPE_HMAC]];

        $this->assertFalse($this->signer->verify(source: SourceEnum::STRIPE, headers: $headers, raw: self::RAW));
    }
}
