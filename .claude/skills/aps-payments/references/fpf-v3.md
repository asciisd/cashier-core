# Integration process

Hello! This is documentation for production environment.

1.  [Overview](#overview)
2.  [Integration Credentials](#integration-credentials)
3.  [Integration Process](#integration-process)
    - [Merchant Onboarding and Integration Steps](#sequence-of-steps)
    - [Integration diagram](#integration-diagram)
    - [Check before starting to work in Prod Environment](#check-before-starting-to-work-in-prod)
    - [Sequence diagram for deposit](#sequence-diagram-for-deposit)
    - [Sequence diagram for remit](#sequence-diagram-for-payout)
4.  [Retrieve FPF URL](#step-1-get-fpf-url)
5.  [Get Available Payment Methods](#get-info-by-payment-methods)
    - [Prefilled fields](#prefilled-fields)
6.  [Confirmation Callback for Remits](#step-2-confirmation-callback-for-withdrawals)
7.  [Get Status Transaction](#step-3-get-status-transaction)
8.  [Transaction Status Callback](#step-4-final-status-callback)
    - [Deposit Transaction Status Callback](#transaction-status-callback-for-deposit)
    - [Remit Transaction Status Callback](#transaction-status-callback-for-remit)
9.  [How to make a Refund](#how-to-make-refund)
10. [Get Merchant Account Balance via API](#how-to-check-balance-merchant-cabinet)
11. [Get Transaction History (CSV)](#get-a-list-of-transactions)

## Overview

This document provides a detailed guide on using APS pre-built UI solution for payments. It outlines the interaction between parties for processing deposit and remit payments.

Participants:

- **Customer** - The end user who makes a deposit or receives a payment (remit) from the merchant
- **Merchant** - Your company
- **FPF** - Fast-Payment-Flow checkout UI
- **FPF-Backend** - The APS backend system

There are two available transaction flows:

- Deposit Flow – The process where customers make payments to the merchant.
- Remit Flow – The process where the merchant remits funds to the customer.

In this guide, the terms remit and payout are used interchangeably to refer to transactions where funds are transferred from the merchant to the customer.

## Integration Credentials

During the onboarding process, you will receive a set of credentials to access the APS API for both staging and production environments. APS API uses token-based authentication.

**Required Authentication Headers**

Every API request must include the following headers:

- App Token – Passed in the `X-App-Token` header.
- App Secret – Passed in the `X-App-Secret` header.

Requests missing these credentials will be rejected.

**Other API Credentials**

- Merchant GUID – A unique identifier for the merchant, used in the API endpoint path as `merchantGUID`.
- Callback Secret Key – Used to validate callback requests from APS. Status callbacks include a request signature for verification.

**Environment-Specific URLs**

- Staging Environment: <https://fpf-api.armenotech.net>
- Production Environment: <https://fpf-api.proc-gw.com>

Each environment has its own credentials, so ensure you use the correct set when switching between staging and production.

## Integration Process

### Merchant Onboarding and Integration Steps

1.  Agreement on Payment Methods, Countries, and Limits

You (the Merchant) and APS (we) will establish agreements regarding supported payment methods, countries, and transfer limits. For inquiries about limits and geographic coverage, please contact your account manager.

1.  Receiving Credentials for the Staging Environment

- We will send you credentials for the Staging Environment via a one-time link sent to your email.

- Before issuing a request, ensure that the set of tokens for your merchantGUID matches the provided credentials. Please verify these credentials with the APS team before making a request.

1.  Testing and Documentation Review

Review this document and perform the following tests:

- Go to [Retrieve fpf-url](#step-1-get-fpf-url) to obtain the payment form.
- Access [Get info by payment methods](#get-info-by-payment-methods). Make this request to receive an array of payment methods available to you. The response will detail the payment currency, fees, and limits for each method.

1.  Transition to the Production Environment

- Once all tests are complete and the necessary documents are signed, make the required settings, and we will send you the credentials for the Production Environment.

- Please note that the credentials for the Production Environment differ from those for the Staging Environment.

- The requests will function in the same way. We will provide you with the documentation for the Production Environment, along with all necessary links and guides.

1.  Access to the Merchant Portal

- You will also gain access to the Merchant Portal, where you can log in using the email address where you received your credentials.
- For detailed instructions on how to use the Merchant Portal, please refer to the Merchant Portal Guide.

### Integration Diagram

![integration steps](/developers/assets/images/steps-b6a44d5b9cbca1ce76162e3e025259d3.png)

### Check-list before Go Live

1.  **Access to Failure Reasons** Ensure that your operations team has access to the `external_message` field in the callback. This field contains the reason for transaction failures, which can help minimize the number of support requests to us.

2.  **Traffic Launch Notification** Notify us at least 1 day in advance before launching live traffic. Do not initiate traffic without our approval.

3.  **Support After Integration**

    - Once integration is complete, you will have access to the Support Portal, where your operations team can resolve issues in the Production environment.

    - To obtain access to the support portal, provide your account manager with the email addresses of your employees.

    - For any inquiries related to live traffic, please use the Support Portal. Before reaching out, check the provided links to identify error causes:

      - [Portal](https://armenotech.atlassian.net/servicedesk/customer/kb/view/959840305) for deposit transactions

      - [Portal](https://armenotech.atlassian.net/servicedesk/customer/article/1311867029) for remit transactions.

### Sequence Diagram for Deposit

![Sequence Diagram for Deposit](/developers/assets/images/SequenceDiagram_For_Deposit-1981e8c4fdf9500f1cb331558bd8ad6b.png)

The happy-path flow is as follows:

1.  A customer initiates a deposit request using a specific payment method, typically accessible via a designated button or link on the merchant's website or application.

2.  The merchant filters and selects the relevant payment method identifiers from the available options. Refer to the [Get Available Payment Methods](#get-info-by-payment-methods) chapter for more details.

3.  The merchant retrieves an FPF URL containing the selected payment methods and redirects the customer to this URL. See the [Retrieve FPF URL](#step-1-get-fpf-url) chapter for guidance.

4.  The customer completes the necessary fields on the FPF form and clicks the "Pay" button.

5.  Upon successful payment, the FPF system redirects the customer back to the merchant's website.

6.  The PSP processes the payment and sends an HTTP callback to the merchant with the transaction result. Refer to the [Transaction Status Callback](#step-4-final-status-callback) chapter for details.

### Sequence Diagram for Remit

![Sequence Diagram for Remit](/developers/assets/images/SequenceDiagram_For_Payout-808dfa17342ed930dcb96d80314d6163.png)

The happy-path flow is as follows:

1.  The customer initiates a remit request using a specific payment method, accessible through a designated button or link on the merchant's website or application.

2.  The merchant filters and selects the relevant payment method identifiers from the available options. Refer to the [Get Available Payment Methods](#get-info-by-payment-methods) chapter for more details..

3.  The merchant retrieves an FPF URL containing the selected payment methods and redirects the customer to this URL. See the [Retrieve FPF URL](#step-1-get-fpf-url) chapter for guidance.

4.  The customer completes the necessary fields on the FPF form and clicks the "Pay" button.

5.  The FPF sends the remit request to the PSP (Payment Service Provider).

6.  The PSP sends a confirmation callback to the merchant's backend to obtain approval for the remit transaction. This step validates the remit amount on the merchant's side before the request is sent to the payment provider. See [Confirmation Callback for Remits](#step-2-confirmation-callback-for-withdrawals) chapter for details.

7.  Upon successful remit registration, the FPF redirects the customer back to the merchant's website.

8.  The PSP processes remit and sends an HTTP callback to the merchant with the transaction result. Refer to the [Transaction Status Callback](#step-4-final-status-callback) chapter for details.

## Retrieve FPF URL

To retrieve the payment form, use the request `POST /fpf-url`. To ensure you have all the required fields, please refer to the section on [Get Available Payment Methods](#get-info-by-payment-methods)

**Request example for deposit with required fields:**

**Retrieve fpf-URL for deposit**

``` bash
curl POST https://fpf-api.proc-gw.com/api/v3/merchantGUID/fpf-url \
--header 'Content-Type: application/json'
--header 'X-App-Token: {APP_KEY}'
--header 'X-App-Secret: {APP_SECRET}'
--data-raw '{
 "mode": "deposit"
  }'
        
```

**Request example for deposit with optional fields:**

**Retrieve fpf-URL for deposit with optional fields**

``` bash
curl POST https://fpf-api.proc-gw.com/api/v3/merchantGUID/fpf-url \
--header 'Content-Type: application/json'
--header 'X-App-Token: {APP_KEY}'
--header 'X-App-Secret: {APP_SECRET}'
--data-raw '{
      "mode": "deposit",
      "redirect_url": "https://redirect.com",
      "status_callback_url": "https://status.com",
      "amount": 100.20,
      "merchant_external_id": "random string",
      "merchant_payer_id": "random string",
      "countries": "GBR,DEU",
      "language": "es",
      "payment_methods": [
          "836303b0-8ae2-4ae8-9002-0dae182bf4a5:c3f4cb0a-7e1c-4779-adb7-7326fbef2f7b"
      ]
  }'
        
```

**Request example for remit:**

**Retrieve fpf-URL for remit**

``` bash
curl POST https://fpf-api.proc-gw.com/api/v3/merchantGUID/fpf-url \
--header 'Content-Type: application/json'
--header 'X-App-Token: {APP_KEY}'
--header 'X-App-Secret: {APP_SECRET}'
--data-raw '{
    "mode": "remit",
    "redirect_url": "https://redirect.com",
    "callback_confirmation_url": "https://confirmation.com",
    "status_callback_url": "https://status.com",
    "amount": 100,
    "merchant_external_id": "random string"
  }'
        
```

You need to redirect the customizer to this URL, you can use it in a new tab/window or in an iFrame.

A JSON object containing the following fields:

| Name | Description | Required |
|----|----|----|
| `mode` | Indicates the payment flow, which can be one of the following: deposit or remit. | yes |
| `redirect_url` | The URL to which the customer will be redirected after the payment is completed. The query parameter status will be appended to this URL to indicate the payment result, with possible values of success or fail. It’s important to note that this status reflects the payment processing status, not the final transaction outcome. A status of success means the customer has successfully submitted their payment details, and the payment system is awaiting confirmation that the funds have been received. | yes |
| `callback_confirmation_url` | The URL where confirmation callbacks will be sent. | yes, only if `mode=remit` |
| `status_callback_url` | The full URL to retrieve the final status of the transaction. | no |
| `amount` | The amount of the payment. If set to 0, the customer will be able to enter any amount within the established limits. | no |
| `merchant_external_id` | The merchant's transaction ID, which can be a string up to 96 characters long. | no |
| `merchant_payer_id` | The payer's identifier in the merchant's internal system, also a string up to 96 characters long. There is no requirement for `merchant_external_id` to be unique across all of the merchant’s transactions. | no |
| `countries` | A string of country codes in alpha-3 format (e.g., GBR for Great Britain, DEU for Germany). | no |
| `language` | An ISO 639-1 language code to set the localization of the FPF form. Supported languages: en - English, es - Spanish, pt - Portuguese, de - German, fr - French. | no |
| `payment_methods` | An array of payment method IDs used to filter and display only specific payment methods on the payment form. | no |

Successful response example - JSON object containing URL:

``` text

{
      "url": https://fpf.proc-gw.com/v3?token=eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.
      eyJwYXlsb2FkIjp7InRyYW5zYWN0aW9uX2d1aWQiOiIxZTJjMzJhNC1iZGJiLTQ0ODItYTAxMC00OWY1ZDc3MzA0OWEiLCJtb2RlIjoiZGVwb3NpdCIsIm1lcmNoYW50X2
      d1aWQiOiI3MWM3YmFlNC1iZDljLTRjZDItOGY5Yi03ZDg5YjQ5MzEyMjEiLCJjYWxsYmFja191cmwiOiJodHRwczovL2Zhc3QtcGF5bWVudC1mbG93LW1lcmNoYW50LmFy
      bWVub3RlY2guZGV2LzcxYzdiYWU0LWJkOWMtNGNkMi04ZjliLTdkODliNDkzMTIyMS90cmFuc2FjdGlvbnMiLCJyZWRpcmVjdF91cmwiOiJodHRwczovL3d3dy55b3V0dW
      JlLmNvbS93YXRjaD92PWRRdzR3OVdnWGNRIiwic3RhdHVzX2NhbGxiYWNrX3VybCI6Imh0dHBzOi8vZmFzdC1wYXltZW50LWZsb3ctbWVyY2hhbnQuYXJtZW5vdGVjaC5k
      ZXYvNzFjN2JhZTQtYmQ5Yy00Y2QyLThmOWItN2Q4OWI0OTMxMjIxL3RyYW5zYWN0aW9ucyIsImFtb3VudCI6MTAwLjJ9LCJleHAiOjE2OTk0Nzk2NDQsImp0aSI6ImMyZD
      JiY2NjLWJjY2MtNDI0OS1hYTNhLTAxZGZiNDY4MDNiNSIsImlhdCI6MTY2NzkyMjA0NH0.
      tt3Psqme0dhs8itLE01-EoRvto-451G4IkDsKMdqiQ67EKkdMgzRM5JCJbJf5DtBHCje6134j56q1EYsXvomESOA0cMhTwPq1PdJyNxS0ZwMVU0SV1Z1YDctnBWQZBHniM
      kp0L43bwMmUrEiSyfdDsXsbkUM6OY8A9ezRrKNu4MWmyhy0ZAA2wvtKoJEgmdX_v6vcz_lGMTiz13qM_obubOp5VeL8S15p5pZrFccy7hwoKK31cOhh-lEqsM9Cwuzy9uG
      OXp6Dlye0EgaAOCm"
      }
```

Error response:

A JSON object containing the following fields:

| Field Name | Description       |
|------------|-------------------|
| `message`  | Error description |
| `trace_id` | Request trace id  |

## Get Available Payment Methods

The `GET /info` request retrieves information about the available payment methods and their details. By making this request, you can obtain all the necessary information about the connected payment methods.

Example request:

**Get info by payment methods**

``` bash
curl https://fpf-api.proc-gw.com/api/v3/merchantGUID/info \
--header 'X-App-Token: {APP_KEY}'
--header 'X-App-Secret: {APP_SECRET}'
        
```

Example response:

``` text
  {
    "methods": [
      {
        "guid": "c8e18874-ce90-4a6a-86cd-bcd17f815476:d93717d0-86d0-435a-8191-ea1b43d04a96",
        "fee_fixed": 0,
        "fee_percent": 0,
        "customer_fee_percent": 0,
        "customer_fee_fixed": 0,
        "min_amount": 1.43,
        "max_amount": 570.72,
        "label": "EUR",
        "logo_url": "https://fpf-assets.armenotech.com/mastercard.svg",
        "mobile_logo_url": "https://fpf-assets.armenotech.com/mastercard.svg",
        "payment_group": "BANKCARD",
        "payment_group_name": "Bank card",
        "digits_delivery": 2,
        "digits_asset": 2,
        "rate": 1.4,
        "delivery_currency": "iso4217:EUR",
        "method_currency": "BRL",
        "method_symbol": "R$",
        "country_code": "WWC",
        "direction": "in"
        "deposit": {
          "guid": "c8e18874-ce90-4a6a-86cd-bcd17f815476:d93717d0-86d0-435a-8191-ea1b43d04a96",
          "fields": {
            "external_id": {
              "optional": true,
              "description": "Merchant’s external ID",
              "hidden": true
            },
            "redirect_url": {
              "optional": true,
              "description": "url where a customer will be redirected with parameters status=[successful|failed|canceled], transaction_id, method, amount, currency",
              "hidden": true,
            },
            "status_callback_url": {
              "optional": true,
              "description": "Callback URL",
              "hidden": true
            }
          },
        }
      }
     ]
 }
```

The response is an array of payment methods available to you. Each method includes detailed information about the payment currency, fees, limits, etc. Most of the parameters are utilized by the FPF Frontend.

| Name | Description |
|----|----|
| `methods[?].guid` | Unique identifier (GUID) for the payment method. |
| `methods[?].fee_fixed` | Fixed fee associated with the payment method, as per the contract with the merchant. |
| `methods[?].fee_percent` | Percentage fee applied to the transaction, in accordance with the merchant's contract. |
| `methods[?].customer_fee_percent` | Percentage fee to be paid by the end user, defined in the merchant's contract. |
| `methods[?].customer_fee_fixed` | Fixed fee that the end user must pay, according to the contract with the merchant. |
| `methods[?].min_amount` | The minimum allowable amount for a single transaction using this payment method. |
| `methods[?].max_amount` | The maximum allowable amount for a single transaction using this payment method. |
| `methods[?].label` | User-friendly label for the payment method. |
| `methods[?].logo_url` | URL for the logo of the payment method. |
| `methods[?].mobile_logo_url` | URL for the logo of the payment method, optimized for mobile display |
| `methods[?].payment_group` | Additional categorization information for the payment method. |
| `methods[?].payment_group_name` | Name of the payment group associated with this method. |
| `methods[?].payment_category` | The category classification of the payment method (e.g., credit card, e-wallet, etc.). |
| `methods[?].digits_delivery` | Number of decimal places allowed for the delivery currency. |
| `methods[?]digits_asset` | Number of decimal places allowed for the asset. |
| `methods[?]rate` | Conversion rate from the asset to the delivery currency. |
| `methods[?]delivery_currency` | Currency code for the delivery currency. |
| `methods[?].method_currency` | The currency in which all amounts related to this payment method are calculated. This is represented as a three-letter currency code (e.g., USD, IDR, MYR). |
| `methods[?].method_symbol` | Symbol of the currency used with this payment method (may be omitted). |
| `methods[?].country_code` | The country code where the payment method is applicable. |
| `methods[?].direction` | Indicates the transaction type: `in`: returned for deposit methods. `out`: returned for remit methods. This field determines whether the method fields specification is found under the deposit or remit attribute. |
| `methods[?].deposit/remit` | The object of type `PaymentMethodFields` containing the fields specification. |
| `PaymentMethodFields.guid` | Unique identifier (GUID) for the payment method fields. |
| `PaymentMethodField.fields` | An associated array with a field name as a key and a field spec as value. key type of `String` and specifies the field name. value type of `PaymentMethodField`. |
| `PaymentMethodField.optional` | Boolean value indicating whether this field is optional (true means the parameter is not required). |
| `PaymentMethodField.description` | Description of the field and its purpose. |
| `PaymentMethodField.hidden` | Instruction for the frontend application to hide this field from the user interface. |

### Prefilled fields

Prefilled fields allow you to provide values that will be displayed on the FPF form, so clients do not need to manually fill in payment details. For example, if a payment method requires First Name, Last Name, and Email, these can be prefilled.

While prefilled fields are optional, we strongly recommend including them as they are important for improving conversion rates.

Merchant can add the following fields to `POST /fpf-url` request in order to provide values:

``` text
"fields": {
"from_first_name": "Yervand Kochar",
"from_last_name": "Yerevan",
"from_email": "anton.aleev@armenotech.com"}
```

In this case, the FPF form will not display the fields, and the client won't need to manually provide details. The field names can be found in the `GET /info` response.

However, there's an exception: if the provided value is incorrect (i.e., fails validation), the FPF form will display the field, allowing the client to correct the issue.

## Confirmation Callback for Remits

Remit transactions initiated via the FPF payment form are not executed immediately. A confirmation step is required to ensure the merchant’s backend approves the transaction before it proceeds.

**Confirmation Workflow**

1.  Transaction Initiation: The customer starts a remit transaction using the FPF form.
2.  Callback Dispatch: Before executing the transaction, the PSP sends a callback to the `callback_confirmation_url` provided in the initial POST /fpf-url request.
3.  Merchant Confirmation: The merchant’s system receives the callback and must decide whether to confirm or reject the transaction. It should respond with:
    - 200 OK or 201 Created to confirm the transaction.
    - Any other HTTP status (e.g. 400, 500) to reject the transaction. In this case, an optional error message can be included in the response body.

**Callback Request Payload**

The callback is a JSON object containing:

| Field | Description |
|----|----|
| `transaction_id` | The unique transaction identifier assigned by the PSP. |
| `merchant_external_id` | (Optional) The merchant's transaction ID, included if provided during the POST /fpf-url request. |
| `amount` | The amount (in float) to be remitted. |

## Get Status Transaction

You can request the transaction status via the API using the transaction ID.

Example request:

**Get Status Transaction**

``` bash
curl https://fpf-api.proc-gw.com/api/v3/merchantGUID/TRANSACTION_ID \
        --header 'X-App-Token: APP_KEY'
        --header 'X-App-Secret: APP_SECRET'
        --header 'Content-Type: application/json'
        
```

Example response:

``` text
{
    "id": "f2a86886-675c-4f55-a247-757a4ef55e53",
    "external_id": "test 2434",
    "status": "completed",
    "refunded": true,
    "amount": 3,
    "amount_in": 3,
    "amount_out": 3,
    "amount_fee": 0,
    "amount_body": 3,
    "customer_fee": 0,
    "merchant_fee": 0,
    "external_message": "APPROVED",
    "fiscal_status": "done",
    "refunds": {
        "amount_refunded": "3",
        "amount_fee": "0",
        "payments": [
            {
                "id": "ee9de236-6f6a-48b7-b1ad-bb496e6ba5dc",
                "external_id": "{YOUR_REFUND_EXTERNAL_ID}",
                "status": "completed",
                "fiscal_status": "refunded",
                "amount": 3,
                "rrn": "688447"
            }
        ]
    }
}
```

A JSON object containing the following fields:

| Name | Type | Optional | Description |
|----|----|----|----|
| `id` | `UUID` | `true` | Unique identifier for the transaction. |
| `merchant_external_id` | `string` | `true` | External transaction ID provided by the merchant. |
| `status` | `string enum` | `true` | Current status of the transaction. Possible values include: |
|  | `pending_sender`: awaiting payment from the customer. |  |  |
|  | `pending_external`: The customer has made a payment, and the transaction is under AML-check (Anti-Money Laundering). Alternatively, the merchant has initiated a refund, and the refund is pending completion. |  |  |
|  | `completed`: The transaction has been successfully completed. |  |  |
|  | `error`: There was an error with the transaction. |  |  |
|  | `pending_transaction_info_update`: An error occurred during transaction initiation. This status should be interpreted as an error. |  |  |
| `fiscal_status` | Indicates the current status of the operation. Possible values include: | yes |  |
|  | `pending`: Operation is in progress. |  |  |
|  | `canceled`: Operation was canceled. |  |  |
|  | `expired`: Payment was not completed in time, and the operation expired. |  |  |
|  | `done`: Operation completed. |  |  |
|  | `failed`: (For Payouts only) Unable to complete the operation due to lack of funds or error. |  |  |
| `amount` | `float` | `false` | The amount specified in the original request (float). |
| `refunded` | `bool` | `true` | Indicates whether the transaction has been refunded (`true` or `false`). |
| `amount_in` | `float` | `true` | Amount received from the sender. For deposit transactions, this reflects the amount the customer paid in the contract currency. For remit transactions, it indicates the amount charged by the merchant to execute the transaction. |
| `amount_out` | `float` | `true` | Amount credited to the receiver. For deposit transactions, this reflects the amount credited to the merchant. For remit transactions, it shows the amount credited to the customer. |
| `amount_fee` | `float` | `true` | The fee calculated according to the contract. |
| `customer_fee` | `float` | `true` | The calculated fee that the customer is required to pay. |
| `merchant_fee` | `float` | `true` | The calculated fee that is paid or earned by the merchant. A positive value indicates a fee to be paid by the merchant, while a negative value signifies a benefit to the merchant. |
| `refunds.amount_refunded` | `float` | `true` | The total amount to be refunded. |
| `refunds.amount_fee` | `float` | `true` | The fee associated with the refund, according to the contract. |
| `refunds.payments[?].id` | `UUID` | `true` | Unique identifier (UUID) for the refund transaction. |
| `refunds.payments[?].external_id` | `string` | `true` | External ID for the refund, as provided by the merchant during the refund creation request. |
| `refunds.payments[?].status` | `string enum` | `true` | Status of the refund, which can be one of the following: |
|  | `pending`: A request has been sent to the acquiring bank to initiate the refund. The refund status will be updated based on feedback from the bank, which can be monitored in the merchant's back office. |  |  |
|  | `completed`: The transaction has been fully refunded. |  |  |
|  | `rejected`: The refund request was rejected |  |  |
| `refunds.payments[?].fiscal_status` | The current status of the refund operation. May be one of the following: | yes |  |
|  | `pending`: Operation is in progress. |  |  |
|  | `failed`: Unable to complete the operation due to lack of funds or error. |  |  |
|  | `refunded`: Refund operation completed. |  |  |
| `refunds.payments[?].amount` | `float` | `true` | The refund amount including any applicable fees based on the contract. |
| `refunds.payments[?].rrn` | `string` | `true` | Retrieval Reference Number (RRN), an identifier for the refund provided by the payment provider. |

## Transaction Status Callback

**Before going live, make sure** your operations team gets access to the external reason that we send you in the callback in the `external_message` field. This is necessary to understand the reasons for rejected transactions.

The transaction status callback notifies merchants about the transaction status after a payment operation is completed.

The PSP sends a request to the endpoint specified as status_callback_url in the `POST /fpf-url` request. The merchant's system should respond with a 200 OK status.

Transaction status defined by 2 elements `sep31_status` and `refunded`:

Successful transaction has `sep31_status` = `completed` and `refunded` = `false`

Refunded transaction has `sep31_status` = `completed` and `refunded` = `true`

Failed transaction has `sep31_status` = `error`

### Callback Security and Request Verification

To ensure that callback requests originate exclusively from our platform and have not been tampered with, webhook requests are signed using the **HMAC-SHA256** algorithm when a secret key is configured.

> ⚠️ **Deprecation Notice:** The legacy fields `md5_sig` and `md5_body_sig` passed within the JSON payload are now deprecated. It is highly recommended to migrate to the new `X-Signature` header validation described below.

Overview

- **Algorithm:** HMAC-SHA256
- **Signature Location:** Sent as a custom HTTP header named `X-Signature`.
- **Payload:** The signature is generated over the **raw bytes** of the entire incoming JSON request body.
- **Secret Key:** Use your unique Callback Secret Key obtained from the [Integration Credentials](#integration-credentials) section. If no secret key is configured for the your account, the `X-Signature` header will not be sent.

Signature Generation Formula

``` text
X-Signature =hex(HMAC-SHA256(secret_key,request_body))
```

Where:

- `request_body` — The raw, unparsed bytes of the JSON body.
- `secret_key` — Your unique secret key.

------------------------------------------------------------------------

Verification Code Examples

To prevent timing attacks, always use constant-time string comparison functions when validating the signature in your application.

Go

``` go
import (
    "crypto/hmac"
    "crypto/sha256"
    "encoding/hex"
)

// Verify checks if the incoming X-Signature header matches the computed HMAC
func Verify(body []byte, secret, headerSig string) bool {
    mac := hmac.New(sha256.New, []byte(secret))
    mac.Write(body)
    expected := hex.EncodeToString(mac.Sum(nil))
    
    return hmac.Equal([]byte(expected), []byte(headerSig))
}
```

### Deposit Transaction Status Callback

Example response:

``` text
  "payload": {
    "amount_body": 500,
    "amount_in": 500,
    "amount_out": 475.5,
    "asset": "stellar:APSUSDM:GB7OUO5NY5WQKXJJ7PFFZEJOKN4BA7IOEN3Z6SWAY26LGTREJJYZH2ZT",
    "body_currency": "iso4217:USD",
    "deposit_details": {
      "bank_card_mask": "471227******0407",
      "from_first_name": "Saurabh",
      "from_last_name": "Ghodmare",
      "payment_group": "bank_card",
      "payment_method": "r:fab9dcd0-bb93-4f09-b65f-2edb69a3a213"
    },
    "md5_body_sig": "30e93a687812eb759f867d55d77a064d",
    "md5_sig": "a99ec94e699e361fdb33aa50140e474b",
    "merchant_external_id": "202678449",
    "refunded": true,
    "refunds": {
      "amount_fee": "2",
      "amount_refunded": "8",
      "amount_refunded_to_customer": "6",
      "payments": [
        {
          "amount": 502,
          "fee": 2,
          "amount_body": 3,
          "id": "2a8bf37c-3d3d-4280-9e42-680c8ce6150a",
          "external_id": "{YOUR_REFUND_EXTERNAL_ID}",
          "rrn": "435307914502",
          "arn": "123456789123",
          "status": "completed",
          "fiscal_status": "refunded"
        }
      ]
    },
    "sep31_status": "completed",
    "seq": 1724999384570,
    "status": "refunded",
    "fiscal_status": "done",
    "transaction_id": "b829f009-afe0-45c2-9996-8941f80bcb0e"
  },
```

Request body contains the following elements:

| Field | Description | Required |
|----|----|----|
| `transaction_id` | Unique identifier for the PSP transaction. | yes |
| `body_currency` | Processing currency applicable between the merchant and PSP. | yes |
| `status` | Current status of the transaction. Possible values include: | yes |
|  | `canceled`: Transaction canceled by the payment provider. |  |
|  | `expired`: Payment not completed in time, transaction expired |  |
|  | `payed`: Payment completed by the customer, but the funds haven't yet settled to the PSP account |  |
|  | `done`: Transaction successfully completed. |  |
|  | `refund_pending`: Refund request sent to the acquiring bank, awaiting feedback on refund status. |  |
|  | `refunded`: Transaction fully refunded. |  |
|  | `refund_rejected`: Refund rejected. |  |
| `fiscal_status` | Indicates the current status of the deposit operation. Possible values include: | yes |
|  | `pending`: Operation is in progress. |  |
|  | `canceled`: Deposit was canceled. |  |
|  | `expired`: - Payment was not completed in time, and the operation expired. |  |
|  | `done`: Operation completed. |  |
| `md5_sig` | MD5 signature to validate callback request. `md5(transaction_id+status+secret_key)` **Deprecated!** Please use `X-Signature` header instead | yes |
| `seq` | Timestamp in UnixMilli format indicating when the callback was created. | yes |
| `sep31_status` | Status of the Sep31 transaction. Possible values: - completed - error | yes |
| `refunded` | Indicates whether the transaction has been refunded: `true`/`false` | yes |
| `refunds.amount_fee` | Fee associated with the refund according to the contract. | yes |
| `amount_refunded` | Amount to be refunded. | yes |
| `amount_refunded_to_customer` | The exact sum that will be send to customer | yes |
| `refunds.payments[?].amount` | Total amount to refund, including any applicable fees as per the contract. | yes |
| `refunds.payments[?].amount_body` | Amount that is due to the end customer | yes |
| `refunds.payments[?].fee` | Fee associated with the refund according to the contract. | yes |
| `refunds.payments[?].id` | Unique identifier (UUID) for the refund transaction. | yes |
| `refunds.payments[?].external_id` | External refund ID provided by the merchant during the refund request. | no |
| `refunds.payments[?].rrn` | Retrieval Reference Number, which is the refund identifier provided by the payment provider. | yes |
| `refunds.payments[?].arn` | Acquirer Reference Number, which is the refund identifier provided by the payment provider. | yes |
| `refunds.payments[?].status` | Refund status, which may be one of the following: | yes |
|  | `pending`: Refund request sent, awaiting feedback from the acquiring bank. |  |
|  | `completed`: Transaction fully refunded |  |
|  | `rejected`: Refund rejected. |  |
| `refunds.payments[?].fiscal_status` | The current status of the refund operation. May be one of the following: | yes |
|  | `pending`: Operation is in progress. |  |
|  | `failed`: Unable to complete the operation due to lack of funds or error. |  |
|  | `refunded`: Refund operation completed. |  |
| `md5_body_sig` | MD5 signature to validate callback request. `md5(transaction_id+sep31_status+refunded+secret_key)` **Deprecated!** Please use `X-Signature` header instead | yes |
| `deposit_details` | Detailed information about the deposit transaction. Specific payload depends on the payment method Examples are: | no |
|  | `{ "payment_group": "bank_card", "bank_card_mask": "1000****0001", "from_first_name": "Saurabh", "from_last_name": "Ghodmare", "payment_method": "r:fab9dcd0-bb93-4f09-b65f-2edb69a3a213"}` |  |
|  | `{ "payment_group": "bank_account"}` |  |
|  | `{ "payment_group": "ewallet","ewallet_account_id": "475846215"}` |  |
|  | `{ "binance_open_user_id": "8f825dd63a8dee291766c81cc4df5c62", "ewallet_account_id": "578144430", "payment_group": "ewallet" "payment_method": "a44ba598-bec9-439e-baef-4dc8b0ae2e4b:80210375-51a1-4ac7-8d83-d3b31857a818",` |  |
|  | `payment_group` field may be one of the following values: `bank_card`,`cash_collection`, `crypto`, `ewallet`,`bank_account` |  |
| `merchant_external_id` | External ID provided by the merchant in the original request. | no |
| `amount_in` | Amount the customer has paid. | yes |
| `amount_out` | Amount settled to the merchant’s account by the PSP. | yes |
| `external_status` | Status provided by the local payment provider | no |
| `external_message` | Error details provided by the local payment provider. | no |

### Remit Transaction Status Callback

Request example:

``` text
"payload": {
    "amount_body": 100.2,
    "amount_in": 100.2,
    "amount_out": 94.68,
    "asset": "stellar:PURPLE:GBT4VVTDPCNA45MNWX5G6LUTLIEENSTUHDVXO2AQHAZ24KUZUPLPGJZH",
    "body_currency": "iso4217:EUR",
    "payout_details": {
      "bank_card_mask": "401200******3010",
      "payment_group": "bank_card",
      "payment_method": "a7b5ac9a-dac2-46c7-919c-9e478e3daf62:1c032242-bc6f-4b0e-b8e9-7d3fca7a668f",
      "rrn": "435307914502"
    },
    "external_message": "The transaction was completed successfully",
    "md5_body_sig": "cee95760668eb0963ab1dc5304fb6046",
    "md5_sig": "6fc67e043cb47b3cd7066450959117fe",
    "merchant_external_id": "123321",
    "refunded": false,
    "sep31_status": "completed",
    "seq": 1724822157474,
    "status": "done",
    "fiscal_status": "done",
    "transaction_id": "07f62464-fcf1-47b3-a804-3af29b884134"
  },
```

Request body contains the following elements:

| Field | Description | Required |
|----|----|----|
| `transaction_id` | Unique identifier for the PSP transaction. | yes |
| `body_currency` | Processing currency applicable between the merchant and PSP. | yes |
| `status` | Current status of the transaction. Possible values include: | yes |
|  | `expired`: The payment was not completed in time, and the transaction expired. |  |
|  | `done`: Transaction successfully completed. |  |
|  | `refunded`: Remit failed, and funds were returned to the merchant. |  |
|  | `canceled`: Transaction canceled by the payment provider. |  |
| `fiscal_status` | The current status of the operation, which may be one of the following: | yes |
|  | `pending`: Operation is in progress. |  |
|  | `canceled`: Remit was canceled. |  |
|  | `expired`: Payment was not completed in time, and the operation expired. |  |
|  | `failed`: Unable to complete the operation due to lack of funds or error. |  |
|  | `done`: Operation completed. |  |
| `md5_sig` | MD5 signature to validate callback request. `md5(transaction_id+status+secret_key)` **Deprecated!** Please use `X-Signature` header instead | yes |
| `seq` | Timestamp in UnixMilli format indicating when the callback was created. | yes |
| `sep31_status` | Status of the Sep31 transaction. Possible values: - completed - error | yes |
| `refunded` | Indicates whether the transaction has been refunded: `true`/`false`. | yes |
| `md5_body_sig` | MD5 signature to validate callback request. `md5(transaction_id+sep31_status+refunded+secret_key)` **Deprecated!** Please use `X-Signature` header instead | yes |
| `payout_details` | Detailed information about the remit transaction. Specific payload depends on the payment method. Example: | no |
|  | `{ "payout_details": { "payment_group": "cash_collection", "ready_to_receive": true, "collection_pin": "123987"}}` |  |
|  | `payment_group` field may be one of the following values: `bank_card`,`cash_collection`, `crypto`, `ewallet`,`bank_account` |  |
| `merchant_external_id` | External ID provided by the merchant in the original request. | no |
| `amount_in` | Amount the customer has paid. | yes |
| `amount_out` | Amount settled to the merchant’s account by the PSP. | yes |
| `external_status` | Status provided by the local payment provider. | no |
| `external_message` | Error details provided by the local payment provider. | no |

## How to make a Refund

If your deposit transaction is in the `completed` status, then you can initiate a refund for it.

Depending on the payment method and issuer the refund process may take:

- 1 to 2 hours

- 1 to 15 bank days

- up to 8-10 weeks in rare cases.

Request example:

**How to make a refund**

``` bash
curl POST https://fpf-api.proc-gw.com/api/v3/merchantGUID/TRANSACTION_ID/refund \
        --header 'X-App-Token: APP_KEY'
        --header 'X-App-Secret: APP_SECRET'
        --header 'Content-Type: application/json'
        --data-raw '{
                      "reason":"{DESCRIPTION}",
                      "external_id": "{YOUR_REFUND_EXTERNAL_ID}"
                    }
        
```

Request body contains the following elements:

| Field | Description | Required |
|----|----|----|
| `reason` | Reason of the refund. | no |
| `external_id` | Your refund external ID. The `external_id` field must be unique. If a refund is registered with a duplicated `external_id`, an error will be returned. | no |

Response body contains the following elements:

| Field                         | Description                     | Required |
|-------------------------------|---------------------------------|----------|
| `id`                          | Unique identifier for a refund. | yes      |
| `merchant_external_refund_id` | Your refund external ID.        | no       |

Example Response:

1.  Status code 200, example body:

``` json
{
  "id":"refund_id", 
  "merchant_external_refund_id":"{YOUR_REFUND_EXTERNAL_ID}"
}
```

2.  Status code 400/500, example body:

``` json
{
  "error":"bad_request, refund is not supported for this transaction"
}
```

3.  When `external_id` is duplicated, status code 400, example body:

``` json
{
  "message": "bad_request, psp client refund deposit: {\"error\":\"saveTxWithRefund(): refundSvc.Set(): duplicate external_id\"}",
  "trace_id": "69f7bd2eebcf2834e8404ba6374902d0"
}
```

## Get Merchant Account Balance via API

We will give you `APP_TOKEN` and `APP_SECRET` separately.

Example request:

**How to check account’s balance using merchant_cabinet API**

``` bash
curl https://merchant-api.aps.money/api/v2/merchantGUID/balance\
        --header 'X-App-Token: APP_KEY'
        --header 'X-App-Secret: APP_SECRET'
        
```

Example response:

``` text
{
   "data": [
       {
           "balance": 306.07,
           "asset": "USD",
           "asset_key": "ATUSD"
       },
       {
           "balance": 25,
           "asset": "EUR",
           "asset_key": "PURPLE"
       }
   ],
   "total": 332.57,
   "total_currency": "USD"
}
```

## Get Transaction History (CSV)

Retrieve your transaction records in CSV format. Use the query parameters below to filter and customize your results.

Example Request:

**How to get a list of transactions**

``` bash
curl -X POST https://merchant-api.aps.money/api/v2/merchantGUID/reports/operations/csv-stream\
        --header 'X-App-Token: APP_KEY' \
        --header 'X-App-Secret: APP_SECRET' \
        --header 'Content-Type: application/json' \
        -d '{
            "created_at": {
                "from": "2024-01-01T00:00:00Z",
                "to": "2024-12-31T23:59:59Z"
            }
        }'
        
```

This endpoint accepts the following query parameters:

| Parameter | Description | Example | Required |
|----|----|----|----|
| `created_at` | Start and end date in RFC-3339 format (e.g., `2023-10-02T00:00:00Z`). Filters operations by creation date. | `"created_at": {"from": "2024-01-01T00:00:00Z", "to": "2024-12-31T23:59:59Z"}` | yes |
| `updated_at` | End date in RFC-3339 format (e.g., `2023-10-02T00:00:00Z`). Filters operations by last updated date. | `"updated_at": {"from": "2024-01-01T00:00:00Z", "to": "2024-12-31T23:59:59Z"}` | no |
| `pagination` | Controls the ordering of results before CSV generation. `sort_by` — Column to order by: `created_at` or `updated_at ordering`. Sort direction: `ASC` or `DESC` | `"pagination": {"sort_by": "created_at", "ordering": "desc"}` | no |
| `types` | Operation type to filter by. Returns all types if not specified. Available values: `deposit`, `remit`, `refund`, `chargeback` | `"types": ["deposit", "remit"]` | no |
| `statuses` | Filter by operation status. Available values: `canceled, expired, failed, payed, pending, done, refunded, chargeback`. Statuses vary by operation type: **Deposits:** `pending` (in progress), `canceled`, `expired` (payment not completed in time), `done` (completed). **Remits:** `pending` (in progress), `canceled`, `expired` (payment not completed in time), `failed` (insufficient funds or error), `done` (completed). **Refunds:** `pending` (in progress), `failed` (insufficient funds or error), `refunded` (completed) **Chargebacks:** `chargeback` (payment disputed and refunded to customer) | `"statuses": ["pending" ]` | no |
| `methods` | Filter by payment method category. Available values: `BANKCARD`, `CASH`, `CRYPTO`, `EWALLET`, `WIRETRANSFER, BINANCE` | `"methods": ["BANKCARD", "WIRETRANSFER"]` | no |
| `operation_ids` | Unique identifier for a single operation within a transaction (e.g., deposit, refund, chargeback, or remit). While a Transaction ID groups all related operations, the Operation ID refers to one specific action. Filtering by this parameter returns only that individual operation. | `"operation_ids": ["550e8400-e29b-41d4-a716-446655440000", "550e8400-e29b-41d4-a716-446655440001"]` | no |
| `unified_transaction_ids` | Unique identifier for the entire payment lifecycle. Links all related operations—deposits, refunds, chargebacks—under a single transaction. Use this to retrieve the full set of related operations in one report. | `"unified_transaction_ids": ["550e8400-e29b-41d4-a716-446655440002", "550e8400-e29b-41d4-a716-446655440003"]` | no |
| `external_ids` | Merchant-assigned reference ID for tracking purposes. Using this attribute in a report generation request results in filtering by the `merchant_external_id` attribute from the transaction initialization request. | `"external_ids": ["ext-12345", "ext-67890"]` | no |
| `body_currencies` | Currencies in which the merchant initiated transactions in PSP. | `"body_currencies": [ "USD", "EUR"]` | no |

Example response:

``` csv
TRANSACTION ID,UNIFIED TRANSACTION ID,RRN,CREATED,UPDATED,EXTERNAL ID,PAYER ID,STATUS,METHOD,TYPE,COUNTRY,BODY AMOUNT,USER FEE,MERCHANT FEE,BODY CURRENCY,CONVERSION RATE,CHARGED AMOUNT,CHARGED CURRENCY,CREDITED AMOUNT,CREDITED CURRENCY,UNIFIED ERROR MESSAGE,CARD HOLDER,CARD MASKED NUMBER
b22843e0-4c60-4c56-8ea8-357a2ed65dee,b22843e0-4c60-4c56-8ea8-357a2ed65dee,,04-04-2025 11:54:58.19752 +0000 UTC,04-04-2025 11:55:31.510134 +0000 UTC,autotestExternalID1743767697350,,canceled,BANKCARD,remit,POLAND,30.00,0.00,0.00,iso4217:USD,1.00,0.00,iso4217:USD,0.00,iso4217:USD,,Automation Automation,4111111111111111
06d2cee4-225e-4edb-af41-e1f85b7f0d04,06d2cee4-225e-4edb-af41-e1f85b7f0d04,,04-04-2025 11:53:28.105722 +0000 UTC,04-04-2025 12:24:50.184459 +0000 UTC,autotestExternalID1743767607103,,expired,BANKCARD,deposit,,30.00,1.00,0.15,iso4217:USD,1.00,0.00,iso4217:USD,0.00,iso4217:USD,,Automation Automation,
e96bf6bf-f23d-48a1-a550-a9860e12ed0a,e96bf6bf-f23d-48a1-a550-a9860e12ed0a,,04-04-2025 11:51:46.282755 +0000 UTC,04-04-2025 12:23:15.59409 +0000 UTC,autotestExternalID1743767504631,,expired,BANKCARD,deposit,,30.00,1.00,0.15,iso4217:USD,1.00,0.00,iso4217:USD,0.00,iso4217:USD,,Automation Automation,
2c233f01-5261-44f9-ab78-cf90f7adf7f6,2c233f01-5261-44f9-ab78-cf90f7adf7f6,,04-04-2025 11:17:00.686727 +0000 UTC,04-04-2025 11:17:34.291443 +0000 UTC,autotestExternalID1743765419873,,canceled,BANKCARD,remit,POLAND,30.00,0.00,0.00,iso4217:USD,1.00,0.00,iso4217:USD,0.00,iso4217:USD,,Automation Automation,4111111111111111
a68b6af9-2c28-4954-8bae-80d505befe91,a68b6af9-2c28-4954-8bae-80d505befe91,,04-04-2025 11:15:25.898994 +0000 UTC,04-04-2025 11:46:49.405302 +0000 UTC,autotestExternalID1743765324863,,expired,BANKCARD,deposit,,30.00,1.00,0.15,iso4217:USD,1.00,0.00,iso4217:USD,0.00,iso4217:USD,,Automation Automation,
31cd8102-ec24-4b72-8f76-f63e9031b2d6,31cd8102-ec24-4b72-8f76-f63e9031b2d6,,04-04-2025 11:13:24.604913 +0000 UTC,04-04-2025 11:45:29.521178 +0000 UTC,autotestExternalID1743765203244,,expired,BANKCARD,deposit,,30.00,1.00,0.15,iso4217:USD,1.00,0.00,iso4217:USD,0.00,iso4217:USD,,Automation Automation,
```

CSV file contains the following transaction details:

| Column | Description |
|:---|:---|
| CREATED | Operation timestamp recorded by APS. Timezone: UTC. |
| UPDATED | Timestamp of the last update to the operation, recorded by APS. Timezone: UTC. |
| TRANSACTION ID | Unique identifier for the entire payment lifecycle. Links all related operations—deposits, refunds, chargebacks—under a single transaction. Use this to retrieve the full set of related operations in one query. |
| UNIFIED TRANSACTION ID | Unique identifier for the entire payment lifecycle. Links all related operations—deposits, refunds, chargebacks—under a single transaction. Use this to retrieve the full set of related operations in one query. |
| OPERATION ID | Unique identifier for a single operation within a transaction (e.g., deposit, refund, chargeback, or remit). Unlike the Transaction ID, which groups all related operations, the Operation ID refers to one specific action. Returns only that individual operation. |
| EXTERNAL ID | Merchant-assigned reference ID for tracking purposes. |
| PAYER ID | Merchant-provided unique identifier for the customer. |
| METHOD | Payment method selected for the operation. May be empty if the operation was abandoned before a payment method was selected. |
| CARD HOLDER | Name provided during payment, either submitted by the payer or passed to APS by the merchant. May not match the name on the bank card. |
| CARD MASKED NUMBER | Masked bank card number used for payment processing. |
| COUNTRY | Bank card issuing country. Applies to card transactions only. |
| TYPE | Operation type: `Deposit`, `Remit`, `Refund`, or `Chargeback`. |
| STATUS | Current status of the operation. Values vary by operation type: **Deposits:** `pending` (in progress), `canceled`, `expired` (payment not completed in time), `done` (completed). **Remits:** `pending` (in progress), `canceled`, `expired` (payment not completed in time), `failed` (insufficient funds or error), `done` (completed). **Refunds:** `pending` (in progress), `failed` (insufficient funds or error), `refunded` (completed). **Chargebacks:** `chargeback` (payment disputed and refunded to customer). |
| RRN | Reference Retrieval Number (RRN). Unique identifier for tracking an operation across payment systems. Applies to deposit and refund operations only. |
| BODY AMOUNT | Amount before applying any fees |
| BODY CURRENCY | Currency of the body amount |
| CONTRACT FEE | The fee rate agreed upon with the merchant |
| CONTRACT FEE CURRENCY | Currency of the contract fee |
| CUSTOMER FEE | The fee the customer sees and pays during the transaction |
| CUSTOMER FEE CURRENCY | Currency of the customer fee |
| CHARGED AMOUNT | Amount paid by the customer in local currency (deposits) or by the merchant in assets (remits). |
| CHARGED CURRENCY | Currency of the charged amount |
| MERCHANT FEE | Amount paid by the merchant to cover transaction costs on behalf of the customer. Value is zero if the customer pays, or negative if the merchant covers part or all of the costs. |
| MERCHANT FEE CURRENCY | Currency of the merchant fee |
| CREDITED AMOUNT | Assets credited to the merchant (deposits) or local currency credited to the customer (remits). |
| CREDITED CURRENCY | Currency of the credited amount |
| CONVERSION RATE | The exchange rate applied to the customer |
| UNIFIED ERROR MESSAGE | Error message that was provided by payment service provider (VISA, MasterCard, etc). |
