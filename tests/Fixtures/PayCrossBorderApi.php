<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests\Fixtures;

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A scripted PayCrossBorder API for payout tests, built from the examples in
 * docs.paycrossborder.com. Every endpoint answers from the options, so a test
 * states only what differs.
 *
 * Defaults: login succeeds with `pcb-token-1`; quotes echo the requested
 * payinAmount at rate 0.98; payouts are accepted as transReference 197930;
 * the enquiry reports 197930 as Payout Processing; the USD-USA Main Wallet
 * holds 5000 with a 10 minimum.
 */
final class PayCrossBorderApi
{
    /** PayCrossBorderPayoutService::reference('WD-01TEST'): too short once de-hyphenated, so hashed. */
    public const REFERENCE = 'PCB8FA8C449782C08525B16D9D630E';

    public const CONFIG = [
        'driver' => 'paycrossborder',
        'base_url' => 'https://pcb.test',
        'username' => '1000008340',
        'password' => 'HASHED-SECRET',
        'sender_code' => '1000008444',
        'customer_code' => '1000002225',
        'source_country' => 'ARE',
        'send_currency' => 'USD-USA',
        'currency_codes' => ['usd' => 'USD-USA', 'usdt' => 'USDT', 'aed' => 'AED', 'php' => 'PHP'],
        'charge_type' => 'OUR',
        'remittance_purpose' => 'PAYP002',
        'source_of_fund' => 'PAYS005',
        'relationship' => 'PAYR002',
        'withdrawals_enabled' => true,
        'withdrawal_max_amount' => '1000',
        'balance_buffer_percent' => '2',
    ];

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function configure(array $overrides = []): array
    {
        $config = array_merge(self::CONFIG, $overrides);

        config()->set('cashier-core.connections.paycrossborder', $config);

        return $config;
    }

    /**
     * @param  array{token?: mixed, quote?: mixed, payout?: mixed, transaction?: mixed, balance?: mixed}  $options
     */
    public static function fake(array $options = []): void
    {
        Http::fake(function (Request $request) use ($options) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            $answer = match (true) {
                $path === '/ewallet/oauth/token' => $options['token'] ?? Http::response(self::token()),
                $path === '/ewallet/api/v1/fxratequotation/api' => $options['quote']
                    ?? Http::response(self::quote(['payinAmount' => (string) ($request['payinAmount'] ?? '')])),
                $path === '/ewallet/api/v1/payoutProcess/api' => $options['payout'] ?? Http::response(self::payout()),
                $path === '/ewallet/api/v1/transactionInfo/api' => $options['transaction'] ?? Http::response(self::transaction()),
                str_starts_with($path, '/ewallet/api/v1/wallet/walletOwner/') => $options['balance'] ?? Http::response(self::balance()),
                default => Http::response([], 404),
            };

            return is_callable($answer) ? $answer($request) : $answer;
        });
    }

    /** @return array<string, mixed> */
    public static function token(string $token = 'pcb-token-1', int $expiresIn = 21150): array
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $expiresIn,
            'scope' => 'read write trust',
            'clientCode' => '1000008483',
            'locale' => 'en',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides  merged into fxRateQuotation
     * @return array<string, mixed>
     */
    public static function quote(array $overrides = []): array
    {
        return [
            'transactionId' => '7558887',
            'resultCode' => '0',
            'resultDescription' => 'Transaction Successful',
            'fxRateQuotation' => array_merge([
                'code' => '114672',
                'fxRateValue' => '0.98',
                'senderMargin' => '0.98',
                'senderCode' => '1000008444',
                'receiverCode' => '1000008397',
                'payinAmount' => '100.00',
                'payoutAmount' => '98.00',
                'fee' => '2.0',
                'duration' => 5,
                'timeLeft' => 4,
                'paymentMode' => 'Cash',
                'chargeTypeCode' => 'OUR',
            ], $overrides),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides  merged into payoutResponseBean
     * @return array<string, mixed>
     */
    public static function payout(array $overrides = []): array
    {
        return [
            'requestTime' => 'Thu Aug 03 12:22:10 IST 2023',
            'resultCode' => '0',
            'resultDescription' => 'Transaction Successful',
            'payoutResponseBean' => array_merge([
                'transReference' => '197930',
                'clientReferenceNumber' => self::REFERENCE,
                'status' => 'In Process',
                'payinCurrency' => 'USD-USA',
                'payinAmount' => 100.0,
                'payoutCurrency' => 'USDT',
                'payoutAmount' => 98.0,
                'serviceType' => 'B2C',
            ], $overrides),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides  merged into the single enquiry row
     * @return array<string, mixed>
     */
    public static function transaction(array $overrides = []): array
    {
        return [
            'resultCode' => '0',
            'resultDescription' => 'Transaction Successful',
            'pageable' => ['totalRecords' => 1],
            'transactionInfoResponse' => [array_merge([
                'id' => 3314,
                'transId' => 197930,
                'senderUniqueId' => self::REFERENCE,
                'sendingAmount' => 100.0,
                'payoutAmount' => 98.0,
                'types' => 'B2C',
                'resultCode' => '0',
                'resultDescription' => 'Transaction Successful',
                'vendorResultCode' => '200',
                'vendorResultDescription' => 'pending_approval',
                'transactionStatus' => 'IP',
                'statusName' => 'Payout Processing',
            ], $overrides)],
        ];
    }

    /** @return array<string, mixed> */
    public static function balance(mixed $value = 5000.0, float $min = 10.0): array
    {
        return [
            'transactionId' => '8474053',
            'resultCode' => '0',
            'resultDescription' => 'Transaction successful',
            'walletList' => [
                [
                    'walletOwnerCode' => '1000008444',
                    'currencyName' => 'USD-USA',
                    'walletTypeName' => 'Comission Wallet',
                    'value' => 0.0,
                    'minTransValue' => 0.0,
                ],
                [
                    'walletOwnerCode' => '1000008444',
                    'currencyName' => 'USD-USA',
                    'walletTypeName' => 'Main Wallet',
                    'value' => $value,
                    'minTransValue' => $min,
                    'maxTransValue' => 100000.0,
                ],
            ],
        ];
    }

    /**
     * A crypto-rail beneficiary (no bank, no wallet). Pass null to drop a key.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function beneficiary(array $overrides = []): array
    {
        return array_replace_recursive([
            'firstName' => 'Kuldeep',
            'lastName' => 'Singh',
            'gender' => 'male',
            'nationality' => 'ARE',
            'dateOfBirth' => '1990-04-01',
            'address' => [
                'line1' => 'Villa 12, Al Wasl Rd.',
                'city' => 'Dubai',
                'state' => 'Dubai',
                'postalCode' => '00000',
                'country' => 'ARE',
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function bankBeneficiary(array $overrides = []): array
    {
        return self::beneficiary(array_replace_recursive([
            'bank' => [
                'accountNumber' => 'AE110260000959024298101',
                'accountHolderName' => 'Kuldeep Singh',
                'bankName' => 'Emirates NBD',
                'swiftCode' => 'EBILAEAD',
            ],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function walletBeneficiary(array $overrides = []): array
    {
        return self::beneficiary(array_replace_recursive([
            'nationality' => 'PHL',
            'phone' => '9171234567',
            'dialCode' => '+63',
            'address' => ['city' => 'Manila', 'state' => 'Metro Manila', 'postalCode' => '1000', 'country' => 'PHL'],
            'wallet' => ['providerName' => 'GCASH-63001', 'providerCode' => '63001'],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides  PayoutRequest constructor arguments
     * @param  array<string, mixed>|null  $beneficiary  defaults to beneficiary()
     */
    public static function request(array $overrides = [], ?array $beneficiary = null): PayoutRequest
    {
        // Bank and wallet payouts must name the destination in payout_address:
        // default it to the beneficiary's account number or wallet phone.
        $destination = $beneficiary['bank']['accountNumber']
            ?? (isset($beneficiary['wallet']) ? ($beneficiary['phone'] ?? null) : null);

        return new PayoutRequest(...array_merge([
            'externalOrderId' => 'WD-01TEST',
            'customerId' => '70001',
            'amount' => '100.00',
            'currency' => 'USD',
            'payoutCurrency' => 'usdt',
            'payoutAddress' => $destination ?? 'TXyzCustomerWalletAddress000001',
            'payoutExtraId' => 'TRC20',
            'beneficiary' => PayoutBeneficiary::fromArray($beneficiary ?? self::beneficiary()),
        ], $overrides));
    }
}
