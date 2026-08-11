# Custody flow

## Common

### Payment status check by id (Custody)

`GET https://api.heropayments.io/custody/payments/:id`

To get information about the status of a deposit/withdrawal, you need to provide a payment ID.

*NOTE!* [GET "Payment status check by id (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#b62b5946-51fe-4d79-b818-4ff427dcf727) *request should be made with the same API key that you used to create a deposit/withdrawal request.*

Possible statuses:

- **new** - the transaction was just created;

- **pending** - the transaction is waiting to be processed;

- **processing** - the transaction is being processed;

- **finished** - the transaction is processed successfully. Considered as a "success" status;

- **failed** - the transaction is failed due to an error;

- **refunded** - the funds were refunded back to the user’s/Merchant’s wallet;

- **hold** - the transaction is marked as suspicious and the user must pass KYC procedure;

- **expired** - the user didn't send the funds to the specified address during 4 hours and therefore the payment expired.

More information could be found in [Payments statuses - (Custody flow)](https://documenter.getpostman.com/view/17469357/UVyvwv7a#payments-statuses-custody-flow)

Response fields:

- **id** – payment ID;

- **subEmail** - if a payment was created by a subuser of the main account, the field reflects the name of the subaccount;

- **currency** - currency of the transaction;

- **amount** - amount specified by user for the deposit/withdrawal in Currency

- **feePercent** - Heropayments processing fee (%);

- **feeAmount** - Heropayments processing fee in the Currency equivalent;

- **clientAmount**:\
  - for deposits - amount of crypto received from a user to a created deposit address\
  - for withdrawals - amount sent to the user;

- **merchantAmount**:\
  - for deposits - amount credited to the merchant’s wallet in Currency;\
  - for withdrawals - amount deducted from the merchant's balance to process the withdrawal for the user;

- **networkFee** - network fee deducted for processing the blockchain transaction;

- **customerId** - the merchant provides an ID that is associated with a particular user of the platform;

- **externalOrderId** - merchant's custom order ID. Example: BGBRB-30020. This ID must be unique to create a transaction;

- **sequence** - helps to differentiate between the subsequent payments. More information could be found in [multiple deposit](https://documenter.getpostman.com/view/17469357/UVyvwv7a#multiple-deposit-processing) [processing](https://documenter.getpostman.com/view/17469357/UVyvwv7a#multiple-deposit-processing);

- **callbackUrl** - HTTP(s) URL of your server which will accept callback requests. Heropayments will send callbacks whenever the status of a transaction gets an update. If your server is inaccessible or cannot accept a callback request, our system will try to send it again until it reaches the limit of five retries;

- **transactionType** - always deposit or withdrawal;

- **address**:\
  - for deposits - deposit address for crypto assets\
  - for withdrawals - destination address of your user where the crypto was withdrawn to;

- **addressExtra** - additional Id which is necessary for identifying a recipient of the transaction. Also known as additional address, memo or destination tag. Users must enter it while withdrawing XRP, BNB, XLM and some other currencies;

- **status** - status of the payment;

- **confirmations** - number of confirmations on the blockchain. We need to wait until the payment gets at least several confirmations to process it;

- **hash** - transaction blockchain hash;

- **hashLink** - link to hash;

- **comment** - additional information about the payment;

- **createdAt** - time, when the transaction was created;

- **updatedAt­­** - time, when the transaction was updated.

#### Status check by id — 200 OK

```json
{
    "id": "d10978f1-270d-4dd8-a8c2-391a1a6d9691",
    "subEmail": null,
    "currency": "usdttrc20",
    "amount": "0",
    "feePercent": 0.5,
    "feeAmount": "0",
    "clientAmount": "0",
    "merchantAmount": "0",
    "networkFee": "2.3",
    "customerId": "123",
    "externalOrderId": "4534534512312",
    "sequence": "original",
    "callbackUrl": "https://example.com/callback",
    "transactionType": "deposit",
    "address": "TH8tjabYVHKa8BWLQ1WhJPXbnfnHGb5Gau",
    "addressExtra": null,
    "status": "new",
    "confirmations": 0,
    "hash": null,
    "hashLink": null,
    "comment": null,
    "createdAt": "2025-04-11T11:56:19.483Z",
    "updatedAt": "2025-04-11T11:56:19.506Z"
}
```

### Payment status check by Order id (Custody)

`GET https://api.heropayments.io/custody/payments/order/:orderId`

To get information about the status of a deposit/withdrawal, you need to provide an Order ID (externalOrderId).

Statuses and response fields are identical to [Payment status check by id (Custody)](https://documenter.getpostman.com/view/17469357/UVyvwv7a#b62b5946-51fe-4d79-b818-4ff427dcf727)

#### Payment status check by Order id (Custody)

```json
{
    "id": "9adb774a-75d5-4af5-8f6b-eb5654f4db",
    "subEmail": null,
    "currency": "usdtbsc",
    "feePercent": 0.5,
    "amount": "4",
    "feeAmount": "0.02",
    "clientAmount": "4",
    "merchantAmount": "4.32",
    "networkFee": "0.3",
    "customerId": "Tony",
    "externalOrderId": "Tony_salary_June",
    "callbackUrl": null,
    "transactionType": "withdrawal",
    "address": "0xf69ca466ac101702b2e8fcd06d2b4a2dd2887",
    "addressExtra": "",
    "status": "finished",
    "confirmations": 0,
    "hash": "0xa32ba5f98d490036fb1f37c2eb637ec77103e10b5e7f7618017dbc226083f",
    "hashLink": "https://bscscan.com/tx/0xa32ba5f98d490036fb1f37c2eb637ec77103e10b5e7f7618017dbc226083f",
    "comment": "June_salary",
    "createdAt": "2025-07-23T11:15:59.992Z",
    "updatedAt": "2025-07-23T11:16:50.884Z"
}
```

### List of payments (Custody)

`GET https://api.heropayments.io/custody/payments?offset=0&limit=10&status=finished&dateFrom=&dateTo=&transactionType=deposit`

Returns the entire list of all transactions, created with a certain API key. The list of optional parameters:

- **limit** - limits the amount of transactions on a page;

- **offset** - specified to exclude a number of entries from the query;

- **status** - payment statuses ("new", "processing", "pending", "finished");

- **transactionType** - always "deposit" or "withdrawal";

- **currency** - currency of the transaction, the full list of supported currencies you can find here [list of supported currencies](https://docs.google.com/spreadsheets/d/1WjpmYaSE6Fd7BnRPdris8w-mKNAEPRay0nB1BTy_6-U/edit?pli=1&gid=0#gid=0);

- **hash** - transaction blockchain hash

- **subEmail** - if a payment was created by a subuser of the main account, the field reflects the name of the subaccount;

- **customerId** - the merchant provides an ID that is associated with a particular user of the platform;

- **externalOrderId** - merchant's custom order ID. Example: BGBRB-30020. This ID must be unique to create a transaction;

- **address**:\
  - for deposits - deposit address for crypto assets;\
  - for withdrawals - destination address of your user where the crypto was withdrawn to;

- **dateFrom** - select the start date for the displayed period (date format: YYYY-MM-DD or yy-MM-ddTHH:mm:ss.SSSZ);

- **dateTo** - select the end date for the displayed period (date format: YYYY-MM-DD or yy-MM-ddTHH:mm:ss.SSSZ).

Payments are sorted by createdAt (newest to oldest).

### Get balance (Custody)

`GET https://api.heropayments.io/custody/balances`

Returns the entire list of all balances, created with a certain API key. The list of parameters:

- **currency** - balance currency;

- **total** - available + locked assets;

- **available** - amount of available assets on your balance;

- **usdEstimate** - balance currency to USD ratio;

- **locked** - temporary locked assets. To get more information, reach out to the account team.

#### Get balance — 200 OK

```json
[
    {
        "currency": "ton",
        "total": "0",
        "available": "0",
        "usdEstimate": "n/a",
        "locked": "0"
    },
    {
        "currency": "trx",
        "total": "5.58",
        "available": "5.58",
        "usdEstimate": "1.38",
        "locked": "0"
    },
    {
        "currency": "usdttrc20",
        "total": "0",
        "available": "0",
        "usdEstimate": "n/a",
        "locked": "0"
    },
    {
        "currency": "xrp",
        "total": "0",
        "available": "0",
        "usdEstimate": "n/a",
        "locked": "0"
    },
    {
        "currency": "usdt20",
        "total": "0",
        "available": "0",
        "usdEstimate": "n/a",
        "locked": "0"
    },
    {
        "currency": "doge",
        "total": "0",
        "available": "0",
        "usdEstimate": "n/a",
        "locked": "0"
    },
    {
        "currency": "bnbbsc",
        "total": "0.000432599994897",
        "available": "0.000432599994897",
        "usdEstimate": "0.28",
        "locked": "0"
    },
    {
        "currency": "adabsc",
        "total": "0",
        "available": "0",
        "usdEstimate": "n/a",
        "locked": "0"
    },
    {
        "currency": "1inch",
        "total": "0",
... 40 more lines elided by the mirror generator (bulk data list; fetch the live response for the full set)
```

### Minimum payment amount (Custody)

`GET https://api.heropayments.io/custody/min-amount`

This method returns the minimum amounts required to process deposit and withdrawal transactions.

To retrieve minimum amounts for all supported cryptocurrencies in bulk, call `custody/min-amount`. To get the minimum amount for a specific cryptocurrency, use the currency query parameter, for example: `?currency=btc`.

To retrieve minimum amounts for all cryptocurrencies estimated in fiat, call `custody/min-amount?baseCurrency=usd`.

Response fields:

- `minDeposit` **—** minimum amount required for a deposit transaction

- `minWithdrawal` **—** minimum amount required for a withdrawal transaction

- `currency` **—** cryptocurrency code

The list of supported cryptocurrencies is available here **-** [**List of supported currencies**](https://docs.google.com/spreadsheets/d/1WjpmYaSE6Fd7BnRPdris8w-mKNAEPRay0nB1BTy_6-U/edit?pli=1&gid=0#gid=0)

#### Minimum payment amount — 200 OK

```json
[
    {
        "minDeposit": 109.24253654,
        "minWithdrawal": 95.58721947,
        "currency": "1inch"
    },
    {
        "minDeposit": 12.14715063,
        "minWithdrawal": 12.14958055,
        "currency": "ada"
    },
    {
        "minDeposit": 84.99268339,
        "minWithdrawal": 84.99268339,
        "currency": "algo"
    },
    {
        "minDeposit": 10.47862411,
        "minWithdrawal": 10.47862411,
        "currency": "alicebsc"
    },
    {
        "minDeposit": 97.6285829,
        "minWithdrawal": 97.6285829,
        "currency": "alpaca"
    },
    {
        "minDeposit": 1300.6645527,
        "minWithdrawal": 1138.08148361,
        "currency": "ankr"
    },
    {
        "minDeposit": 64.11818382,
        "minWithdrawal": 44.88272867,
        "currency": "ape"
    },
    {
        "minDeposit": 27.75185426,
        "minWithdrawal": 27.75185426,
        "currency": "arbarb"
    },
    {
        "minDeposit": 83.25556278,
        "minWithdrawal": 83.25556278,
        "currency": "arberc"
    },
    {
        "minDeposit": 278.07955042,
        "minWithdrawal": 278.07955042,
        "currency": "ardr"
    },
    {
        "minDeposit": 27.89654364,
        "minWithdrawal": 13.94827182,
        "currency": "arkm"
    },
    {
        "minDeposit": 730.01441973,
        "minWithdrawal": 851.68348969,
        "currency": "arpa"
... 617 more lines elided by the mirror generator (bulk data list; fetch the live response for the full set)
```

### Supported currencies

`GET https://api.heropayments.io/v2/currencies`

You can get the list of available currencies either via our [V2 "Supported currencies"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#699875a1-914c-4a63-ae69-204f7ab5626d) method or via our public document - ["Supported currencies"](https://docs.google.com/spreadsheets/d/1WjpmYaSE6Fd7BnRPdris8w-mKNAEPRay0nB1BTy_6-U/edit?pli=1&gid=0#gid=0).

## Create a deposit/withdrawal

### Create a deposit (Custody)

`POST https://api.heropayments.io/custody/deposit`

**Request body**

```json
{
  "amount": 100,
  "currency": "trx",
  "customerId": "new_test_user",
  "externalOrderId": "merchant_order_id_1",
  "callbackUrl": "https://webhook.site/your_callback_url"
}
```

Use this method to create a deposit transaction. Please provide your data as a JSON-object payload.

Request fields:

- **currency** (required) - the cryptocurrency for deposit specified by the user from the currency list;

- **customerId** (required) - the Merchant provides an ID that is associated with a particular user of the platform;

- **externalOrderId** (required) - merchant's custom order ID. Example: BGBRB-30020. This ID must be unique to create a transaction;

- **callbackUrl (optional)** - HTTP(s) URL of your server which will accept callback requests. Heropayments will send callbacks whenever the status of a transaction gets an update. If your server is inaccessible or cannot accept a callback request, our system will try to send it again until it reaches the limit of five retries.

Response fields:

- **id** - payment ID;

- **subEmail** - if a payment was created by a subuser of the main account, the field reflects the name of the subaccount;

- **currency** - user's account currency: fiat or cryptocurrency (ex. USD, EUR, BTC, etc.);

- **amount** - paid amount;

- **feePercent** - Heropayments processing fee (%);

- **feeAmount** - Heropayments processing fee in the Currency equivalent;

- **clientAmount** - amount to be credited to the user’s account;

- **merchantAmount** - amount credited to Merchant’s wallet in Currency;

- **networkFee** - network fee deducted for processing the blockchain transaction. Estimated in a payment currency equivalent;

- **customerId** - the merchant provides an ID that is associated with a particular user of the platform;

- **externalOrderId** - merchant's custom order ID. Example: BGBRB-30020. This ID must be unique to create a transaction;

- **sequence** - helps to differentiate between the subsequent payments. Please read multiple deposit processing;

- **callbackUrl** - HTTP(s) URL of your server which will accept callback requests. Heropayments will send callbacks whenever the status of a transaction gets an update. If your server is inaccessible or cannot accept a callback request, our system will try to send it again until it reaches the limit of five retries;

- **transactionType** - always deposit or withdrawal, in this case deposit;

- **address** - deposit address for crypto assets;

- **confirmations** - number of confirmations on the blockchain. We need to wait until the payment gets at least several confirmations to process it;

- **hash** - deposit hash;

- **hashLink** - link to deposit hash;

- **comment** - additional information about the deposit;

- **createdAt** - time, when the deposit was created;

- **updatedAt­­** - time, when the deposit was updated.

You can check the status of the payment via [GET "Payment status check by id (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#b62b5946-51fe-4d79-b818-4ff427dcf727)

#### Create deposit — 200 OK

```json
{
    "id": "f3748917-d4ae-4dae-bec1-c00e269be9",
    "subEmail": null,
    "currency": "usdtbsc",
    "amount": "4",
    "feePercent": 0.5,
    "feeAmount": "0.02",
    "clientAmount": "4",
    "merchantAmount": "3.68",
    "networkFee": "0.3",
    "customerId": "test_user",
    "externalOrderId": "Test_deposit",
    "sequence": "original",
    "callbackUrl": "https://webhook.site/07e09722-fc19-4d0d-b81f-f536a8c",
    "transactionType": "deposit",
    "address": "0xfc9ca35f6296cadfc5f95145acd55abfd0eaf",
    "addressExtra": null,
    "status": "finished",
    "confirmations": 23,
    "hash": "0xc81b15173d536f5063ec4c11cfd7aa5cfbfd668ac2433d175efea100bfd25",
    "hashLink": "https://bscscan.com/tx/0xc81b15173d536f5063ec4c11cfd7aa5cfbfd668ac2433d175efea100bfd25",
    "comment": null,
    "createdAt": "2025-03-13T14:16:53.315Z",
    "updatedAt": "2025-03-13T14:21:22.163Z"
}
```

### Create a withdrawal (Custody)

`POST https://api.heropayments.io/custody/withdrawal`

**Request body**

```json
{
    "customerId": "usdtbsc",
    "address": "0x3e53A2FD8B63599B92A19458d4B01a5D74BCd125",
    "currency": "usdtbsc",
    "amount": "6",
    "callbackUrl": "https://example.com/callback",
    "externalOrderId": "100500"
}
```

Use this method to create a withdrawal (payout) transaction. Please provide your data as a JSON-object payload.

Request fields:

- **amount** (required) - the amount that the user will receive;

- **e-mail** (optional) - user’s email address;

- **address** (required) - your user’s address to receive the withdrawal;

- **addressExtra** (optional) - additional Id which is necessary for identifying a recipient of the transaction. Also known as additional address, memo or destination tag. Users must enter it while withdrawing XRP, BNB, XLM and some other currencies;

- **currency** (required) - cryptocurrency of a withdrawal specified by the user from a supported currency list;

- **customerId** (required) - the merchant provides an ID that is associated with a particular user of the platform;

- **externalOrderId** (required) - merchant's custom order ID. Example: BGBRB-30020. This ID must be unique to create a transaction;

- **callbackUrl** (optional) - HTTP(s) URL of your server which will accept callback requests. Heropayments will send callbacks whenever the status of a transaction gets an update. If your server is inaccessible or cannot accept a callback request, our system will try to send it again until it reaches the limit of five retries.

Response fields:

- **id** – payment ID;

- **subEmail** - if a payment was created by a subuser of the main account, the field reflects the name of the subaccount;

- **currency** - cryptocurrency of the withdrawal specified by the the user from a supported currency list;

- **amount** - amount requested for the withdrawal;

- **feePercent** - Heropayments processing fee (%);

- **feeAmount** - Heropayments fee deducted for a transaction in a payment currency equivalent;

- **clientAmount** - amount sent to the user;

- **merchantAmount** - amount deducted from Merchant's balance to process the withdrawal for the user;

- **networkFee** - network fee deducted for processing the blockchain transaction;

- **customerId** - the merchant provides an ID that is associated with a particular user of the platform;

- **externalOrderId** - merchant's custom order ID. Example: BGBRB-30020;

- **callbackUrl** - HTTP(s) URL of your server which will accept callback requests. Heropayments will send callbacks whenever the status of a transaction gets an update. If your server is inaccessible or cannot accept a callback request, our system will try to send it again until it reaches the limit of five retries;

- **transactionType** - always deposit or withdrawal, in this case withdrawal;

- **address** - destination address of your user where the crypto was withdrawn to;

- **addressExtra** - additional Id which is necessary for identifying a recipient of the transaction. Also known as additional address, memo or destination tag. Users must enter it while withdrawing XRP, BNB, XLM and some other currencies;

- **status** - status of the payment;

- **confirmations** - valid only for deposit transactions;

- **hash** - withdrawal hash;

- **hashLink** - link to withdrawal hash;

- **comment** - additional information about the payment;

- **createdAt** - time, when the withdrawal was created;

- **updatedAt­­** - time, when the withdrawal was updated.

You can check the status of the payment via [GET "Payment status check by id (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#b62b5946-51fe-4d79-b818-4ff427dcf727)

We have 2 types of daily limits:

- Merchant withdrawal limit – for the entire merchant account per day

- Customer withdrawal limit – for each customerId per day

To set up the limits, reach us via [partners@heropayments.io](https://mailto:partners@heropayments.io/) or contact your personal account manager.

#### Create withdrawal — 200 OK

```json
{
    "id": "9adb774a-75d5-4af5-8f6b-eb562d54f4db",
    "subEmail": null,
    "currency": "usdtbsc",
    "feePercent": 0.5,
    "amount": "4",
    "feeAmount": "0.02",
    "clientAmount": "4",
    "merchantAmount": "4.32",
    "networkFee": "0.3",
    "customerId": "test_user",
    "externalOrderId": "Test_withdrawal",
    "callbackUrl": "https://webhook.site/07e09722-fc19-4d0d-b81f-f536a8c",
    "transactionType": "withdrawal",
    "address": "0xDABBe153ae4DfaA7A40Aed87AB58ca2f91ef436e",
    "addressExtra": "",
    "status": "finished",
    "confirmations": 0,
    "hash": "0xa32ba5f98d490036fb1f37c2eb637ec77103e10b5e7f761bd28017dbc22608",
    "hashLink": "https://bscscan.com/tx/0xa32ba5f98d490036fb1f37c2eb637ec77103e10b5e7f761bd28017dbc22608",
    "createdAt": "2025-07-23T11:15:59.992Z",
    "updatedAt": "2025-07-23T11:16:50.884Z"
}
```
