<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface BillingTransformerInterface
{
    /**
     * Transform payment data by auto-populating billing details from user.
     */
    public function transform(Authenticatable $user, array $paymentData): array;

    /**
     * Check if this transformer can handle the given processor.
     */
    public function canHandle(string $processor): bool;

    /**
     * Get the processor name this transformer handles.
     */
    public function getProcessorName(): string;
}
