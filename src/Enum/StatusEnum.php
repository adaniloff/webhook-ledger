<?php

namespace App\Enum;

enum StatusEnum: string
{
    case RECEIVED = 'r';
    case DISPATCHED = 'dis';
    case SUCCEEDED = 's';
    case FAILED = 'f';
    case DEAD = 'dead';

    public function canReplay(): bool
    {
        return self::DEAD === $this;
    }
}
