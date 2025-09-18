<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Abstracts;

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Exceptions\InvalidPaymentDataException;
use Illuminate\Support\Facades\Validator;

abstract class AbstractPaymentProcessor implements PaymentProcessorInterface
{
    protected array $config;
    protected array $supportedFeatures = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    abstract public function charge(array $data): PaymentResult;

    abstract public function refund(string $transactionId, ?int $amount = null): RefundResult;

    public function capture(string $transactionId, ?int $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('Capture method not implemented for ' . $this->getName());
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('Authorize method not implemented for ' . $this->getName());
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('Void method not implemented for ' . $this->getName());
    }

    public function getPaymentStatus(string $transactionId): string
    {
        throw new \BadMethodCallException('Get payment status method not implemented for ' . $this->getName());
    }

    public function validatePaymentData(array $data): array
    {
        $rules = $this->getValidationRules();
        
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new InvalidPaymentDataException(
                'Invalid payment data: ' . implode(', ', $validator->errors()->all())
            );
        }

        return $validator->validated();
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures);
    }

    protected function getValidationRules(): array
    {
        return [
            'amount' => 'required|integer|min:1',
            'currency' => 'required|string|size:3',
            'description' => 'sometimes|string|max:255',
        ];
    }

    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    protected function formatAmount(int $amount): float
    {
        return $amount / 100;
    }

    protected function parseAmount(float $amount): int
    {
        return (int) ($amount * 100);
    }

    protected function createSuccessResult(
        string $transactionId,
        int $amount,
        string $currency,
        ?string $message = null,
        ?array $metadata = null
    ): PaymentResult {
        return new PaymentResult(
            success: true,
            transactionId: $transactionId,
            status: PaymentStatus::Succeeded,
            amount: $amount,
            currency: $currency,
            message: $message,
            metadata: $metadata
        );
    }

    protected function createFailureResult(
        string $transactionId,
        int $amount,
        string $currency,
        string $message,
        ?string $errorCode = null,
        ?array $metadata = null
    ): PaymentResult {
        return new PaymentResult(
            success: false,
            transactionId: $transactionId,
            status: PaymentStatus::Failed,
            amount: $amount,
            currency: $currency,
            message: $message,
            metadata: $metadata,
            errorCode: $errorCode
        );
    }
}
