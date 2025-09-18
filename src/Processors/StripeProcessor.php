<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Processors;

use Asciisd\CashierCore\Abstracts\AbstractPaymentProcessor;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\RefundStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;

class StripeProcessor extends AbstractPaymentProcessor
{
    protected array $supportedFeatures = [
        'charge',
        'refund',
        'capture',
        'authorize',
        'void',
        'webhooks',
        'recurring',
    ];

    public function getName(): string
    {
        return 'stripe';
    }

    public function charge(array $data): PaymentResult
    {
        try {
            $validatedData = $this->validatePaymentData($data);

            // Simulate Stripe API call
            $response = $this->makeStripeApiCall('charges', [
                'amount' => $validatedData['amount'],
                'currency' => strtolower($validatedData['currency']),
                'source' => $validatedData['source'] ?? $validatedData['payment_method'] ?? null,
                'description' => $validatedData['description'] ?? 'Payment via Stripe',
                'metadata' => $validatedData['metadata'] ?? [],
            ]);

            if ($response['status'] === 'succeeded') {
                return $this->createSuccessResult(
                    transactionId: $response['id'],
                    amount: $validatedData['amount'],
                    currency: $validatedData['currency'],
                    message: 'Payment processed successfully',
                    metadata: $response['metadata'] ?? null
                );
            }

            return $this->createFailureResult(
                transactionId: $response['id'] ?? 'unknown',
                amount: $validatedData['amount'],
                currency: $validatedData['currency'],
                message: $response['failure_message'] ?? 'Payment failed',
                errorCode: $response['failure_code'] ?? null
            );

        } catch (\Exception $e) {
            throw new PaymentProcessingException(
                message: 'Stripe payment processing failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function refund(string $transactionId, ?int $amount = null): RefundResult
    {
        try {
            $refundData = ['charge' => $transactionId];
            
            if ($amount !== null) {
                $refundData['amount'] = $amount;
            }

            // Simulate Stripe refund API call
            $response = $this->makeStripeApiCall('refunds', $refundData);

            return new RefundResult(
                success: $response['status'] === 'succeeded',
                refundId: $response['id'],
                originalTransactionId: $transactionId,
                status: $response['status'] === 'succeeded' ? RefundStatus::Succeeded : RefundStatus::Failed,
                amount: $response['amount'],
                currency: $response['currency'],
                message: $response['status'] === 'succeeded' ? 'Refund processed successfully' : 'Refund failed'
            );

        } catch (\Exception $e) {
            throw new PaymentProcessingException(
                message: 'Stripe refund processing failed: ' . $e->getMessage(),
                transactionId: $transactionId,
                previous: $e
            );
        }
    }

    public function capture(string $transactionId, ?int $amount = null): PaymentResult
    {
        try {
            $captureData = [];
            
            if ($amount !== null) {
                $captureData['amount'] = $amount;
            }

            // Simulate Stripe capture API call
            $response = $this->makeStripeApiCall("charges/{$transactionId}/capture", $captureData);

            return new PaymentResult(
                success: $response['captured'],
                transactionId: $response['id'],
                status: $response['captured'] ? PaymentStatus::Succeeded : PaymentStatus::Failed,
                amount: $response['amount'],
                currency: $response['currency'],
                message: $response['captured'] ? 'Payment captured successfully' : 'Capture failed'
            );

        } catch (\Exception $e) {
            throw new PaymentProcessingException(
                message: 'Stripe capture failed: ' . $e->getMessage(),
                transactionId: $transactionId,
                previous: $e
            );
        }
    }

    public function authorize(array $data): PaymentResult
    {
        $data['capture'] = false;
        return $this->charge($data);
    }

    public function void(string $transactionId): PaymentResult
    {
        return $this->refund($transactionId);
    }

    public function getPaymentStatus(string $transactionId): string
    {
        // Simulate Stripe retrieve API call
        $response = $this->makeStripeApiCall("charges/{$transactionId}");
        
        return match ($response['status']) {
            'succeeded' => PaymentStatus::Succeeded->value,
            'pending' => PaymentStatus::Pending->value,
            'failed' => PaymentStatus::Failed->value,
            default => PaymentStatus::Processing->value,
        };
    }

    protected function getValidationRules(): array
    {
        return array_merge(parent::getValidationRules(), [
            'source' => 'sometimes|string',
            'payment_method' => 'sometimes|string',
            'customer' => 'sometimes|string',
            'metadata' => 'sometimes|array',
        ]);
    }

    /**
     * Simulate Stripe API call (in real implementation, use Stripe SDK)
     */
    private function makeStripeApiCall(string $endpoint, array $data = []): array
    {
        // This is a mock implementation
        // In real implementation, you would use Stripe SDK here
        
        if (str_contains($endpoint, 'charges')) {
            return [
                'id' => 'ch_' . uniqid(),
                'status' => 'succeeded',
                'amount' => $data['amount'] ?? 1000,
                'currency' => $data['currency'] ?? 'usd',
                'captured' => !isset($data['capture']) || $data['capture'],
                'metadata' => $data['metadata'] ?? [],
            ];
        }

        if (str_contains($endpoint, 'refunds')) {
            return [
                'id' => 're_' . uniqid(),
                'status' => 'succeeded',
                'amount' => $data['amount'] ?? 1000,
                'currency' => 'usd',
            ];
        }

        return [
            'id' => 'mock_' . uniqid(),
            'status' => 'succeeded',
        ];
    }
}
