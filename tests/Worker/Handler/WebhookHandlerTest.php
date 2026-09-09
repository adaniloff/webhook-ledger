<?php

namespace App\Tests\Worker\Handler;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WebhookHandlerTest extends KernelTestCase
{
    public function testInvoke(): void
    {
    }

    public function testInvokeHandlesGithubSource(): void
    {
    }

    public function testInvokeHandlesStripeSource(): void
    {
    }

    public function testInvokeDoesNothingWhenEntityNotFound(): void
    {
    }

    public function testGithubLogsEventTypeFromHeader(): void
    {
    }
}
