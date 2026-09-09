<?php

use Asciisd\CashierCore\Models\Refund;
use Asciisd\CashierCore\Models\Transaction;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The named connection used when a charge does not specify one. Must be a
    | key of the `connections` map below.
    |
    */
    'default_connection' => env('CASHIER_DEFAULT_CONNECTION', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Named Connections
    |--------------------------------------------------------------------------
    |
    | A connection is one PSP account: a `driver` string plus the credentials
    | and routing values for that account. Several connections may share a
    | driver — two APS merchant accounts are two connections — which is why
    | your payment methods should name a connection rather than a driver.
    |
    | `driver` is the plain string persisted to `transactions.provider`.
    | `class`  is optional: bundled and plugin drivers resolve through the
    |          `drivers` map below; set it to override with your own provider.
    |
    | 'aps' => [
    |     'driver' => 'aps',
    |     'base_url' => env('APS_BASE_URL'),  // defaults to the production host
    |     'merchant_guid' => env('APS_MERCHANT_GUID'),
    |     'app_token' => env('APS_APP_TOKEN'),
    |     'app_secret' => env('APS_APP_SECRET'),
    |     // Signs callbacks, and is issued separately from the app secret.
    |     // Without it, verification falls back to the app secret and rejects
    |     // every callback APS sends.
    |     'callback_secret' => env('APS_CALLBACK_SECRET'),
    |     // The payment method guid a deposit is opened against. charge()
    |     // throws without it.
    |     'deposit_method' => env('APS_DEPOSIT_METHOD'),
    |     // Optional — these fall back to the `payment.success` and
    |     // `webhooks.aps` routes where the host defines them.
    |     'redirect_url' => env('APS_REDIRECT_URL'),
    |     'webhook_url' => env('APS_WEBHOOK_URL'),
    |     // Optional. Rewrites the host of a card checkout URL: APS returns
    |     // `how` pointing at its JSON API, which shows the customer raw JSON
    |     // instead of the card form. Unlisted hosts pass through untouched.
    |     'checkout_host_map' => ['api.pci-gw.com' => 'form.pci-gw.com'],
    | ],
    |
    | A second APS account is another connection on the same driver — APS
    | issues one account per product, each with its own guid, app key and
    | callback secret. No driver code and no route are involved: every APS
    | account posts to the one `webhooks.aps` URL and the sender is identified
    | by whose callback secret verifies the signature, which is why the URLs
    | below are left to their route defaults.
    |
    | 'aps_apple_pay' => [
    |     'driver' => 'aps',            // same driver, different account
    |     'base_url' => env('APS_APPLEPAY_BASE_URL'),
    |     'merchant_guid' => env('APS_APPLEPAY_MERCHANT_GUID'),
    |     'app_token' => env('APS_APPLEPAY_APP_TOKEN'),
    |     'app_secret' => env('APS_APPLEPAY_APP_SECRET'),
    |     'callback_secret' => env('APS_APPLEPAY_CALLBACK_SECRET'),
    |     // From `GET /api/v3/{merchantGuid}/info` on this account.
    |     'deposit_method' => env('APS_APPLEPAY_DEPOSIT_METHOD'),
    | ],
    |
    | MyFatoorah is one API key per COUNTRY, and the key must be sent to that
    | country's host — api.myfatoorah.com serves Kuwait, Bahrain, Oman and
    | Jordan, while Saudi Arabia, the UAE, Qatar and Egypt each have their
    | own. Every country shares the apitest.myfatoorah.com sandbox. The
    | authoritative host map is a public JSON file,
    | https://portal.myfatoorah.com/Files/API/mf-config.json — read it to fill
    | `base_url` in, but the package does not fetch it.
    |
    | 'myfatoorah' => [
    |     'driver' => 'myfatoorah',
    |     'base_url' => env('MYFATOORAH_BASE_URL'),
    |     'api_key' => env('MYFATOORAH_API_KEY'),
    |     // Required. Enabled per webhook in the portal as the "secure key",
    |     // and mandatory for V2 deliveries. The connection will not resolve
    |     // without it: a blank secret is still a working HMAC key, and every
    |     // field the signature covers is public, so anyone could forge a
    |     // callback that verifies.
    |     'webhook_secret' => env('MYFATOORAH_WEBHOOK_SECRET'),
    |     // Required. MyFatoorah cannot charge USD: Order.Currency accepts
    |     // only SAR, BHD, AED, QAR, OMR, KWD, JOD and EGP. The driver
    |     // overrides the pinned default with this, and refuses to resolve
    |     // when it is missing or unsupported.
    |     'currency' => env('MYFATOORAH_CURRENCY', 'KWD'),
    |     // Optional. CARD | KNET | APPLE_PAY | GOOGLE_PAY. Omit to land the
    |     // customer on MyFatoorah's own picker showing every method enabled
    |     // on the account — the docs are ambiguous about whether the picker
    |     // is reachable for redirection flows, so confirm it in the sandbox
    |     // before leaving this unset in production.
    |     'payment_method' => env('MYFATOORAH_PAYMENT_METHOD'),
    |     // Optional — these fall back to the `payment.success` and
    |     // `cashier.webhooks.myfatoorah` routes where the host defines them.
    |     'redirect_url' => env('MYFATOORAH_REDIRECT_URL'),
    |     'webhook_url' => env('MYFATOORAH_WEBHOOK_URL'),
    |     // Optional. EN | AR. Defaults to the customer's cashierLocale().
    |     'language' => env('MYFATOORAH_LANGUAGE'),
    | ],
    |
    | A second country is another connection on the same driver:
    |
    | 'myfatoorah_sau' => [
    |     'driver' => 'myfatoorah',
    |     'base_url' => 'https://apisa.myfatoorah.com',
    |     'api_key' => env('MYFATOORAH_SAU_API_KEY'),
    |     'webhook_secret' => env('MYFATOORAH_SAU_WEBHOOK_SECRET'),
    |     'currency' => 'SAR',
    | ],
    |
    | Xoala is a white-label of the Paymentz platform. Deposits go through
    | Standard Checkout, whose entry point is a browser form POST rather than a
    | URL — the package serves a signed bridge page that submits it, so nothing
    | in the host application has to render the form.
    |
    | 'xoala' => [
    |     'driver' => 'xoala',
    |     // Sandbox: https://secure-checkout-sandbox.xoala.com
    |     // Live:    https://secure-checkout.xoala.com
    |     'base_url' => env('XOALA_BASE_URL'),
    |     // Merchant id, assigned by Xoala. Authenticates every request.
    |     'member_id' => env('XOALA_MEMBER_ID'),
    |     // Generated in the Xoala dashboard. Signs every checksum, and is
    |     // never itself transmitted.
    |     'secure_key' => env('XOALA_SECURE_KEY'),
    |     // Required, and account-specific — the spec calls it "Merchant's
    |     // Partner name". It is the second field of the request checksum, so
    |     // a wrong value fails the payment at the hosted page with no useful
    |     // message. The provider refuses to resolve without it.
    |     'totype' => env('XOALA_TOTYPE'),
    |     // Optional. Sent as `merchant.username` when generating the REST
    |     // auth token that retrieve()/sync depends on. Whether an account
    |     // requires it is unconfirmed — the merchant auth-token page
    |     // documents only the secure key, but Xoala's own sample request
    |     // also carries a username — and it is left off the request
    |     // entirely when unset, so leaving this blank is safe to try first.
    |     // Plays no part in any checksum.
    |     'username' => env('XOALA_USERNAME'),
    |     // Optional. Required on some account shapes ("Conditional" in the
    |     // spec); sent only when set.
    |     'terminal_id' => env('XOALA_TERMINAL_ID'),
    |     // Optional. Restricts the hosted page to one method — e.g. CC.
    |     // Unset shows every method the account has enabled.
    |     'payment_mode' => env('XOALA_PAYMENT_MODE'),
    |     'payment_brand' => env('XOALA_PAYMENT_BRAND'),
    |     // Optional. DB authorizes and captures in one step, which is what a
    |     // deposit wants; PA leaves the funds held and uncaptured.
    |     'transaction_type' => env('XOALA_TRANSACTION_TYPE', 'DB'),
    |     // Optional. The currency this connection invoices in, declared to
    |     // the engine before the charge so it can price a converted leg.
    |     'currency' => env('XOALA_CURRENCY'),
    |     // Optional — these fall back to the `payment.success` and
    |     // `cashier.webhooks.xoala` routes where the host defines them.
    |     'redirect_url' => env('XOALA_REDIRECT_URL'),
    |     'webhook_url' => env('XOALA_WEBHOOK_URL'),
    |     // Optional. Hosted page language; defaults to the app locale.
    |     'language' => env('XOALA_LANGUAGE'),
    | ],
    |
    | A second Xoala merchant account is another connection on the same driver.
    | Both post to the one `cashier.webhooks.xoala` URL and the payload names
    | no merchant, so the sender is identified by whose secure key verifies the
    | checksum.
    |
    */
    'connections' => [],

    /*
    |--------------------------------------------------------------------------
    | Driver Map
    |--------------------------------------------------------------------------
    |
    | driver string => provider class. Bundled drivers are merged in by the
    | service provider; plugin packages (asciisd/cashier-paytiko, asciisd/knet)
    | append theirs here. A connection whose config carries a `class` key wins
    | over this map.
    |
    */
    'drivers' => [],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | `transaction` may point at your own model extending the package base
    | model. `customer` must implement CustomerContract; it stays null until
    | the host application binds its user model.
    |
    */
    'models' => [
        'transaction' => Transaction::class,
        'refund' => Refund::class,
        'customer' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Routes
    |--------------------------------------------------------------------------
    |
    | The package registers POST {prefix}/{driver} for every bundled driver.
    | Disable with `enabled` (or Cashier::ignoreRoutes()) to register your own.
    |
    */
    'routes' => [
        'enabled' => env('CASHIER_ROUTES_ENABLED', true),
        'prefix' => env('CASHIER_WEBHOOK_PREFIX', 'api/webhooks'),
        'middleware' => ['api', 'throttle:cashier-webhooks'],

        /*
         * Middleware to strip from the group. If your `api` group appends its
         * own throttle, name it here — otherwise it stacks with the webhook
         * limiter and the tighter of the two wins.
         */
        'without_middleware' => [],
        'name_prefix' => 'cashier.webhooks.',

        /*
         * The hosted-checkout bridge. Xoala's Standard Checkout is entered by
         * a browser form POST rather than a URL, so the package serves a signed
         * GET page that submits that form. No `web` middleware: the page holds
         * no session and no CSRF token, and requiring the host's `web` group
         * would be a surprising coupling for a page whose only job is to
         * submit to an external host.
         */
        'checkout' => [
            'prefix' => env('CASHIER_CHECKOUT_PREFIX', 'cashier'),
            'middleware' => ['signed', 'throttle:cashier-checkout'],
            'name_prefix' => 'cashier.checkout.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Webhook jobs move money — keep them on their own queue with a dedicated
    | worker rather than behind bulk mail on `default`.
    |
    */
    'queue' => [
        'connection' => env('CASHIER_QUEUE_CONNECTION'),
        'queue' => env('CASHIER_QUEUE', 'payments'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'default' => env('CASHIER_CURRENCY', 'USD'),
        'supported' => [
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'SEK', 'NOK', 'DKK',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'deposit' => [
            'min' => env('CASHIER_DEPOSIT_MIN', 10),
            'max' => env('CASHIER_DEPOSIT_MAX', 1_000_000),
        ],
        'withdrawal' => [
            'min' => env('CASHIER_WITHDRAWAL_MIN', 10),
            'max' => env('CASHIER_WITHDRAWAL_MAX', 1_000_000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Settlement
    |--------------------------------------------------------------------------
    |
    | Client-controlled rails (crypto invoices) settle whatever the customer
    | actually sent. Shortfalls within the tolerance are treated as network
    | variance; beyond it the deposit is reconciled down to what arrived.
    |
    */
    'settlement' => [
        'tolerance_percent' => (float) env('CASHIER_SETTLEMENT_TOLERANCE', 2.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Withdrawals
    |--------------------------------------------------------------------------
    |
    | approve_first (default): submissions create a Pending row and an admin
    | approval performs the ledger debit. Legacy mode debits at submit time.
    |
    */
    'withdrawals' => [
        'approve_first' => env('CASHIER_WITHDRAWAL_APPROVE_FIRST', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | `verify_signature` exists for local development only. In production it
    | is ignored — signature verification is always enforced, and disabling
    | it is logged as critical. `amount_tolerance_percent` is the band within
    | which a webhook-reported amount may differ from the invoiced amount
    | before the deposit is held for review instead of credited.
    |
    */
    'webhooks' => [
        'enabled' => env('CASHIER_WEBHOOKS_ENABLED', true),
        'verify_signature' => env('CASHIER_VERIFY_WEBHOOK_SIGNATURE', true),
        'amount_tolerance_percent' => (float) env('CASHIER_WEBHOOK_AMOUNT_TOLERANCE', 1.0),
        'replay_ttl_days' => env('CASHIER_WEBHOOK_REPLAY_TTL_DAYS', 30),

        /*
         * Callback relay, per driver. Set a URL when this application took
         * over a callback endpoint that already belonged to a third party:
         * every verified delivery that matches no local transaction is then
         * relayed there verbatim, so the party that opened the deposit can
         * still see how it settled. Unset drivers relay nothing.
         *
         * Relaying happens only after the signature verifies, so the endpoint
         * cannot be used to pump arbitrary payloads at the recipient.
         */
        'relay' => array_filter([
            'jenapay' => env('CASHIER_RELAY_JENAPAY_URL'),
        ]),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security (PCI DSS posture)
    |--------------------------------------------------------------------------
    |
    | Payload allowlists and redaction keep PSP callback PII out of storage
    | and logs; encryption covers what must be kept. Retention drives the
    | `cashier:purge` command.
    |
    */
    'security' => [
        'encrypt_provider_payload' => env('CASHIER_ENCRYPT_PAYLOADS', true),
        'encrypt_withdrawal_details' => env('CASHIER_ENCRYPT_WITHDRAWAL_DETAILS', true),
        'redact_keys' => [
            'email', 'zip', 'zip_code', 'postal_code', 'ip', 'client_ip',
            'card*', 'pan', 'cvv', 'phone', 'address*', 'account_number',
            'account_info', 'payment_info', 'iban', 'beneficiary_name',
            'full_name', 'customer_name', 'customer_email',
            'registered_email', 'merchant_email',
        ],
        'allowed_redirect_hosts' => [],
        'retention' => [
            'provider_payload_days' => env('CASHIER_PAYLOAD_RETENTION_DAYS', 180),
            'webhook_events_days' => env('CASHIER_WEBHOOK_EVENTS_RETENTION_DAYS', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'connection' => env('CASHIER_DB_CONNECTION'),
        'tables' => [
            'transactions' => 'transactions',
            'refunds' => 'refunds',
            'webhook_events' => 'cashier_webhook_events',
            'admin_actions' => 'cashier_admin_actions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('CASHIER_LOGGING_ENABLED', true),
        'channel' => env('CASHIER_LOG_CHANNEL'),
        'level' => env('CASHIER_LOG_LEVEL', 'info'),
    ],
];
