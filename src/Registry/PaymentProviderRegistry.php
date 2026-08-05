<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Registry;

use Asciisd\CashierCore\Connections\ConnectionRegistry;

/**
 * @deprecated since 2.0 — type-hint {@see ConnectionRegistry} instead. This
 *             subclass exists so 1.x hosts upgrade without touching every
 *             injection site; it will be removed in 3.0.
 */
class PaymentProviderRegistry extends ConnectionRegistry {}
