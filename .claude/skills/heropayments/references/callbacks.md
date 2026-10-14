# Heropayments — callbacks

# Callbacks

Callbacks are used for notifications when transaction status is changed. To use them, you should complete the following steps:

1.  Get the secret key in "API" section at the admin panel;

2.  When you call POST ["Create a deposit (V2)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#412c5848-dbed-42c5-980b-a3060e9e8c46) / ["Create a deposit (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#20b9f2a0-936b-4909-980f-9b7ca72ed6c8) or POST ["Сreate a withdrawal (V2)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#a4e3c2d9-c88d-417e-90eb-947fc8eb9c10) / ["Create a withdrawal (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#bb5ca6c4-abbe-4bc3-b2ff-4f7425cac02a) request, insert your URL address in "callbackUrl" field to get notifications.

3.  You will receive all the parameters at the URL address you specified in (2) by POST request. The POST request will contain x-api-sign parameter in the header. The body of the request is similar to GET [“Payment status check by id (V2)”](https://documenter.getpostman.com/view/17469357/UVyvwv7a#f850d90d-7926-46f6-bcbf-4b90369cbbf8) / ["Payment status check by id (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#b62b5946-51fe-4d79-b818-4ff427dcf727) response body, but not identical;

4.  Convert the response body to string using [JSON.stringify](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/JSON/stringify);

5.  Sign a string with a secret key with **HMAC-SHA512** algorithm;

6.  Compare the signed string from the previous step with the x-api-sign stored in the header of the callback request. If these strings are identical, the system works properly. Otherwise, contact us via [support@heropayments.io](https://mailto:support@nowpayments.io) to solve the problem.

### Callback notification examples

#### V2 deposit Callback

```
"id": "f5529a76-cb68-4d19-a97b-12dcd6712c02", // Heropayments payment ID.
      "fiat": true, // parameter to specify for priceCurrency. True is set by default. Pass false to create a transaction with crypto estimation.
      "status": "finished", // status of the transaction.
      "feeUSDT": 0.02694825, // Heropayments processing fee (clientAmountUsdt * feePercent)
"invoice": {
            "id": "9852d8c1-fca6-45d8-aa55-99e0050c2d94", // invoice ID, not recommended to use it as payment identificator, as it does not appear in the report.
            "orderId": "1454445333244115", // merchant's custom order ID.
            "customerId": "123", //  ID of the customer.
            "priceAmount": 100, //  amount specified by the user for the deposit in priceCurrency. The estimated amount to complete the payment, is not equal to the sent amount.
            "priceCurrency": "usd" // user's account currency: fiat or cryptocurrency (USD, EUR, BTC,DOGE etc.)
           },
            "payHash": "0x4fee71ad6617f18cfd0e75a9a349496f8bab04d4bbab44944a47376f40ead6a", // transaction hash to the deposit address.
            "payRate": 598.85, //  payCurrency to outcomeCurrency ratio.
            "payExtra": null, // additional Id which is necessary for identifying a recipient of the transaction (memo, destination tag). Obligatory for deposits in XRP, XLM, HBAR.
            "sequence": "original", // as soon as each payment has to have its own unique externalOrderId, we add an additional field "sequence" to differentiate the subsequent payments.
Use case: after finishing the first payment, the customer saves the payAddress and sends the funds again to this address without creating an invoice on the site.
Example for the first payment:
externalOrderID: 1254435345456456
sequence: original
Example for the second and further payments:
externalOrderID: 1254435345456456
sequence (2nd payment): 4fb7defa35a056ecc64fc74ea97ef90f (new unique ID)
sequence (3rd payment: 4fb7d453566545623ав2343455(new unique ID)
etc.
        "createdAt": "2025-05-02T19:56:13.000Z", // time, when the transaction was created.
        "payAmount": 0.1678591, // expected amount to pay by the user in crypto (payCurrency) to finish the transaction.
        "priceRate": 0.9996, // priceCurrency to outcomeCurrency ratio.
        “userNotes” : null, // technical field, not used.
        "feePercent": 0.5, // Heropayments processing fee (%).
        "networkFee": 0,5, // network fee deducted for processing the blockchain transaction.
        "paidAmount": 0.009, //  amount that was actually sent in crypto (payCurrency) in the transaction.
        "payAddress": "0x2d7e23d8f9e04110d1c6d255b40841a8585d6662", //  deposit address for crypto assets, used to process the deposit.
        "callbackUrl": "https://example.com/callback", // HTTP(s) URL of your server which will accept callback requests. Heropayments will send callbacks whenever the status of a transaction gets an update.
        "outcomeHash": "0x4fa6f8ca917de24ddf289a997de597657f48b1c9a2f5ffae96847e8e6c36595", //  transaction hash to the merchant’s balance wallet.
        "payCurrency": "bnbbsc", // cryptocurrency sent by the user to finish the deposit
        "payHashLink": "https://bscscan.com/tx/0x4fee71ad664f18cfffd0e75a9a3494696f8bab0d4bbab44944a4376f40ea56a", //  link to the transaction hash.
        "clientAmount": 5.39, //  fiat equivalent of clientAmountUsdt in priceCurrency. Shows the amount to be credited to user’s account. 
        "payinSenders": [
            "0x3b3cfe3336b6accf2044274cf3c4825c4b644cb" // customer’s wallet, used to process the deposit.
        ],
        "customerEmail": "TestEmail.com", // user’s email address (not required).
        "outcomeAmount": 4.86270175, // amount credited to the merchant’s wallet.
        "outcomeAddress": "0x8a466fc4c95dd97d51fa5532890dea7fff0cfbf",
        "externalOrderId": "1454445333244115", // merchant's custom order ID
        "outcomeCurrency": "usdt20", // merchant’s balance currency.
        "outcomeHashLink": "https://etherscan.io/token/0xdac17f958d2ee523a22006994597c13d831ec7?a=0x4fa6f8ca917de24ddf28cc9a997de597657f8b1c9a2f5ffae96847e84e6c36595", // transaction hash link after the exchange to merchant’s wallet.
        "transactionType": "deposit", // always deposit or withdrawal
        "clientAmountUsdt": 5.38965 // deposit amount  received from the  user and converted to USDT. Calculated as “merchantAmountUsdt” + network fee + processing fee.
        "merchantAmountUsdt": 4.86270175 // amount credited to Merchant’s wallet in USDT. Calculated as ClienttAmountUsdt” - network fee - processing fee.
```

**V2 withdrawal Callback**

```
 "id": "536d0fcf-beb3-4b6b-b752-4ec5b16e02e8", // Heropayments payment ID.
        "fiat": true, // // parameter to specify priceCurrency. True is set by default. Pass false to create a transaction with crypto estimation, if the user's account is in crypto.
        "status": "finished", //  // status of the transaction.
        "feeUSDT": 0.03933199, // Heropayments processing fee (clientAmountUsdt * feePercent).
        "invoice": {
            "id": "b4d98885-f83e-42d9-a5b2-fec6f868f5cb", // invoice ID, not recommended to use it as payment identificator, as it does not appear in the report.
            "orderId": null, // always null
            "customerId": "12312", //  ID of the customer.
            "priceAmount": 600, //  amount specified by the user for the withdrawal in priceCurrency. The estimated amount to complete the payment, is not equal to the sent sum.
            "priceCurrency": "rub" // user's account currency: fiat or cryptocurrency (ex. USD, EUR, BTC,DOGE etc.)
        "payHash": "0x0f248ffd2874c0def2b15141eb1409603a9ed24a870c948d13644402f84fd", // transaction hash from the merchant's balance address to an exchange address.
        "payRate": 1, // payCurrency to outcomeCurrency ratio.
        "payExtra": null, // additional Id which is necessary for identifying a recipient of the transaction. Also known as additional address, memo or destination tag. Users must enter it while withdrawing XRP, BNB, XLM and some other currencies
        "sequence": "original", //used for deposits only, for withdrawals remains "original".
        "createdAt": "2025-03-06T10:57:22.000Z", // time, when the transaction was created.
        "payAmount": 6.85714285, //  the amount that must be deducted from the merchant's account to process a withdrawal.
        "priceRate": 0.01086, // priceCurrency to outcomeCurrency ratio.
        "userNotes": null, // technical field, not used.
        "feePercent": 0.6, // Heropayments processing fee (%).
        "networkFee": 0.3, // network fee deducted for processing the blockchain transaction.
        "paidAmount": 6.85714285, // the amount deducted from the balance to process the payment.
        "callbackUrl": "https://webhook.site/07e09722-fc19-4d0d-b81f-f36a8c65850", // HTTP(s) URL of your server which will accept callback requests. Heropayments will send callbacks whenever the status of a transaction gets an update.
        "outcomeHash": "0x98a839528e39a1d4c1472fcc878df6aa62f41ca5bf9ffd902035b9402cfe8f", // transaction hash to the user's wallet
        "payCurrency": "usdtbsc", // merchant's balance currency.
        "payHashLink": "https://bscscan.com/tx/0x0f248ffd2874c0d2b15141eb141b09603a9ed624a870c948d13644402f84fd", // transaction hash from the merchant's balance address to an exchange address.
        "clientAmount": 600, // equivalent of priceAmount in priceCurrency. This amount that user should receive for a withdrawal.
        "customerEmail": "test@test.com", //  user's email address (not required).
        "outcomeAmount": 6.516, // the amount that a user received for a withdrawal
        "outcomeAddress": "0x3b3cfe3336b6accf2044274cf3c5c25c4b64cb", //  user's address to receive the withdrawal.
        "outcomeExtraId": null, // the same as payExtra.
        "externalOrderId": "112434321", // merchant's custom order ID.
        "outcomeCurrency": "usdtbsc", // a currency that the user chose for the withdrawal.
        "outcomeHashLink": "https://bscscan.com/tx/0x98a839528e39a4c1472fcc878df6aa62f41ca5bb9f9ffd902035b9402cfe8f", // transaction hash  link to the user's wallet.
        "transactionType": "withdrawal", // always deposit or withdrawal.
        "clientAmountUsdt": 6.51781086, //  USDT equivalent of a withdrawal amount sent to the user. Calculated as "merchantAmountUsdt" -  network fee -  processing fee.
        "merchantAmountUsdt": 6.85714285 // the amount deducted from the balance to process the payment. Calculated as "ClienttAmountUsdt" + network fee + processing fee.
```

**Custody deposit Callback**

```
"id": "f3748917-d4ae-4dae-bec1-c00e269be990", // Heropayments payment ID.
        "hash": "0xc81b15173d536f5063ec4c11cfd37aa5cfbfd668ac2433d175efea100bfd25234", // deposit hash
        "amount": "4", // deposited amount.
        "status": "finished", // status of the transaction.
        "address": "0xfc9ca35f6296cadfc5f925145acd55abfd0eafae8", //  deposit address for crypto assets.
        "comment": null, //  additional information about the deposit.
        "currency": "usdtbsc", // deposited currency.
        "hashLink": null,
        "sequence": "original", //  helps to differentiate between the subsequent payments. Please read multiple deposit processing.
        "subEmail": null, //  if a payment was created by a subuser of the main account, the field reflects the name of the subaccount.
        "createdAt": "2025-03-13T14:16:53.315Z", // time, when the deposit was created.
        "feeAmount": "0.02", // Heropayments processing fee in the Currency equivalent.
        "updatedAt": "2025-03-13T14:21:22.163Z", // time, when the deposit was updated.
        "customerId": "new_test_user",
        "feePercent": 0.5, //  Heropayments processing fee (%).
        "networkFee": "0.3", // network fee deducted for processing the blockchain transaction. Estimated in a payment currency equivalent
        "callbackUrl": "https://webhook.site/07e09722-fc19-4d0d-b81f-f5236a8c65850", //  HTTP(s) URL of your server which will accept callback requests. Heropayments will send callbacks whenever the status of a transaction gets an update. If your server is inaccessible or cannot accept a callback request, our system will try to send it again until it reaches the limit of five retries.
        "addressExtra": null, // additional Id which is necessary for identifying a recipient of the transaction (memo, destination tag). Obligatory for deposits in XRP, XLM, HBAR.
        "clientAmount": "4", // amount of crypto received from a user to a created deposit address.
        "confirmations": 23, //  number of confirmations on the blockchain. We need to wait until the payment gets at least several confirmations to process it.
        "merchantAmount": "3.68", //amount credited to Merchant's wallet in Currency
        "externalOrderId": "mercha56564554n2t_44o5rder_id_123123", // merchant's custom order ID. Example: BGBRB-30020. This ID must be unique to create a transaction.
        "transactionType": "deposit" // always deposit or withdrawal.
```

## Fallbacks

We strongly recommend you to back up callback services by using GET ["Payment status check by id (V2)”](https://documenter.getpostman.com/view/17469357/UVyvwv7a#f850d90d-7926-46f6-bcbf-4b90369cbbf8) / ["Payment status check by id (Custody)"](https://documenter.getpostman.com/view/17469357/UVyvwv7a#b62b5946-51fe-4d79-b818-4ff427dcf727) to receive the same info about payments as you get from the callback notification.

## **CallbackUrl recommendations**

We highly recommend setting static callBackUrls both for deposit and withdrawals. Using static callBackUrls will facilitate the process of changing callback URLs if a need arises.

Example of static callBackUrls (recommended flow)

- **Deposit:** [**https://m54543qin7.com/deposit/hero_payments**](https://m54543qin7.com/deposit/hero_payments) **(for all deposit transactions one callBackUrl)**

- **Withdrawal:** [**https://m54543qin7.com/withdrawal/hero_payments**](https://m54543qin7.com/withdrawal/hero_payments) **(for all withdrawal transactions one callBackUrl)**

Example of dynamic callBackUrls (not recommended flow)

- Deposit:\
  [**https://m54543qin7.com/deposit/hero_payments**](https://m54543qin7.com/deposit/hero_payments)/payment_1\
  [**https://m54543qin7.com/deposit/hero_payments**](https://m54543qin7.com/deposit/hero_payments)/payment_2\
  [**https://m54543qin7.com/deposit/hero_payments**](https://m54543qin7.com/deposit/hero_payments)/payment_3\
  etc.

- Withdrawal:\
  [**https://m54543qin7.com/withdwaral/hero_payments**](https://m54543qin7.com/deposit/hero_payments)/payment_1\
  [**https://m54543qin7.com/withdwaral/hero_payments**](https://m54543qin7.com/deposit/hero_payments)/payment_2\
  [**https://m54543qin7.com/withdwaral/hero_payments**](https://m54543qin7.com/deposit/hero_payments)/payment_3\
  etc.
