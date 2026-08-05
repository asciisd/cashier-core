<?php

namespace Asciisd\CashierCore\Drivers\Internal;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;

class BankTransferProvider implements PaymentProcessorInterface
{
    /**
     * Prefix applied to every bank wire transfer reference.
     */
    private const REFERENCE_PREFIX = 'BWT-';

    /**
     * Number of random characters appended after the prefix.
     */
    private const REFERENCE_RANDOM_LENGTH = 8;

    /**
     * Unambiguous character set for references — excludes easily confused
     * characters (0/O, 1/I/L) so clients can read and re-type them reliably.
     */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Create a pending deposit and return company bank details for the user.
     */
    public function charge(array $data): PaymentResult
    {
        $currency = $data['currency'] ?? 'USD';

        $bankAccounts = $this->bankAccounts();

        if ($bankAccounts === []) {
            throw new PaymentProcessingException('No company bank accounts are configured for wire transfers.');
        }

        $reference = $this->generateUniqueReference();

        return new PaymentResult(
            success: true,
            transactionId: $reference,
            status: PaymentStatus::Pending,
            amount: (int) $data['amount'],
            currency: $currency,
            message: 'Bank wire transfer initiated. Please transfer funds using the provided bank details.',
            metadata: [
                'provider_type' => 'bank_transfer',
                'company_bank_accounts' => $bankAccounts,
                'reference' => $reference,
            ],
            paymentMethodSnapshot: PaymentMethodSnapshot::fromBankTransfer('wire_transfer', 'Bank Wire Transfer'),
        );
    }

    /**
     * The company bank accounts shown to the customer, as plain arrays.
     *
     * Read from the connection config (`bank_accounts`) by default. Hosts that
     * keep their accounts in a model override this and map it here — the
     * provider stays ignorant of where the details live.
     *
     * @return list<array<string, mixed>>
     */
    protected function bankAccounts(): array
    {
        return array_values((array) ($this->config['bank_accounts'] ?? []));
    }

    /**
     * Generate a short, human-readable reference that is unique across
     * existing bank transfer transactions.
     */
    private function generateUniqueReference(): string
    {
        $model = Cashier::transactionModel();

        do {
            $reference = self::REFERENCE_PREFIX.$this->randomReferenceCode();
        } while (
            $model::query()
                ->withoutGlobalScopes($model::cashierBypassedScopes())
                ->where('provider_transaction_id', $reference)
                ->exists()
        );

        return $reference;
    }

    /**
     * Build the random portion of the reference from the unambiguous alphabet.
     */
    private function randomReferenceCode(): string
    {
        $alphabet = self::REFERENCE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < self::REFERENCE_RANDOM_LENGTH; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    public function refund(string $transactionId, ?int $amount = null): RefundResult
    {
        throw new PaymentProcessingException('Bank transfer provider does not support automatic refunds');
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        return null;
    }

    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        throw new PaymentProcessingException('Bank transfer provider does not support webhooks');
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return false;
    }

    public function supports(string $feature): bool
    {
        return $feature === 'charge';
    }

    public function capture(string $transactionId, ?int $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('Not supported');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('Not supported');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('Not supported');
    }

    public function getPaymentStatus(string $transactionId): string
    {
        throw new \BadMethodCallException('Not supported');
    }

    public function validatePaymentData(array $data): array
    {
        return $data;
    }

    public function getName(): string
    {
        return 'bank_transfer';
    }
}
