<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Logging;

use Illuminate\Support\Facades\Log;

class TransactionLogger
{
    /**
     * The log channel every payment line goes to — configurable per host via
     * cashier-core.logging.channel; null falls through to the default channel.
     */
    protected static function channel(): \Psr\Log\LoggerInterface
    {
        $channel = config('cashier-core.logging.channel');

        // See PaymentLogger::channel() — the facade root keeps a host's
        // `Log::shouldReceive(...)` from returning null through `channel()`.
        return $channel ? Log::channel($channel) : Log::getFacadeRoot();
    }

    // --- Members TransactionController ---

    public static function mt5WithdrawalFailedDuringInternalTransfer(int $fromAccount, float $amount): void
    {
        self::channel()->error('MT5 withdrawal failed during internal transfer', [
            'from_account' => $fromAccount,
            'amount' => $amount,
        ]);
    }

    public static function mt5DepositFailedDuringInternalTransferAttemptingRollback(int $toAccount, float $amount): void
    {
        self::channel()->error('MT5 deposit failed during internal transfer, attempting rollback', [
            'to_account' => $toAccount,
            'amount' => $amount,
        ]);
    }

    public static function internalTransferFailed(int $userId, string $error): void
    {
        self::channel()->error('Internal transfer failed', [
            'user_id' => $userId,
            'error' => $error,
        ]);
    }

    // --- API V1 TransactionController ---

    public static function depositProcessedViaApi(int $userId, ?string $transactionId, mixed $amount): void
    {
        self::channel()->info('Deposit processed via API', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
        ]);
    }

    public static function invalidDepositDataViaApi(int $userId, string $error): void
    {
        self::channel()->error('Invalid deposit data via API', [
            'user_id' => $userId,
            'error' => $error,
        ]);
    }

    public static function depositProcessingFailedViaApi(int $userId, string $error): void
    {
        self::channel()->error('Deposit processing failed via API', [
            'user_id' => $userId,
            'error' => $error,
        ]);
    }

    public static function paymentProviderNotFoundViaApi(int $userId, string $error): void
    {
        self::channel()->error('Payment provider not found via API', [
            'user_id' => $userId,
            'error' => $error,
        ]);
    }

    public static function withdrawalRequestedViaApi(int $userId, int $transactionId, mixed $amount, string $withdrawalMethod): void
    {
        self::channel()->info('Withdrawal requested via API', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'withdrawal_method' => $withdrawalMethod,
        ]);
    }

    public static function withdrawalRequestFailedViaApi(int $userId, string $error): void
    {
        self::channel()->error('Withdrawal request failed via API', [
            'user_id' => $userId,
            'error' => $error,
        ]);
    }

    // --- Members TransactionController::withdraw ---

    public static function mt5WithdrawalFailedForWithdrawalRequest(int $userId, int $tradingAccountLogin, float $amount): void
    {
        self::channel()->error('MT5 withdrawal failed for member withdrawal request', [
            'user_id' => $userId,
            'trading_account_login' => $tradingAccountLogin,
            'amount' => $amount,
        ]);
    }

    public static function withdrawalDbPersistFailedRollbackAttempted(int $userId, int $tradingAccountLogin, float $amount, string $error): void
    {
        self::channel()->critical('Withdrawal DB persist failed after MT5 deduction - rolling back MT5', [
            'user_id' => $userId,
            'trading_account_login' => $tradingAccountLogin,
            'amount' => $amount,
            'error' => $error,
        ]);
    }

    public static function withdrawalRollbackFailed(int $userId, int $tradingAccountLogin, float $amount): void
    {
        self::channel()->critical('MT5 rollback (re-deposit) failed after withdrawal DB error - manual intervention required', [
            'user_id' => $userId,
            'trading_account_login' => $tradingAccountLogin,
            'amount' => $amount,
        ]);
    }

    // --- WithdrawalService ---

    public static function withdrawalRequestCreated(
        int $userId,
        int $transactionId,
        mixed $amount,
        string $withdrawalMethod,
        ?int $tradingAccountId,
        ?string $withdrawalReason,
    ): void {
        self::channel()->info('Withdrawal request created', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'withdrawal_method' => $withdrawalMethod,
            'trading_account_id' => $tradingAccountId,
            'withdrawal_reason' => $withdrawalReason,
        ]);
    }

    // --- Nova: Withdrawal admin actions ---

    public static function withdrawalApproved(
        int $adminId,
        int $transactionId,
        int $tradingAccountLogin,
        float $amount,
        string $mt5Ticket,
    ): void {
        self::channel()->info('Withdrawal approved by admin (MT5 debited)', [
            'admin_id' => $adminId,
            'transaction_id' => $transactionId,
            'trading_account_login' => $tradingAccountLogin,
            'amount' => $amount,
            'mt5_ticket_number' => $mt5Ticket,
        ]);
    }

    public static function withdrawalApproveMt5DebitFailed(
        int $adminId,
        int $transactionId,
        int $tradingAccountLogin,
        float $amount,
    ): void {
        self::channel()->error('MT5 debit failed while approving withdrawal; transaction left Pending', [
            'admin_id' => $adminId,
            'transaction_id' => $transactionId,
            'trading_account_login' => $tradingAccountLogin,
            'amount' => $amount,
        ]);
    }

    public static function withdrawalRejected(
        int $adminId,
        int $transactionId,
        ?string $reason,
        bool $mt5Refunded,
    ): void {
        self::channel()->info('Withdrawal rejected by admin', [
            'admin_id' => $adminId,
            'transaction_id' => $transactionId,
            'reason' => $reason,
            'mt5_refunded' => $mt5Refunded,
        ]);
    }

    public static function withdrawalRejectRefundFailed(
        int $adminId,
        int $transactionId,
        int $tradingAccountLogin,
        float $amount,
    ): void {
        self::channel()->critical('MT5 refund failed while rejecting withdrawal - manual intervention required', [
            'admin_id' => $adminId,
            'transaction_id' => $transactionId,
            'trading_account_login' => $tradingAccountLogin,
            'amount' => $amount,
        ]);
    }

    public static function withdrawalMarkedPaid(
        int $adminId,
        int $transactionId,
        ?string $payoutReference,
    ): void {
        self::channel()->info('Withdrawal marked as paid by admin', [
            'admin_id' => $adminId,
            'transaction_id' => $transactionId,
            'payout_reference' => $payoutReference,
        ]);
    }

    // --- TransferService ---

    public static function internalTransferCompleted(
        int $userId,
        int $transferFromId,
        int $transferToId,
        ?int $fromAccountLogin,
        ?int $toAccountLogin,
        float $amount,
    ): void {
        self::channel()->info('Internal transfer completed', [
            'user_id' => $userId,
            'transfer_from_id' => $transferFromId,
            'transfer_to_id' => $transferToId,
            'from_account' => $fromAccountLogin,
            'to_account' => $toAccountLogin,
            'amount' => $amount,
        ]);
    }

    // --- API V1 BankAccountController ---

    public static function bankAccountCreatedViaApi(int $userId, int $bankAccountId): void
    {
        self::channel()->info('Bank account created via API', [
            'user_id' => $userId,
            'bank_account_id' => $bankAccountId,
        ]);
    }

    public static function bankAccountCreationFailedViaApi(int $userId, string $error): void
    {
        self::channel()->error('Bank account creation failed via API', [
            'user_id' => $userId,
            'error' => $error,
        ]);
    }

    public static function bankAccountUpdatedViaApi(int $userId, int $bankAccountId): void
    {
        self::channel()->info('Bank account updated via API', [
            'user_id' => $userId,
            'bank_account_id' => $bankAccountId,
        ]);
    }

    public static function bankAccountUpdateFailedViaApi(int $userId, int $bankAccountId, string $error): void
    {
        self::channel()->error('Bank account update failed via API', [
            'user_id' => $userId,
            'bank_account_id' => $bankAccountId,
            'error' => $error,
        ]);
    }

    public static function bankAccountDeletedViaApi(int $userId, int $bankAccountId): void
    {
        self::channel()->info('Bank account deleted via API', [
            'user_id' => $userId,
            'bank_account_id' => $bankAccountId,
        ]);
    }

    public static function bankAccountDeletionFailedViaApi(int $userId, int $bankAccountId, string $error): void
    {
        self::channel()->error('Bank account deletion failed via API', [
            'user_id' => $userId,
            'bank_account_id' => $bankAccountId,
            'error' => $error,
        ]);
    }

    public static function withdrawalCancelledByUser(int $userId, int $transactionId, float $amount, bool $mt5Reversed): void
    {
        self::channel()->info('Withdrawal cancelled by user', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'mt5_reversed' => $mt5Reversed,
        ]);
    }

    public static function transactionDeletedByUser(int $userId, int $transactionId, string $status): void
    {
        self::channel()->info('Transaction soft deleted by user', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'status' => $status,
        ]);
    }

    public static function withdrawalCancelMt5ReversalFailed(int $userId, int $transactionId, int $login, float $amount): void
    {
        self::channel()->error('Withdrawal cancel MT5 reversal failed', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'login' => $login,
            'amount' => $amount,
        ]);
    }
}
