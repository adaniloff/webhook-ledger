<?php

namespace App\Enum;

enum StatusEnum: string
{
    case RECEIVED = 'r';
    case DISPATCHED = 'dis';
    case SUCCEEDED = 's';
    case FAILED = 'f';
    case DEAD = 'dead';
}
