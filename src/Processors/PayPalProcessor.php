<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Processors;

use Asciisd\CashierCore\Abstracts\AbstractPaymentProcessor;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\RefundStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;

class PayPalProcessor extends AbstractPaymentProcessor
{
    protected array $supportedFeatures = [
        'charge',
        'refund',
        'capture',
        'authorize',
        'webhooks',
    ];

    public function getName(): string
    {
        return 'paypal';
    }

    public function charge(array $data): PaymentResult
    {
        try {
            $validatedData = $this->validatePaymentData($data);

            // Simulate PayPal API call
            $response = $this->makePayPalApiCall('payments/payment', [
                'intent' => 'sale',
                'payer' => [
                    'payment_method' => 'paypal'
                ],
                'transactions' => [[
                    'amount' => [
                        'total' => $this->formatAmount($validatedData['amount']),
                        'currency' => strtoupper($validatedData['currency'])
                    ],
                    'description' => $validatedData['description'] ?? 'Payment via PayPal'
                ]],
                'redirect_urls' => [
                    'return_url' => $this->getConfig('return_url', 'https://example.com/return'),
                    'cancel_url' => $this->getConfig('cancel_url', 'https://example.com/cancel')
                ]
            ]);

            if ($response['state'] === 'approved') {
                return $this->createSuccessResult(
                    transactionId: $response['id'],
                    amount: $validatedData['amount'],
                    currency: $validatedData['currency'],
                    message: 'Payment processed successfully via PayPal'
                );
            }

            return $this->createFailureResult(
                transactionId: $response['id'] ?? 'unknown',
                amount: $validatedData['amount'],
                currency: $validatedData['currency'],
                message: 'PayPal payment failed',
                errorCode: $response['failure_reason'] ?? null
            );

        } catch (\Exception $e) {
            throw new PaymentProcessingException(
                message: 'PayPal payment processing failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function refund(string $transactionId, ?int $amount = null): RefundResult
    {
        try {
            $refundData = [];
            
            if ($amount !== null) {
                $refundData['amount'] = [
                    'total' => $this->formatAmount($amount),
                    'currency' => 'USD' // Should be retrieved from original transaction
                ];
            }

            // Simulate PayPal refund API call
            $response = $this->makePayPalApiCall("payments/sale/{$transactionId}/refund", $refundData);

            return new RefundResult(
                success: $response['state'] === 'completed',
                refundId: $response['id'],
                originalTransactionId: $transactionId,
                status: $response['state'] === 'completed' ? RefundStatus::Succeeded : RefundStatus::Failed,
                amount: $this->parseAmount($response['amount']['total']),
                currency: $response['amount']['currency'],
                message: $response['state'] === 'completed' ? 'Refund processed successfully' : 'Refund failed'
            );

        } catch (\Exception $e) {
            throw new PaymentProcessingException(
                message: 'PayPal refund processing failed: ' . $e->getMessage(),
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
                $captureData['amount'] = [
                    'total' => $this->formatAmount($amount),
                    'currency' => 'USD'
                ];
            }

            // Simulate PayPal capture API call
            $response = $this->makePayPalApiCall("payments/authorization/{$transactionId}/capture", $captureData);

            return new PaymentResult(
                success: $response['state'] === 'completed',
                transactionId: $response['id'],
                status: $response['state'] === 'completed' ? PaymentStatus::Succeeded : PaymentStatus::Failed,
                amount: $this->parseAmount($response['amount']['total']),
                currency: $response['amount']['currency'],
                message: $response['state'] === 'completed' ? 'Payment captured successfully' : 'Capture failed'
            );

        } catch (\Exception $e) {
            throw new PaymentProcessingException(
                message: 'PayPal capture failed: ' . $e->getMessage(),
                transactionId: $transactionId,
                previous: $e
            );
        }
    }

    public function authorize(array $data): PaymentResult
    {
        $validatedData = $this->validatePaymentData($data);

        // Simulate PayPal authorization
        $response = $this->makePayPalApiCall('payments/payment', [
            'intent' => 'authorize',
            'payer' => [
                'payment_method' => 'paypal'
            ],
            'transactions' => [[
                'amount' => [
                    'total' => $this->formatAmount($validatedData['amount']),
                    'currency' => strtoupper($validatedData['currency'])
                ],
                'description' => $validatedData['description'] ?? 'Authorization via PayPal'
            ]],
        ]);

        return new PaymentResult(
            success: $response['state'] === 'approved',
            transactionId: $response['id'],
            status: $response['state'] === 'approved' ? PaymentStatus::RequiresCapture : PaymentStatus::Failed,
            amount: $validatedData['amount'],
            currency: $validatedData['currency'],
            message: $response['state'] === 'approved' ? 'Payment authorized successfully' : 'Authorization failed'
        );
    }

    public function getPaymentStatus(string $transactionId): string
    {
        // Simulate PayPal get payment API call
        $response = $this->makePayPalApiCall("payments/payment/{$transactionId}");
        
        return match ($response['state']) {
            'approved', 'completed' => PaymentStatus::Succeeded->value,
            'created' => PaymentStatus::Pending->value,
            'failed', 'cancelled' => PaymentStatus::Failed->value,
            default => PaymentStatus::Processing->value,
        };
    }

    protected function getValidationRules(): array
    {
        return array_merge(parent::getValidationRules(), [
            'payer_id' => 'sometimes|string',
            'payment_method' => 'sometimes|string',
            'return_url' => 'sometimes|url',
            'cancel_url' => 'sometimes|url',
        ]);
    }

    /**
     * Simulate PayPal API call (in real implementation, use PayPal SDK)
     */
    private function makePayPalApiCall(string $endpoint, array $data = []): array
    {
        // This is a mock implementation
        // In real implementation, you would use PayPal SDK here
        
        if (str_contains($endpoint, 'payments/payment')) {
            return [
                'id' => 'PAY-' . uniqid(),
                'state' => 'approved',
                'intent' => $data['intent'] ?? 'sale',
                'transactions' => $data['transactions'] ?? [],
            ];
        }

        if (str_contains($endpoint, 'refund')) {
            return [
                'id' => 'REF-' . uniqid(),
                'state' => 'completed',
                'amount' => [
                    'total' => '10.00',
                    'currency' => 'USD'
                ],
            ];
        }

        if (str_contains($endpoint, 'capture')) {
            return [
                'id' => 'CAP-' . uniqid(),
                'state' => 'completed',
                'amount' => [
                    'total' => '10.00',
                    'currency' => 'USD'
                ],
            ];
        }

        return [
            'id' => 'MOCK-' . uniqid(),
            'state' => 'approved',
        ];
    }
}
