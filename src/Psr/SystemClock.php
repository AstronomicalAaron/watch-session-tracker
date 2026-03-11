<?php

declare(strict_types=1);

namespace App\Psr;

class SystemClock implements ClockInterface
{
    public const string DATE_FORMAT = 'Y-m-d\TH:i:s.v\Z';

    /**
     * @return \DateTimeImmutable
     * @throws \DateMalformedStringException
     */
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
