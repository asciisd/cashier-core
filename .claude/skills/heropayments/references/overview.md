# Heropayments — overview

Here you can find the workflow and detailed explanation of API requests that let you process crypto payments on your platform.

### **We are partnered with cashiers like:**

- Devcode/Payments IQ: <https://payments-iq.com>

- Praxis: <https://praxis.tech>

And others like Corefy, FXbo and etc.

If you have any questions or you want to test our solution, feel free to reach out to us via [support@heropayments.io](https://mailto:support@heropayments.io)

# Authentication

To use Heropayments API, you should do the following:

- Sign up at <https://app.heropayments.io/> or at [my.heropayments.io](http://app.heropayments.io/my.heropayments.io);

- Import our collection of API methods in Postman;

- Insert API keys in Postman;

- Send a request.

**DO NOT SHARE YOUR API/SECRET KEY WITH ANY 3RD PARTIES!**

# Recommended integration flow

### DEPOSIT FLOW

A user wants to top up the account:

1.  UI - Add a "top up with crypto" method to your platform;

2.  UI - Ask your user to choose a cryptocurrency for the deposit and specify the amount to create a deposit transaction in the user's account currency (transaction amount is optional);

3.  API - To get estimated deposit amount for the selected cryptocurrency use [GET "Get estimated price"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#434f0b85-7d59-407c-994b-4455dfef82df) and to get minimum deposit amount use [GET "Minimum payment amount (V2)'](https://documenter.getpostman.com/view/17469357/UVyvwv7a#74245569-2457-46f7-bbce-55df4d9d4229);

4.  UI - Display the minimum deposit amount to your user and inform them that a lower amount won't be processed;

5.  API - To create the transaction for deposit and to generate a deposit address call the POST ["Create a deposit (V2)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#412c5848-dbed-42c5-980b-a3060e9e8c46) / ["Create a deposit (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#20b9f2a0-936b-4909-980f-9b7ca72ed6c8);

6.  UI - Show the generated deposit address to your user and ask to send the payment there. Once Heropayments accepts the deposit, it will be automatically converted into your balance currency and credited to your Merchant's account in our system.

7.  API - Get the transaction status either via our callbacks or manually via GET ["Payment status check by id (V2)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#f850d90d-7926-46f6-bcbf-4b90369cbbf8) / ["Payment status check by id (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#b62b5946-51fe-4d79-b818-4ff427dcf727);

8.  All deposits are accumulated on your Merchant's account USDT wallet in our system. Call [GET "Get balance"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#51715a30-cf5e-4237-8815-2d3228311cd7) method to check the balance. You can get a settlement upon the request.

Our [multiple deposit processing feature](https://documenter.getpostman.com/view/17469357/UVyvwv7a#multiple-deposit-processing) increases the conversion rate of deposits. It will let your users pay twice or more to the same deposit address without visiting the cashier.

[Automated mistaken deposits processing](https://documenter.getpostman.com/view/17469357/UVyvwv7a#automated-mistaken-deposits-processing) will allow us to accept deposits if users mistakenly send a currency different from the one specified in the invoice.

### WITHDRAWAL FLOW (PAYOUTS TO USERS)

First, you can view the available balance using GET [“Get balance (V2)”](https://documenter.getpostman.com/view/17469357/UVyvwv7a#51715a30-cf5e-4237-8815-2d3228311cd7) / ["Get balance (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#8b8de6a1-fec5-4255-a087-1dece185ba01).

To create a withdrawal, use POST [“Create a withdrawal (V2)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#a4e3c2d9-c88d-417e-90eb-947fc8eb9c10) / ["Create a withdrawal (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#bb5ca6c4-abbe-4bc3-b2ff-4f7425cac02a) request method. Specify the address, currency and amount for the withdrawal.

You can monitor transaction status via our callbacks or manually, using GET ["Payment status check by id (V2)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#f850d90d-7926-46f6-bcbf-4b90369cbbf8) / ["Payment status check by id (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#b62b5946-51fe-4d79-b818-4ff427dcf727).

1.  UI - Add the withdrawal with crypto option to your platform;

2.  UI - Ask your user to choose a cryptocurrency for withdrawal;

3.  API - Get the estimated amount for the selected currency by using [POST "Get estimated price"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#434f0b85-7d59-407c-994b-4455dfef82df) and minimum withdrawal amount by using ["Minimum payment amount (V2)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#74245569-2457-46f7-bbce-55df4d9d4229);

4.  UI - Display the minimum withdrawal amount to your user and make clear that a lower amount won't be processed;

5.  UI - Ask the user to specify a crypto wallet address to receive a withdrawal;

6.  API - Call POST ["Create a withdrawal (V2)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#a4e3c2d9-c88d-417e-90eb-947fc8eb9c10) / ["Create a withdrawal (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#bb5ca6c4-abbe-4bc3-b2ff-4f7425cac02a);

7.  API - Get the transaction status via our callbacks or manually, by calling GET ["Payment status check by id (V2)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#b4efb371-d1c9-48f6-b47b-5f8622accbdc) / ["Payment status check by id (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#b62b5946-51fe-4d79-b818-4ff427dcf727) method to receive actual information about the withdrawal request.

**Daily withdrawal limits:**

To elaborate on this, withdrawal limits are made to prevent situations when a merchant's account might accidentally run out of funds or overpay to a particular user.

We have 2 types of daily limits:

- Merchant withdrawal limit – for the entire merchant account per day

- Customer withdrawal limit – for each customerID per day

To set up the limits, reach us via [partners@heropayments.io](https://mailto:partners@heropayments.io) or contact your personal account manager.

# **API Request signing**

In order to sign API requests, you will have to use your API Secret (which can be found in your admin panel - <https://app.heropayments.io/api> or [https://my.heropayments.io/settings/api-keys)](https://my.heropayments.io/settings/api-keys) to calculate the request signature using the **HMAC-SHA512** algorithm.

You have to pass the request body as the data parameter and your API Secret as **secretKey** parameter. After calculating the signature, you have to attach it to the **x-api-sign** header.

### POST

For all requests with body - you should sign body and specify it to **x-api-sign** header.

### GET

For all GET requests - you should sign query string without question mark(?). For example:\
[`https://api.heropayments.io/v2/method?foo=bar`](https://api.heropayments.io/v2/currencies?foo=bar) should be signed string `foo=bar`

If a request doesn't have a query string, you should sign an empty string:

`yourSigningFunction('')`

Then specify it to **x-api-sign** header.

**PLEASE NOTE**

To make sure you calculate the correct signature, you have to normalize the JSON data:

- it should not contain any spaces or newlines in it.

- it should not have any zero-padded numbers (from both sides) unless they're quoted strings (e.g. 001.10 is invalid, "001.10" is valid)

Easiest way to do this automatically with Javascript:\
`JSON.stringify(JSON.parse(yourJsonString))`

You can check if the created signature is valid or not via our signature validation tool - <https://codepen.io/ethan-reynolds-9823/full/jEOVRbV>

### Examples

Postman pre-request script (V2):

```
const crypto = require('crypto-js');
let payload = '';
const secret = pm.environment.has('api-secret')
    ? pm.environment.get('api-secret')
    : pm.variables.has('api-secret')
        ? pm.variables.get('api-secret')
        : pm.globals.has('api-secret')
            ? pm.globals.get('api-secret')
            : pm.collectionVariables.has('api-secret')
                ? pm.collectionVariables.get('api-secret')
                : console.error(new Error('api-secret is missing'));
try {
    switch (pm.request.method) {
        case 'POST': 
            payload = JSON.stringify(JSON.parse(pm.request.body.toString()));
            break;
        case 'GET':
            payload = pm.request.url.query.toString();
            break;
    }
} catch {
    console.error(new Error('Cant decode JSON body'))
}
console.log('payload', payload);
const sign = crypto.HmacSHA512(payload, secret).toString();
pm.request.headers.upsert({
    key: 'x-api-sign',
    value: sign,
});
```

Postman pre-request script (Custody):

```
const crypto = require('crypto-js');
let payload = '';
const secret = pm.environment.has('api-secret-custody')
? pm.environment.get('api-secret-custody')
: pm.variables.has('api-secret-custody')
? pm.variables.get('api-secret-custody')
: pm.globals.has('api-secret-custody')
? pm.globals.get('api-secret-custody')
: pm.collectionVariables.has('api-secret-custody')
? pm.collectionVariables.get('api-secret-custody')
: console.error(new Error('api-secret-custody is missing'));
try {
payload = JSON.stringify(JSON.parse(pm.request.body.toString()));
} catch {
console.error(new Error('Cant decode JSON body'))
}
const sign = crypto.HmacSHA512(payload, secret).toString();
// console.log(secret, sign);
pm.request.headers.upsert({
key: 'x-api-sign',
value: sign,
});
```

An example in node.js (backend):

```
import crypto from 'crypto';
function calculateSignature (data: string | Buffer, secretKey: string | Buffer): string {
    return crypto
      .createHmac('SHA512', secretKey.toString())
      .update(data.toString())
      .digest('hex');
}
```

An example in C#:

```
using System;
using System.Security.Cryptography;
using System.Text;
public class HmacHelper
{
    public static string CalculateSignature(object data, object secretKey)
    {
        byte[] dataBytes;
        byte[] secretKeyBytes;
        if (data is string dataString)
        {
            dataBytes = Encoding.UTF8.GetBytes(dataString);
        }
        else if (data is byte[] dataBuffer)
        {
            dataBytes = dataBuffer;
        }
        else
        {
            throw new ArgumentException("Data must be a string or byte array");
        }
        if (secretKey is string secretKeyString)
        {
            secretKeyBytes = Encoding.UTF8.GetBytes(secretKeyString);
        }
        else if (secretKey is byte[] secretKeyBuffer)
        {
            secretKeyBytes = secretKeyBuffer;
        }
        else
        {
            throw new ArgumentException("Secret key must be a string or byte array");
        }
        using (var hmac = new HMACSHA512(secretKeyBytes))
        {
            var hashBytes = hmac.ComputeHash(dataBytes);
            return BitConverter.ToString(hashBytes).Replace("-", "").ToLower();
        }
    }
    public static void testStrings()
    {
        string data = "example data";
        string secretKey = "your-secret-key";
        string signature = CalculateSignature(data, secretKey);
        Console.WriteLine("Signature: " + signature);
    }
    public static void testBytes()
    {
        byte[] data = System.Text.Encoding.UTF8.GetBytes("example data"); // Data in buffer form
        byte[] secretKey = System.Text.Encoding.UTF8.GetBytes("your-secret-key"); // Key as a buffer
        string signature = CalculateSignature(data, secretKey);
        Console.WriteLine("Signature: " + signature);
    }
    public static void Main()
    {
        testStrings();
        testBytes();
    }
}
```

An example in PHP:

```
  $apiKey = 'YOUR_KEY';
  $apiSecret = 'YOUR_SECRET';
  $apiUrl = 'https://api.heropayments.io/v2/payments';
  $message = json_encode(
    array(
        'payCurrency' => 'trx',
        'priceCurrency' => 'usd',
        'priceAmount' => '1000',
        'customerId' => '123',
        'customerEmail' => 'test@test.com',
        'externalOrderId' => '100500',
        'callbackUrl' => 'https://example.com/callback?orderId=100500'
    ),
    JSON_UNESCAPED_SLASHES
  );
  $sign = hash_hmac('sha512', $message, $apiSecret);
  $requestHeaders = [
    'x-api-key:' . $apiKey,
    'x-api-sign:' . $sign,
    'Content-type: application/json'
  ];
  $ch = curl_init($apiUrl);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
  $response = curl_exec($ch);
  curl_close($ch);
  var_dump($response);
```

An example in Python:

```
 #Python 3.8
import requests
import json
import hashlib
import hmac
url = "https://api.heropayments.io/v2/payments"
def generate_signature(data):
  key = "YOUR_SECRET" 
  key_bytes= key.encode() 
  data_bytes = data.encode()
  return hmac.new(key_bytes, data_bytes, hashlib.sha512).hexdigest()
payload = {
    "payCurrency":"trx",
    "priceCurrency":"usd",
    "priceAmount":1000,
    "customerId":"123",
    "externalOrderId":"100500",
    "customerEmail":"test@test.com",
    "callbackUrl":"https://example.com/callback?orderId=100500",
    "fiat": True,
}
payload = json.dumps(payload, separators=(',', ':'))
headers = {
  'x-api-key': 'YOUR_KEY',
  'Content-Type': 'application/json',
  'x-api-sign': generate_signature(payload)
}
response = requests.request("POST", url, headers=headers, data=payload)
```

An example in browser:

```
import hmacSHA512 from 'crypto-js/hmac-sha512';
function calculateSignature (data: string | Buffer, secretKey: string | Buffer): string {
    return hmacSHA512(data.toString(), secretKey.toString());
}Also you can use Postman pre-request script:
```

# Payment Statuses - (V2 flow)

The transaction's status describes what is happening to the funds at any given moment and their current state.

For the detailed description of the statuses, please refer to the [GET "Payment status check by id (V2)”](https://documenter.getpostman.com/view/17469357/UVyvwv7a#b4efb371-d1c9-48f6-b47b-5f8622accbdc) method.

#### Deposit statuses

In progress:

- waiting

- confirming

- exchanging

- hold

Failed or unsuccessful (suspend the transaction on your end):

- failed

- refunded

- expired

Successful (complete the deposit on your end by updating the balances, etc.):

- sending

- finished

#### Withdrawal statuses

In progress:

- waiting

- confirming

- exchanging

- hold

- sending

Failed or unsuccessful (suspend the transaction on your end):

- failed

- refunded

Success (complete the withdrawal on your end by updating the balances, etc.):

- finished

## Faster Top-Ups with the "Confirming" Status

How to implement user balance top-up at the **Confirming** status?

In the confirming callback, we send the value of the `paidAmount` field — this is the actual amount of cryptocurrency the user transferred to the deposit address.

### Top-up options:

**1) Balance currency: USD — payment in stablecoin**\
If the user’s balance currency is USD and they pay in a stablecoin (USDT TRC20, USDT ERC20, etc.), you can use the `paidAmount` value and credit the equivalent amount in fiat.

**2) Balance currency is not USD**\
To estimate how much fiat should be credited to the user, we recommend using the endpoint:\
<https://api.heropayments.io/v2/rate>

------------------------------------------------------------------------

### Risk:

Sometimes a transaction does not reach the `finished` status and, after `confirming`, receives the `failed` status (meaning the funds never reached us). In this case, the user’s balance would be credited even though the funds were not actually received.

### Why might a transaction not reach the `finished` status?

The client may:

- Set a network fee that is too low, causing the transaction to fail because miners do not include it in a block (relevant for Ethereum).

- Send a transaction to the network and then cancel it immediately (BTC, ETH). Some wallets, such as Trezor, allow this.

- Send a transaction with a low network fee, cancel it, and then resend it to the same deposit address with a higher fee. In this case, we will mark the first transaction as failed and create a new one, processing the payment as a multi-deposit.

------------------------------------------------------------------------

Before using the **confirming** status to credit user balances, please contact our team for additional information.

# Payments statuses - (Custody flow)

For the detailed description of the statuses, please refer to the [GET "Get custody payment status”](https://documenter.getpostman.com/view/17469357/UVyvwv7a#b62b5946-51fe-4d79-b818-4ff427dcf727) method.

#### Deposit statuses

In progress:

- new

- pending

- processing

- hold

Failed or unsuccessful (suspend the transaction on your end):

- failed

- expired

Success (complete the withdrawal on your end by updating the balances, etc.):

- finished

#### **Withdrawal statuses**

In progress:

- pending

- processing

- hold

Failed or unsuccessful (suspend the transaction on your end):

- failed

- refunded

Success (complete the withdrawal on your end by updating the balances, etc.):

- finished

# Multiple deposit processing

In case a customer deposits to the same payAddress twice or more, it will still be processed.

Each customer’s transfer is a new payment. As soon as each payment has to have its own unique externalOrderId, we add a new field "sequence" to differentiate the subsequent payments.

The initial payment receives an original externalOrderId and the "sequence" field remains unchanged.

For the subsequent payments, "sequence" field gets a value.

Example for the first payment:

- externalOrderID: 1254435345456456

- sequence: original

Example for the second and further payments:

- externalOrderID: 1254435345456456

- sequence: 4fb7defa35a056ecc64fc74ea97ef90f (new unique ID)

A customer may save the deposit address on their side and send the funds without going through the process all over again.

In cases of multiple deposits, callbacks are sent to the callbackUrl of the initial payment. Our support may also notify you in case of multiple deposits.

Deposit status:

A new generated deposit-transaction remains in the status “waiting” for 4 hours (TTL) after the creation. If the transaction is not completed within this period, it receives status “expired”. If there is no new empty “waiting” transaction, we will generate a multi-deposit for an incoming payment.

Action required: adjust your system to field sequence to handle the callbacks of multiple deposits.

# Automated mistaken deposits processing

**Flow description:**

1\. A user creates a deposit request in USDT (TRC20) (**payCurrency**: **usdttrc20**), but sends TRX instead (**payCurrency**: **trx**).

2\. We will automatically detect the incorrect currency and credit the funds to your balance.

Since we process the deposit for the user in priceCurrency, we will send a callback to the same URL with the same **externalOrderId** and the field **sequence**: **original**, but with the updated **payCurrency**: **trx**.

## Original deposit:

payCurrency: **usdttrc20**\
externalOrderId: **order_id123**\
sequence: **original**\
callbackUrl: **webhook**.url/**merchant123**

## Automatically processed incorrect deposit:

payCurrency: **trx**\
externalOrderId: **order_id123**\
sequence: **original**\
callbackUrl: **webhook.url/merchant123**

**Important notes:**

1.  The **payCurrency** of the newly processed incorrect deposit contains the actual deposited currency (as shown above).

2.  The **externalOrderId** of the processed deposit is taken from the original payment.

3.  We support processing incorrect deposits only within the same blockchain network.

4.  The feature does not work on the Solana blockchain.

# Static deposit address per each customer

Methods to generate a deposit transaction:

**V2 flow**: `v2/payments`, `v2/payments-address`

**Custody flow**: `custody/deposit`

We generate a deposit address for each unique customerId. The next time you create a deposit, your customer will receive the same deposit address.

If a customer sends a deposit without creating an order on your side, it will be considered as a [multiple deposit](https://documenter.getpostman.com/view/17469357/UVyvwv7a#multiple-deposit-processing)**.**

# API errors code description

V2:

<https://docs.google.com/spreadsheets/d/16R_DIBU_3TIwKG7j6e2Iq6smyWXvSK5kWRYPpyPwym4/edit?gid=0#gid=0>

Custody:

<https://docs.google.com/spreadsheets/d/16R_DIBU_3TIwKG7j6e2Iq6smyWXvSK5kWRYPpyPwym4/edit?gid=1669444698#gid=1669444698>
