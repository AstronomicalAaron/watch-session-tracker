<?php

declare(strict_types=1);

namespace App\Psr;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
