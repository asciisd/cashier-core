<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Processor
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment processor that will be used
    | when no specific processor is requested. You may change this to any
    | of the registered processors in your application.
    |
    */
    'default' => env('CASHIER_DEFAULT_PROCESSOR', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Payment Processors
    |--------------------------------------------------------------------------
    |
    | Here you may configure the payment processors for your application.
    | Each processor should have a corresponding class that implements
    | the PaymentProcessorInterface.
    |
    */
    'processors' => [


        // Add more processors here as needed
        // 'square' => [
        //     'class' => \App\PaymentProcessors\SquareProcessor::class,
        //     'config' => [
        //         'application_id' => env('SQUARE_APPLICATION_ID'),
        //         'access_token' => env('SQUARE_ACCESS_TOKEN'),
        //         'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
        //     ],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    |
    | Here you may configure the default currency and supported currencies
    | for your payment system.
    |
    */
    'currency' => [
        'default' => env('CASHIER_CURRENCY', 'USD'),
        'supported' => [
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'SEK', 'NOK', 'DKK',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Settings
    |--------------------------------------------------------------------------
    |
    | Configure database table names and connection settings.
    |
    */
    'database' => [
        'connection' => env('CASHIER_DB_CONNECTION', null),
        'tables' => [
            'transactions' => 'cashier_transactions',
            'payment_methods' => 'cashier_payment_methods',
            'refunds' => 'cashier_refunds',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | Configure webhook handling for payment processors.
    |
    */
    'webhooks' => [
        'enabled' => env('CASHIER_WEBHOOKS_ENABLED', true),
        'tolerance' => env('CASHIER_WEBHOOK_TOLERANCE', 300), // seconds
        'verify_signature' => env('CASHIER_VERIFY_WEBHOOK_SIGNATURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | Configure logging for payment transactions and errors.
    |
    */
    'logging' => [
        'enabled' => env('CASHIER_LOGGING_ENABLED', true),
        'channel' => env('CASHIER_LOG_CHANNEL', 'single'),
        'level' => env('CASHIER_LOG_LEVEL', 'info'),
        'log_successful_payments' => env('CASHIER_LOG_SUCCESSFUL_PAYMENTS', true),
        'log_failed_payments' => env('CASHIER_LOG_FAILED_PAYMENTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure security settings for payment processing.
    |
    */
    'security' => [
        'encrypt_sensitive_data' => env('CASHIER_ENCRYPT_SENSITIVE_DATA', true),
        'mask_card_numbers' => env('CASHIER_MASK_CARD_NUMBERS', true),
        'store_processor_response' => env('CASHIER_STORE_PROCESSOR_RESPONSE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    |
    | Configure retry logic for failed payment attempts.
    |
    */
    'retry' => [
        'enabled' => env('CASHIER_RETRY_ENABLED', true),
        'max_attempts' => env('CASHIER_RETRY_MAX_ATTEMPTS', 3),
        'delay' => env('CASHIER_RETRY_DELAY', 5), // seconds
        'backoff_multiplier' => env('CASHIER_RETRY_BACKOFF_MULTIPLIER', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific features of the payment system.
    |
    */
    'features' => [
        'refunds' => env('CASHIER_FEATURE_REFUNDS', true),
        'partial_refunds' => env('CASHIER_FEATURE_PARTIAL_REFUNDS', true),
        'recurring_payments' => env('CASHIER_FEATURE_RECURRING_PAYMENTS', false),
        'payment_methods_storage' => env('CASHIER_FEATURE_PAYMENT_METHODS_STORAGE', true),
        'webhooks' => env('CASHIER_FEATURE_WEBHOOKS', true),
    ],
];
