<?php

declare(strict_types=1);

namespace ZJKiza\HttpResponseValidator\Tests\Resources\Logger;

use Psr\Log\AbstractLogger;

final class TestLogger extends AbstractLogger
{
    public $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = \compact('level', 'message', 'context');
    }
}
