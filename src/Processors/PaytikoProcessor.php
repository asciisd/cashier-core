<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Processors;

use Asciisd\CashierCore\Abstracts\AbstractPaymentProcessor;
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\RefundStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;

class PaytikoProcessor extends AbstractPaymentProcessor
{
    protected array $supportedFeatures = [
        'charge',
        'refund',
        'webhooks',
    ];

    public function getName(): string
    {
        return 'paytiko';
    }

    public function charge(array $data): PaymentResult
    {
        try {
            $validatedData = $this->validatePaymentData($data);

            // Simulate Paytiko API call
            $response = $this->makePaytikoApiCall('payments', [
                'amount' => $validatedData['amount'],
                'currency' => strtoupper($validatedData['currency']),
                'payment_method' => $validatedData['payment_method'] ?? 'fawry',
                'description' => $validatedData['description'] ?? 'Payment via Paytiko',
                'metadata' => $validatedData['metadata'] ?? [],
            ]);

            if ($response['status'] === 'completed') {
                // Extract payment method info from Paytiko response
                $paymentMethodSnapshot = $this->extractPaymentMethodFromPaytikoResponse($response);
                
                return $this->createSuccessResult(
                    transactionId: $response['transaction_id'],
                    amount: $validatedData['amount'],
                    currency: $validatedData['currency'],
                    message: 'Payment processed successfully via Paytiko',
                    metadata: $response['metadata'] ?? null,
                    paymentMethodSnapshot: $paymentMethodSnapshot
                );
            }

            return $this->createFailureResult(
                transactionId: $response['transaction_id'] ?? 'unknown',
                amount: $validatedData['amount'],
                currency: $validatedData['currency'],
                message: $response['error_message'] ?? 'Paytiko payment failed',
                errorCode: $response['error_code'] ?? null
            );

        } catch (\Exception $e) {
            throw new PaymentProcessingException(
                message: 'Paytiko payment processing failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function refund(string $transactionId, ?int $amount = null): RefundResult
    {
        try {
            $refundData = ['transaction_id' => $transactionId];
            
            if ($amount !== null) {
                $refundData['amount'] = $amount;
            }

            // Simulate Paytiko refund API call
            $response = $this->makePaytikoApiCall('refunds', $refundData);

            return new RefundResult(
                success: $response['status'] === 'completed',
                refundId: $response['refund_id'],
                originalTransactionId: $transactionId,
                status: $response['status'] === 'completed' ? RefundStatus::Succeeded : RefundStatus::Failed,
                amount: $response['amount'],
                currency: $response['currency'],
                message: $response['status'] === 'completed' ? 'Refund processed successfully' : 'Refund failed'
            );

        } catch (\Exception $e) {
            throw new PaymentProcessingException(
                message: 'Paytiko refund processing failed: ' . $e->getMessage(),
                transactionId: $transactionId,
                previous: $e
            );
        }
    }

    public function getPaymentStatus(string $transactionId): string
    {
        // Simulate Paytiko get payment status API call
        $response = $this->makePaytikoApiCall("payments/{$transactionId}");
        
        return match ($response['status']) {
            'completed' => PaymentStatus::Succeeded->value,
            'pending' => PaymentStatus::Pending->value,
            'failed', 'cancelled' => PaymentStatus::Failed->value,
            default => PaymentStatus::Processing->value,
        };
    }

    protected function getValidationRules(): array
    {
        return array_merge(parent::getValidationRules(), [
            'payment_method' => 'sometimes|string|in:fawry,vodafone,orange,etisalat,instapay,valu,binance_pay,wire_transfer',
            'customer_phone' => 'sometimes|string',
            'customer_email' => 'sometimes|email',
            'metadata' => 'sometimes|array',
        ]);
    }

    /**
     * Simulate Paytiko API call (in real implementation, use Paytiko SDK/HTTP client)
     */
    private function makePaytikoApiCall(string $endpoint, array $data = []): array
    {
        // This is a mock implementation
        // In real implementation, you would use Paytiko API here
        
        if (str_contains($endpoint, 'payments') && !str_contains($endpoint, '/')) {
            $paymentMethod = $data['payment_method'] ?? 'fawry';
            
            return [
                'transaction_id' => 'ptk_' . uniqid(),
                'status' => 'completed',
                'amount' => $data['amount'] ?? 1000,
                'currency' => $data['currency'] ?? 'EGP',
                'payment_method' => [
                    'type' => $paymentMethod,
                    'provider' => $this->getProviderName($paymentMethod),
                    'last_four' => $this->getLastFour($paymentMethod),
                ],
                'metadata' => $data['metadata'] ?? [],
            ];
        }

        if (str_contains($endpoint, 'refunds')) {
            return [
                'refund_id' => 'ref_' . uniqid(),
                'status' => 'completed',
                'amount' => $data['amount'] ?? 1000,
                'currency' => 'EGP',
            ];
        }

        return [
            'transaction_id' => 'mock_' . uniqid(),
            'status' => 'completed',
        ];
    }

    private function extractPaymentMethodFromPaytikoResponse(array $response): PaymentMethodSnapshot
    {
        $paymentMethod = $response['payment_method'];
        $type = $paymentMethod['type'];
        
        return match ($type) {
            'fawry' => PaymentMethodSnapshot::fromDigitalWallet('fawry'),
            'vodafone' => PaymentMethodSnapshot::fromDigitalWallet('vodafone'),
            'orange' => PaymentMethodSnapshot::fromDigitalWallet('orange'),
            'etisalat' => PaymentMethodSnapshot::fromDigitalWallet('etisalat'),
            'instapay' => PaymentMethodSnapshot::fromDigitalWallet('instapay'),
            'valu' => PaymentMethodSnapshot::fromDigitalWallet('valu'),
            'binance_pay' => PaymentMethodSnapshot::fromDigitalWallet('binance_pay'),
            'wire_transfer' => PaymentMethodSnapshot::fromBankTransfer('wire_transfer'),
            'visa' => PaymentMethodSnapshot::fromCardData('visa', $paymentMethod['last_four'] ?? '0000'),
            'mastercard' => PaymentMethodSnapshot::fromCardData('mastercard', $paymentMethod['last_four'] ?? '0000'),
            default => PaymentMethodSnapshot::fromDigitalWallet('other'),
        };
    }

    private function getProviderName(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'fawry' => 'Fawry',
            'vodafone' => 'Vodafone Cash',
            'orange' => 'Orange Money',
            'etisalat' => 'Etisalat Cash',
            'instapay' => 'InstaPay',
            'valu' => 'valU',
            'binance_pay' => 'Binance Pay',
            'wire_transfer' => 'Wire Transfer',
            default => 'Unknown',
        };
    }

    private function getLastFour(string $paymentMethod): ?string
    {
        // Only card payments have last four digits
        return in_array($paymentMethod, ['visa', 'mastercard']) ? '0000' : null;
    }
}
