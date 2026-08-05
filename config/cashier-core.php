<?php

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
    |     'base_url' => env('APS_BASE_URL'),
    |     'merchant_guid' => env('APS_MERCHANT_GUID'),
    |     'app_token' => env('APS_APP_TOKEN'),
    |     'app_secret' => env('APS_APP_SECRET'),
    | ],
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
        'transaction' => \Asciisd\CashierCore\Models\Transaction::class,
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
        'name_prefix' => 'cashier.webhooks.',
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
