<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests\Fixtures;

/**
 * Stands in for a host's display enum over the engine's driver strings — a
 * backed enum with no string conversion, which is the whole point.
 */
enum HostProvider: string
{
    case Aps = 'aps';
    case Manual = 'manual';
}
