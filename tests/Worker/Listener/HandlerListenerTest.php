<?php

namespace App\Tests\Worker\Listener;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
final class HandlerListenerTest extends KernelTestCase
{
    public function testOnDispatchedMarksEntityDispatched(): void
    {
    }

    public function testOnSucceededMarksEntitySucceeded(): void
    {
    }

    public function testOnFailureMarksEntityFailedWhenWillRetry(): void
    {
    }

    public function testOnFailureDoesNothingWhenWillNotRetry(): void
    {
    }

    public function testOnDeadMarksEntityDeadWhenWillNotRetry(): void
    {
    }

    public function testOnDeadDoesNothingWhenWillRetry(): void
    {
    }
}
