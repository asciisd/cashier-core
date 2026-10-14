<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Xoala;

use Asciisd\CashierCore\Contracts\PaymentAdapterInterface;
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

class XoalaAdapter implements PaymentAdapterInterface
{
    /**
     * Xoala's long transaction statuses, from the Status Description page.
     *
     * `reversed` and `chargeback` map to Canceled on this package's own
     * authority: WebhookProcessor documents that "a settled deposit may only
     * move to Canceled (refund/chargeback)" and drops anything else arriving
     * after Succeeded as out-of-order. Any other mapping makes a chargeback a
     * silent no-op.
     *
     * The payout statuses (payoutsuccessful/payoutstarted/payoutfailed) are
     * deliberately absent — they cannot reach a deposit, and listing them would
     * imply a payout scope this driver does not have.
     *
     * @var array<string, PaymentStatus>
     */
    private const LONG_STATUSES = [
        'capturesuccess' => PaymentStatus::Succeeded,
        'settled' => PaymentStatus::Succeeded,
        'authsuccessful' => PaymentStatus::Succeeded,

        'begun' => PaymentStatus::Pending,
        'authstarted' => PaymentStatus::Pending,
        'capturestarted' => PaymentStatus::Pending,
        'cancelstarted' => PaymentStatus::Pending,
        'markedforreversal' => PaymentStatus::Pending,

        'authfailed' => PaymentStatus::Failed,
        'capturefailed' => PaymentStatus::Failed,
        'failed' => PaymentStatus::Failed,

        'cancelled' => PaymentStatus::Canceled,
        'authcancelled' => PaymentStatus::Canceled,
        'reversed' => PaymentStatus::Canceled,
        'chargeback' => PaymentStatus::Canceled,
    ];

    /**
     * The short statuses, as sent in `transactionStatus` (and in `status` on a
     * redirect-back POST). `3D` means the customer is still at the ACS page.
     *
     * @var array<string, PaymentStatus>
     */
    private const SHORT_STATUSES = [
        'y' => PaymentStatus::Succeeded,
        'n' => PaymentStatus::Failed,
        'p' => PaymentStatus::Pending,
        '3d' => PaymentStatus::Pending,
        'c' => PaymentStatus::Canceled,
    ];

    /**
     * Turn the charge context the provider assembled into a Pending result.
     *
     * There is no provider response to transform: Standard Checkout has no
     * server-to-server leg, so `charge()` never calls Xoala. What arrives here
     * is what we decided to send, plus the bridge URL that will send it.
     *
     * `success: true` is not a claim that the money arrived — it means "nothing
     * failed". PaymentService::createTransactionRecord() stamps `failed_at`
     * from `isFailed()`, i.e. from `! success` alone, so a Pending hosted-page
     * charge reporting false would be written to the database as failed the
     * moment it is created.
     */
    public function fromProviderResponse(mixed $response): PaymentResult
    {
        /** @var array<string, mixed> $response */
        return new PaymentResult(
            success: true,
            transactionId: (string) $response['merchant_transaction_id'],
            status: PaymentStatus::Pending,
            amount: $this->wholeUnits($response['amount'] ?? null) ?? 0,
            currency: (string) ($response['currency'] ?? config('cashier-core.currency.default', 'USD')),
            metadata: array_filter([
                'redirect_url' => $response['redirect_url'] ?? null,
                // The optional extras the bridge re-renders. Everything the
                // checksum covers is deliberately NOT here: it lives on the
                // transaction row, so each signed value has one source.
                'xoala_fields' => $response['fields'] ?? null,
            ], fn ($value) => $value !== null && $value !== []),
            processorResponse: $response,
        );
    }

    /**
     * Transform an inquiry (`paymentType=IN`) response.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromProviderPayload(string $transactionId, array $payload): PaymentResult
    {
        $status = $this->statusFor($payload);

        return new PaymentResult(
            success: $status === PaymentStatus::Succeeded,
            transactionId: $transactionId,
            status: $status,
            amount: $this->wholeUnits($payload['amount'] ?? null) ?? 0,
            currency: (string) ($payload['currency'] ?? config('cashier-core.currency.default', 'USD')),
            message: $this->resultDescription($payload),
            metadata: $this->metadataFrom($payload),
            processorResponse: $payload,
            paymentMethodSnapshot: $this->snapshot($payload),
        );
    }

    /**
     * Transform a notification callback.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromWebhook(array $payload): TransactionWebhookUpdate
    {
        $status = $this->statusFor($payload);
        // Canceled covers reversed/chargeback as well as an outright
        // cancellation, and WebhookProcessor writes error_code/error_message
        // on a Canceled transition too — a chargeback is the one status where
        // an operator most needs the reason, so it must carry one here.
        $carriesReason = $status === PaymentStatus::Failed || $status === PaymentStatus::Canceled;

        return new TransactionWebhookUpdate(
            status: $status,
            processorResponse: $payload,
            paymentMethodSnapshot: $this->snapshot($payload),
            metadata: $this->metadataFrom($payload),
            errorCode: $carriesReason ? $this->resultCode($payload) : null,
            errorMessage: $carriesReason ? $this->resultDescription($payload) : null,
            // float, not int: TransactionWebhookUpdate keeps the decimals, and
            // the reconciliation guard in WebhookProcessor compares against the
            // invoice with them.
            amount: isset($payload['amount']) && $payload['amount'] !== ''
                ? (float) $payload['amount']
                : null,
            currency: ($payload['currency'] ?? null) ?: null,
        );
    }

    public function mapStatus(mixed $providerStatus): PaymentStatus
    {
        return self::LONG_STATUSES[strtolower(trim((string) $providerStatus))] ?? PaymentStatus::Pending;
    }

    public function mapShortStatus(mixed $providerStatus): PaymentStatus
    {
        return self::SHORT_STATUSES[strtolower(trim((string) $providerStatus))] ?? PaymentStatus::Pending;
    }

    public function getProviderName(): string
    {
        return 'xoala';
    }

    /**
     * The status of a payload carrying both forms.
     *
     * The long status wins where we recognise it — it distinguishes
     * `authsuccessful` from `capturesuccess`, which the short form flattens to
     * `Y`. Where it is absent OR unknown, the short form decides: a status
     * Xoala adds later must not read as Pending while the payload states the
     * outcome plainly two fields away.
     *
     * @param  array<string, mixed>  $payload
     */
    private function statusFor(array $payload): PaymentStatus
    {
        $long = strtolower(trim((string) ($payload['status'] ?? '')));

        if (isset(self::LONG_STATUSES[$long])) {
            return self::LONG_STATUSES[$long];
        }

        return $this->mapShortStatus($payload['transactionStatus'] ?? null);
    }

    /**
     * Xoala amounts are major units; PaymentResult::$amount is a whole-unit int.
     */
    private function wholeUnits(mixed $amount): ?int
    {
        return $amount === null || $amount === '' ? null : (int) round((float) $amount);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadataFrom(array $payload): array
    {
        return array_filter([
            // Kept because a refund or reversal keys on it and nothing else we
            // hold carries it — provider_transaction_id is our own id.
            'xoala_payment_id' => $payload['paymentId'] ?? null,
            'xoala_result_code' => $this->resultCode($payload),
            'xoala_bank_reference_id' => $payload['bankReferenceId'] ?? null,
            'xoala_terminal_id' => $payload['terminalId'] ?? null,
            'xoala_remark' => $payload['remark'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resultCode(array $payload): ?string
    {
        $code = data_get($payload, 'result.code') ?? $payload['resultCode'] ?? null;

        return $code === null || $code === '' ? null : (string) $code;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resultDescription(array $payload): ?string
    {
        $description = data_get($payload, 'result.description')
            ?? $payload['resultDescription']
            ?? null;

        return $description === null || $description === '' ? null : (string) $description;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function snapshot(array $payload): ?PaymentMethodSnapshot
    {
        $mode = strtoupper(trim((string) ($payload['paymentMode'] ?? '')));
        $brand = strtolower(trim((string) ($payload['paymentBrand'] ?? '')));
        $lastFour = (string) (data_get($payload, 'card.last4Digits')
            ?? data_get($payload, 'card.lastFourDigits')
            ?? $payload['cardLast4Digits']
            ?? '');

        if ($mode === '' && $brand === '' && $lastFour === '') {
            return null;
        }

        return new PaymentMethodSnapshot(
            type: $this->methodType($mode),
            brand: $this->brand($brand),
            lastFour: $lastFour !== '' ? $lastFour : null,
            displayName: $this->displayName($payload['paymentBrand'] ?? null, $lastFour),
        );
    }

    /**
     * Xoala's `paymentMode` codes: CC credit card, NB net banking / bank
     * transfer, EW e-wallet, SEPA direct debit, PV prepaid voucher, MMA mobile
     * money.
     */
    private function methodType(string $mode): PaymentMethodType
    {
        return match ($mode) {
            'CC' => PaymentMethodType::CreditCard,
            'DC' => PaymentMethodType::DebitCard,
            'NB', 'BT', 'SEPA' => PaymentMethodType::BankTransfer,
            'EW', 'MMA' => PaymentMethodType::DigitalWallet,
            'PV' => PaymentMethodType::Cash,
            default => PaymentMethodType::Other,
        };
    }

    private function brand(string $brand): PaymentMethodBrand
    {
        return match ($brand) {
            'visa' => PaymentMethodBrand::Visa,
            'mc', 'mastercard' => PaymentMethodBrand::Mastercard,
            'amex' => PaymentMethodBrand::AmericanExpress,
            'jcb' => PaymentMethodBrand::JCB,
            'cup', 'unionpay' => PaymentMethodBrand::UnionPay,
            'diners' => PaymentMethodBrand::DinersClub,
            'discover' => PaymentMethodBrand::Discover,
            'sepaexpress', 'sepa', 'directdebit' => PaymentMethodBrand::SEPA,
            'applepay' => PaymentMethodBrand::ApplePay,
            'googlepay' => PaymentMethodBrand::GooglePay,
            default => PaymentMethodBrand::Other,
        };
    }

    private function displayName(mixed $brand, string $lastFour): string
    {
        $label = trim((string) $brand) !== '' ? trim((string) $brand) : 'Xoala';

        return $lastFour === '' ? $label : "{$label} •••• {$lastFour}";
    }
}
