<?php

declare(strict_types=1);

namespace App\DTO;

enum ZmqStatus: string
{
    case OK = 'ok';
    case ERROR = 'error';
    case TIMEOUT = 'timeout';
}
