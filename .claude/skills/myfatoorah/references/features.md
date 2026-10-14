# MyFatoorah — Features

## Features

*`https://docs.myfatoorah.com/docs/features` — updated 2026-02-16*

#### Introduction

We provide a comprehensive set of features designed to simplify payment processing, improve operational efficiency, and support a wide range of business models.
Whether you are building a simple checkout flow or a complex marketplace solution, these features help you integrate, scale, and manage payments with confidence.

***

- [Saving Card Information](https://docs.myfatoorah.com/docs/v3-saving-card-options) — Securely save customer card details to enable faster and smoother future payments.

- [Refund](https://docs.myfatoorah.com/docs/refund) — Support both partial and full refunds.

- [Authorize & Capture](https://docs.myfatoorah.com/docs/v3-auth-capture) — Authorize a payment amount and capture it later (partially or full), once the order is confirmed.

- [Recurring Payment](https://docs.myfatoorah.com/docs/recurring-payment) — Easily manage subscription-based services and recurring billing cycles.

- [Multiple Suppliers](https://docs.myfatoorah.com/docs/multiple-suppliers) — Handle payments and settlements for multiple suppliers within a single platform.

- [Shipping Integration](https://docs.myfatoorah.com/docs/shipping) — Integrate seamlessly with supported shipping providers through a simple workflow.

- [Plugins](https://docs.myfatoorah.com/docs/plugin-overview) — Quickly integrate using ready-made plugins for the most popular platforms and frameworks.

- [SDKs](https://docs.myfatoorah.com/docs/sdk-overview) — Integrate faster using our official SDKs that handle common workflows for you.

<br />

## Get Payment Details

*`https://docs.myfatoorah.com/docs/get-payment-details` — updated 2026-02-16*

#### **Overview**

Use this endpoint to retrieve the status and detailed information of a specific payment.\
When a payment is processed through MyFatoorah, the **PaymentId** is returned as a parameter in the **Redirection URL**. This **PaymentId** should then be used to inquire about the payment details and confirm whether the payment was successful or not.

> 👍 Good Practice
>
> As a good practice, to ensure the payment response is returned from the MyFatoorah end, we encourage you to call `GET /v3/payments/{paymentId}` endpoint ([Get Payment Details](https://docs.myfatoorah.com/reference/get-payment-details)) once you receive the callback response to confirm the payment’s final status directly from MyFatoorah.

#### How it works

MyFatoorah invoice may have more than one transaction. The **InvoiceStatus** parameter represents the overall status of the invoice.

* The invoice will be "**PAID**" if there is a successful transaction (with "SUCCESS" status).
* The invoice will be "**PENDING**" if all transactions have "FAILED", "INPROGRESS", "AUTHORIZE", or "CANCELED" status.

```text Endpoint
GET /v3/payments/{paymentId}
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "Invoice": {
            "Id": "6389662",
            "Status": "PAID",
            "Reference": "2025060928",
            "CreationDate": "2025-12-24T16:23:49.5900000Z",
            "ExpirationDate": "2026-06-22T16:23:49.5900000Z",
            "ExternalIdentifier": null,
            "UserDefinedField": "",
            "MetaData": null
        },
        "Transaction": {
            "Id": "104690",
            "Status": "SUCCESS",
            "PaymentMethod": "VISA/MASTER",
            "PaymentId": "07076389662322472373",
            "ReferenceId": "535816104690",
            "TrackId": "24-12-2025_3224723",
            "AuthorizationId": "104690",
            "TransactionDate": "2025-12-24T16:23:50.7000000Z",
            "ECI": "",
            "IP": {
                "Address": "",
                "Country": ""
            },
            "Error": {
                "Code": "",
                "Message": ""
            },
            "Card": {
                "NameOnCard": "Muhammad",
                "Number": "512345xxxxxx0008",
                "Token": "TKN-0fb06aac-634d-418a-bf78-953202b67b53",
                "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                "ExpiryMonth": "12",
                "ExpiryYear": "36",
                "Brand": "Mastercard",
                "Issuer": "Test Bank",
                "IssuerCountry": "KWT",
                "FundingMethod": "credit"
            }
        },
        "Customer": {
            "Reference": "",
            "Name": "Anonymous",
            "Mobile": "+965",
            "Email": ""
        },
        "Amount": {
            "BaseCurrency": "KWD",
            "ValueInBaseCurrency": "20",
            "ServiceCharge": "0.4",
            "ServiceChargeVAT": "0.06",
            "ReceivableAmount": "19.54",
            "DisplayCurrency": "KWD",
            "ValueInDisplayCurrency": "20",
            "PayCurrency": "KWD",
            "ValueInPayCurrency": "20"
        },
        "Suppliers": []
    }
}
```

#### Error Codes

| Code  | Explanation                                                                                                                                                                                      |
| :---- | :----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| MF001 | 3DS authentication failed, possible reasons (user inserted a wrong password, cardholder/card issuer are not enrolled with 3DS, or the issuer bank has technical issue).                          |
| MF002 | The issuer bank has declined the transaction, possible reasons (invalid card details, insufficient funds, denied by risk, the card is expired/held, or card is not enabled for online purchase). |
| MF003 | The transaction has been blocked from the gateway, possible reasons (unsupported card BIN, fraud detection, or security blocking rules).                                                         |
| MF004 | Insufficient funds                                                                                                                                                                               |
| MF005 | Session timeout                                                                                                                                                                                  |
| MF006 | Transaction canceled                                                                                                                                                                             |
| MF007 | The card is expired.                                                                                                                                                                             |
| MF008 | The card issuer doesn't respond.                                                                                                                                                                 |
| MF009 | Denied by Risk                                                                                                                                                                                   |
| MF010 | Wrong Security Code                                                                                                                                                                              |
| MF020 | Unspecified Failure                                                                                                                                                                              |

<br />

> 📘 Old Integration (V2)
>
> In case you are using the integration for v2, you can find the documentation here:
>
> * [Payment Inquiry](https://docs.myfatoorah.com/docs/payment-inquiry)
> * [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status)
> * [Updating Payment Status Guidelines](https://docs.myfatoorah.com/docs/update-payment-status-guidelines)

## Payment Inquiry

*`https://docs.myfatoorah.com/docs/payment-inquiry` — updated 2026-02-16*

Get the status of your transaction

#### **Introduction**

By calling the [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint, you get the status of your invoice to check whether the payment is done or not.

**MyFatoorah** returns a unique **InvoiceId** parameter in the response body of the [SendPayment](https://docs.myfatoorah.com/docs/send-payment) and [ExecutePayment](https://docs.myfatoorah.com/docs/execute-paymentt) endpoints. Use this **InvoiceId** parameter to fetch all details about this invoice and its transactions.

Moreover, after a successful/failed payment, **MyFatoorah** returns a **PaymentId** as a parameter in the **CallBack**/**Error** URL. This **PaymentId** parameter is used to inquire about the payment information and its status.

In addition, if you provided a customer reference to the request body of the [SendPayment](https://docs.myfatoorah.com/docs/send-payment) and [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoints, you can check your payment data using the same value sent in the **CustomerReference** parameter.

> 👍 Good Practice
>
> As a good practice, to ensure the payment response is returned from the **MyFatoorah** end, we encourage you to call the [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint once you receive the result on your page.

***

#### **How it works**

MyFatoorah invoice may have more than one transaction. The **InvoiceStatus** parameter represents the overall status of the invoice.

* The invoice will be "**Paid**" if there is a successful transaction (with "**Succss**" status).
* The invoice will be "**Pending**" if all transactions have "**InProgress**" or "**Failed**" status.

You can call the [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint using the **InvoiceId**, **PaymentId**, or **CustomerReference**. The latter differs from each transaction. If you request the [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint using **InvoiceId** as **KeyType** (i.e **Key**: 599578), the response will be the same for all requests of [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint using **PaymentId** as **KeyType** (i.e **Key**: 100202108342170497, 060659957848741164, 100202108357808709, and 060659957848741765). Also, the response is the same for the request of [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint using **CustomerReference** as **KeyType**

Example Response:

```json Request
{
  "Key": "912952",
  "KeyType": "invoiceid"
}
OR
{
  "Key": "100202120965964751",
  "KeyType": "PaymentId"
}
OR
{
  "Key": "order_1",
  "KeyType": "CustomerReference"
}
```
```json Response
{
  "IsSuccess": true,
  "Message": "",
  "ValidationErrors": null,
  "Data": {
    "InvoiceId": 915102,
    "InvoiceStatus": "Paid",
    "InvoiceReference": "2021005465",
    "CustomerReference": "order_1",
    "CreatedDate": "2021-07-28T13:25:06.153",
    "ExpiryDate": "July 31, 2021",
    "InvoiceValue": 12345,
    "Comments": "",
    "CustomerName": "test inquiry invoices",
    "CustomerMobile": "",
    "CustomerEmail": null,
    "UserDefinedField": null,
    "InvoiceDisplayValue": "12,345.000 KD",
    "InvoiceItems": [],
    "InvoiceTransactions": [
      {
        "TransactionDate": "2021-07-28T13:25:20.1",
        "PaymentGateway": "KNET",
        "ReferenceId": "060691510275442662",
        "TrackId": "28-07-2021_754426",
        "TransactionId": "060691510275442662",
        "PaymentId": "100202120933974848",
        "AuthorizationId": "060691510275442662",
        "TransactionStatus": "Failed",
        "TransationValue": "12,345.000",
        "CustomerServiceCharge": "0.000",
        "DueValue": "12,345.000",
        "PaidCurrency": "KD",
        "PaidCurrencyValue": "12,345.000",
        "Currency": "KD",
        "Error": "Transaction canceled!",
        "CardNumber": null,
        "ErrorCode": "MF006"
      },
      {
        "TransactionDate": "2021-07-28T13:25:51.807",
        "PaymentGateway": "MADA",
        "ReferenceId": "060691510275442762",
        "TrackId": "28-07-2021_754427",
        "TransactionId": "060691510275442762",
        "PaymentId": "060691510275442762",
        "AuthorizationId": "060691510275442762",
        "TransactionStatus": "InProgress",
        "TransationValue": "12,345.000",
        "CustomerServiceCharge": "185.175",
        "DueValue": "12,530.180",
        "PaidCurrency": "SR",
        "PaidCurrencyValue": "154,622.430",
        "Currency": "KD",
        "Error": null,
        "CardNumber": null,
        "ErrorCode": ""
      },
      {
        "TransactionDate": "2021-07-28T13:26:09.13",
        "PaymentGateway": "Visa/Master Direct",
        "ReferenceId": "120910000002",
        "TrackId": "28-07-2021_754428",
        "TransactionId": "202120934050845",
        "PaymentId": "602202120965954201",
        "AuthorizationId": "000000",
        "TransactionStatus": "Failed",
        "TransationValue": "12,345.000",
        "CustomerServiceCharge": "0.000",
        "DueValue": "12,345.000",
        "PaidCurrency": "KD",
        "PaidCurrencyValue": "12,345.000",
        "Currency": "KD",
        "Error": "Transaction not Captured!",
        "CardNumber": "545301xxxxxx5539",
        "ErrorCode": "MF002"
      },
      {
        "TransactionDate": "2021-07-28T13:27:33.367",
        "PaymentGateway": "KNET",
        "ReferenceId": "120910000653",
        "TrackId": "28-07-2021_754431",
        "TransactionId": "202120965959207",
        "PaymentId": "100202120965964751",
        "AuthorizationId": "B77780",
        "TransactionStatus": "Succss",
        "TransationValue": "12,345.000",
        "CustomerServiceCharge": "0.000",
        "DueValue": "12,345.000",
        "PaidCurrency": "KD",
        "PaidCurrencyValue": "12,345.000",
        "Currency": "KD",
        "Error": null,
        "CardNumber": null,
        "ErrorCode": ""
      }
    ],
    "Suppliers": []
  }
}
```

***

#### **Payment Status**

If you use the [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint with **PaymentId** as a **KeyType** (i.e **Key**: 100202108342170497), you need to check **InvoiceStatus** first.

* If the invoice is still in **Pending** status, you should search in the **InvoiceTransactions** array for the transaction with the same key of **PaymentId** (i.e Key: 100202108342170497) to get the **Error** of this transaction when its status is **Failed**, but if the transaction is in **InProgress** status, then the customer has not tried to pay the invoice till this moment.

* If the invoice is **Paid**, you should search in the **InvoiceTransactions** array for the transaction with ("**Succss**" status), and you can fetch its data like **ReferenceId**.

 <hr />

#### **Invoice Status**

If you use the [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint with the **InvoiceId** as a **KeyType** (i.e **Key**: 599578), you need to check **InvoiceStatus** first.

* If the invoice is still in **Pending** status, you should get the last created transaction that occurred in the **InvoiceTransactions** array by the recent date (usually the one at the end of the array). Then, you can fetch its data like **ReferenceId**.

* If the invoice is **Paid**, you should search in the **InvoiceTransactions** array for the transaction with ("**Succss**" status), and you can fetch its data like **ReferenceId**.

> 📘 CustomerReference
>
> If you use the [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint with the **CustomerReference** as a **KeyType** (i.e **Key**: order\_1), you should deal with the data exactly as if you retrieve it using the **InvoiceId** key's type.

> 🚧 CustomerReference is not unique
>
> The **CustomerReference** parameter is not unique. When using the same CustomerReference multiple times, the [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint returns the last invoice created for this customer reference.

## Updating Payment Status Guidelines

*`https://docs.myfatoorah.com/docs/v3-updating-payment-status-guidelines` — updated 2026-02-16*

#### **Overview**

In this section, we will cover the paths available to update the order status on your system after the customer makes the payment on the MyFatoorah system.

We are covering the:

* Redirection to RedirectionUrl
* Transactions Webhook
* Handling both the webhook and the redirection together

#### RedirectionUrl:

After the customer makes a payment, MyFatoorah redirects the customer to the provided redirection URL, appending the **PaymentId** to the URL.

##### Steps:

* Call the **([Get Payment Details](https://docs.myfatoorah.com/reference/get-payment-details))** endpoint using the **PaymentId**.
* In the response of GET Payments, you will get the order payment status.
* Display the payment result to your customer OR redirect them to a suitable page based on the payment status.

> 🚧 Many calls to GET Payments
>
> GET Payments endpoint has a rate limit. If you are going to send a lot of requests to the endpoint in a short duration, [please contact the account manager](https://www.myfatoorah.com/en/contact-us-2/) to increase the rate limit for your account.

> 🚧 Redirection Reliability
>
> Relying on redirection may not always be reliable, as it depends on the customer's behavior and network conditions. For instance, redirection may fail if the customer closes the page before it completes or if a slow network prevents the redirection process from finishing successfully.

#### Webhook:

After the customer completes the payment attempt, MyFatoorah triggers a **[webhook event](https://docs.myfatoorah.com/docs/webhook)** to your server with the status of the transaction.

##### Steps:

* Validate the [webhook signature ](https://docs.myfatoorah.com/docs/webhook#webhook-signature)to confirm that the webhook event is from MyFatoorah.
* Update the Payment Status of the order on your system.
* Return HTTP 200 OK to MyFatoorah POST request of the webhook.

> 📘 MyFatoorah Webhook Retries
>
> Webhook V2:
>
> You can configure the retries from the dashboard by setting how many times and how often the retries should happen.
>
> Webhook V1:
>
> MyFatoorah attempts to trigger the webhook to your server four times, each time for nearly 100 seconds, with a delay 10 seconds between each attempt. If we get a successful response from your side, we will not try to trigger it again.
>
> If after the fourth attempt, we still get a failure status from your server, we will log the webhook event on our side and mark it as failed. We will not attempt to send the webhook again after the fourth attempt.
>
> If we get a timeout from your server without getting any response, we will not attempt to send the webhook again after the fourth attempt.

#### Updating Order Sequence:

We recommend utilizing both the **Webhook** and **Get Payment Details** from MyFatoorah to make sure you get the latest payment status update from MyFatoorah side. In this section, we will cover the two possible flows to occur if you're using both of them.

##### Cases:

###### Getting the Redirection after the Webhook:

* Check if the Payment Status is updated by the webhook.
* If updated, redirect to the result page.
* If not updated, continue the [redirection steps.](#callbackurlerrorurl)

###### Getting the Webhook after the Redirection:

* Check if the Payment Status is updated by GET Payments.
* If updated, return HTTP 200 OK.
* If not, continue the [webhook steps.](#webhook)

> 🚧 Multiple Webhook Calls
>
> In some rare cases, your webhook endpoint may get multiple calls. This behavior is happening because of some payment methods (e.g: KNET) are sending multiple webhooks to MyFatoorah. In this case, a webhook event indicating a **successful** payment status overrides any other status (even if received first).

> 📘 Success Status
>
> In MyFatoorah system, the Success Payment Status is final and cannot be override. If you get the payment status as success, you can safely mark the order as paid on your system.

#### Resilience:

This section of the page provides recommended solutions to ensure resilience in case GetPaymentStatus becomes unreachable.

##### Cases:

###### GET Payments is not Reachable:

* Schedule a retry to call GET Payments three times. Set 5 seconds as the wait time between each retry.
* It is advised to set the timeout to at least 30 seconds to ensure that you get a response even in high load circumstances.
* If the MyFatoorah endpoint is unreachable after 3 retry attempts, set the status to Pending\_Status\_Update and decide on the next steps:
  * Schedule a reattempt after a defined period.
  * Handle the process manually.

> 📘 Webhook vs CallBack/Error URL
>
> Both the **Webhook** and **Redirection** are triggered from MyFatoorah at the same time. However, it is most likely that you will get the webhook event first because it is a server-to-server integration making it independent of the slowing factors the redirection might face like poor network connections, or slow devices.

## Updating Payment Status Guidelines

*`https://docs.myfatoorah.com/docs/update-payment-status-guidelines` — updated 2026-02-16*

#### **Overview**

In this section, we will cover the paths available to update the order status on your system after the customer makes the payment on the MyFatoorah system.

We are covering the:

* Redirection to CallBack/Error URL
* Transactions Webhook
* Handling both the webhook and the redirection together

#### CallBackUrl/ErrorUrl:

After the customer makes a payment, MyFatoorah redirects the customer to the provided CallBackUrl/ErrorUrl appending the **PaymentId** to the URL.

##### Steps:

* Call the **[GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status)** endpoint using the **PaymentId**.
* In the response of GetPaymentStatus, you will get the order payment status.
* Display the payment result to your customer OR redirect him to a suitable page based on the payment status.

> 🚧 Many calls to GetPaymentStatus
>
> GetPaymentStatus endpoint has a rate limit. If you are going to send a lot of requests to the endpoint in a short duration, [please contact the account manager](https://www.myfatoorah.com/en/contact-us-2/) to increase the rate limit for your account.

> 🚧 Redirection Reliability
>
> Relying on redirection may not always be reliable, as it depends on the customer's behavior and network conditions. For instance, redirection may fail if the customer closes the page before it completes or if a slow network prevents the redirection process from finishing successfully.

> 👍 CallBackUrl/ErrorUrl
>
> We recommend not relying on whether the redirection happened to the CallBackUrl and ErrorUrl to update the payment status. Instead, we recommend relying on the response from GetPaymentStatus.

***

#### Webhook:

After the customer completes the payment attempt, MyFatoorah triggers a **[webhook event](https://docs.myfatoorah.com/docs/webhook)** to your server with the status of the transaction.

##### Steps:

* Validate the [webhook signature ](https://docs.myfatoorah.com/docs/webhook#webhook-signature)to confirm that the webhook event is from MyFatoorah.
* If you want extra fields about the transaction, call the GetPaymentStatus endpoint. Otherwise, move on to the next step.
* Update the Payment Status of the order on your system.
* Return HTTP 200 OK to MyFatoorah POST request of the webhook.

> 📘 MyFatoorah Webhook Retries
>
> MyFatoorah attempts to trigger the webhook to your server four times, each time for nearly 100 seconds, with a delay 10 seconds between each attempt. If we get a successful response from your side, we will not try to trigger it again.
>
> If after the fourth attempt, we still get a failure status from your server, we will log the webhook event on our side and mark it as failed. We will not attempt to send the webhook again after the fourth attempt.
>
> If we get a timeout from your server without getting any response, we will not attempt to send the webhook again after the fourth attempt.

***

#### Updating Order Sequence:

We recommend utilizing both the **Webhook** and **GetPaymentStatus** from MyFatoorah to make sure you get the latest payment status update from MyFatoorah side. In this section we will cover the two possible flows to occur if you're using both of them.

##### Cases:

###### Getting the Redirection after the Webhook:

* Check if the Payment Status is updated by the webhook.
* If updated, redirect to the result page.
* If not updated, continue the [redirection steps.](#callbackurlerrorurl)

###### Getting the Webhook after the Redirection:

* Check if the Payment Status is updated by GetPaymentStatus.
* If updated, return HTTP 200 OK.
* If not, continue the [webhook steps.](#webhook)

![Update Payment Status Sequence Flowchart](https://files.readme.io/50902c43d422f7ba892cce6254711cfa6cea63007a58e0c9ee746321bd383d56-Update_Payment_Status.png)

> 🚧 Multiple Webhook Calls
>
> In some rare cases, your webhook endpoint may get multiple calls. This behavior is happening because of some payment methods (e.g: KNET) are sending multiple webhooks to MyFatoorah. In this case, a webhook event indicating a **successful** payment status overrides any other status (even if received first).

> 📘 Success Status
>
> In MyFatoorah system, the Success Payment Status is final and cannot be override. If you get the payment status as success, you can safely mark the order as paid on your system.

***

#### Resilience:

This section of the page provides recommended solutions to ensure resilience in case GetPaymentStatus becomes unreachable.

##### Cases:

###### GetPaymentStatus is not Reachable:

* Schedule a retry to call GetPaymentStatus three times. Set 5 seconds as the wait time between each retry.
* It is advised to set the timeout to at least 30 seconds to ensure that you get a response even in high load circumstances.
* If the MyFatoorah endpoint is unreachable after 3 retry attempts, set the status to Pending\_Status\_Update and decide on the next steps:
  * Schedule a reattempt after a defined period.
  * Handle the process manually.

> 📘 Webhook vs CallBack/Error URL
>
> Both the **Webhook** and **Redirection** are triggered from MyFatoorah at the same time. However, it is most likely that you will get the webhook event first because it is a server-to-server integration making it independent of the slowing factors the redirection might face like poor network connections, or slow devices.

## UpdatePaymentStatus

*`https://docs.myfatoorah.com/docs/updatepaymentstatus` — updated 2026-02-16*

Endpoint

#### **Overview**

The "UpdatePaymentStatus" endpoint is a POST request. It is used to either **capture fully/partially** the invoice amount or **release** the amount back into the customer's account. Detailed functionality of how to use this endpoint is explained below.

The endpoint on Swagger is: [Payment\_UpdatePaymentStatus](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_UpdatePaymentStatus).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 🚧 Number of Processes
>
> Please notice that you can make **only one** Capture/Release operation on each invoice.

***

#### **Request Model**

The request is a POST request with the following parameters:

| Input Parameter | Type | Description |
|---|---|---|
| **Operation** | string | "**Capture**": Refers to capturing fully or partially the invoice value.\ "**Release**": Refers to releasing fully the invoice value to the customer's account. |
| **Amount** | float | The amount to be captured/released.\ **Capture**: The amount has to be less than or equal to the invoice value. ***Mandatory*** * \*Releas&#x65;**: The amount has to be equal to the invoice value. \_**&#x4F;ptional\*\*\_ |
| **Key** | string | Refers to the Invoice ID, payment ID, or Customer Reference based on the key type |
| **KeyType** | string | "**InvoiceId**": Refers to the invoice number that MyFatoorah generates "**PaymentId**": The value is returned upon having any update on the invoice payment "**CustomerReference**": The reference used to link your orders in the store |

> 👍 PaymentId
>
> We strongly recommend to use the value **PaymentId** (*which is returned in the CallBackURL* ) to update the payment status, this will get you full information about the invoice that you can use within your application. As long as there is not payment or transactions done for a certain invoice, you can inquire using **InvoiceId**.

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find complete details about the **Data** **Model** of this API endpoint. Let's check it and its contents.

| Response Field | Type | Description |
|---|---|---|
| **InvoiceId** | number | Represents the invoice ID that was used in the inquiry call |
| **InvoiceStatus** | string | "**Pending**": The customer made the payment but no operations on it **OR**A release is made on the amount.\ "**Paid**": A capture is made on the amount |
| **InvoiceReference** | string | Invoice reference that MyFatoorah generates |
| **CustomerReference** | string | The customer reference data associated with the invoice |
| **CreatedDate** | string | The creation date of the invoice |
| **ExpiryDate** | string | The expiry date for the invoice |
| **InvoiceValue** | number | The value of Capture/Release |
| **Comments** | string | Comments that are associated with the invoice (mainly used by portal users) |
| **CustomerName** | string | The customer name that was save along with the invoice |
| **CustomerMobile** | string | Customer mobile number |
| **CustomerEmail** | string | Customer email address |
| **UserDefinedField** | string | The user-defined field that was stored during the invoice creation |
| **InvoiceDisplayValue** | string | Invoice value that is displayed in case of a different currency from the base one |
| **DueDeposit** | number | The amount that will be deposited to the vendor |
| **DepositStatus** | string | The deposit status of the invoice, if it is "Deposited" or "Not Deposited". |
| **InvoiceItems** | Array of [InvoiceItemModel](#invoiceitemmodel) objects |  |
| **InvoiceTransactions** | Array of [InvoiceTransactionModel](#invoicetransactionmodel) objects |  |

#### InvoiceItemModel

| Response Field | Type | Description |
|---|---|---|
| **ItemName** | string | Invoice item name that is stored with the invoice |
| **Quantity** | integer | Item Quantity |
| **UnitPrice** | number | Item unit price |
| **Weight** | number | 100 >= Weight > 0\ Weight in kg |
| **Width** | number | 200 >= Width > 0\ Width in cm |
| **Height** | number | 160 >= Height > 0\ Height in cm |
| **Depth** | number | 200 >= Depth > 0\ Depth in cm |

#### InvoiceTransactionModel

| Response Field | Type | Description |
|---|---|---|
| **TransactionDate** | string | The date of the transaction related to the invoice |
| **PaymentGateway** | string | The gateway the transaction was processed through |
| **ReferenceId** | string | The reference that is generated by the payment gateway |
| **TrackId** | string | The track number that is used to track the transaction with the gateway |
| **TransactionId** | string | The transaction ID |
| **PaymentId** | string | The payment ID that is assigned to this transaction |
| **AuthorizationId** | string | The authorization ID that is assigned to this transaction |
| **TransactionStatus** | string | The status of the transaction could be any of: * InProgress\_: The payment attempt is not completed * Succss\_: The capture was successful * Failed\_: A transaction attempt failed * Authorize\_: The client makes the payment * Canceled\_: The amount is released |
| **TransationValue** | string | The value of the transaction |
| **CustomerServiceCharge** | string | The service charges considered on the customer during the transaction |
| **TotalServiceCharge** | string | Total service charge deducted from MyFatoorah side |
| **DueValue** | string | The amount value of this transaction |
| **PaidCurrency** | string | The currency that was used to pay the transaction |
| **PaidCurrencyValue** | string | The currency value that was used to pay the transaction |
| **VatAmount** | string | The value of the VAT amount for the transaction. |
| **Currency** | string | Transaction currency |
| **Error** | string | The error message that might be associated with the transaction.\ This error is from the acquirer bank/platform |
| **ErrorCode** | string | The MyFatoorah error code. Kindly, refer to the [Error Codes](https://myfatoorah.readme.io/docs/get-payment-status#error-codes) table. |
| **ECI** | string | The ECI record of the transaction. |
| **Card** | object | Contains details about the card used for payment. |

> 📘 Transactions
>
> Transactions information are totally related to the gateway response that came from the acquirer bank / platform. Not necessarily to have information to all the fields, and this was designed only to provide you information about all transactions that happened for a certain invoice

#### Card Model

| Response Field    | Type   | Description                                                   |
| :---------------- | :----- | :------------------------------------------------------------ |
| **NameOnCard**    | string | The name of the cardholder entered by the payer.              |
| **Number**        | string | The masked card number.                                       |
| **PanHash**       | string | A unique identifier for the PAN.                              |
| **ExpiryMonth**   | string | Expiry month entered by the customer.                         |
| **ExpiryYear**    | string | Expiry year entered by the customer.                          |
| **Brand**         | string | Visa/Mastercard/Mada                                          |
| **Issuer**        | string | Name of the issuer bank                                       |
| **IssuerCountry** | string | The issuer country of the card                                |
| **FundingMethod** | string | The funding method of the card (e.g.: debit, credit, prepaid) |

***

#### **Sample Message**

**Capture Request:**

```json Capture Request
{
  "Operation": "capture",
  "Amount": 2,
  "Key": "5822983",
  "KeyType": "Invoiceid"
}
```
```json Capture Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": 5822983,
        "InvoiceStatus": "Paid",
        "InvoiceReference": "2025051962",
        "CustomerReference": "3MmGr8oXAtTnLciEoUAQp6ZZPdEZJ4",
        "CreatedDate": "2025-06-11T11:49:35.507",
        "ExpiryDate": "November 8, 2025",
        "ExpiryTime": "11:49:35.507",
        "InvoiceValue": 2.000,
        "Comments": null,
        "CustomerName": "Anonymous",
        "CustomerMobile": "+965",
        "CustomerEmail": null,
        "UserDefinedField": null,
        "InvoiceDisplayValue": "2.000 KD",
        "DueDeposit": 1.955,
        "DepositStatus": "Not Deposited",
        "InvoiceItems": [],
        "InvoiceTransactions": [
            {
                "TransactionDate": "2025-06-11T11:49:37.4666667",
                "PaymentGateway": "VISA/MASTER",
                "ReferenceId": "516208132783",
                "TrackId": "11-06-2025_2785331",
                "TransactionId": "132783",
                "PaymentId": "07075822983278533173",
                "AuthorizationId": "630070",
                "TransactionStatus": "Authorize",
                "TransationValue": "40.000",
                "CustomerServiceCharge": "0.000",
                "TotalServiceCharge": "0.780",
                "DueValue": "40.000",
                "PaidCurrency": "KD",
                "PaidCurrencyValue": "40.000",
                "VatAmount": "0.117",
                "IpAddress": "102.43.239.185",
                "Country": "Egypt",
                "Currency": "KD",
                "Error": null,
                "CardNumber": "545454xxxxxx5454",
                "ErrorCode": "",
                "ECI": "02",
                "Card": {
                    "NameOnCard": "test",
                    "Number": "545454xxxxxx5454",
                    "PanHash": "3cc8217a6aad545082e07e563edeec444ce961a2468fa1a5eddf238969095735",
                    "ExpiryMonth": "12",
                    "ExpiryYear": "34",
                    "Brand": "Mastercard",
                    "Issuer": "Test Bank",
                    "IssuerCountry": "KWT",
                    "FundingMethod": "credit"
                }
            },
            {
                "TransactionDate": "2025-06-11T11:50:21.8333333",
                "PaymentGateway": "VISA/MASTER",
                "ReferenceId": "516208132783",
                "TrackId": "11-06-2025_2785333",
                "TransactionId": "133803",
                "PaymentId": "07075822983278533374",
                "AuthorizationId": "630070",
                "TransactionStatus": "Succss",
                "TransationValue": "2.000",
                "CustomerServiceCharge": "0.000",
                "TotalServiceCharge": "0.039",
                "DueValue": "2.000",
                "PaidCurrency": "KD",
                "PaidCurrencyValue": "2.000",
                "VatAmount": "0.006",
                "IpAddress": null,
                "Country": null,
                "Currency": "KD",
                "Error": null,
                "CardNumber": "545454xxxxxx5454",
                "ErrorCode": "",
                "ECI": "02",
                "Card": {
                    "NameOnCard": "",
                    "Number": "545454xxxxxx5454",
                    "PanHash": "",
                    "ExpiryMonth": "",
                    "ExpiryYear": "",
                    "Brand": "",
                    "Issuer": "",
                    "IssuerCountry": "",
                    "FundingMethod": ""
                }
            }
        ],
        "Suppliers": []
    }
}
```

*Kindly note that if the "amount" in the capture request is less than the primary "InvoiceValue", the "InvoiceValue" in the response will change to the amount entered in the request.*

**Release Request:**

```json Release Request
{
  "Operation": "release",
  "Amount": 40,
  "Key": "5822987",
  "KeyType": "Invoiceid"
}
```
```json Release Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": 5822987,
        "InvoiceStatus": "Pending",
        "InvoiceReference": "2025051963",
        "CustomerReference": "1P1seSwCdMTdUTjCKme6KKaMrbpf",
        "CreatedDate": "2025-06-11T11:51:23.727",
        "ExpiryDate": "November 8, 2025",
        "ExpiryTime": "11:51:23.727",
        "InvoiceValue": 40.000,
        "Comments": null,
        "CustomerName": "Anonymous",
        "CustomerMobile": "+965",
        "CustomerEmail": null,
        "UserDefinedField": null,
        "InvoiceDisplayValue": "40.000 KD",
        "DueDeposit": 0.000,
        "DepositStatus": "Not Deposited",
        "InvoiceItems": [],
        "InvoiceTransactions": [
            {
                "TransactionDate": "2025-06-11T11:51:25.1133333",
                "PaymentGateway": "VISA/MASTER",
                "ReferenceId": "516208135873",
                "TrackId": "11-06-2025_2785335",
                "TransactionId": "135873",
                "PaymentId": "07075822987278533574",
                "AuthorizationId": "538820",
                "TransactionStatus": "Authorize",
                "TransationValue": "40.000",
                "CustomerServiceCharge": "0.000",
                "TotalServiceCharge": "0.780",
                "DueValue": "40.000",
                "PaidCurrency": "KD",
                "PaidCurrencyValue": "40.000",
                "VatAmount": "0.117",
                "IpAddress": "102.43.239.185",
                "Country": "Egypt",
                "Currency": "KD",
                "Error": null,
                "CardNumber": "545454xxxxxx5454",
                "ErrorCode": "",
                "ECI": "02",
                "Card": {
                    "NameOnCard": "test",
                    "Number": "545454xxxxxx5454",
                    "PanHash": "3cc8217a6aad545082e07e563edeec444ce961a2468fa1a5eddf238969095735",
                    "ExpiryMonth": "12",
                    "ExpiryYear": "34",
                    "Brand": "Mastercard",
                    "Issuer": "Test Bank",
                    "IssuerCountry": "KWT",
                    "FundingMethod": "credit"
                }
            },
            {
                "TransactionDate": "2025-06-11T11:52:04.64",
                "PaymentGateway": "VISA/MASTER",
                "ReferenceId": "07075822987278533674",
                "TrackId": "11-06-2025_2785336",
                "TransactionId": "07075822987278533674",
                "PaymentId": "07075822987278533674",
                "AuthorizationId": "07075822987278533674",
                "TransactionStatus": "Canceled",
                "TransationValue": "40.000",
                "CustomerServiceCharge": "0.000",
                "TotalServiceCharge": "0.780",
                "DueValue": "40.000",
                "PaidCurrency": "KD",
                "PaidCurrencyValue": "40.000",
                "VatAmount": "0.117",
                "IpAddress": null,
                "Country": null,
                "Currency": "KD",
                "Error": "Transaction Released",
                "CardNumber": null,
                "ErrorCode": "",
                "ECI": null,
                "Card": {
                    "NameOnCard": "",
                    "Number": "",
                    "PanHash": "",
                    "ExpiryMonth": "",
                    "ExpiryYear": "",
                    "Brand": "",
                    "Issuer": "",
                    "IssuerCountry": "",
                    "FundingMethod": ""
                }
            }
        ],
        "Suppliers": []
    }
}
```

***

#### **Sample Code**

```php
<?php

/* For simplicity check our PHP SDK library here https://myfatoorah.readme.io/php-library */

//PHP Notice:  To enable MyFatoorah auto-update, kindly give the write/read permissions to the library folder
//use zip file
include 'myfatoorah-library-2.2/MyfatoorahLoader.php';
include 'myfatoorah-library-2.2/MyfatoorahLibrary.php';

//use composer
//require 'vendor/autoload.php';
//use MyFatoorah\Library\MyFatoorah;

/* --------------------------- Configurations ------------------------------- */
//Test
$mfConfig = [
    /**
     * API Token Key (string)
     * Accepted value:
     * Live Token: https://myfatoorah.readme.io/docs/live-token
     * Test Token: https://myfatoorah.readme.io/docs/test-token
     */
    'apiKey' => '',
    /*
     * Vendor Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'vcCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest' => true,
];

/* --------------------------- UpdatePaymentStatus Endpoint ----------------- */

//------------- Post Fields -------------------------
//Check https://docs.myfatoorah.com/docs/updatepaymentstatus#request-model
$postFields = [
    'Operation' => 'capture', //or 'release'
    'Amount'    => 1,
    'Key'       => '2834690',
    'KeyType'   => 'Invoiceid', //'PaymentId', or 'CustomerReference'
];

//------------- Call the Endpoint -------------------------
try {
    $mfObj  = new MyFatoorah($mfConfig);
    $apiURL = $mfObj->getApiUrl();
    $obj    = $mfObj->callAPI("$apiURL/v2/UpdatePaymentStatus", $postFields);

    //Display the result to your customer
    echo '<h3><u>UpdatePaymentStatus Response Object:</u></h3><pre>';
    print_r($obj);
    echo '</pre>';
} catch (Exception $ex) {
    echo $ex->getMessage();
    die;
}
```

## Saving Card Options

*`https://docs.myfatoorah.com/docs/v3-saving-card-options` — updated 2026-02-16*

MyFatoorah Save Card Options enable you to save your customer's card details for future payments.

Based on MyFatoorah payment integration you are using, You will find a description for enabling Saving Card Information:

* [Embedded Payment](https://docs.myfatoorah.com/docs/v3-saving-card-embedded-payment)
* [Direct Payment Tokenization](https://docs.myfatoorah.com/docs/v3-direct-tokenization)
* [KFast Payment](https://docs.myfatoorah.com/docs/kfast)

> 📘 Old Integration (V2)
>
> In case you are using the integration for v2, you can find the documentation here:
>
> * [Saving Card Options](https://docs.myfatoorah.com/docs/saving-card-options)

## Saving Card Options

*`https://docs.myfatoorah.com/docs/saving-card-options` — updated 2026-02-16*

**MyFatoorah** Save Card Options enable you to save your customer's card details to make future payments easier.

Based on **MyFatoorah** payment integration you are using, You will find a description for enabling Saving Card Information:

1. [MyFatoorah Page](https://docs.myfatoorah.com/docs/saving-card-myfatoorah-page)
2. [Embedded Payment](https://docs.myfatoorah.com/docs/saving-card-embedded-payment)
3. [Tokenization](https://docs.myfatoorah.com/docs/tokenization)

## Tokenization

*`https://docs.myfatoorah.com/docs/tokenization` — updated 2026-02-16*

Save the payment card using a token

#### **Introduction**

Interested in making your customer pay faster? Okay, Tokenization is the solution for you. Tokenization is a feature that you can use through our API that will enable you to save your customer's credit card encrypted and hashed based on token communication.

With a simple call, you can request to save the credit card token, get the token back to your application, and associate it with your customer profile for future easier payment execution.

Moreover, **MyFatoorah** provides the Save Card Information feature that will do all the job for you.

> ❗️ Approval is Needed
>
> Kindly, contact your account manager or sales representative to activate the **Direct Payment** and **Tokenization** features.

***

#### **How it works**

In the beginning, you need to create a new **card token** by using the [DirectPayment](https://docs.myfatoorah.com/docs/direct-payment-endpoint) endpoint and passing the **PaymentType** parameter with the value "card" and the **SaveToken** parameter with the value "true".

Note that, this request is a direct payment request. The payment process will go through, the invoice amount will be deducted from the client account, and the card token will be generated. The response body contains a **Token** parameter. This **Token** will be saved in your system with your customer profile to be used in any potential direct payment requests.

To create new payment requests using the **card token**, use the [DirectPayment](https://docs.myfatoorah.com/docs/direct-payment-endpoint) endpoint and pass the **PaymentType** parameter with the value "token" and the **token** parameter with the stored card token value.

For more security, you have to send the CVV (security code) in the request body with the token.

> 📘 Cancel Token
>
> If the customer wants to change their card information, or their card information has been expired or stolen, you have the possibility to cancel a credit card token by using the [CancelToken](https://docs.myfatoorah.com/docs/canceltoken) endpoint.

> 👍 Testing Tokenization
>
> For the test purpose, you should use the **PaymentMethodId** parameter with value "20" in the request body of [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint.

***

#### **Sample Messages**

[DirectPayment](https://docs.myfatoorah.com/docs/direct-payment-endpoint) request with card token generation:

```json Request
{
   "PaymentType":"card",
   "Bypass3DS":false,
   "SaveToken":true,
   "Card":{
      "Number":"5453010000095539",
      "ExpiryMonth":"05",
      "ExpiryYear":"21",
      "SecurityCode":"212",
      "HolderName":"fname lname"
   }
}
```
```json Response
{
  "IsSuccess": true,
  "Message": null,
  "ValidationErrors": null,
  "Data": {
    "Status": "Success",
    "ErrorMessage": null,
    "PaymentId": "060666602253577763",
    "Token": "TOKEN316652",
    "RecurringId": null,
    "PaymentURL": "https://securepaymentstest.fsspaynet.com/ipayd/VPAS.htm?actionVPAS=VbvVEReqProcessHTTP&trandata=2D71384184321893BD4D3A3D78C83FBCAADB97D259F089CB6DCA9E397C40E6A203AA1AE6EB7511AA515F2FA63CA805CF6D98C35616FA73839C3E8696F1704AF81A21A208F21B868D5D7A7D63CB1A711F56A10FE8B714927D9446BBB7F18F5FA3709AEF6DD8EDB5B45642E7646408DF6EC6C7FA674F9E9C63A6EC360A3932DE8D2DFE8802E9FFD5B522EF16DE635BCCC6C7D9460BBCABC680535CAB703C922E2E8A6EED4061A2E310116E9776579185EA20784AD54111AEABAAAF7642925F3C17F04148ECEF45F2A83F8CA572D2EAFD31F7CD960AC1874FB8C19A11C7F18ED688D35144F46E886031B6B337617EFE74B6B4C3E9468D90B9C57B31A03E8DEC85F2FBD496FF87C0949A129302B0039993A60FCCC1E45197E4FF0E298A5BC56669940326FE7EFD40041721959E9BCC9BBBB9B72B64A1FBF56ECE302D5E9AA79BECC86D3AF29945927C95180CE9B35DA88A68B1D583366402773BAD4112C86E4057AE65F340FFF4CE5DD0AB1CF08C49EC51019E8CDC16D006BD25DCCC6E14698142E0A86F7075FA19406E5D7A7D63CB1A711F6A6A23E143E92694327E444B9869E6E1B1F71D88697EDD7E4528BF6941650AB025D9BADDF6ACCA6E427AEC0E0AAE2EBCFF185C9CEAEE4A70F3A638B15D56B2F5D092CD8C3DD1A962&errorURL=https://demo.MyFatoorah.com/En/KWT/PayInvoice/FssFail/09-06-2021_535777&responseURL=https://demo.MyFatoorah.com/En/KWT/PayInvoice/FssSuccess/09-06-2021_535777&tranportalId=E0208401",
    "CardInfo": null
  }
}
```

[DirectPayment](https://docs.myfatoorah.com/docs/direct-payment-endpoint) request using the card token:

```json Token Payment Request
{
   "PaymentType":"token",
   "Bypass3DS":false,
   "token":"TOKEN316652",
   "Card":{
      "SecurityCode":"212"
   }
}
```
```json Token Payment Response
{
  "IsSuccess": true,
  "Message": null,
  "ValidationErrors": null,
  "Data": {
    "Status": "Success",
    "ErrorMessage": null,
    "PaymentId": "060666602253578262",
    "Token": null,
    "RecurringId": null,
    "PaymentURL": "https://securepaymentstest.fsspaynet.com/ipayd/VPAS.htm?actionVPAS=VbvVEReqProcessHTTP&trandata=2D71384184321893BD4D3A3D78C83FBCAADB97D259F089CB6DCA9E397C40E6A203AA1AE6EB7511AA515F2FA63CA805CF6D98C35616FA73839C3E8696F1704AF81A21A208F21B868D5D7A7D63CB1A711F56A10FE8B714927D9446BBB7F18F5FA3709AEF6DD8EDB5B45642E7646408DF6EC6C7FA674F9E9C63A6EC360A3932DE8D2DFE8802E9FFD5B522EF16DE635BCCC6C7D9460BBCABC680535CAB703C922E2E8A6EED4061A2E310116E9776579185EA20784AD54111AEABAAAF7642925F3C17F04148ECEF45F2A83F8CA572D2EAFD31F7CD960AC1874FB8C19A11C7F18ED688D35144F46E886031B6B337617EFE74B658DFC4754E1F9D65C2630F577978DE58FBD496FF87C0949A129302B0039993A60FCCC1E45197E4FF0E298A5BC56669940326FE7EFD40041721959E9BCC9BBBB9B72B64A1FBF56ECE302D5E9AA79BECC86D3AF29945927C95180CE9B35DA88A68EDF5B2A44A67549BAD4112C86E4057AE65F340FFF4CE5DD0AB1CF08C49EC51019E8CDC16D006BD25DCCC6E14698142E0A86F7075FA19406E5D7A7D63CB1A711F6A6A23E143E92694327E444B9869E6E1B1F71D88697EDD7E4528BF6941650AB025D9BADDF6ACCA6E427AEC0E0AAE2EBCFF185C9CEAEE4A70F3A638B15D56B2F5D092CD8C3DD1A962&errorURL=https://demo.MyFatoorah.com/En/KWT/PayInvoice/FssFail/09-06-2021_535782&responseURL=https://demo.MyFatoorah.com/En/KWT/PayInvoice/FssSuccess/09-06-2021_535782&tranportalId=E0208401",
    "CardInfo": null
  }
}
```

***

## Embedded Payment

*`https://docs.myfatoorah.com/docs/v3-saving-card-embedded-payment` — updated 2026-02-16*

> Save the payment card with the embedded payment

#### Overview

To provide a seamless payment solution, MyFatoorah added the savings card information to the Embedded Payment Card View.

#### How It Works

##### 1- Enable Save Card Option for Embedded Payment

> 📘 Approval is Needed
>
> Kindly, contact your [account manager](https://www.myfatoorah.com/en/contact-us/) to enable this feature (Save Card Information).

##### 2- Send the Customer.Reference to POST /v3/sessions

In POST /vs/sessions API Request, send the parameter "Customer.Reference" with a unique value for each customer. This value cannot be used for more than one Customer.

```json Request
{
    "OperationType": "PAY", 
    "PaymentMode": "COMPLETE_PAYMENT",
    "Order": {
        "Amount": 22,
    },
    "Customer": {
        "Reference": "NewToken-1"
    }
}
```

In case you do not send "Customer.Reference" in POST /vs/sessions Request, the Save Card Option will not be visible.

##### 3- Complete the regular Embedded Payment Steps

When adding the SessionId generated from the previous step, the saving card option will be available for customers as shown in the following photo.

![](https://files.readme.io/42edc5e71a5038ba35df7b45d4681f82ecbb238edde9fb6ae6cf0bde30c990a9-image.png)

The customer will have access to saving and managing an unlimited number of cards.

![](https://files.readme.io/8fef5aa056f1d7871e8a53d1db5875c004dfb94264d94833ae298a16e91072ca-image.png)

##### 4- Customize Saving Card (Optional)

You can customize the text and messages that appear to the end-users while saving, adding, or deleting a card by adding the following parameters in the Embedded Payment Style Parameter with the preferred Messages.

```javascript
text: {
   saveCard: "Save card no. for future payments",
   addCard: "Use Another Card",
   deleteAlert: {
      tilte: "Delete Card",
      message: "Are you sure you want to remove this card?",
      confirm: "Yes",
      cancel: "No"
      }
}
```

> 📘 Note
>
> If you are using COLLECT\_DETAILS mode, the card information will be saved against the Customer.Reference in the POST /v3/sessions API.

> 📘 Embedded Tokenization
>
> If you need to handle tokenization on your side and manage the display of saved cards, please refer to the following link: <https://docs.myfatoorah.com/docs/v3-token-payments>

## Embedded Payment

*`https://docs.myfatoorah.com/docs/saving-card-embedded-payment` — updated 2026-02-16*

Save the payment card with the embedded payment

To provide a seamless payment solution, MyFatoorah added saving card information to Embedded Payment Card View.

#### **How it Works**

##### 1. Enable Save Card Option for Embedded Payment

> ❗️ Approval is Needed
>
> Kindly, contact your account manager or sales representative to enable this feature.

##### 2. Send a CustomerIdentifier in InitiateSession API

In InitiateSession API Request, send the parameter "CustomerIdentifier" with a unique value for each customer. This value cannot be used for more than one Customer.

```json Initiate Session Request
{
  "CustomerIdentifier": "" // Add a unique value per each customer.
}
```

In case you do not send "CustomerIdentifier" in InitiateSession Request, the Save Card Option will not be visible.

##### 3. Complete the regular Embedded Payment Steps

When adding the sessionId generated from the previous step, the saving card option will be available for customers as shown in the following photo.

![](https://files.readme.io/1b162f9-2021-11-07.png "2021-11-07.png")

The customer will have access to saving and managing an unlimited number of cards.

![](https://files.readme.io/389caee-2021-11-07_1.png "2021-11-07 (1).png")

##### 4. Customize Saving Card (Optional)

You can customize the text and messages that appear to the end-users while saving, adding, or deleting a card by adding the following parameters in the Embedded Payment Style Parameter with the preferred Messages.

```html
text: {
   saveCard: "Save card no. for future payments",
   addCard: "Use Another Card",
   deleteAlert: {
      tilte: "Delete Card",
      message: "Are you sure you want to remove this card?",
      confirm: "Yes",
      cancel: "No"
      }
}
```

## MyFatoorah Page

*`https://docs.myfatoorah.com/docs/saving-card-myfatoorah-page` — updated 2026-02-16*

Save the payment card with MyFatoorah page

#### **Introduction**

This feature allows the customers to save their card details (linked with a specific vendor) for the future payments.

> ❗️ Approval is needed!
>
> In order to enable this feature, you need to contact your [account manager](https://www.myfatoorah.com/contact.html).

> 📘 Supported Payment Methods
>
> Save Card Information feature works with the following methods:
>
> * Visa/MasterCard.
> * MADA.
> * UAE Debit/Credit Cards.

***

#### **How it works**

The vendor should provide a unique key related to the customer, and this key should start with "CK-". This unique key should be set in the UserDefinedField parameter at the request body of the SendPayment or ExecutePayment endpoints as described in the below example:

```
"UserDefinedField": "CK-12345", 
```

> 🚧 Field Length
>
> The maximum length of the **UserDefinedField** parameter is 50 chars in case of starting with "CK-"

After adding the unique key in the request body, customers will find a checkbox on the payment page asking them to save their card information for the next payment.

In the next payment, the customers will find their saved cards and can choose any card to perform the payment, providing the security code. Also, the customers can remove the saved cards or add new cards.

![](https://files.readme.io/756b4f4db2bbf88acd7ad2985ce78b98eb2fefedb6a77448ac889b51767d3dcc-image.png)

![](https://files.readme.io/f675201f7978abe20797b3998bf864635485d69e0b3c3522b17e1ad284f55884-image.png)

> 🚧 Note:
>
> This feature is not available on Direct Payment, and you can use the tokenization instead.

***

## Direct Payment Tokenization

*`https://docs.myfatoorah.com/docs/v3-direct-tokenization` — updated 2026-02-16*

#### Introduction

In [Direct Integration](https://docs.myfatoorah.com/docs/v3-direct-payment), you can save the card information as a token, get the token back to your application, and associate it with your customer profile for easier future payment execution.

> 📘 Approval Needed
>
> In case you are using Direct Integration you need to contact your [account manager](https://www.myfatoorah.com/en/contact-us/) to activate Tokenization features for Direct Payment.

#### How it works

##### 3D Secure Flow

###### Step 1: Create the Payment

You need to send the card details, unique Customer.Reference and SaveCardOptions.SaveToken=true along with the remaining payment data to create a payment request. Then we return an OTP (3D Secure) URL in the response. You must redirect the customer to this URL to complete the authentication.
After the customer completes the payment, we redirect the user to your Redirection URL, appending the paymentId.

**Endpoint: `POST /v3/payments`**([Create Payment](https://docs.myfatoorah.com/reference/create-payment))

```json Request
{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 20
    },
    "SaveCardOptions": {
        "SaveToken": true
    },
    "Customer": {
        "Reference": "ref-1"
    },
    "SourceOfFund": {
        "Card": {
            "Number": "5123450000000008",
            "ExpiryMonth": "01",
            "ExpiryYear": "39",
            "SecurityCode": "100",
            "HolderName": "JOHN DOE"
        }
    },
    "IntegrationUrls": {
        "Redirection": "https://your-website.com/payment-callback"
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": "6389799",
        "PaymentId": "07076389799322483474",
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/MpgsAuthentication?paymentId=07076389799322483474&sessionId=SESSION0002859250654K70081809F5&mfSessionId=",
        "PaymentCompleted": false,
        "TransactionDetails": null
    }
}
```

###### Step 2: Inquire About the Payment Status and get the card token

After the customer redirects back to your website, use the paymentId to inquire about the payment status.

**Endpoint: `GET /v3/payments/:paymentId`**

```curl Request
GET /v3/payments/07076389799322483474
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "Invoice": {
            "Id": "6389799",
            "Status": "PAID",
            "Reference": "2025001626",
            "CreationDate": "2025-12-24T18:21:10.7770000Z",
            "ExpirationDate": "2026-05-23T18:21:10.7770000Z",
            "ExternalIdentifier": null,
            "UserDefinedField": "",
            "MetaData": null
        },
        "Transaction": {
            "Id": "110012",
            "Status": "SUCCESS",
            "PaymentMethod": "VISA/MASTER",
            "PaymentId": "07076389799322483474",
            "ReferenceId": "535818108932",
            "TrackId": "24-12-2025_3224834",
            "AuthorizationId": "108932",
            "TransactionDate": "2025-12-24T18:27:17.5370000Z",
            "ECI": "02",
            "IP": {
                "Address": "197.32.51.213",
                "Country": "Egypt"
            },
            "Error": {
                "Code": "",
                "Message": ""
            },
            "Card": {
                "NameOnCard": "JOHN DOE",
                "Number": "512345xxxxxx0008",
                "Token": "TKN-13ee9854-a358-492b-be24-2c91a6a80dc4",
                "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                "ExpiryMonth": "01",
                "ExpiryYear": "39",
                "Brand": "Mastercard",
                "Issuer": "Test Bank",
                "IssuerCountry": "KWT",
                "FundingMethod": "credit"
            }
        },
        "Customer": {
            "Reference": "",
            "Name": "Anonymous",
            "Mobile": "+965",
            "Email": ""
        },
        "Amount": {
            "BaseCurrency": "KWD",
            "ValueInBaseCurrency": "20",
            "ServiceCharge": "0.002",
            "ServiceChargeVAT": "0",
            "ReceivableAmount": "19.998",
            "DisplayCurrency": "KWD",
            "ValueInDisplayCurrency": "20",
            "PayCurrency": "KWD",
            "ValueInPayCurrency": "20"
        },
        "Suppliers": []
    }
}
```

> 📘 Webhook
>
> You will receive the card token also in the [webhook ](https://docs.myfatoorah.com/docs/webhook-v2-payment-status-data-model) data.

##### Non-3D Secure Flow

The payment is processed immediately without redirecting the customer for authentication.
The payment result and card **token** are returned directly in the same response.

**Endpoint: `POST /v3/payments`**([Create Payment](https://docs.myfatoorah.com/reference/create-payment))

```json Request
{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 20
    },
    "SaveCardOptions": {
        "SaveToken": true
    },
    "Customer": {
        "Reference": "ref-1"
    },
    "SourceOfFund": {
        "Card": {
            "Number": "5123450000000008",
            "ExpiryMonth": "01",
            "ExpiryYear": "39",
            "SecurityCode": "100",
            "HolderName": "JOHN DOE"
        }
    },
    "IntegrationUrls": {
        "Redirection": "https://your-website.com/payment-callback"
    },
    "ThreeDS":{
        "Enabled": false
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": "6389804",
        "PaymentId": "07076389804322483974",
        "PaymentURL": "https://your-website.com/payment-callback?paymentId=07076389804322483974&Id=07076389804322483974",
        "PaymentCompleted": true,
        "TransactionDetails": {
            "Invoice": {
                "Id": "6389804",
                "Status": "PAID",
                "Reference": "2025001627",
                "CreationDate": "2025-12-24T18:28:48.4897351Z",
                "ExpirationDate": "2026-05-23T18:28:48.4897351Z",
                "ExternalIdentifier": null,
                "UserDefinedField": "",
                "MetaData": null
            },
            "Transaction": {
                "Id": "112092",
                "Status": "SUCCESS",
                "PaymentMethod": "VISA/MASTER",
                "PaymentId": "07076389804322483974",
                "ReferenceId": "535818111042",
                "TrackId": "24-12-2025_3224839",
                "AuthorizationId": "111042",
                "TransactionDate": "2025-12-24T18:28:49.9295716Z",
                "ECI": "",
                "IP": {
                    "Address": "",
                    "Country": ""
                },
                "Error": {
                    "Code": "",
                    "Message": ""
                },
                "Card": {
                    "NameOnCard": "JOHN DOE",
                    "Number": "512345xxxxxx0008",
                    "Token": "TKN-425fc686-bccc-4a81-9eac-a5b8384ac6f0",
                    "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                    "ExpiryMonth": "01",
                    "ExpiryYear": "39",
                    "Brand": "Mastercard",
                    "Issuer": "Test Bank",
                    "IssuerCountry": "KWT",
                    "FundingMethod": "credit"
                }
            },
            "Customer": {
                "Reference": "",
                "Name": "Anonymous",
                "Mobile": "+965",
                "Email": ""
            },
            "Amount": {
                "BaseCurrency": "KWD",
                "ValueInBaseCurrency": "20",
                "ServiceCharge": "0.002",
                "ServiceChargeVAT": "0",
                "ReceivableAmount": "19.998",
                "DisplayCurrency": "KWD",
                "ValueInDisplayCurrency": "20",
                "PayCurrency": "KWD",
                "ValueInPayCurrency": "20"
            },
            "Suppliers": []
        }
    }
}
```

> 📘 Cancel Token
>
> If the customer wants to change their card information, or their card information has been expired or stolen, you have the possibility to cancel a credit card token by using the [CancelToken ](https://docs.myfatoorah.com/docs/canceltoken) endpoint.

***

##### Request using the card token:

**Endpoint: `POST /v3/payments`**([Create Payment](https://docs.myfatoorah.com/reference/create-payment))

###### Using 3DS flow with CVV

```json Request
{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 20
    },
    "Customer": {
        "Reference": "ref-1"
    },
    "SourceOfFund": {
        "Token": "TKN-425fc686-bccc-4a81-9eac-a5b8384ac6f0",
        "Card": {
            "SecurityCode": "100"
        }
    },
    "IntegrationUrls": {
        "Redirection": "https://your-website.com/payment-callback"
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": "6389809",
        "PaymentId": "07076389809322484474",
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/MpgsAuthentication?paymentId=07076389809322484474&sessionId=SESSION0002206942478H99513423M1&mfSessionId=",
        "PaymentCompleted": false,
        "TransactionDetails": null
    }
}
```

###### Using Non-3DS and BypassCVV

```json Request
{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 20
    },
    "SourceOfFund": {
        "Token": "TKN-425fc686-bccc-4a81-9eac-a5b8384ac6f0"
    },
    "IntegrationUrls": {
        "Redirection": "https://your-website.com/payment-callback"
    },
    "ThreeDS": {
        "Enabled": false
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": "6418111",
        "PaymentId": "07076418111324568272",
        "PaymentURL": "https://your-website.com/payment-callback?paymentId=07076418111324568272&Id=07076418111324568272",
        "PaymentCompleted": true,
        "TransactionDetails": {
            "Invoice": {
                "Id": "6418111",
                "Status": "PAID",
                "Reference": "2026000121",
                "CreationDate": "2026-01-07T07:54:04.0723477Z",
                "ExpirationDate": "2026-06-06T07:54:04.0723477Z",
                "ExternalIdentifier": null,
                "UserDefinedField": "",
                "MetaData": null
            },
            "Transaction": {
                "Id": "81615",
                "Status": "SUCCESS",
                "PaymentMethod": "VISA/MASTER",
                "PaymentId": "07076418111324568272",
                "ReferenceId": "600707081615",
                "TrackId": "07-01-2026_3245682",
                "AuthorizationId": "081615",
                "TransactionDate": "2026-01-07T07:54:05.6126662Z",
                "ECI": "",
                "IP": {
                    "Address": "",
                    "Country": ""
                },
                "Error": {
                    "Code": "",
                    "Message": ""
                },
                "Card": {
                    "NameOnCard": "JOHN DOE",
                    "Number": "512345xxxxxx0008",
                    "Token": "TKN-425fc686-bccc-4a81-9eac-a5b8384ac6f0",
                    "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                    "ExpiryMonth": "01",
                    "ExpiryYear": "39",
                    "Brand": "Mastercard",
                    "Issuer": "Test Bank",
                    "IssuerCountry": "KWT",
                    "FundingMethod": "credit"
                }
            },
            "Customer": {
                "Name": "Anonymous",
                "Mobile": "+965",
                "Email": ""
            },
            "Amount": {
                "BaseCurrency": "KWD",
                "ValueInBaseCurrency": "20",
                "ServiceCharge": "0.2",
                "ServiceChargeVAT": "0.03",
                "ReceivableAmount": "19.77",
                "DisplayCurrency": "KWD",
                "ValueInDisplayCurrency": "20",
                "PayCurrency": "KWD",
                "ValueInPayCurrency": "20"
            },
            "Suppliers": []
        }
    }
}
```

> 📘 Approval Needed
>
> In case you need to use Bypass3DS and BypassCVV you need to contact your [account manager](https://www.myfatoorah.com/en/contact-us/) to activate it for your account.

> 📘 FastPay
>
> Fastpay is different from Bypass3DS where Fastpay will always bypass the OTP page for a tokenized card that is 3DSVerified before. Fastpay overrides Bypass3DS.

## KFast Payment

*`https://docs.myfatoorah.com/docs/kfast` — updated 2026-02-16*

#### **Introduction**

KFAST is a feature managed by KNET, by which the customer can save their card(s) details, enabling them to carry out any future transactions with the same merchant, in a speedy manner, by only having to enter their PIN when prompted.

![KFast.png](https://files.readme.io/1eddc89-KFast.png)

> ❗️ Important Note
>
> A unique mobile number must be sent for each customer in MyFatoorah request to have this feature enabled. As it depends on the phone number of the customer.
>
> Moreover, this feature must be enabled from the Account Management Side.

> 📘 Cards Management
>
> Cards being saved by KFAST service are managed by KNET side, not MyFatoorah.

## CancelToken

*`https://docs.myfatoorah.com/docs/canceltoken` — updated 2026-02-16*

Endpoint

#### **Overview**

The "CancelToken" endpoint is a POST request. It is used to remove a certain token. This will be useful to remove the old information of the card in cases like expired card, new card, or stolen card.

The endpoint on Swagger is: [Payment\_CancelToken](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_CancelToken).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following query string parameter:

| Input Parameter | Type | Description |
|---|---|---|
| **token** | string |  |

***

#### **Response Model**

The response is a text in the **Message** parameter.

***

#### **Sample Messages**

```json Request
/v2/CancelToken?token=TOKEN2439
```
```json Response
{
   "IsSuccess":true,
   "Message":"Canceled successfully",
   "ValidationErrors":null,
   "Data":true
}
```

***

## Refund

*`https://docs.myfatoorah.com/docs/refund` — updated 2026-02-16*

> Make a refund request

#### **Introduction**

When you have charged a payer and need to cancel the payment and return the funds to the customer, the funds will be returned to the original credit or debit card used for the charge. It is caused by double orders, products/services not being available, canceled bookings, etc. Now, you can make the refund request from the [MakeRefund](https://docs.myfatoorah.com/docs/make-refund) endpoint directly without the need to log in to your portal account. We have been working hard to make it as easy as possible to handle all vital business transactions from your side. We will explain how to integrate the [MakeRefund](https://docs.myfatoorah.com/docs/make-refund) endpoint into your site or application. Also, we will explain how to check a refund using your portal account.

> 🚧 Refund Request
>
> Please note that this integration will generate a refund request at our finance operation team to be executed by them, so this means that this Refund Request is not sent to the customer till it's been reviewed and executed by MyFatoorah

***

#### **How it works**

You should provide at least three required parameters to make a refund request, which are the **KeyType**, **Key**, and **Amount** parameters. The **KeyType** parameter can be either the "InvoiceId" or "PaymentId". You should previously save the **InvoiceId** parameter value from the response of calling the [SendPayment](https://docs.myfatoorah.com/docs/send-payment) or [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoints. Moreover, after successful payment, **MyFatoorah** returns a **PaymentId** as a parameter in the callback URL. This **PaymentId** parameter can also be used to make a refund request.

You can optionally refund only part of a charge. You can do so multiple times until the entire charge has been refunded. Once entirely refunded, a charge can’t be refunded again. This method will return an error when called on an already-refunded charge or when trying to refund more money than is left on a charge.

> ❗️ Currency Parameter
>
> In the request, the currency parameter has been omitted, as the value passed for the refund would represent the account default currency based on the token you are using. So, please get sure of the amount and used token as they determine the exact amount to be refunded.

You can still follow up on the refund request status from your MyFatoorah portal account as follows:

1. Log in to the [Myfatoorah portal](https://portal.myfatoorah.com/) using your **Super Master Account**.
2. Navigate to **Refunds** → **Refunds List**.
3. You will have the full information about all your refund requests.

![](https://files.readme.io/d41a0710286e601074eed0cb74bd98ce1fc285782ae3f49d276d34d383065405-image.png)

## MakeRefund

*`https://docs.myfatoorah.com/docs/make-refund` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "MakeRefund" endpoint is a POST request. It is used to cancel the payment and return the funds to the customer. Detailed functionality of how to use this endpoint is explained in the [Refund](https://docs.myfatoorah.com/docs/refund) section.

The endpoint on Swagger is: [Refund\_MakeRefund](https://apitest.myfatoorah.com/swagger/ui/index#!/Refund/Refund_MakeRefund).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

> ❗️ Refund Request Responsibility
>
> Please make sure to send one refund request each time, to avoid duplicate partial refund requests (You need to handle this from your system front-end side).\
> Moreover, Kindly note that the refund request responsibility is on the vendor side not on MyFatoorah Side.

***

#### **Request Model**

The request is a POST request with the following parameters:

| Input Parameter | Type | Description |
|---|---|---|
| **KeyType** | string | State either it's "InvoiceId" or "PaymentId" |
| **Key** | string | Value of the key type mentioned |
| **ServiceChargeOnCustomer** | boolean | Determine whether the customer will be charged for the service fees or not. Service fees (for MyFatoorah). |
| **Amount** | decimal | The amount to be refunded |
| **Comment** | string | Extra comments for your reference |
| **ExternalIdentifier** | string | External data associated with the refund, which will be received in the webhook. |
| **AmountDeductedFromSupplier** | number, optional | This is the amount that will be deducted from the supplier in the refund process. It will be part form the total amount. For example: If the total amount is 100 and the AmountDeductedFromSupplier is 70, the vendor will pay the 30 and the supplier will pay 70 |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field         | Type   | Description                                                                         |
| :--------------------- | :----- | :---------------------------------------------------------------------------------- |
| **Key**                | string | The key value you have passed for the Request Transaction                           |
| **RefundId**           | number |                                                                                     |
| **RefundReference**    | string | The refund reference generated by MyFatoorah for following up with the finance team |
| **RefundInvoiceId**    | string | The InvoiceId of the refunded amount                                                |
| **Amount**             | number | The amount to be refunded                                                           |
| **Comment**            | string | The comments that you have passed in the request                                    |
| **ExternalIdentifier** | string | The External Identifier you provided in the request                                 |

***

#### **Sample Message**

```json Request
{
  "Key": "6424767",
  "KeyType": "invoiceid",
  "ServiceChargeOnCustomer": false,
  "ExternalIdentifier": "refund-external-id",
  "Amount": 1,
  "Comment": "partial refund to the customer",
  "AmountDeductedFromSupplier": 0
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Refund Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "Key": "6424767",
        "RefundId": 246275,
        "RefundReference": "2026000012",
        "RefundInvoiceId": 6426263,
        "ExternalIdentifier": "refund-external-id",
        "Amount": 1.0,
        "Comment": "partial refund to the customer"
    }
}
```

***

## GetRefundStatus

*`https://docs.myfatoorah.com/docs/getrefundstatus` — updated 2026-06-25*

> Endpoint

#### **Overview**

The "GetRefundStatus" endpoint is a POST request. It is used to get the status of the refund to check if it is refunded, rejected, or still pending.\
The endpoint on Swagger is: [Refund\_GetRefundStatus](https://apitest.myfatoorah.com/swagger/ui/index#!/Refund/Refund_GetRefundStatus).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following parameters:

| Input Parameter | Type   | Description                                                       |
| :-------------- | :----- | :---------------------------------------------------------------- |
| **KeyType**     | string | supported keys are "InvoiceId", "RefundReference", and "RefundId" |
| **Key**         | string | Value of the key type mentioned                                   |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field         | Type  | Description                           |
| :--------------------- | :---- | :------------------------------------ |
| **RefundStatusResult** | Array | An array of refund status result data |

#### RefundStatusResult

| Response Field         | Type   | Description                                                                                           |
| :--------------------- | :----- | :---------------------------------------------------------------------------------------------------- |
| **RefundId**           | number |                                                                                                       |
| **RefundStatus**       | string | The status of the refund. It takes one of the following values: "Refunded", "Canceled", or "Pending". |
| **InvoiceId**          | number | Represents the invoice ID that was used in the inquiry call.                                          |
| **Amount**             | number | The amount to be refunded.                                                                            |
| **RefundReference**    | string | The refund reference generated by MyFatoorah                                                          |
| **ExternalIdentifier** | string | The ExternalIdentifier that you passed in the MakeRefund request.                                     |
| **RRN**                | string | Refund Reference Number that the customer can check with their bank.                                  |
| **RefundAmount**       | number | The actual amount refunded to the customer.                                                           |
| **BaseCurrency**       | string | Your account base currency                                                                            |

***

#### **Sample Message**

```json Request
{
  "Key": "6867014",
  "KeyType": "InvoiceId"
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "RefundStatusResult": [
            {
                "RefundId": 275988,
                "RefundStatus": "Refunded",
                "InvoiceId": 6867014,
                "Amount": 1.000,
                "RefundReference": "2026002024",
                "ExternalIdentifier": "MF-222",
                "RefundAmount": 1.000,
                "RRN": "617614024566",
                "BaseCurrency": "KWD"
            },
            {
                "RefundId": 275990,
                "RefundStatus": "Pending",
                "InvoiceId": 6867014,
                "Amount": 1.000,
                "RefundReference": "2026002025",
                "ExternalIdentifier": "MF-33",
                "RefundAmount": 1.000,
                "RRN": null,
                "BaseCurrency": "KWD"
            }
        ]
    }
}
```

***

## Sample Code

*`https://docs.myfatoorah.com/docs/refund-sample-code` — updated 2026-02-16*

#### **Overview**

In this section, we provide sample codes for:

* [Make Refund](#make-refund)
* [Get Refund Status](#get-refund-status)

***

#### **Make Refund**

```php
<?php

/* For simplicity check our PHP SDK library here https://myfatoorah.readme.io/php-library */

//PHP Notice:  To enable MyFatoorah auto-update, kindly give the write/read permissions to the library folder
//use zip file
include 'myfatoorah-library-2.2/MyfatoorahLoader.php';
include 'myfatoorah-library-2.2/MyfatoorahLibrary.php';

//use composer
//require 'vendor/autoload.php';
//use MyFatoorah\Library\API\MyFatoorahRefund;

/* --------------------------- Configurations ------------------------------- */
//Test
$mfConfig = [
    /**
     * API Token Key (string)
     * Accepted value:
     * Live Token: https://myfatoorah.readme.io/docs/live-token
     * Test Token: https://myfatoorah.readme.io/docs/test-token
     */
    'apiKey'      => '',
    /*
     * Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'countryCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest'      => true,
];

/* --------------------------- MakeRefund Endpoint -------------------------- */

//Inquiry using InvoiceId
//InvoiceId should be returned in the send/execute payment endpoit response
$keyId   = '3110788';
$KeyType = 'InvoiceId';

//Inquiry using PaymentId
//PaymentId should be returned in the callback
$keyId   = '07073110788180275773';
$KeyType = 'PaymentId';

//------------- Post Fields -------------------------
//Check https://docs.myfatoorah.com/docs/make-refund#request-model
$postFields = [
    // Fill required Data
    'KeyType' => $KeyType,
    'Key'     => $keyId,
    'Amount'  => 1, //can be full/partial refund
        //Fill optional Data
        //'CurrencyIso'                => 'KWD',
        //'Comment'                    => "Test Refund",
        //'ServiceChargeOnCustomer'    => false,
        //'AmountDeductedFromSupplier' => 0
];

//------------- Call the Endpoint -------------------------
try {
    $mfObj = new MyFatoorahRefund($mfConfig);
    $data  = $mfObj->makeRefund($postFields);

    //Display the result to your customer
    echo '<h3><u>Summary:</u></h3>';
    echo "Refund Id is <b>$data->RefundId</b><br>";
    echo "Refund Reference is <b>$data->RefundReference</b>";

    echo '<h3><u>MyFatoorahRefund Response Data:</u></h3><pre>';
    print_r($data);
    echo '</pre>';
} catch (Exception $ex) {
    echo $ex->getMessage();
    die;
}
```
```csharp
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using System;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Threading.Tasks;

namespace Refund
{

    class Program
    {
        // You can get test token from this page  https://myfatoorah.readme.io/docs/test-token
        static string token = "";
        static string baseURL = "https://apitest.myfatoorah.com";

        static async Task Main(string[] args)
        {
            // MakeRefund
            var refundResponse = await MakeRefund().ConfigureAwait(false);
            Console.WriteLine("Make Refund Response :");
            Console.WriteLine(refundResponse);
           
            // MakeRefundWithSupplier
            var refundWithSupplierResponse = await MakeRefundWithSupplier().ConfigureAwait(false);
            Console.WriteLine("Make Refund WithSupplier Response :");
            Console.WriteLine(refundWithSupplierResponse);

            Console.ReadLine();
        }
        public static async Task<string> MakeRefund()
        {
            var makeRefundRequest = new
            {
                //required fields
                key = "665217",
                KeyType = "invoiceid",
                Amount = 1,
                Comment = "refund comment",
                //optional fields 
                RefundChargeOnCustomer = false,
                ServiceChargeOnCustomer = false,
                AmountDeductedFromSupplier = 0,
            };
            var executeRequestJSON = JsonConvert.SerializeObject(makeRefundRequest);
            return await PerformRequest(executeRequestJSON, endPoint: "MakeRefund").ConfigureAwait(false);
        }

        public static async Task<string> MakeRefundWithSupplier()
        {
            var makeRefundWithSupplier = new
            {
                key = "665217",
                KeyType = "invoiceid",
                VendorDeductAmount = 1,
                Comment = "refund comment",
                Suppliers = new[] {
                        new {
                          SupplierCode = 1,
                          SupplierDeductedAmount = 1
                        }
                 }
            };
            var executeRequestJSON = JsonConvert.SerializeObject(makeRefundWithSupplier);
            return await PerformRequest(executeRequestJSON, endPoint: "MakeSupplierRefund").ConfigureAwait(false);
        }
        public static async Task<string> PerformRequest(string requestJSON, string url = "", string endPoint = "")
        {
            if (string.IsNullOrEmpty(url))
                url = baseURL + $"/v2/{endPoint}";

            HttpClient client = new HttpClient();
            client.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
            client.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", token);
            var httpContent = new StringContent(requestJSON, System.Text.Encoding.UTF8, "application/json");
            var responseMessage = await client.PostAsync(url, httpContent).ConfigureAwait(false);
            string response = string.Empty;
            if (!responseMessage.IsSuccessStatusCode)
            {
                response = JsonConvert.SerializeObject(new
                {
                    IsSuccess = false,
                    Message = responseMessage.StatusCode.ToString()
                });
            }
            else
            {
                response = await responseMessage.Content.ReadAsStringAsync();
            }

            return response;
        }
    }

}
```
```python
#Refund Payment API

### Import required libraries (make sure it is installed!)
import requests
import json
import sys

### -----------------------------Define Functions

def check_data(key, response_data):
    if key in response_data.keys() and response_data[key] is not None:
        return True
    else:
        return False


### Error Handle Function
def handle_response(response):
    if response.text == "":  # In case of empty response
        raise Exception("API key is not correct")

    response_data = response.json()
    response_keys = response_data.keys()

    if "IsSuccess" in response_keys and response_data["IsSuccess"] is True:
        return  # Successful
    elif check_data("ValidationErrors", response_data):
        error = []
        for i in range(len(response.json()["ValidationErrors"])):
            v_error = [response_data["ValidationErrors"][i].get(key) for key in ["Name", "Error"]]
            error.append(v_error)
    elif check_data("ErrorMessage", response_data):
        error = response_data["ErrorMessage"]
    elif check_data("Message", response_data):
        error = response_data["Message"]
    else:
        error = "An Error has occurred. API response: " + response.text
    raise Exception(error)


### Call API Function
def call_api(api_url, api_key, request_data, request_type="POST"):
    request_data = json.dumps(request_data)
    headers = {"Content-Type": "application/json", "Authorization": "Bearer " + api_key}
    response = requests.request(request_type, api_url, data=request_data, headers=headers)
    handle_response(response)
    return response


### Refund Function
def refund(refund_request):
    api_url = base_url + "/v2/MakeRefund"
    refund_response = call_api(api_url, api_key, refund_request).json()
    refund_data = refund_response["Data"]
    print("Successful Refund \nRefund Response: ", refund_data)
    return refund_data


### Test Environment
base_url = "https://apitest.myfatoorah.com"
api_key = "MyTokenValue"  # Test token value to be placed here: https:#myfatoorah.readme.io/docs/test-token

### Live Environment
### base_url = "https:#api.myfatoorah.com"
### api_key = "mytokenvalue" #Live token value to be placed here: https:#myfatoorah.readme.io/docs/live-token

refund_request = {
                 "KeyType": "invoiceid",
                 "Key": "962899",
                 "RefundChargeOnCustomer": False,
                 "ServiceChargeOnCustomer": False,
                 "Amount": 105.033,
                 "Comment": "Test Api",
                 "AmountDeductedFromSupplier": 0
                }

try:
    refund(refund_request)
except:
    ex_type, ex_value, ex_traceback = sys.exc_info()
    print("Exception type : %s " % ex_type.__name__)
    print("Exception message : %s" % ex_value)
```

***

#### **Get Refund Status**

```php
<?php

/* For simplicity check our PHP SDK library here https://myfatoorah.readme.io/php-library */

//PHP Notice:  To enable MyFatoorah auto-update, kindly give the write/read permissions to the library folder
//use zip file
include 'myfatoorah-library-2.2/MyfatoorahLoader.php';
include 'myfatoorah-library-2.2/MyfatoorahLibrary.php';

//use composer
//require 'vendor/autoload.php';
//use MyFatoorah\Library\MyFatoorah;

/* --------------------------- Configurations ------------------------------- */
//Test
$mfConfig = [
    /**
     * API Token Key (string)
     * Accepted value:
     * Live Token: https://myfatoorah.readme.io/docs/live-token
     * Test Token: https://myfatoorah.readme.io/docs/test-token
     */
    'apiKey'      => '',
    /*
     * Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'countryCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest'      => true,
];

/* --------------------------- GetRefundStatus Endpoint --------------------- */

//Inquiry using InvoiceId
//InvoiceId should be returned in the send/execute payment endpoit response
$keyId   = '3110788';
$KeyType = 'InvoiceId';

//Inquiry using RefundReference
//RefundReference should be returned in the callback
$keyId   = '2023000787';
$KeyType = 'RefundReference';

//Inquiry using RefundId
//RefundId should be returned in the callback of MakeRefund endpoint
$keyId   = '85342';
$KeyType = 'RefundId';

//------------- Post Fields -------------------------
//Check https://docs.myfatoorah.com/docs/getrefundstatus#request-model
$postFields = [
    // Fill required Data
    'KeyType' => $KeyType,
    'Key'     => $keyId,
];

//------------- Call the Endpoint -------------------------
try {
    $mfObj  = new MyFatoorah($mfConfig);
    $apiURL = $mfObj->getApiUrl();
    $obj    = $mfObj->callAPI("$apiURL/v2/GetRefundStatus", $postFields);

    //Display the result to your customer
    echo '<h3><u>Summary:</u></h3>';
    echo 'Refund status is <b>' . $obj->Data->RefundStatusResult[0]->RefundStatus . '</b>';

    echo '<h3><u>GetRefundStatus Response Data:</u></h3><pre>';
    print_r($obj->Data);
    echo '</pre>';
} catch (Exception $ex) {
    echo $ex->getMessage();
    die;
}
```

## Authorize & Capture

*`https://docs.myfatoorah.com/docs/v3-auth-capture` — updated 2026-02-16*

> Capturing/Releasing Amounts

#### **Introduction**

When a payment is made using a gateway that supports **Authorization & Capture**, you have the ability to **capture** (either partially or fully) the amount of the authorized payment or **release** it back to the customer.

* Once the invoice is paid, it will appear in your **MyFatoorah Portal** and in the response of `GET /v3/payments` with the status `"AUTHORIZE"`.
* You can perform **Capture** or **Release** operations using the `PUT /v3/payments/{paymentId}` endpoint.
* In the request body, set the `"OperationType"` parameter to either `"CAPTURE"` or `"RELEASE"` Depending on your intended action.

> 🚧 **Approval Needed**
>
> Kindly contact your account manager to check the availability for enabling the Authorization Capture feature.

> 📘 **Auto-Capture Option**
>
> You can choose between two flows:
>
> 1. **Authorize & Capture** in two separate steps.
> 2. **Auto-Capture** the payment directly in one step using `"OperationType": "PAY"` in your payment request.

#### **How It Works**

##### **Step 1: Create a Payment with Authorization**

When initiating the payment, send the `"OperationType": "AUTHORIZE"` parameter in the request.

```json Hosted Payment Page (POST /v3/payments)
{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 10
    },
    "OperationType": "AUTHORIZE",
    "IntegrationUrls": {
       "Redirection": "https://your-website.com/payment-callback"
    }
}
```
```json Embedded Integration (POST /v3/sessions)
{
    "PaymentMode": "COMPLETE_PAYMENT", 
    "Order": {
        "Amount": 30
    },
    "OperationType": "AUTHORIZE",
    "IntegrationUrls": {
        "Redirection": "https://your-website.com/payment-callback"
    }
}
```

**Payment Response:**

```json Get Payment Details
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "Invoice": {
            "Id": "6345277",
            "Status": "PENDING",
            "Reference": "2025001280",
            "CreationDate": "2025-12-06T18:29:12.2770000Z",
            "ExpirationDate": "2026-05-05T18:29:12.2770000Z",
            "ExternalIdentifier": null,
            "UserDefinedField": "",
            "MetaData": null
        },
        "Transaction": {
            "Id": "53059",
            "Status": "AUTHORIZE",
            "PaymentMethod": "VISA/MASTER",
            "PaymentId": "07076345277319592774",
            "ReferenceId": "534018053059",
            "TrackId": "06-12-2025_3195927",
            "AuthorizationId": "053059",
            "TransactionDate": "2025-12-06T18:29:17.0030000Z",
            "ECI": "02",
            "IP": {
                "Address": "197.32.110.20",
                "Country": "Egypt"
            },
            "Error": {
                "Code": "",
                "Message": ""
            },
            "Card": {
                "NameOnCard": "test test",
                "Number": "512345xxxxxx0008",
                "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                "ExpiryMonth": "01",
                "ExpiryYear": "39",
                "Brand": "Mastercard",
                "Issuer": "Test Bank",
                "IssuerCountry": "KWT",
                "FundingMethod": "credit"
            }
        },
        "Customer": {
            "Reference": "",
            "Name": "Anonymous",
            "Mobile": "+965",
            "Email": ""
        },
        "Amount": {
            "BaseCurrency": "KWD",
            "ValueInBaseCurrency": "23",
            "ServiceCharge": "0.003",
            "ServiceChargeVAT": "0",
            "ReceivableAmount": "22.997",
            "DisplayCurrency": "KWD",
            "ValueInDisplayCurrency": "23",
            "PayCurrency": "KWD",
            "ValueInPayCurrency": "23"
        },
        "Suppliers": []
    }
}
```
```json Webhook Data
{
  "Event": {
    "Code": 1,
    "Name": "PAYMENT_STATUS_CHANGED",
    "CountryIsoCode": "KWT",
    "CreationDate": "2025-12-06T18:29:26.1300000Z",
    "Reference": "WH-595351"
  },
  "Data": {
    "Invoice": {
      "Id": "6345277",
      "Status": "PENDING",
      "Reference": "2025001280",
      "CreationDate": "2025-12-06T18:29:12.277Z",
      "ExpirationDate": "2026-05-05T18:29:12.277Z",
      "UserDefinedField": "",
      "ExternalIdentifier": null,
      "MetaData": null
    },
    "Transaction": {
      "Id": "53059",
      "Status": "AUTHORIZE",
      "PaymentMethod": "VISA/MASTER",
      "PaymentId": "07076345277319592774",
      "ReferenceId": "534018053059",
      "TrackId": "06-12-2025_3195927",
      "AuthorizationId": "053059",
      "TransactionDate": "2025-12-06T18:29:17.003Z",
      "ECI": "02",
      "IP": {
        "Address": "197.32.110.20",
        "Country": "Egypt"
      },
      "Error": {
        "Code": "",
        "Message": ""
      },
      "Card": {
        "NameOnCard": "test test",
        "Number": "512345xxxxxx0008",
        "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
        "ExpiryMonth": "01",
        "ExpiryYear": "39",
        "Brand": "Mastercard",
        "Issuer": "Test Bank",
        "IssuerCountry": "KWT",
        "FundingMethod": "credit"
      }
    },
    "Customer": {
      "Name": "Anonymous",
      "Mobile": "+965",
      "Email": ""
    },
    "Amount": {
      "BaseCurrency": "KWD",
      "ValueInBaseCurrency": "23",
      "ServiceCharge": "0.003",
      "ServiceChargeVAT": "0",
      "ReceivableAmount": "22.997",
      "DisplayCurrency": "KWD",
      "ValueInDisplayCurrency": "23",
      "PayCurrency": "KWD",
      "ValueInPayCurrency": "23"
    },
    "Suppliers": []
  }
}
```

##### **Step 2: Capture or Release the Payment**

After the payment is authorized, you can either **Capture** the payment (fully or partially) or **Release** it back to the customer using the following endpoint:

**Endpoint:** `PUT /v3/payments/{paymentId}` ([Update Payment](https://docs.myfatoorah.com/reference/update-payment))

```json Capture Request

{
    "OperationType": "CAPTURE",
    "Amount": 10
}
```
```json Capture Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "Invoice": {
            "Id": "6283869",
            "Status": "PAID",
            "Reference": "2025001139",
            "CreationDate": "2025-11-12T21:40:55.4400000Z",
            "ExpirationDate": "2026-04-11T21:40:55.4400000Z",
            "ExternalIdentifier": null,
            "UserDefinedField": "",
            "MetaData": null
        },
        "Transaction": {
            "Id": "189591",
            "Status": "SUCCESS",
            "PaymentMethod": "VISA/MASTER",
            "PaymentId": "07076283869314693674",
            "ReferenceId": "531621188501",
            "TrackId": "13-11-2025_3146936",
            "AuthorizationId": "188501",
            "TransactionDate": "2025-11-12T21:41:47.0156741Z",
            "ECI": "02",
            "IP": {
                "Address": "",
                "Country": ""
            },
            "Error": {
                "Code": "",
                "Message": ""
            },
            "Card": {
                "NameOnCard": "string",
                "Number": "512345xxxxxx0008",
                "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                "ExpiryMonth": "01",
                "ExpiryYear": "39",
                "Brand": "Mastercard",
                "Issuer": "Test Bank",
                "IssuerCountry": "KWT",
                "FundingMethod": "credit"
            }
        },
        "Customer": {
            "Reference": "",
            "Name": "Anonymous",
            "Mobile": "+965",
            "Email": ""
        },
        "Amount": {
            "BaseCurrency": "KWD",
            "ValueInBaseCurrency": "10",
            "ServiceCharge": "0.001",
            "ServiceChargeVAT": "0",
            "ReceivableAmount": "9.999",
            "DisplayCurrency": "KWD",
            "ValueInDisplayCurrency": "10",
            "PayCurrency": "KWD",
            "ValueInPayCurrency": "10"
        },
        "Suppliers": []
    }
}

```

> 🚧 **Note:**
> If the "Amount" in the capture request is less than the original amount in the first payment request, the response will be updated to the captured amount.

```json Release Request
{
    "OperationType": "RELEASE",
}
```
```json Release Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "Invoice": {
            "Id": "6283870",
            "Status": "PENDING",
            "Reference": "2025001140",
            "CreationDate": "2025-11-12T21:43:45.0970000Z",
            "ExpirationDate": "2026-04-11T21:43:45.0970000Z",
            "ExternalIdentifier": null,
            "UserDefinedField": "",
            "MetaData": null
        },
        "Transaction": {
            "Id": "07076283870314694073",
            "Status": "CANCELED",
            "PaymentMethod": "VISA/MASTER",
            "PaymentId": "07076283870314694073",
            "ReferenceId": "07076283870314694073",
            "TrackId": "13-11-2025_3146940",
            "AuthorizationId": "07076283870314694073",
            "TransactionDate": "2025-11-12T21:47:32.8772780Z",
            "ECI": "",
            "IP": {
                "Address": "",
                "Country": ""
            },
            "Error": {
                "Code": "",
                "Message": "Transaction Released"
            },
            "Card": {
                "NameOnCard": "string",
                "Number": "512345xxxxxx0008",
                "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                "ExpiryMonth": "01",
                "ExpiryYear": "39",
                "Brand": "Mastercard",
                "Issuer": "Test Bank",
                "IssuerCountry": "KWT",
                "FundingMethod": "credit"
            }
        },
        "Customer": {
            "Reference": "",
            "Name": "Anonymous",
            "Mobile": "+965",
            "Email": ""
        },
        "Amount": {
            "BaseCurrency": "KWD",
            "ValueInBaseCurrency": "10",
            "ServiceCharge": "0.001",
            "ServiceChargeVAT": "0",
            "ReceivableAmount": "9.999",
            "DisplayCurrency": "KWD",
            "ValueInDisplayCurrency": "10",
            "PayCurrency": "KWD",
            "ValueInPayCurrency": "10"
        },
        "Suppliers": []
    }
}
```

> 📘 **Important Notes**
>
> * You can perform **only one Capture or Release** operation per invoice.
> * Once captured or released, the payment cannot be modified again.

> 📘 Old Integration (V2)
>
> In case you are using the integration for v2, you can find the documentation here:
>
> * [Authorization and Capture](https://docs.myfatoorah.com/docs/authorization-capture)
> * [UpdatePaymentStatus](https://docs.myfatoorah.com/docs/updatepaymentstatus)

## Authorization and Capture

*`https://docs.myfatoorah.com/docs/authorization-capture` — updated 2026-02-16*

Capturing/Releasing Amounts

#### **Introduction**

When a payment is made using a gateway that supports authorization capture, you will have the ability to capture either partially or fully the amount of the invoice paid. You will also be able to release the amount of the invoice back to the customer.

***

#### **How it works**

<Embed url="https://www.youtube.com/watch?v=w1VrhWk87cY" favicon="https://www.youtube.com/favicon.ico" image="http://i.ytimg.com/vi/w1VrhWk87cY/hqdefault.jpg" provider="youtube.com" href="https://www.youtube.com/watch?v=w1VrhWk87cY" typeOfEmbed="youtube" title="undefined" html="%3Ciframe%20class%3D%22embedly-embed%22%20src%3D%22%2F%2Fcdn.embedly.com%2Fwidgets%2Fmedia.html%3Fsrc%3Dhttps%253A%252F%252Fwww.youtube.com%252Fembed%252Fw1VrhWk87cY%26display_name%3DYouTube%26url%3Dhttps%253A%252F%252Fwww.youtube.com%252Fwatch%253Fv%253Dw1VrhWk87cY%26image%3Dhttp%253A%252F%252Fi.ytimg.com%252Fvi%252Fw1VrhWk87cY%252Fhqdefault.jpg%26type%3Dtext%252Fhtml%26schema%3Dyoutube%22%20width%3D%22854%22%20height%3D%22480%22%20scrolling%3D%22no%22%20title%3D%22YouTube%20embed%22%20frameborder%3D%220%22%20allow%3D%22autoplay%3B%20fullscreen%3B%20encrypted-media%3B%20picture-in-picture%3B%22%20allowfullscreen%3D%22true%22%3E%3C%2Fiframe%3E" />

* Once the invoice is paid, it will appear in your MyFatoorah portal & in the response of GetPaymentStatus as "Authorize".
* You can use the Release/Capture operations in the UpdatePaymentStatus endpoint.
* In the body of the request, you can set the action to be either "Capture" or "Release"

> 🚧 Approval Needed
>
> Contact your account manager to enable for you the Authorization Capture feature.

> 📘 Auto Capture
>
> You can choose if you want to **Authorize & Capture** on two different steps or **auto-capture** the amount directly using the [ProcessingDetails](https://docs.myfatoorah.com/docs/execute-payment#processingdetails) parameter in the request to ExecutePayment.

***

## Card Verification

*`https://docs.myfatoorah.com/docs/card-verification` — updated 2026-06-28*

### **Introduction**

Card Verification is a MyFatoorah functionality used to **authenticate a customer’s card** without deducting any amount and without creating any transaction record in MyFatoorah.

It allows you to:

* Validate the card
* Confirm the card supports 3D Secure
* Confirm the customer completed authentication (OTP)
* Tokenize the card for later usage

> 📘 **Integration Availability**
>
> Card Verification works only with [Embedded Integration](https://docs.myfatoorah.com/update/docs/embedded-payment-v3) & [Direct Integration](https://docs.myfatoorah.com/docs/v3-direct-payment)

### **How It Works**

#### Embedded Integration

You will receive the verification result either:

* Directly in **MyFatoorah callback** (if using `shouldHandlePaymentUrl: true`)
* Or by **calling the inquiry API** `GET /v3/sessions/{sessionId}`

##### **Step 1:  Create a Verification Session**

**Endpoint:** `POST /v3/sessions` ([Create Session](https://docs.myfatoorah.com/reference/create-session))

```json Request Example
{
    "PaymentMode": "COMPLETE_PAYMENT",
    "Order": {
        "Amount": 0
    },
    "Customer": {
        "Reference": "NewToken-1"
    },
    "OperationType": "Verify",
    "IntegrationUrls": {
        "Redirection": "https://your-website.com/payment-callback"
    },
    "SaveCardOptions": {
        "SaveToken": true
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "SessionId": "KWT-3e13d9ef-4049-4451-946a-9d130de7afc1",
        "SessionExpiry": "2025-11-18T22:10:11.0402350Z",
        "EncryptionKey": "5Yisq7Z7lR/1Bg1FuA+wGg5P/sjhIQyPoMVCEzBO+bo=",
        "OperationType": "Verify",
        "Order": {
            "Amount": 0.0,
            "Currency": "KWD",
            "ExternalIdentifier": null
        },
        "Customer": {
            "Reference": "123456",
            "Cards": null
        }
    }
}
```

##### **Step 2: Implement Embedded Integration**

Use the Embedded integration guide: [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-payment-v3#step-2-initialize-client-side-integration)

##### **Step 3: Receiving Verification Result**

**Option 1: shouldHandlePaymentUrl = true**

You will receive the verification result **directly in MyFatoorah callback**.

```json Callback Example
{
    "isSuccess": true,
    "sessionId": "KWT-b980fb36-e6da-4110-9b5c-812ee14e7cf3",
    "paymentCompleted": true,
    "paymentData": "07kipVdwrbJuYN/yDTbDgGCU087bEgq7xEnW+j5voFiKPxK5+IiCoMBLg0IIFsYnvyXNUgZMR27X6IM3453gsMkn2zJtgDAwyPRyap4soK+yDGt5OzerOyCOCO7jrSw1VZkDqUSiivNBs5J9jjjxMiocb77/LxIS/oWCgd7HNME8myqQ+aXdZh1bV49fnHzb3/sZUN8jGJrEEOSZxM8Iw9g3TjGi7rl3G6M0W5pDhGA0xdYeVknmoeR0sG/wTiq+HdlBe4zoZMzEv8NutWzB+4XTLHC3oAvuxuMsGu/wYf9+7L3fPwKGSGldmIbCq0bLCBwilxMWL5sb3aUgeQLT8/geVUWsX0Ub4GqhScpxXCwsoimK0NilmjM78XFyOMpq0ffR0Mn/sZ92DUFjDsDqXKgKhXVi0OXdz4lDaD3m4T1vO9gqWL/pLnaqCG8GZxWQrlA8kjwhBIjagtHLCedN2dwPIn2HuW6vZbcxI8ZEF0i8GDDaI4npWyMWMywd7Lwjt5Lpw1D9FY5l7neM3RscGV7Wv0MZ+6koox6AseLv5mV/fgC7dG0A1kBdSJzDgh+mj8Ze4snX4tyYDY9EshDfxD07VFoojhbpLUdaFUtIimoTeSpOG+WBdTNMr4k7YlxnnkNk4lOi4OlvKQB8Mp8Z5FJ5sAyVpQz86I6GimnWYHO112DaQWSbxc2FYUYqVxlv9f5I382PZoFMQSEABsVEKoSVL53VR9pz9Oq7+soL99Gay9j8nl+0BU3fxU/bhGj6rbntoPlq5nkCWz1As9ow/pb89XaGC1s4dOpjOZ59X9s=",
    "paymentType": "CARD",
    "redirectionUrl": "https://demo.MyFatoorah.com/payments/v3/En/KWT/VerifyResult?sessionId=KWT-b980fb36-e6da-4110-9b5c-812ee14e7cf3"
}
```
```json Decrypted Payment Data
{
    "IsCardVerified": true,
    "Error": null,
    "Card": {
        "Number": "512345xxxxxx0008",
        "ExpiryMonth": "01",
        "ExpiryYear": "39",
        "Brand": "Mastercard",
        "PanType": "Card",
        "Issuer": "Test Bank",
        "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
        "TokenId": null,
        "Token": "TKN-7b5a78ea-ca6f-46cc-be5c-a9801d189d48",
        "TransactionType": null,
        "AgreementId": null,
        "NetworkTransactionId": "",
        "Bypass3ds": false,
        "NameOnCard": "test",
        "IssuerCountry": "KWT",
        "FundingMethod": "credit",
        "ProductName": "Mastercard Titanium",
        "First8digit": "51234500",
        "Is3DSVerified": true,
        "IsLocalCard": true
    },
    "Customer": {
        "Reference": "NewToken-1"
    }
}
```

You must **decrypt** `paymentData` using the session’s `EncryptionKey`.

A successful verification means: IsCardVerified = true and Is3DSVerified = true.

**Option 2: shouldHandlePaymentUrl = false**

MyFatoorah will send a callback that includes the OTP redirection link, and you must redirect the customer to the redirectionUrl value.

```json Sample Callback
{
  "isSuccess": true,
  "paymentType": "CARD",
  "sessionId": "KWT-444c3461-a50d-4b22-bf85-4dc90fb03fe8",
  "paymentCompleted": false,
  "card": {
    "brand": "Mastercard",
    "panHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
    "token": "Token05065471913927257",
    "number": "512345xxxxxx0008",
    "nameOnCard": "test",
    "expiryYear": "39",
    "expiryMonth": "01",
    "issuer": "Test Bank",
    "issuerCountry": "KWT",
    "fundingMethod": "credit",
    "productName": "Mastercard Titanium"
  },
  "redirectionUrl": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/MFCardVerification?sessionId=444c3461-a50d-4b22-bf85-4dc90fb03fe8"
}
```

The customer will complete OTP authentication, and then MyFatoorah will redirect the customer back to your website with the sessionId: `https://your-website.com/payment-callback?sessionId=444c3461-a50d-4b22-bf85-4dc90fb03fe8`

You need to call the [**Get Session Details**](https://docs.myfatoorah.com/reference/get-session-details) endpoint to inquire about the verification result:

```curl Endpoint
GET /v3/sessions/{sessionId}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "SessionExpiry": "2025-12-24T18:52:24.4687915+00:00",
        "IsUsed": true,
        "OperationType": "VERIFY",
        "Order": {
            "Amount": 0.0,
            "Currency": "KWD",
            "ExternalIdentifier": null
        },
        "Customer": {
            "Reference": "NewToken-1"
        },
        "Card": {
            "Number": "512345xxxxxx0008",
            "ExpiryMonth": "01",
            "ExpiryYear": "39",
            "Brand": "Mastercard",
            "PanType": "Card",
            "Issuer": "Test Bank",
            "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
            "Token": "TKN-7b5a78ea-ca6f-46cc-be5c-a9801d189d48",
            "NameOnCard": "test",
            "IssuerCountry": "KWT",
            "FundingMethod": "credit",
            "ProductName": "Mastercard Titanium",
            "Is3DSVerified": true
        },
        "TransactionResult": null
    }
}
 
```

The card is verified when: `Is3DSVerified: true`

> 📘 Note
>
> If you are using "PaymentMode": "COLLECT\_DETAILS" we will redirect your customer to your Redirection URL with the `SessionId` appended. You then need to call `GET /v3/sessions/:sessionId` to check the verification result.

#### Direct Integration

**Endpoint:** POST /v3/payments

```json Request
{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 0
    },
    "OperationType": "VERIFY",
    "SourceOfFund": {
        "Card": {
            "Number": "5123450000000008",
            "ExpiryMonth": "01",
            "ExpiryYear": "39",
            "SecurityCode": "100",
            "HolderName": "JOHN DOE"
        }
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": null,
        "PaymentId": null,
        "PaymentURL": null,
        "PaymentCompleted": true,
        "TransactionDetails": null,
        "Card": {
            "Number": "512345xxxxxx0008",
            "ExpiryMonth": "01",
            "ExpiryYear": "39",
            "Brand": "Mastercard",
            "PanType": "Card",
            "Issuer": "Test Bank",
            "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
            "Token": "",
            "NameOnCard": "JOHN DOE",
            "IssuerCountry": "KWT",
            "FundingMethod": "credit",
            "ProductName": "Mastercard Titanium",
            "IsValidCard": true,
            "Is3DSVerified": false
        }
    }
}
```

Card validity is determined by the `IsValidCard `value in the response.

* true: The card is valid
* false: The card is invalid

> 📘 Tokenization
>
> To tokenize the card in Direct Integration, you will need to send the following in the request:
>
> ```json
> "SaveCardOptions": {
>         "SaveToken": true
>     },
> "Customer": {
>         "Reference": "111"
> }
> ```

## Recurring Payment

*`https://docs.myfatoorah.com/docs/recurring-payment` — updated 2026-02-16*

> Execute Payments automatically

#### **Introduction:**

Recurring payment enables you to deduct amounts from your customers on a regular or irregular basis. The recurring payment is useful for subscriptions, installments, and unscheduled deductions.

***

#### **How it works**

There are two ways of managing recurring payments with MyFatoorah.

##### 1- Utilizing MyFatoorah Recurring Payment

> 🚧 Availability
>
> MyFatoorah Recurring Payment is only available in the V2 APIs of the payment flow

In this implementation, you will use MyFatoorah [RecurringModel](https://docs.myfatoorah.com/docs/execute-payment#recurringmodel) in the ExecutePayment endpoint to specify:

* How often you want the deduction to take place
* The number of deductions

The subsequent deductions will be made in the same amount as the initial payment.

When you send the request to MyFatoorah, MyFatoorah returns in the response the RecurringId.

The **Iteration** parameter determines how many times you will charge the customer for your services. If the Iteration parameter is set to "0", it will be an unlimited time of charging the customer until you cancel the recurring.

The **RecurringType** parameter defines the interval time of charging the customer again with the same amount sent in the ExecutePayment endpoint request. The value of the RecurringType parameter is as follows

* Custom
* Daily
* Weekly
* Monthly

If you choose any value of the previous choices except the "**custom**" value, the interval days parameter will be ignored. In the case of choosing the "**Custom**" as the RecurringType, then you should set the value of the IntervalDays **between 1 and 180** days based on your business needs.

The **RetryCount** parameter is used to set the number of times that the system should retry a failed recurring payment before stopping the subscription cycle. It is an optional parameter, and it is between 0 to 5. The retry process is done every day until the counter becomes zero or the invoice gets paid. The counter is reset with each iteration.

Once you have initiated a Recurring Payment, the status of the recurring will be "**Draft**" until the user pays the invoice. After that, the recurring status becomes "**Active**".

You have to **save the RecurringId** in your system with your customer profile so that, you can keep track of all payments done against that customer. Moreover, you will be able to cancel it when needed later on.

> 👍 Testing Recurring
>
> To test the Recurring on the public API Key, you can use **PaymentMethodId: 2** in the request to ExecutePayment endpoint.

> 📘 Requirements
>
> Kindly, contact your [account manager](https://www.myfatoorah.com/contact.html) or sales representative to activate the **Recurring** feature. Make sure to know what payment methods will support the recurring payment.

> 📘 Use Case
>
> MyFatoorah Recurring is useful for subscription payments and installments.

You can implement the MyFatoorah Managed Recurring Payment in two payment flows:

* [Recurring with Embedded](https://docs.myfatoorah.com/docs/recurring-payment-embedded)
* [Recurring with Redirection](https://docs.myfatoorah.com/docs/recurring-payment-redirection)

###### Checking Recurring Payment Status:

After the payment is made, you can call [GetRecurringPayment ](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_GetRecurringPayment)endpoint to get the status of the RecurringId and how many deductions took place on it. For more information, please check [GetRecurringPayment](https://docs.myfatoorah.com/docs/getrecurringpayment).

We strongly recommend implementing the [Webhook for recurring](https://docs.myfatoorah.com/docs/recurring-data-model) to get a webhook event for every deduction attempt on the RecurringId and its status.

##### 2- Vendor Managed Recurring Payment

Vendor Managed Recurring gives you full control over the subsequent deductions from the client.

In this implementation, you will make the deductions from your side, fully deciding how much you want to deduct, how often, and how many times.

For more information about the implementation, please check [Vendor-Managed Recurring](https://docs.myfatoorah.com/docs/v3-vendor-managed-recurring)

> 📘 Use Case
>
> Vendor Managed Recurring is useful for subscription payments, installments and unscheduled recurring payment.

## Recurring Payment | Embedded Integration

*`https://docs.myfatoorah.com/docs/recurring-payment-embedded` — updated 2026-02-16*

Execute Payments automatically through Card View

#### **Steps**

##### 1. Call InitiateSession endpoint

You will call the [InitiateSession ](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_InitiateSession)endpoint and specify that this is a recurring session.

```json Request
{
  "CustomerIdentifier": "TestRecurring-123",
  "IsRecurring": true
}
```
```json Response
{
  "IsSuccess": true,
  "Message": "Initiated Successfully!",
  "ValidationErrors": null,
  "Data": {
    "SessionId": "36201229-25ce-4360-9afc-5d901715b3a3",
    "CountryCode": "KWT",
    "CustomerTokens": []
  }
}
```

> 🚧 Recurring Sessions
>
> Recurring Sessions don't save tokens and it is prohibbited to send the value of SaveToken to be true in a recurring session.

##### 2. Use the SessionId in the Card View

You will use the SessionId to display the **Embedded Payment** to enable your customer to fill in his card information. For more information about the steps to do this, check the [Embedded Payment Section](https://docs.myfatoorah.com/docs/embedded-integration-steps)

##### 3. Call ExecutePayment using the SessionId and add the Recurring Model

After the customer fills in his card information and is ready for the next step, you will call [ExecutePayment](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_ExecutePayment) endpoint using the SessionId and add the Recurring Model to the request.

```json Request
{
    "SessionId": "36201229-25ce-4360-9afc-5d901715b3a3",
    "invoiceValue": 20,
    "RecurringModel": {
        "RecurringType": "Daily",
        "IntervalDays": null,
        "Iteration": 1,
        "RetryCount": 5
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Invoice Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": 4283109,
        "IsDirectPayment": false,
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/MpgsAuthentication?paymentId=07074283109216281472&sessionId=SESSION0002341682400N21310362I5",
        "CustomerReference": null,
        "UserDefinedField": null,
        "RecurringId": "RECUR121809319"
    }
}
```

##### 4. Redirect the customer to the PaymentUrl

You will redirect the customer to the PaymentUrl to enter his OTP. After that, the customer is redirected to your CallBackUrl/ErrorUrl that you sent in ExecutePayment request.

## Recurring Payment | Redirection

*`https://docs.myfatoorah.com/docs/recurring-payment-redirection` — updated 2026-02-16*

Execute Payments through redirecting for card payments

#### \*Steps\*\*

##### 1. Call ExecutePayment using the RecurringModel

You will call [ExecutePayment](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_ExecutePayment) using the PaymentMethodId of the payment method on which the recurring payment is enabled for your account. You add the RecurringModel to the ExecutePayment request.

```json Request
{
    "PaymentMethodId": 2,
    "invoiceValue": 20,
    "RecurringModel": {
        "RecurringType": "Daily",
        "IntervalDays": null,
        "Iteration": 1,
        "RetryCount": 5
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Invoice Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": 4283360,
        "IsDirectPayment": false,
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/Checkout?invoiceKey=050712180428336061-bfe90261&paymentGatewayId=22",
        "CustomerReference": null,
        "UserDefinedField": null,
        "RecurringId": "RECUR121809320"
    }
}
```

##### 2. Redirect the customer to the PaymentUrl

You will redirect your customer to the PaymentUrl you get from ExecutePayment response. The customer will enter their card details on the page, then go to the OTP page and then they are redirected to your CallBackUrl/ErrorUrl.

## GetRecurringPayment

*`https://docs.myfatoorah.com/docs/getrecurringpayment` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "GetRecurringPayment" endpoint is a GET request. It is used to get all recurring payments created in your account.

The endpoint on Swagger is: [Payment\_GetRecurringPayment](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_GetRecurringPayment).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a GET request without any parameters.

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field       | Type                                                             | Description |
| :------------------- | :--------------------------------------------------------------- | :---------- |
| **RecurringPayment** | array of [RecurringPaymentModel](#recurringpaymentmodel) objects |             |

#### RecurringPaymentModel

| Response Field | Type | Description |
|---|---|---|
| **RecurringId** | string | A unique number for each recurring payment. It's recommended to save this ID in your system with your customer profile so that, you can keep track of all payments done against that customer. Moreover, you will be able to cancel it when needed later on. |
| **RecurringStatus** | string | * \*ACTIVE:\*\* The recurring payment is being processed normally but its [iterations](https://docs.myfatoorah.com/docs/execute-payment#recurringmodel) have not been completed yet. * \*UNCOMPLETED:\*\* MyFatoorah tried to withdraw the recurring value but the payment has failed and also [Retry Counts](https://docs.myfatoorah.com/docs/execute-payment#recurringmodel) have been executed and produced failed payments. * \*COMPLETED:\*\* The recurring payment has been executed successfully with all its [iterations](https://docs.myfatoorah.com/docs/execute-payment#recurringmodel) and will not be executed again. * \*CANCELED:\*\*: The recurring status is changed to canceled when you use[ CancelRecurringPayment](https://docs.myfatoorah.com/docs/cancelrecurringpayment) endpoint to stop a recurring payment from being executed. * \*DRAFT:\*\*The recurring status is initially set as draft. When the first payment (first invoice before recurring) is done the status changes to active. |
| **CreationDate** | string |  |
| **RecurringValue** | number | The value to be paid by your customer. |
| **RecurringType** | string | Recurring type you set in the request while creating the recurring payment. Possible values: (Custom-Daily-Weekly-Monthly) |
| **IntervalDays** | integer | Valid when recurring type is set to "custom" |
| **ExecutedTimes** | integer | How many times the recurring payment has been already executed. |
| **LastPayDate** | string | Last date the recurring payment was executed. |
| **NextPayDate** | string | Next date the recurring payment will be executed. |
| **IsActive** | boolean | Whether the recurring status is active or not |
| **RecurringInvoices** | array of [RecurringInvoicesModel](#recurringinvoicesmodel) objects | The list of invoices related to this recurring payment. |

#### RecurringInvoicesModel

| Response Field        | Type    | Description |
| :-------------------- | :------ | :---------- |
| **InvoiceId**         | integer |             |
| **CustomerReference** | string  |             |
| **CustomerName**      | string  |             |
| **CustomerMobile**    | string  |             |
| **CreatedDate**       | string  |             |
| **InvoiceStatus**     | string  |             |

***

#### **Sample Messages**

```json Request
/v2/GetRecurringPayment
```
```json Response
{
  "IsSuccess": true,
  "Message": "",
  "ValidationErrors": null,
  "Data": {
    "RecurringPayment": [
      {
        "RecurringId": "RECUR2136",
        "RecurringStatus": "Canceled",
        "CreationDate": "2021-04-13T11:57:51.733",
        "RecurringValue": 50,
        "RecurringType": "Custom",
        "IntervalDays": 1,
        "ExecutedTimes": 0,
        "LastPayDate": "2021-04-13T11:57:51.733",
        "NextPayDate": "2021-04-14T00:00:00",
        "IsActive": false,
        "RecurringInvoices": null
      },
      {
        "RecurringId": "RECUR2171",
        "RecurringStatus": "Active",
        "CreationDate": "2021-04-22T15:17:56.38",
        "RecurringValue": 50,
        "RecurringType": "Custom",
        "IntervalDays": 180,
        "ExecutedTimes": 0,
        "LastPayDate": "2021-04-22T15:17:56.38",
        "NextPayDate": "2021-10-19T00:00:00",
        "IsActive": true,
        "RecurringInvoices": null
      },
      {
        "RecurringId": "RECUR2137",
        "RecurringStatus": "Uncompleted",
        "CreationDate": "2021-04-13T12:06:41.067",
        "RecurringValue": 50,
        "RecurringType": "Custom",
        "IntervalDays": 1,
        "ExecutedTimes": 2,
        "LastPayDate": "2021-04-15T04:02:45.717",
        "NextPayDate": "2021-04-16T00:00:00",
        "IsActive": false,
        "RecurringInvoices": [
          {
            "InvoiceId": 614309,
            "CustomerReference": null,
            "CustomerName": "fname lname",
            "CustomerMobile": "+965",
            "CreatedDate": "2021-04-14T04:03:31.277",
            "InvoiceStatus": "Paid"
          },
          {
            "InvoiceId": 615024,
            "CustomerReference": null,
            "CustomerName": "fname lname",
            "CustomerMobile": "+965",
            "CreatedDate": "2021-04-15T04:02:43.077",
            "InvoiceStatus": "Paid"
          }
        ]
      },
      ...
    ]
  }
}
```

***

## CancelRecurringPayment

*`https://docs.myfatoorah.com/docs/cancelrecurringpayment` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "CancelRecurringPayment" endpoint is a POST request. It is used to cancel a recurring payment.

The endpoint on Swagger is: [Payment\_CancelRecurringPayment](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_CancelRecurringPayment).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following query string parameter:

| Input Parameter | Type | Description |
|---|---|---|
| **recurringId** | string |  |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get,  the result of your request is a boolean in the  **Data** parameter.

***

#### **Sample Messages**

```json Request
/v2/CancelRecurringPayment?recurringId=RECUR2175
```
```json Response
{
   "IsSuccess":true,
   "Message":"Canceled successfully",
   "ValidationErrors":null,
   "Data":true
}
```

***

## ResumeRecurringPayment

*`https://docs.myfatoorah.com/docs/resumerecurringpayment` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "ResumeRecurringPayment" endpoint is a POST request. It is used to manually retry a failed recurring payment created by the vendor account. The Request is used only with the uncompleted recurring payment which means that the recurring status is inactive.

The endpoint on Swagger is: [Payment\_ResumeRecurringPayment](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_ResumeRecurringPayment).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following query string parameter:

| Input Parameter | Type |  |
|---|---|---|
| **recurringId** | string |  |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get,  the result of your request is a boolean in the  **Data** parameter.

***

#### **Sample Messages**

```json Request
/v2/ResumeRecurringPayment?recurringId=RECUR2175
```
```json Response Success
{
  "IsSuccess": true,
  "Message": "Recurring Resumed successfully",
  "ValidationErrors": null,
  "Data": true
}
```
```json Response Failure
{
  "IsSuccess": false,
  "Message": "Invalid data",
  "ValidationErrors": [
    {
      "Name": "RecurringId",
      "Error": "This recurring cannot be resumed as it is active or the status is not Uncompleted"
    }
  ],
  "Data": false
}
```

***

## Sample Code

*`https://docs.myfatoorah.com/docs/recurring-payment-sample-code` — updated 2026-02-16*

> Recurring

#### **Overview**

In this section, we provide sample codes for:

* [Recurring Payment](#recurring-payment)
* [Cancel Recurring](#cancel-recurring)
* [Get Recurring Payments](#get-recurring-payments)

***

#### **Recurring Payment**

```php
<?php

/* For simplicity check our PHP SDK library here https://myfatoorah.readme.io/php-library */

//PHP Notice:  To enable MyFatoorah auto-update, kindly give the write/read permissions to the library folder
//use zip file
include 'myfatoorah-library-2.2/MyfatoorahLoader.php';
include 'myfatoorah-library-2.2/MyfatoorahLibrary.php';

//use composer
//require 'vendor/autoload.php';
//use MyFatoorah\Library\MyFatoorah;
//use MyFatoorah\Library\API\Payment\MyFatoorahPayment;

/* --------------------------- Configurations ------------------------------- */
//Test
$mfConfig = [
    /**
     * API Token Key (string)
     * Accepted value:
     * Live Token: https://myfatoorah.readme.io/docs/live-token
     * Test Token: https://myfatoorah.readme.io/docs/test-token
     */
    'apiKey'      => '',
    /*
     * Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'countryCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest'      => true,
];

/* --------------------------- InitiatePayment Endpoint --------------------- */
$invoiceValue       = 50;
$displayCurrencyIso = 'KWD';

//------------- Post Fields -------------------------
//Check https://docs.myfatoorah.com/docs/initiate-payment#request-model
//------------- Call the Endpoint -------------------------
try {
    $mfObj          = new MyFatoorahPayment($mfConfig);
    $paymentMethods = $mfObj->initiatePayment($invoiceValue, $displayCurrencyIso);
} catch (Exception $ex) {
    echo $ex->getMessage();
    die;
}


//You can save $paymentMethods information in database to be used later
$paymentMethodId = 20;
//foreach ($paymentMethods as $pm) {
//    if ($pm->PaymentMethodEn == 'Visa/Master Direct 3DS Flow' && $pm->IsDirectPayment) {
//        $paymentMethodId = $pm->PaymentMethodId;
//        break;
//    }
//}

/* --------------------------- ExecutePayment Endpoint ---------------------- */

//Fill customer address array
/* $customerAddress = array(
  'Block'               => 'Blk #', //optional
  'Street'              => 'Str', //optional
  'HouseBuildingNo'     => 'Bldng #', //optional
  'Address'             => 'Addr', //optional
  'AddressInstructions' => 'More Address Instructions', //optional
  ); */

//Fill invoice item array
/* $invoiceItems[] = [
  'ItemName'  => 'Item Name', //ISBAN, or SKU
  'Quantity'  => '2', //Item's quantity
  'UnitPrice' => '25', //Price per item
  ]; */

//Fill suppliers array
/* $suppliers = [
  [
  'SupplierCode'  => 1,
  'InvoiceShare'  => '2',
  'ProposedShare' => null,
  ]
  ]; */

//Parse the phone string
$phone = MyFatoorah::getPhone('+965 123456789');

//------------- Post Fields -------------------------
//Check https://docs.myfatoorah.com/docs/execute-payment#request-model
$postFields = [
    //Fill required data
    'InvoiceValue'    => $invoiceValue,
    'PaymentMethodId' => $paymentMethodId,
    'RecurringModel'  => [
        'RecurringType' => 'Custom',
        'IntervalDays'  => 180,
        'Iteration'     => 1
    ],
        //Fill optional data
        //'CustomerName'       => 'fname lname',
        //'DisplayCurrencyIso' => $displayCurrencyIso,
        //'MobileCountryCode'  => $phone[0],
        //'CustomerMobile'     => $phone[1],
        //'CustomerEmail'      => 'email@example.com',
        //'CallBackUrl'        => 'https://example.com/callback.php',
        //'ErrorUrl'           => 'https://example.com/callback.php', //or 'https://example.com/error.php' 
        //'Language'           => 'en', //or 'ar'
        //'CustomerReference'  => 'orderId',
        //'CustomerCivilId'    => 'CivilId',
        //'UserDefinedField'   => 'This could be string, number, or array',
        //'ExpiryDate'         => '', //The Invoice expires after 3 days by default. Use 'Y-m-d\TH:i:s' format in the 'Asia/Kuwait' time zone.
        //'CustomerAddress'    => $customerAddress,
        //'InvoiceItems'       => $invoiceItems,
        //'Suppliers'          => $suppliers,
];

//------------- Call the Endpoint -------------------------
try {
    $mfObj = new MyFatoorahPayment($mfConfig);
    $data  = $mfObj->executePayment($postFields);

    //You can save payment data in database as per your needs
    $invoiceId   = $data->InvoiceId;
    $paymentLink = $data->PaymentURL;

    $recurringId = $data->RecurringId;
} catch (Exception $ex) {
    echo $ex->getMessage();
    die;
}

/* --------------------------- DirectPayment Endpoint ----------------------- */
//------------- Post Fields -------------------------
$cardInfo = [
    'PaymentType' => 'card',
    'Bypass3DS'   => false,
    'Card'        => [
        'Number'         => '5123450000000008',
        'ExpiryMonth'    => '05',
        'ExpiryYear'     => '21',
        'SecurityCode'   => '100',
        'CardHolderName' => 'fname lname'
    ]
];

//------------- Call the Endpoint -------------------------
try {
    $mfObj = new MyFatoorah($mfConfig);
    $json  = $mfObj->callAPI($paymentLink, $cardInfo);

    //You can save payment data in database as per your needs
    $paymentId = $json->Data->PaymentId;
    $otpLink   = $json->Data->PaymentURL;

    //Display the result to your customer
    //Redirect your customer to complete the payment process
    echo '<h3><u>Summary:</u></h3>';
    echo 'Recurring Id: <b>' . $recurringId . '</b>.<br>';
    echo "To pay the invoice ID <b>$invoiceId</b> and with payment ID: <b>$paymentId</b>, click on:<br>";
    echo "<a href='$otpLink' target='_blank'>$otpLink</a><br><br>";

    echo '<h3><u>DirectPayment Response Object:</u></h3><pre>';
    print_r($json);
    echo '</pre>';

    echo '<h3><u>ExecutePayment Response Data:</u></h3><pre>';
    print_r($data);
    echo '</pre>';

    echo '<h3><u>InitiatePayment Response Data:</u></h3><pre>';
    print_r($paymentMethods);
    echo '</pre>';
} catch (Exception $ex) {
    echo $ex->getMessage();
    die;
}

```
```csharp
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using System;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Threading.Tasks;

namespace RecurringPayment
{
    class Program
    {
        // You can get test token from this page  https://myfatoorah.readme.io/docs/test-token
        static string token = "";
        static string baseURL = "https://apitest.myfatoorah.com";

        static async Task Main(string[] args)
        {
            // Execute Payment
            var executeResponse = await ExecutePaymentWithRecurring().ConfigureAwait(false);
            Console.WriteLine("Execute Payment with recurring Response :");
            Console.WriteLine(executeResponse);
        }
        public static async Task<string> ExecutePaymentWithRecurring()
        {
            var executePaymentRequest = new
            {
                //required fields
                PaymentMethodId = "20",// get this Id from IntiatePayment where isDirectPayment is true
                InvoiceValue = 100,
                CallBackUrl = "https://example.com/callback",
                ErrorUrl = "https://example.com/error",
                //optional fields 
                CustomerName = "Customer Name",
                DisplayCurrencyIso = "KWD",
                MobileCountryCode = "965",
                CustomerMobile = "12345678",
                CustomerEmail = "email@example.com",
                Language = "En",
                CustomerReference = "",
                CustomerCivilId = "",
                UserDefinedField = "",
                ExpiryDate = DateTime.Now.AddYears(1),
                // recurring details
                RecurringModel = new
                {
                    RecurringType = "Custom",
                    IntervalDays = 30,
                    Iteration = 2
                }

            };
            var executeRequestJSON = JsonConvert.SerializeObject(executePaymentRequest);
            return await PerformRequest(executeRequestJSON, endPoint: "ExecutePayment").ConfigureAwait(false);
        }
        public static async Task<string> PerformRequest(string requestJSON, string url = "", string endPoint = "")
        {
            if (string.IsNullOrEmpty(url))
                url = baseURL + $"/v2/{endPoint}";

            HttpClient client = new HttpClient();
            client.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
            client.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", token);
            var httpContent = new StringContent(requestJSON, System.Text.Encoding.UTF8, "application/json");
            var responseMessage = await client.PostAsync(url, httpContent).ConfigureAwait(false);
            string response = string.Empty;
            if (!responseMessage.IsSuccessStatusCode)
            {
                response = JsonConvert.SerializeObject(new
                {
                    IsSuccess = false,
                    Message = responseMessage.StatusCode.ToString()
                });
            }
            else
            {
                response = await responseMessage.Content.ReadAsStringAsync();
            }

            return response;
        }
    }
}
```

***

#### **Cancel Recurring**

```php
    <?php

/* For simplicity check our PHP SDK library here https://myfatoorah.readme.io/php-library */

//PHP Notice:  To enable MyFatoorah auto-update, kindly give the write/read permissions to the library folder
//use zip file
include 'myfatoorah-library-2.2/MyfatoorahLoader.php';
include 'myfatoorah-library-2.2/MyfatoorahLibrary.php';

//use composer
//require 'vendor/autoload.php';
//use MyFatoorah\Library\MyFatoorah;

/* --------------------------- Configurations ------------------------------- */
//Test
$mfConfig = [
    /**
     * API Token Key (string)
     * Accepted value:
     * Live Token: https://myfatoorah.readme.io/docs/live-token
     * Test Token: https://myfatoorah.readme.io/docs/test-token
     */
    'apiKey'      => '',
    /*
     * Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'countryCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest'      => true,
];

/* --------------------------- CancelRecurringPayment Endpoint -------------- */

//RecurringId should be saved previosly in a secure place to be used here in cancel request
$recurringId = 'RECUR27079';

//------------- Call the Endpoint -------------------------
try {
    $mfObj  = new MyFatoorah($mfConfig);
    $apiURL = $mfObj->getApiUrl();
    $obj    = $mfObj->callAPI("$apiURL/v2/CancelRecurringPayment?recurringId=$recurringId", '');

    //Display the result to your customer
    echo '<h3><u>CancelRecurringPayment Response Object:</u></h3><pre>';
    print_r($obj);
    echo '</pre>';
} catch (Exception $ex) {
    echo $ex->getMessage();
    die;
}
```
```csharp
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using System;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Threading.Tasks;

namespace CancelRecurringPayment
{
    class Program
    {
        // You can get test token from this page  https://myfatoorah.readme.io/docs/test-token
        static string token = "";
        static string baseURL = "https://apitest.myfatoorah.com";
        static async Task Main(string[] args)
        {
            string recurringId = "{RecurringID";//replace with valid recurring Id like RECUR2188
            Console.WriteLine($"Cancel  For Recurring Id : {recurringId} ");

            var cancelRecurringResponse = await CancelRecurringPayment(recurringId).ConfigureAwait(false);
            Console.WriteLine("Cancel Recurring Response :");
            Console.WriteLine(cancelRecurringResponse);

            Console.ReadLine();
        }
        public static async Task<string> CancelRecurringPayment(string recurringId)
        {
            string url = baseURL + $"/v2/CancelRecurringPayment?recurringId={recurringId}";

            HttpClient client = new HttpClient();
            client.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
            client.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", token);
            var responseMessage = await client.PostAsync(url, null).ConfigureAwait(false);
            string response = string.Empty;
            if (!responseMessage.IsSuccessStatusCode)
            {
                response = JsonConvert.SerializeObject(new
                {
                    IsSuccess = false,
                    Message = responseMessage.StatusCode.ToString()
                });
            }
            else
            {
                response = await responseMessage.Content.ReadAsStringAsync();
            }

            return response;
        }
    }

}
```

***

#### **Get Recurring Payments**

```php
<?php

/* For simplicity check our PHP SDK library here https://myfatoorah.readme.io/php-library */

//PHP Notice:  To enable MyFatoorah auto-update, kindly give the write/read permissions to the library folder
//use zip file
include 'myfatoorah-library-2.2/MyfatoorahLoader.php';
include 'myfatoorah-library-2.2/MyfatoorahLibrary.php';

//use composer
//require 'vendor/autoload.php';
//use MyFatoorah\Library\MyFatoorah;

/* --------------------------- Configurations ------------------------------- */
//Test
$mfConfig = [
    /**
     * API Token Key (string)
     * Accepted value:
     * Live Token: https://myfatoorah.readme.io/docs/live-token
     * Test Token: https://myfatoorah.readme.io/docs/test-token
     */
    'apiKey'      => '',
    /*
     * Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'countryCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest'      => true,
];

/* --------------------------- GetRecurringPayment Endpoint ----------------- */

//------------- Call the Endpoint -------------------------
try {
    $mfObj  = new MyFatoorah($mfConfig);
    $apiURL = $mfObj->getApiUrl();
    $obj    = $mfObj->callAPI("$apiURL/v2/GetRecurringPayment");

    //Display the result to your customer
    echo '<h3><u>GetRecurringPayment Response Object:</u></h3><pre>';
    print_r($obj);
    echo '</pre>';
} catch (Exception $ex) {
    echo $ex->getMessage();
    die;
}
```
```csharp
using Newtonsoft.Json;
using System;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Threading.Tasks;

namespace RecurringPayments
{
    class Program
    {
        // You can get test token from this page  https://myfatoorah.readme.io/docs/test-token
        static string token = "";
        static string baseURL = "https://apitest.myfatoorah.com";
        static async Task Main(string[] args)
        {
            var GetRecurringPaymentsResponse = await GetRecurringPayments().ConfigureAwait(false);
            Console.WriteLine("GetRecurringPaymentsResponse :");
            Console.WriteLine(GetRecurringPaymentsResponse);
            Console.ReadLine();
        }
        public static async Task<string> GetRecurringPayments()
        {
            string url = baseURL + $"/v2/GetRecurringPayment";

            HttpClient client = new HttpClient();
            client.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
            client.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", token);
            var responseMessage = await client.GetAsync(url).ConfigureAwait(false);
            string response = string.Empty;
            if (!responseMessage.IsSuccessStatusCode)
            {
                response = JsonConvert.SerializeObject(new
                {
                    IsSuccess = false,
                    Message = responseMessage.StatusCode.ToString()
                });
            }
            else
            {
                response = await responseMessage.Content.ReadAsStringAsync();
            }

            return response;
        }


    }
}
```

***

## Vendor-Managed Recurring

*`https://docs.myfatoorah.com/docs/v3-vendor-managed-recurring` — updated 2026-02-16*

> Manage the recurring deductions from your side

#### **Description:**

This feature allows you to fully manage recurring payments independently, without relying on MyFatoorah's recurring model. You have complete control over

* **the frequency of deductions,**
* **the timing of each deduction,**
* **the number of deductions, and**
* **the amounts to be deducted.**

#### **Requirements:**

You need to have for your account the following features to be able to manage the recurring at your end:

* **Tokenization:** This feature enables you to tokenize the card information that the customer entered on his first payment. You will then use the token to make subsequent deductions. For more information, please check the [Tokenized Embedded Payment](https://docs.myfatoorah.com/docs/v3-token-payments).
* **FastPay OR Bypass3DS:**  **FastPay** enables you to complete the payment without going to the 3DS page when making a payment from a tokenized/saved card that is 3DS verified before. **Bypass3DS** gives you control on each payment on whether to go to the 3DS page or not.

> 🚧 FastPay
>
> This feature will not take you to the OTP page if and only if the saved/tokenized card has been 3DS verfied before. For tokenized cards, you can know this from the value of the Is3DSVerified parameter returned in the response of GET /v3/customers.

* **BypassCvv:** This feature enables you to make the payment without sending the CVV for the tokenized card from which you will deduct the amount.

#### **Steps:**

##### **1. Tokenize the card from the first payment**

You will tokenize the card information that the customer enters by following the steps in the [Tokenized Embedded Payment Section](https://docs.myfatoorah.com/docs/v3-token-payments#/)

```json Request Example
{
    "PaymentMode": "COMPLETE_PAYMENT",
    "Order": {
        "Amount": 10
    },
    "SaveCardOptions": {
        "SaveToken": true,
        "ShowSavedCardsInCardView": true,
        "RetrieveSavedTokens": true
    },
    "Customer": {
        "Reference": "#1234"
    },
    "IntegrationUrls": {
        "Redirection": "https://your-website.com/payment-callback"
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "SessionId": "KWT-892bd72a-bb85-4756-b68e-85b46ab8efd6",
        "SessionExpiry": "2025-10-26T16:48:15.9725428Z",
        "EncryptionKey": "4Iy7JAzgjCjdtrhOINSFP5kEp08w/6s1QWdVMhR+IH0=",
        "OperationType": "PAY",
        "Order": {
            "Amount": 10.0,
            "Currency": "KWD",
            "ExternalIdentifier": null
        },
        "Customer": {
            "Reference": "#1234",
            "Cards": []
        }
    }
}
```

##### **2. Call GET/v3/customers using the same Customer Reference**

You need to call the GET /v3/customers API ([Get Customers](https://docs.myfatoorah.com/update/reference/get-customer-details)) using the same Customer Reference to get the tokenized cards against a specific reference.

```json Response
{
    "IsSuccess": true,
    "Message": "Initiated Successfully!",
    "ValidationErrors": null,
    "Data": {
        "Reference": "#1234",
        "Cards": [
            {
                "Is3DSVerified": true,
                "Token": "TKN-0fb06aac-634d-418a-bf78-953202b67b53",
                "Number": "512345xxxxxx0008",
                "Brand": "Master",
                "TokenType": "CARD"
            }
        ]
    }
}
```

##### **3. Make subsequent payments with the token**

**Endpoint:** `POST /v3/payments` ([Create Payment](https://docs.myfatoorah.com/reference/create-payment))

```json Request Example
{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 10
    },
    "SourceOfFund": {
        "Token": "TKN-0fb06aac-634d-418a-bf78-953202b67b53"
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": "6389662",
        "PaymentId": "07076389662322472373",
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/Result?paymentId=07076389662322472373",
        "PaymentCompleted": true,
        "TransactionDetails": {
            "Invoice": {
                "Id": "6389662",
                "Status": "PAID",
                "Reference": "2025060928",
                "CreationDate": "2025-12-24T16:23:49.5894833Z",
                "ExpirationDate": "2026-06-22T16:23:49.5894833Z",
                "ExternalIdentifier": null,
                "UserDefinedField": "",
                "MetaData": null
            },
            "Transaction": {
                "Id": "104690",
                "Status": "SUCCESS",
                "PaymentMethod": "VISA/MASTER",
                "PaymentId": "07076389662322472373",
                "ReferenceId": "535816104690",
                "TrackId": "24-12-2025_3224723",
                "AuthorizationId": "104690",
                "TransactionDate": "2025-12-24T16:23:50.7016503Z",
                "ECI": "",
                "IP": {
                    "Address": "",
                    "Country": ""
                },
                "Error": {
                    "Code": "",
                    "Message": ""
                },
                "Card": {
                    "NameOnCard": "das",
                    "Number": "512345xxxxxx0008",
                    "Token": "TKN-0fb06aac-634d-418a-bf78-953202b67b53",
                    "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                    "ExpiryMonth": "12",
                    "ExpiryYear": "36",
                    "Brand": "Mastercard",
                    "Issuer": "Test Bank",
                    "IssuerCountry": "KWT",
                    "FundingMethod": "credit"
                }
            },
            "Customer": {
                "Reference": "",
                "Name": "Anonymous",
                "Mobile": "+965",
                "Email": ""
            },
            "Amount": {
                "BaseCurrency": "KWD",
                "ValueInBaseCurrency": "20",
                "ServiceCharge": "0.4",
                "ServiceChargeVAT": "0.06",
                "ReceivableAmount": "19.54",
                "DisplayCurrency": "KWD",
                "ValueInDisplayCurrency": "20",
                "PayCurrency": "KWD",
                "ValueInPayCurrency": "20"
            },
            "Suppliers": []
        }
    }
}
```

> 📘 Old Integration (V2)
>
> In case you are using the integration for v2, you can find the documentation here: <https://docs.myfatoorah.com/docs/vendor-managed-recurring>

## Vendor-Managed Recurring

*`https://docs.myfatoorah.com/docs/vendor-managed-recurring` — updated 2026-02-16*

Manage the recurring deductions from your side

#### Description:

This feature allows you to fully manage recurring payments independently, without relying on MyFatoorah's recurring model. You have complete control over

* **the frequency of deductions,**
* **the timing of each deduction,**
* **the number of deductions, and**
* **the amounts to be deducted.**

#### Requirements:

You need to have for your account the following features to be able to manage the recurring at your end:

* **Tokenization:** This feature enables you to tokenize the card information that the customer entered on his first payment. You will then use the token to make subsequent deductions. For more information, please check the [Tokenized Embedded Payment](https://docs.myfatoorah.com/docs/tokenized-embedded-payments)
* **FastPay:** This feature enables you to complete the payment without going to the 3DS page when making a payment from a tokenized/saved card.

> 🚧 FastPay
>
> This feature will not take you to the OTP page if and only if the saved/tokenized card has been 3DS verfied before. For tokenized cards, you can know this from the value of the Is3DSVerified parameter returned in the response of InitiateSession.

* **BypassCvv:** This feature enables you to make the payment without sending the CVV for the tokenized card from which you will deduct the amount.

#### Steps:

##### 1. Tokenize the card from the first payment

You will tokenize the card information that the customer enters by following the steps in the [Tokenized Embedded Payment Section](https://docs.myfatoorah.com/docs/tokenized-embedded-payments)

```json Request
{
    "CustomerIdentifier": "vendor-managed-recurring",
    "SaveToken": true,
}
```

##### 2. Call InitiateSession to get the token of the cards

You have to call InitiateSession using the same **CustomerIdentifier** on which you have the card tokenized.

```json Request
{
    "CustomerIdentifier": "vendor-managed-recurring",
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Initiated Successfully!",
    "ValidationErrors": null,
    "Data": {
        "SessionId": "2277328f-029a-4d06-9b87-df1e0c7a4071",
        "CountryCode": "KWT",
        "CustomerTokens": [
            {
                "Token": "Token0505121804295351",
                "CardNumber": "545454xxxxxx5454",
                "CardBrand": "Master",
                "Is3DSVerified": true
            }
        ]
    }
}
```

##### 3. Call UpdateSession using the Token

You will now call UpdateSession using the Token that is returned in the InitiateSession response. The token must have the value of **"Is3DSVerified": true** to not go to the 3DS page by using the FastPay feature.

```json Request
{
    "SessionId": "2277328f-029a-4d06-9b87-df1e0c7a4071",
    "Token": "Token0505121804295351",
    "TokenType": "mftoken",
}
```
```json Response
{
    "IsSuccess": true,
    "Message": null,
    "ValidationErrors": null,
    "Data": {
        "SessionId": "2277328f-029a-4d06-9b87-df1e0c7a4071",
        "CountryCode": "KWT"
    }
}
```

##### 4. Call ExecutePayment endpoint

You will call ExecutePayment endpoint using the SessionId and determine the amount that you want to deduct. You will execute steps 2, 3, and 4 when you want to make a deduction from the previously tokenized card.

```json Request
{
    "SessionId": "2277328f-029a-4d06-9b87-df1e0c7a4071",
    "invoiceValue": 20,
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Invoice Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": 4283765,
        "IsDirectPayment": false,
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/Result?paymentId=07074283765216314472",
        "CustomerReference": null,
        "UserDefinedField": null,
        "RecurringId": ""
    }
}
```

## Merchant Initiated Transaction (MIT)

*`https://docs.myfatoorah.com/docs/merchant-initiated-transaction` — updated 2026-02-16*

#### Overview

A merchant-initiated transaction (MIT) allows you, the merchant, to make payments from your customer’s account without them having to take any further action.\
MIT is used **after the customer has previously completed a successful payment** and authorized the merchant to store their card details and use them for future charges.

#### Integration Availability

- [Embedded Integration](https://docs.myfatoorah.com/docs/v3-vendor-managed-recurring) — Please follow this guide to know how to implement the flow using the Embedded Integration.

- [Direct Integration](https://docs.myfatoorah.com/docs/v3-direct-tokenization) — Please follow this guide to know how to implement the flow using the Direct Integration (It requires being PCI certified).


## Bypass 3DS

*`https://docs.myfatoorah.com/docs/bypass3ds` — updated 2026-02-16*

#### Introduction

**3D Secure (3DS)** is an additional security layer for online card transactions designed to protect merchants and cardholders from fraudulent activities. When enabled, the cardholder is prompted to authenticate the payment through their card issuer, commonly done via:

* **One-Time Password (OTP)**
* **Biometric verification (Fingerprint/Face ID)**
* **Bank Mobile App / Token Authentication**

This verification happens during the final stage of the payment processing.

> 🚧 **Default Behavior**
>
> By default, **3DS is enabled for all payments** on MyFatoorah.
> If you want to **control enabling/disabling 3DS per payment**, you must check with your [**Account Manager**](https://www.myfatoorah.com/en/contact-us/)

> 📘 **Integration Availability**
>
> Controlling 3DS behavior requires using the [Embedded Integration](https://docs.myfatoorah.com/update/docs/embedded-payment-v3/) or [Direct Payment](https://docs.myfatoorah.com/docs/card-direct-integration).

> 🚧 **Approval Needed**
>
> Kindly contact your account manager to check the availability for enabling Bypass3DS feature.

#### How It Works

To control 3DS behavior, include the **ThreeDS object** in your [**Session Initiation request**](https://docs.myfatoorah.com/docs/embedded-payment-v3#step-1-create-payment-session) or **[Create Payment](https://docs.myfatoorah.com/reference/create-payment)**:

```json
"ThreeDS": {
       "Enabled": true
 }
```

**Behavior Based on 3DS value:**

| Value              | Result                                                                                   |
| ------------------ | ---------------------------------------------------------------------------------------- |
| `"Enabled": true`  | The user will complete the **OTP / authentication step** before the payment processing.  |
| `"Enabled": false` | No authentication step. You will receive payment details directly after the transaction. |

> 📘 **Fastpay**
>
> Fastpay is different from Bypass3DS where Fastpay will always bypass the OTP page for a saved or tokenized card that is 3DSVerified before. **Fastpay overrides bypass3DS.**

## Reporting

*`https://docs.myfatoorah.com/docs/reporting` — updated 2026-02-16*

#### **Introduction**

Reporting endpoints are made to make retrieving the data from MyFatoorah easier. It is a better and more convenient way to access your data in the MyFatoorah system and update your system accordingly.

The currently available endpoints are:

* GetInvoicesByDepositReference

## GetDepositedInvoices

*`https://docs.myfatoorah.com/docs/getinvoicesbydepositreference` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "GetDepositedInvoices" endpoint is a POST request. It is used to get the list of invoices included in a deposit.

The endpoint on Swagger: [Reports\_GetDepositedInvoices](https://apitest.myfatoorah.com/swagger/ui/index#!/Reports/Reports_GetInvoicesByDepositReference).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following parameters:

#### Required Fields

| Input Parameter      | Type   | Description                                                   |
| :------------------- | :----- | :------------------------------------------------------------ |
| **DepositReference** | string | the Deposit Reference you would like to receive its invoices. |

#### Optional Fields

| Input Parameter | Type | Description |
|---|---|---|
| **Type** | string | * \*Vendor\*\*: The deposit reference belongs to the vendor. (Default) * \*Supplier\*\*: The deposit reference belongs to a supplier. |
| **SupplierCode** | integer | The supplier code to which the deposit reference belongs. |

> 🚧 Type & SupplierCode
>
> If you don't send Type & SupplierCode in the request to the endpoint, MyFatoorah will consider that the DepositReference is of a vendor.
>
> In case the DepositReference belongs to a vendor. You can only send the DepositReference or you can add the Type and specify its value as a **Vendor**

> 📘 SupplierCode
>
> **SupplierCode** is mandatory to send in case the Type value is a **supplier**

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.\
**The data model is a list of InvoiceDetailsModel which is explained below.**

#### InvoiceDetailsModel

| Response Field          | Type   | Description                                |
| :---------------------- | :----- | :----------------------------------------- |
| **InvoiceId**           | number | InvoiceId                                  |
| **PaymentGateway**      | string |                                            |
| **PaymentId**           | string |                                            |
| **InvoiceReference**    | string |                                            |
| **CustomerReference**   | string |                                            |
| **CreatedDate**         | date   |                                            |
| **InvoiceValue**        | string |                                            |
| **PaidCurrencyValue**   | string |                                            |
| **PaidCurrency**        | string |                                            |
| **InvoiceDisplayValue** | string |                                            |
| **DueValue**            | string | The total amount of the invoice            |
| **PaidDate**            | date   |                                            |
| **TotalServiceCharge**  | string | Transaction service charge                 |
| **DueDeposit**          | string | The amount received by the vendor/supplier |
| **DepositReference**    | string |                                            |
| **DepositDate**         | date   |                                            |

***

#### **Sample Message**

```json Request for Vendor
{
  "DepositReference": "2023000011",
  "Type": "Vendor"
}
```
```json Response for Vendor
{
  "IsSuccess": true,
  "Message": null,
  "ValidationErrors": null,
  "Data": [
    {
      "InvoiceId": 2608271,
      "PaymentGateway": "VISA/MASTER",
      "PaymentId": "07072608271167936573",
      "InvoiceReference": "2023004944",
      "CustomerReference": null,
      "CreatedDate": "2023-08-14T09:13:50.197",
      "InvoiceValue": "232.500",
      "PaidCurrencyValue": "232.500",
      "PaidCurrency": "KD",
      "InvoiceDisplayValue": "232.500",
      "DueValue": "232.500",
      "PaidDate": "2023-08-14T09:13:52.103",
      "TotalServiceCharge": "6.975",
      "DueDeposit": "224.479",
      "DepositReference": "2023000011",
      "DepositDate": "2023-08-14T09:26:02.237"
    },
    {
      "InvoiceId": 2608272,
      "PaymentGateway": "VISA/MASTER",
      "PaymentId": "07072608272167936673",
      "InvoiceReference": "2023004945",
      "CustomerReference": null,
      "CreatedDate": "2023-08-14T09:13:52.247",
      "InvoiceValue": "232.500",
      "PaidCurrencyValue": "232.500",
      "PaidCurrency": "KD",
      "InvoiceDisplayValue": "232.500",
      "DueValue": "232.500",
      "PaidDate": "2023-08-14T09:13:53.15",
      "TotalServiceCharge": "6.975",
      "DueDeposit": "224.479",
      "DepositReference": "2023000011",
      "DepositDate": "2023-08-14T09:26:02.237"
    }
  ]
}
```
```json Request for Supplier
{
  "DepositReference": "2023000204",
  "Type": "Supplier",
  "SupplierCode": "1"
}
```
```json Response for Supplier
{
  "IsSuccess": true,
  "Message": null,
  "ValidationErrors": null,
  "Data": [
    {
      "InvoiceId": 2146266,
      "PaymentGateway": "KNET",
      "PaymentId": "100202308140512007",
      "InvoiceReference": "2023000019",
      "CustomerReference": null,
      "CreatedDate": "2023-03-22T13:30:14.36",
      "InvoiceValue": "100.000",
      "PaidCurrencyValue": "100.000",
      "PaidCurrency": "KD",
      "InvoiceDisplayValue": "100.000",
      "DueValue": "100.000",
      "PaidDate": "2023-03-22T13:30:39.723",
      "TotalServiceCharge": "2.100",
      "DueDeposit": "95.585",
      "DepositReference": "2023000204",
      "DepositDate": "2023-03-28T09:14:00"
    },
    {
      "InvoiceId": 2146271,
      "PaymentGateway": "KNET",
      "PaymentId": "100202308140564923",
      "InvoiceReference": "2023000020",
      "CustomerReference": null,
      "CreatedDate": "2023-03-22T13:32:02.293",
      "InvoiceValue": "100.000",
      "PaidCurrencyValue": "100.000",
      "PaidCurrency": "KD",
      "InvoiceDisplayValue": "100.000",
      "DueValue": "100.000",
      "PaidDate": "2023-03-22T13:32:21.433",
      "TotalServiceCharge": "2.100",
      "DueDeposit": "90.000",
      "DepositReference": "2023000204",
      "DepositDate": "2023-03-28T09:14:00"
    },
    {
      "InvoiceId": 2146276,
      "PaymentGateway": "KNET",
      "PaymentId": "100202308140631527",
      "InvoiceReference": "2023000021",
      "CustomerReference": null,
      "CreatedDate": "2023-03-22T13:34:15.47",
      "InvoiceValue": "100.000",
      "PaidCurrencyValue": "100.000",
      "PaidCurrency": "KD",
      "InvoiceDisplayValue": "100.000",
      "DueValue": "100.000",
      "PaidDate": "2023-03-22T13:34:35.75",
      "TotalServiceCharge": "2.100",
      "DueDeposit": "40.000",
      "DepositReference": "2023000204",
      "DepositDate": "2023-03-28T09:14:00"
    }
  ]
}
```

***

## GetBanks

*`https://docs.myfatoorah.com/docs/getbanks` — updated 2026-02-16*

Endpoint

#### **Overview**

The "GetBanks" endpoint is a GET request. It is used to retrieve a list that contains all the banks available in your country of registration and their corresponding BankId.

The endpoint on Swagger is [GetBanks](https://apitest.myfatoorah.com/swagger/ui/index#!/List/List_GetBanksList).

> 📘 BankId
>
> You will use the BankId to enter the bank information when creating new suppliers using the APIs.

***

#### **Request Model**

The request is a GET request without any parameters.

***

#### **Response Model**

The response is an array of the available banks and their corresponding BankId as follows:

| Response Field | Type    | Description               |
| :------------- | :------ | :------------------------ |
| **Value**      | integer | The BankId for that bank. |
| **Text**       | string  | The name of the bank      |

***

#### **Sample Message**

```json Request
/v2/GetBanks
```
```json Response
[
  {
    "Value": 1,
    "Text": "Kuwait - National Bank of Kuwait (NBK)"
  },
  {
    "Value": 2,
    "Text": "Kuwait - Kuwait Finance House (KFH)"
  },
  {
    "Value": 149,
    "Text": "Kuwait - Ahli United Bank"
  },
  {
    "Value": 150,
    "Text": "Kuwait - Bank of Bahrain & Kuwait (BBK)"
  },
  {
    "Value": 151,
    "Text": "Kuwait - Boubyan Bank (Boubyan)"
  },
  {
    "Value": 152,
    "Text": "Kuwait - Burgan Bank (Burgan)"
  },
  {
    "Value": 153,
    "Text": "Kuwait - Commercial Bank of Kuwait (CBK)"
  },
  {
    "Value": 154,
    "Text": "Kuwait - Doha Bank (Doha)"
  },
  {
    "Value": 155,
    "Text": "Kuwait - Gulf Bank of Kuwait (GBK)"
  },
  {
    "Value": 157,
    "Text": "Kuwait - Kuwait International Bank (KIB)"
  },
  {
    "Value": 158,
    "Text": "Kuwait - Mashreq Bank (Mashreq)"
  },
  {
    "Value": 159,
    "Text": "Kuwait - National Bank of Kuwait (NBK)"
  },
  {
    "Value": 160,
    "Text": "Kuwait - National Bank of Abu Dhabi (NBAD)"
  },
  {
    "Value": 161,
    "Text": "Kuwait - Qatar National Bank (QNB)"
  },
  {
    "Value": 162,
    "Text": "Kuwait - AL RAJHI BANK KUWAIT"
  },
  {
    "Value": 163,
    "Text": "Kuwait - Union National Bank (UNB)"
  },
  {
    "Value": 164,
    "Text": "Kuwait - Warba Bank (WARBA)"
  },
  {
    "Value": 165,
    "Text": "Kuwait - Al Ahli Bank (ABK)"
  },
  {
    "Value": 223,
    "Text": "Kuwait - QNB"
  },
  {
    "Value": 224,
    "Text": "Kuwait - MASRAF AL RAYAN"
  },
  {
    "Value": 225,
    "Text": "Kuwait - KFH - Kuwait -  GCC"
  },
  {
    "Value": 226,
    "Text": "Kuwait - AUB - Kuwait - Direct"
  },
  {
    "Value": 227,
    "Text": "Kuwait - KFH - Kuwait - Tokenization"
  },
  {
    "Value": 228,
    "Text": "Kuwait - HSBC BANK"
  },
  {
    "Value": 229,
    "Text": "Kuwait - KFH - Kuwait - BMW"
  },
  {
    "Value": 232,
    "Text": "Kuwait - KFH - Kuwait - AIRLINE (MCC: 4511)"
  },
  {
    "Value": 246,
    "Text": "Kuwait - KFH - Kuwait - WOMEN SHOPS (MCC: 5631)"
  },
  {
    "Value": 249,
    "Text": "Kuwait - AUB - Kuwait - Alturath"
  },
  {
    "Value": 250,
    "Text": "Kuwait - National Bank of Egypt"
  },
  {
    "Value": 251,
    "Text": "Kuwait - NATIONAL BANK OF OMAN"
  },
  {
    "Value": 252,
    "Text": "Kuwait - INDUSTRIAL BANK OF KUWAIT K S C"
  },
  {
    "Value": 253,
    "Text": "Kuwait - Central Bank of Kuwait"
  }
]
```

***

## Response Model

*`https://docs.myfatoorah.com/docs/response-model` — updated 2026-02-16*

All responses from the different endpoint requests will give a standard model. That response is common for all functions except for the data model which is a function-related response. Here, we are going to explain the Response Model that you will get for all requests sent to the API.

| Response Field                        | Type   | Description                                                                                                                                                                                                              |
| :------------------------------------ | :----- | :----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **IsSuccess**                         | string | "true" or "false" indicating the status of your request                                                                                                                                                                  |
| **Message**                           | string | The message response associated with the request done                                                                                                                                                                    |
| **ValidationErrors**/**FieldsErrors** | model  | A model that contains two keys "Name" and "Error". This is used to indicate the validation result for all parameters you have sent in your request. This can have one or more items based on the invalid parameter count |
| **Data**                              | model  | This model is the response data of each endpoint.                                                                                                                                                                        |

## Idempotency

*`https://docs.myfatoorah.com/docs/idempotency` — updated 2026-02-26*

#### Overview

Idempotency allows you to safely retry API requests without creating duplicate operations.
You can label an API request with an **Idempotency-Key**, and the system will cache the original request for **250 minutes**. If the same request is sent again using the same Idempotency-Key within this period, the system will return the exact same response instead of processing the request again.

This feature helps to:

* Prevent sending multiple duplicate requests.
* Avoid completing the same payment or refund more than once

#### How It Works

To use idempotency, include the following header in your API request:

```Text Header
Idempotency-Key: <unique-value>
```

The Idempotency-Key should be a unique value for each operation.\
**If a request with the same key is received again within the cache duration, the previously cached response will be returned.**

```curl POST /v3/payments (First Request)
curl --location 'https://apitest.myfatoorah.com/v3/payments' \
--header 'Idempotency-Key: req20' \
--header 'Content-Type: application/json' \
--header 'Authorization: ••••••' \
--header 'Cookie: X-Oracle-BMC-LBS-Route=56f55515c92256899687eb6a49595b2f10a5707c' \
--data '{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 800,
        "Currency": "SAR"
    },
    "SourceOfFund": {
        "Token": "TKN-ac22319e-617f-4d3b-9759-20293e833e89"
    }
}'
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": "6548530",
        "PaymentId": "07076548530334809573",
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/Result?paymentId=07076548530334809573",
        "PaymentCompleted": true,
        "TransactionDetails": {
            "Invoice": {
                "Id": "6548530",
                "Status": "PAID",
                "Reference": "2026003084",
                "CreationDate": "2026-02-26T12:23:04.6122368Z",
                "ExpirationDate": "2026-07-26T12:23:04.6122368Z",
                "ExternalIdentifier": null,
                "UserDefinedField": "",
                "MetaData": null
            },
            "Transaction": {
                "Id": "137119",
                "Status": "SUCCESS",
                "PaymentMethod": "VISA/MASTER (SAR)",
                "PaymentId": "07076548530334809573",
                "ReferenceId": "605712137119",
                "TrackId": "26-02-2026_3348095",
                "AuthorizationId": "137119",
                "TransactionDate": "2026-02-26T12:23:05.5133690Z",
                "ECI": "",
                "IP": {
                    "Address": "",
                    "Country": ""
                },
                "Error": {
                    "Code": "",
                    "Message": ""
                },
                "Card": {
                    "NameOnCard": "test",
                    "Number": "512345xxxxxx0008",
                    "Token": "TKN-ac22319e-617f-4d3b-9759-20293e833e89",
                    "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                    "ExpiryMonth": "01",
                    "ExpiryYear": "39",
                    "Brand": "Mastercard",
                    "Issuer": "Test Bank",
                    "IssuerCountry": "KWT",
                    "FundingMethod": "credit"
                }
            },
            "Customer": {
                "Name": "Anonymous",
                "Mobile": "+965",
                "Email": ""
            },
            "Amount": {
                "BaseCurrency": "KWD",
                "ValueInBaseCurrency": "64.772",
                "ServiceCharge": "0.324",
                "ServiceChargeVAT": "0.049",
                "ReceivableAmount": "64.407",
                "DisplayCurrency": "KWD",
                "ValueInDisplayCurrency": "800",
                "PayCurrency": "SAR",
                "ValueInPayCurrency": "800"
            },
            "Suppliers": []
        },
        "Card": null
    }
}
```
```curl Subsequent Request (Same Idempotency-Key)
curl --location 'https://apitest.myfatoorah.com/v3/payments' \
--header 'Idempotency-Key: req20' \
--header 'Content-Type: application/json' \
--header 'Authorization: ••••••' \
--header 'Cookie: X-Oracle-BMC-LBS-Route=56f55515c92256899687eb6a49595b2f10a5707c' \
--data '{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 1000,
        "Currency": "SAR"
    },
    "SourceOfFund": {
        "Token": "TKN-ac22319e-617f-4d3b-9759-20293e833e89"
    }
}'
```
```json Same Response Data
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": "6548530",
        "PaymentId": "07076548530334809573",
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/Result?paymentId=07076548530334809573",
        "PaymentCompleted": true,
        "TransactionDetails": {
            "Invoice": {
                "Id": "6548530",
                "Status": "PAID",
                "Reference": "2026003084",
                "CreationDate": "2026-02-26T12:23:04.6122368Z",
                "ExpirationDate": "2026-07-26T12:23:04.6122368Z",
                "ExternalIdentifier": null,
                "UserDefinedField": "",
                "MetaData": null
            },
            "Transaction": {
                "Id": "137119",
                "Status": "SUCCESS",
                "PaymentMethod": "VISA/MASTER (SAR)",
                "PaymentId": "07076548530334809573",
                "ReferenceId": "605712137119",
                "TrackId": "26-02-2026_3348095",
                "AuthorizationId": "137119",
                "TransactionDate": "2026-02-26T12:23:05.5133690Z",
                "ECI": "",
                "IP": {
                    "Address": "",
                    "Country": ""
                },
                "Error": {
                    "Code": "",
                    "Message": ""
                },
                "Card": {
                    "NameOnCard": "test",
                    "Number": "512345xxxxxx0008",
                    "Token": "TKN-ac22319e-617f-4d3b-9759-20293e833e89",
                    "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                    "ExpiryMonth": "01",
                    "ExpiryYear": "39",
                    "Brand": "Mastercard",
                    "Issuer": "Test Bank",
                    "IssuerCountry": "KWT",
                    "FundingMethod": "credit"
                }
            },
            "Customer": {
                "Name": "Anonymous",
                "Mobile": "+965",
                "Email": ""
            },
            "Amount": {
                "BaseCurrency": "KWD",
                "ValueInBaseCurrency": "64.772",
                "ServiceCharge": "0.324",
                "ServiceChargeVAT": "0.049",
                "ReceivableAmount": "64.407",
                "DisplayCurrency": "KWD",
                "ValueInDisplayCurrency": "800",
                "PayCurrency": "SAR",
                "ValueInPayCurrency": "800"
            },
            "Suppliers": []
        },
        "Card": null
    }
}
```

#### Endpoints That Support Idempotency

* [MakeRefund](https://docs.myfatoorah.com/docs/make-refund)
* [MakeSupplierRefund](https://docs.myfatoorah.com/docs/make-supplier-refund)
* [POST /v3/payments](https://docs.myfatoorah.com/reference/create-payment)
* [PUT /v3/payments](https://docs.myfatoorah.com/reference/update-payment)

> 🚧 Availability
>
> This feature is currently available only in the following countries:
>
> * Kuwait
> * Saudi Arabia
