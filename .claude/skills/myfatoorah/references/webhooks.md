# MyFatoorah — Webhooks

## Webhook

*`https://docs.myfatoorah.com/docs/webhook` — updated 2026-02-16*

#### **Introduction**

We use webhooks to notify your application when an event happens in your account. Webhooks are particularly useful for asynchronous events, like when a customer has made a payment, deposited money to your account, the refund status has changed, or the supplier account status has changed.\
You can listen to specific events by webhook, as you don’t need to inquire about these actions to get the response.

> ❗️ SSL
>
> SSL is required. Ensure that your server is configured to support HTTPS, with a valid server certificate.

***

#### **How it Works**

> 📘 Portal account
>
> Before you build Your Endpoint, you need to set up your webhook information in your portal account as described in the [Webhook Information](webhook-information) section.

To receive and process events, you need to set up your server and provide a URL where the webhook can take place. The URL will be something like that:\
<https://www.yourwebsite.com/your-endpoint-name>

MyFatoorah webhook will call your endpoint with a RESTful call. It means your endpoint will receive a JSON body with event data based on the Webhook Version you are using. There are two Webhook Versions in MyFatoorah:

* [Webhook V1](https://docs.myfatoorah.com/docs/webhook-v1)
* [Webhook V2](https://docs.myfatoorah.com/docs/webhook-v2) **(Recommended to use)**

MyFatoorah uses [Webhook Signature](https://docs.myfatoorah.com/docs/webhook-signature) to enable you confirm that the webhook events are actually triggered from MyFatoorah.

## Webhook Information

*`https://docs.myfatoorah.com/docs/webhook-information` — updated 2026-02-16*

To start with the MyFatoorah Webhook service, you can follow these steps to complete your integration settings:

1. Log in to your portal account and go to **Integration Settings** => **Webhook Settings** and configure your settings.
2. Enable the Webhook Feature.
3. Add your endpoint URL, which will handle the event.
4. Select the event types to get the webhook events.
5. Choose the Webhook Version. If you choose V2, make sure to configure the retries.
6. Click on the **Save** button.
7. Be ready to receive the notifications.

![Webhook v2 configuration](https://files.readme.io/a1685587b81fb008599016dc7f518db5dd660309c826f267b963297e1970826a-image.png)

## Webhook Signature

*`https://docs.myfatoorah.com/docs/webhook-signature` — updated 2026-02-16*

#### Introduction

MyFatoorah can optionally sign the webhook events it sends to your endpoints by including a signature in each event’s **MyFatoorah-Signature** header. This allows you to verify that the events were sent by MyFatoorah, not by a third party.

MyFatoorah **recommends** this option for security and this option can be configured from the webhook page on your portal by enabling the **secure key**.

The `myfatoorah-signature` header is included in each signing event, which contains a signature that is encrypted by your secret key.

MyFatoorah generates signatures using a hash-based message authentication code ([HMAC](https://en.wikipedia.org/wiki/HMAC)) with [SHA-256](https://en.wikipedia.org/wiki/SHA-2). To prevent [downgrade attacks](https://en.wikipedia.org/wiki/Downgrade_attack), you should order the data model properties according to the event type as described in the documentation of each event type with their values, then encrypt them with the secret key, then compare the generated signature with `myfatoorah-signature` to make sure that this request is from our side.

#### Steps:

1. Prepare all the data properties as described in the documentation.
2. Create one string from the data after ordering it to be like that\
   `key=value,key2=value2 ...`

> 🚧 Null Properties
>
> If the value of any property is **null**, replace it with the empty string. See how the customer email is represented in the below example:\
> `CreatedDate=04032021211555,CustomerEmail=,CustomerMobile=96512345678`

3. Encode the secret key and ordered data with **UTF-8**.
4. Encrypt the string using **HMAC SHA-256** with the secret key from the portal in binary mode.
5. Encode the result from the previous point with **base64**.
6. Compare the signature header with the encrypted hash string. If they are equal, then the request is valid and from the MyFatoorah side.

## Webhook V1

*`https://docs.myfatoorah.com/docs/webhook-v1` — updated 2026-02-16*

#### **Introduction**

Webhook V1 allows you to asynchronously update your system with changes occurring in your MyFatoorah account.

In Webhook V1, MyFatoorah will attempt to send the webhook event up to four times if a Success status code is not received from your side. The retries are scheduled with a 10-second interval between each attempt.

To ensure the security of the webhook events, we highly recommend enabling the **Webhook Secret Key**. This key is included in the headers of the webhook event as the value of the `myfatoorah-signature` header, providing secure event verification.

To know more about the signature, please refer to [MyFatoorah Signature](https://docs.myfatoorah.com/docs/webhook-signature)

> ❗️ SSL
>
> SSL is required. Ensure that your server is configured to support HTTPS, with a valid server certificate.

![](https://files.readme.io/bf08d285c371a9510c59821a03c2964f0d82242730fd9220d02e5282f3a4a26c-image.png)

***

#### **How it Works**

> 📘 Portal account
>
> Before you build Your Endpoint, you need to set up your webhook information in your portal account as described in the [Webhook Information](webhook-information) section.

To receive and process events, you need to set up your server and provide a URL where the webhook can take place. The URL will be something like that:\
<https://www.yourwebsite.com/your-endpoint-name>

MyFatoorah webhook will request your endpoint with a RESTful call. It means your endpoint will receive a JSON body with event data that will be in the following structure.

| Input Parameter | Type | Description |
|---|---|---|
| **EventType** | number | * \*1\*\* For Transaction Status Changed * \*2\*\* For Refund Status Changed * \*3\*\* For Balance Transferred * \*4\*\* For Supplier Status Changed * \*5\*\* For Recurring Status Changed * \*6\*\* For Dispute Status Changed * \*7\*\* For Supplier Update Request Changed |
| **Event** | string | TransactionsStatusChanged\ RefundStatusChanged\ BalanceTransferred\ SupplierStatusChanged\ RecurringStatusChanged\ DisputeStatusChanged\ SupplierUpdateRequestChanged |
| **DateTime** | datetime | The event date time format "ddMMyyyyHHmmss" |
| **CountryIsoCode** | string | The country for the event portal with [3 alpha ISO format](https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes) |
| **Data** | DataModel | The event data model depends on the event type. See the description below. |

For now, we support four types of events:

1. **Transaction Status Changed**:\
   This will notify your system of the transaction status (success, failed). You will find the needed information about this event in the [Transaction Data Model](transaction-data-model) section.

> 👍 Updating Order Status in your System
>
> As a best practice, we recommend relying on both the **webhook** and **[GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status)** to update your system with the latest status of the transaction.

> 🚧 Two Webhook Events for the same Transaction
>
> In some rare scenarios, you may receive two webhook events for the same transaction. If the TransactionStatus one of these webhook events is **SUCCESS**, ignore the other webhook event and mark the order as paid in your system.

2. **Refund Status Changed**:\
   This will notify your system of the refund status (refunded, canceled). You will find the needed information about this event in the [Refund Data Model](https://docs.myfatoorah.com/docs/refund-data-model) section.
3. **Balance Transferred**:\
   This will notify your system of the new deposit whenever Myfatoorah transfers the balance to your bank account. You will find the needed information about this event in the [Deposit Data Model](https://docs.myfatoorah.com/docs/deposit-data-model) section.
4. **Supplier Status Changed**:\
   This will notify your system of the supplier account status (approved, rejected). You will find the needed information about this event in the [Supplier Data Model](https://docs.myfatoorah.com/docs/supplier-data-model) section.
5. **Recurring Status Changed**:\
   This will send a webhook event for every deduction attempt on a Recurring Payment. You will find the needed information about this event in the [Recurring Data Model](https://docs.myfatoorah.com/docs/recurring-data-model) section.
6. **Dispute Status Changed**:\
   This will send a webhook for every dispute made over any of your transactions. You will find the needed information about this event [Dispute Data Model](https://docs.myfatoorah.com/docs/dispute-data-model) section.
7. **Supplier Update Request Changed**:\
   This will send a webhook event for the status of the request for editing an approved supplier. You will find the needed information about this event in the [Supplier Update Request Data Model](https://docs.myfatoorah.com/docs/supplier-update-request-data-model) section.

## Refund Data Model

*`https://docs.myfatoorah.com/docs/refund-data-model` — updated 2026-02-16*

Data Model

#### **Overview**

The "Refund Data Model" is a **Data** model type used in your webhook endpoint. This model provides information about any **RefundStatusChanged** event.

Your webhook endpoint will receive a POST request from **MyFatooah** that contains an **EventType** parameter with a value of "**2**". It is used to notify your system of the refund status (refunded, canceled). Detailed functionality of how to implement your webhook endpoint is explained in the [Webhook](https://docs.myfatoorah.com/docs/webhook) section.

Now, we are going to declare the **Refund Data Model** properties of your endpoint and its models along with each accepted parameter and possible value.

***

#### **Request Model**

| Input Parameter | Type | Description |
|---|---|---|
| **RefundId** | number |  |
| **RefundReference** | string |  |
| **CreatedDate** | datetime | Format "ddMMyyyyHHmmss" |
| **RefundStatus** | string | REFUNDED\ CANCELED |
| **Amount** | string |  |
| **Comments** | string |  |
| **InvoiceId** | string |  |
| **InvoiceRefernce** | string |  |
| **GatewayReference** | [GatewayReference](#gatewayreference) | This parameter should not be included in the signature properties. |

### GatewayReference

| Input Parameter     | Type   | Description |
| :------------------ | :----- | :---------- |
| **PaymentId**       | string |             |
| **AuthorizationId** | string |             |
| **ReferenceId**     | string |             |
| **TransactionId**   | string |             |
| **RefundAmount**    | string |             |
| **Currency**        | string |             |
| **PaymentMethod**   | string |             |

***

#### **Sample Message**

The below sample JSON message is sent by MyFatoorah to your endpoint.

```json Refund
{
   "EventType":2,
   "Event":"RefundStatusChanged",
   "DateTime":"04032021211057",
   "CountryIsoCode":"KWT",
   "Data":{
      "RefundId":2840,
      "RefundReference":"2021000019",
      "CreatedDate":"04032021210447",
      "RefundStatus":"REFUNDED",
      "Amount":"40",
      "Comments":"Test Webhook",
      "InvoiceId":"585757",
      "GatewayReference":{
         "AuthorizationId":"B64769",
         "PaymentId":"100202109798688317",
         "ReferenceId":"109710001316",
         "RefundAmount":"40",
         "TransactionId":"202109701626674",
         "Currency":"KWD",
        "PaymentMethod":"VISA/MASTER"
      }
   }
}
```

<br />

#### Webhook Signature

> 👍 Signature
>
> To generate the signature of this model, you should order only the **below parameters** alphabetic with its values then encrypt it by the secret key.
>
> The **GatewayReference** parameter should not be included in the signature properties.

The following will be a sample string used in generating the signature.

```
Amount=40,Comments=Test Webhook,CreatedDate=04032021210447,InvoiceId=585757,RefundId=2840,RefundReference=2021000019,RefundStatus=REFUNDED
```

***

## Deposit Data Model

*`https://docs.myfatoorah.com/docs/deposit-data-model` — updated 2026-02-16*

Data Model

#### **Overview**

The "Deposit Data Model" is a **Data** model type used in your webhook endpoint. This model provides information about any **BalanceTransferred** event.

Your webhook endpoint will receive a POST request from **MyFatooah** that contains an **EventType** parameter with a value of "**3**".  It is used to notify your system of the new deposit whenever Myfatoorah transferred the balance to your bank account. Detailed functionality of how to implement your webhook endpoint is explained in the [Webhook](https://docs.myfatoorah.com/docs/webhook) section.

Now, we are going to declare the **Transaction Data Model** properties of your endpoint and its models along with each accepted parameter and possible value.

***

#### **Request Model**

| Input Parameter | Type | Description |
|---|---|---|
| **DepositReference** | string |  |
| **DepositedAmount** | string |  |
| **NumberOfTransactions** | string |  |
| DepositFor | string | VENDOR\ SUPPLIER |
| SupplierCode | number |  |

***

#### **Sample Message**

The below sample JSON message is sent by MyFatoorah to your endpoint.

```json Deposit For Vendor
{
   "EventType":3,
   "Event":"BalanceTransferred",
   "DateTime":"04032021212041",
   "CountryIsoCode":"KWT",
   "Data":{
      "DepositReference":"2021000002",
      "DepositedAmount":"6917.282",
      "NumberOfTransactions":"24",
      "DepositFor":"VENDOR",
      "SupplierCode":""
   }
}
```
```json Deposit For Supplier
{
   "EventType":3,
   "Event":"BalanceTransferred",
   "DateTime":"04032021212041",
   "CountryIsoCode":"KWT",
   "Data":{
      "DepositReference":"2021000002",
      "DepositedAmount":"6917.282",
      "NumberOfTransactions":"24",
      "DepositFor":"SUPPLIER",
      "SupplierCode":3
   }
}
```

#### Webhook Signature

> 👍 Signature
>
> To generate the signature of this model, you should order only the **above parameters** alphabetic with its values then encrypt it by the secret key.

The following will be a sample string used in generating the signature.

```
DepositedAmount=6917.282,DepositFor=VENDOR,DepositReference=2021000002,NumberOfTransactions=24,SupplierCode=
```

***

## Supplier Data Model

*`https://docs.myfatoorah.com/docs/supplier-data-model` — updated 2026-02-16*

Data Model

#### **Overview**

The "Supplier Data Model" is a **Data** model type used in your webhook endpoint. This model provides information about any **SupplierStatusChanged** event.

Your webhook endpoint will receive a POST request from **MyFatooah** that contains an **EventType** parameter with a value of "**4**". It is used to notify your system of the supplier account status (approved, rejected). Detailed functionality of how to implement your webhook endpoint is explained in the [Webhook](https://docs.myfatoorah.com/docs/webhook) section.

Now, we are going to declare the **Transaction Data Model** properties of your endpoint and its models along with each accepted parameter and possible value.

***

#### **Request Model**

| Input Parameter | Type | Description |
|---|---|---|
| **SupplierCode** | number |  |
| **SupplierName** | string |  |
| **SupplierMobile** | string |  |
| **SupplierEmail** | string |  |
| **SupplierStatus** | string | APPROVED\ REJECTED |
| **KycFeedback** | KycFeedback Model | The admin feedback in case of rejection |

***

#### **KycFeedbackModel**

| Input Parameter   | Type   | Description                                                                                  |
| :---------------- | :----- | :------------------------------------------------------------------------------------------- |
| **Comments**      | string |                                                                                              |
| **RejectReasons** | string | the [Rejection Reasons](https://docs.myfatoorah.com/docs/rejection-reasons) Ids for rejection in a comma-separated string |

***

#### **Sample Message**

The below sample JSON message is sent by MyFatoorah to your endpoint.

```json Supplier
{
   "EventType":4,
   "Event":"SupplierStatusChanged",
   "DateTime":"04032021212759",
   "CountryIsoCode":"KWT",
   "Data":{
      "SupplierCode":11,
      "SupplierName":"test webhok 10",
      "SupplierMobile":"123456",
      "SupplierEmail":"test@myfatoorah.com",
      "SupplierStatus":"REJECTED",
      "KycFeedback":{
         "Comments":"missing documents",
         "RejectReasons":"1,5,15"
      }
   }
}
```

#### Webhook Signature

> 👍 Signature
>
> To generate the signature of this model, you should order only the **above parameters** alphabetic with its values then encrypt it by the secret key.
>
> The **KycFeedback** parameter should not be included in the signature properties.

The following will be a sample string used in generating the signature.

```
SupplierCode=11,SupplierEmail=test@myfatoorah.com,SupplierMobile=123456,SupplierName=test webhok 10,SupplierStatus=APPROVED
```

***

## Recurring Data Model

*`https://docs.myfatoorah.com/docs/recurring-data-model` — updated 2026-02-16*

Data Model

#### **Overview**

The "Recurring Data Model" is a **Data** model type used in your webhook endpoint. This model provides information about any **RecurringStatusChanged** event.

Your webhook endpoint will receive a POST request from **MyFatooah** that contains an **EventType** parameter with a value of "**5**". It is used to notify your system that a recurring payment has occurred. It also notifies your system with each [retry count](https://docs.myfatoorah.com/docs/recurring-payment#how-it-works) (When MyFatoorah retries to withdraw the recurring value again after payment failure. You can check the details in the [Recurring Model](https://docs.myfatoorah.com/docs/execute-payment#recurringmodel)). It contains the transaction status (SUCCESS or FAILED) as well as the recurring status (ACTIVE, UNCOMPLETED or COMPLETED).

Detailed functionality of how to implement your webhook endpoint is explained in the [Webhook](https://docs.myfatoorah.com/docs/webhook) section.

Now, we are going to declare the **Recurring Data Model** properties of your endpoint and its models along with each accepted parameter and possible value.<hr />

#### **Request Model**

| Input Parameter | Type | Description |
|---|---|---|
| **RecurringId** | string | A unique number for each recurring payment. It's recommended to save this ID in your system with your customer profile so that, you can keep track of all payments done against that customer. Moreover, you will be able to cancel it when needed later on. |
| **RecurringStatus** | string | * \*ACTIVE:\*\* The recurring payment is being processed normally but its [iterations](https://docs.myfatoorah.com/docs/recurring-payment#how-it-works) have not been completed yet. * \*UNCOMPLETED:\*\* MyFatoorah tried to withdraw the recurring value but the payment has failed and also [Retry Counts](https://docs.myfatoorah.com/docs/recurring-payment#how-it-works) have been executed and produced failed payments. * \*COMPLETED:\*\* The recurring payment has been executed successfully with all its[ iterations](https://docs.myfatoorah.com/docs/recurring-payment#how-it-works) and will not be executed again. |
| **NextPayDate** | string | The next date recurring payment will be executed. |
| **InvoiceId** | number | The unique ID of the invoice for this payment. |
| **InvoiceReference** | string | The unique reference for this invoice. |
| **CreatedDate** | string |  |
| **TransactionStatus** | string | The status of this particular transaction of the recurring payment. it can be whether **SUCCESS** or **FAILED**. |
| **ReferenceId** | string |  |
| **TrackId** | string |  |
| **PaymentId** | string |  |
| **AuthorizationId** | string |  |
| **InvoiceValueInBaseCurrency** | string | Recurring Value in your account base currency. |
| **BaseCurrency** | string | Your account base currency. |

***

#### **Sample Message**

The below sample JSON message is sent by MyFatoorah to your endpoint.

```json Transaction
{
	"EventType": 5,
	"Event": "RecurringStatusChanged",
	"DateTime": "14092024040707",
	"CountryIsoCode": "SAU",
	"Data": {
		"RecurringId": "RECUR31144164",
		"RecurringStatus": "ACTIVE",
		"NextPayDate": "11092024000000",
		"InvoiceId": 36170506,
		"InvoiceReference": "2024000730",
		"CreatedDate": "14092024040707",
		"TransactionStatus": "INPROGRESS",
		"ReferenceId": "0808361705063528110582",
		"TrackId": "14-09-2024_35281105",
		"PaymentId": "0808361705063528110582",
		"AuthorizationId": "0808361705063528110582",
		"InvoiceValueInBaseCurrency": "0.1",
		"BaseCurrency": "SAR"
	}
}
```

#### Webhook Signature

> 👍 Signature
>
> To generate the signature of this model, you should order only the **above parameters** alphabetic with its values then encrypt it by the secret key.

The following will be a sample string used in generating the signature.

```
AuthorizationId=B68413,BaseCurrency=KWD,CreatedDate=04032021211555,InvoiceId=586170,InvoiceReference=2021000184,InvoiceValueInBaseCurrency=456.75,NextPayDate=2021-04-07T00:00:00,PaymentId=100202106359084366,RecurringId=RECUR287,RecurringStatus=Completed,ReferenceId=106310001097,TrackId=04-03-2021_477336,TransactionStatus=SUCCESS
```

***

## Dispute Data Model

*`https://docs.myfatoorah.com/docs/dispute-data-model` — updated 2026-02-16*

Data Model

#### **Overview**

The "Dispute Data Model" is a **Data** model type used in your webhook endpoint. This model provides information about any **DisputeStatusChanged** event.

The "**DisputeStatusChanged**" webhook is triggered whenever there is a dispute triggered for a payment on your account. This webhook ensures that your system is notified of the disputes taking place and their updates, allowing for real-time updates and appropriate actions.

Your webhook endpoint will receive a POST request containing an event with **EventType** of value 6 and **Event** of value **DisputeStatusChanged**. This event is particularly useful for tracking the type of chargeback and its status.

#### **Request Model**

| Input Parameter | Type | Description |
|---|---|---|
| InvoiceId | string | Unique identifier for the invoice. |
| InvoiceReference | string | A unique reference number assigned to the invoice. |
| CustomerReference | string | The CustomerIdentifier you sent in the request to create the invoice |
| CustomerName | string | Name of the customer. |
| CustomerMobile | string | Customer's mobile number. |
| CustomerEmail | string | Customer's email address. |
| TransactionStatus | string | The status of the transaction (e.g., `SUCCESS`). |
| UserDefinedField | string | Any custom user-defined field associated with the invoice. |
| InvoiceValueInBaseCurrency | string | The invoice amount in the base currency. |
| BaseCurrency | string | The base currency of your MyFatoorah account (e.g., `KWD`). |
| InvoiceValueInDisplayCurreny | string | The invoice amount in the display currency. |
| DisplayCurrency | string | The currency used for display. |
| InvoiceValueInPayCurrency | string | The invoice amount in the payment currency. |
| PayCurrency | string | The currency used for payment. |
| DisputeTransactionId | integer | Unique identifier for the dispute transaction. |
| DisputeType | string | The type of dispute raised (e.g., `CHARGEBACK `- `DOCUMENTREQUEST `- `FRAUDALERT `- `UNVERIFY`). |
| DisputeStatus | string | The current status of the dispute (e.g., `PENDING - RESOLVED - LOST`). |
| DisputeChargeBackType | string | The chargeback classification (e.g., `General - Trading - Airways`).\ Has value only if DisputeType is **CHARGEBACK** |
| DisputeReason | string | The reason provided for the dispute. Has value only if DisputeType is **CHARGEBACK** |
| InvoiceTransactionId | integer | Identifier of the invoice transaction associated with the dispute. |
| DisputeCreatedDate | string | The date and time when the dispute was created (`ddMMyyyyHHmmss`). |
| InvoiceStatus | string | The current status of the invoice (e.g., `PAID`). |
| InvoiceCreationDate | string | The creation date of the invoice (`ddMMyyyyHHmmss`). |
| InvoiceExternalIdentifier | string | External identifier associated with the invoice. |
| TransactionId | string | Unique identifier for the transaction. |
| TransactionPaymentMethod | string | The payment method used (e.g., `KNET`). |
| TransactionReferenceId | string | A unique identifier assigned to the transaction for tracking purposes. |
| TransactionECI | string | The Electronic Commerce Indicator (ECI) code, if available. |
| TransactionDate | string | The date and time when the transaction was processed (`ddMMyyyyHHmmss`). |
| TransactionAuthorizationId | string | A unique ID associated with the authorization of the payment. |
| TransactionTrackId | string | The tracking ID for the payment transaction. |
| TransactionPaymentId | string | The unique payment ID associated with the transaction. |
| ServiceChargeVAT | string | The VAT applied to the service charge. |
| ServiceCharge | string | The service charge applied in base currency. |
| ReceivableAmount | string | The amount the vendor receives in base currency. |

***

#### **Sample Message**

The below sample JSON message is sent by MyFatoorah to your endpoint.

```json Dispute
{
  "EventType": 6,
  "Event": "DisputeStatusChanged",
  "DateTime": "09072025170115",
  "CountryIsoCode": "KWT",
  "Data": {
    "InvoiceId": 5901147,
    "InvoiceReference": "2025059473",
    "CustomerReference": "3zfFL6R2rwUadhU4ke3q24nos",
    "CustomerName": "Anonymous",
    "CustomerMobile": "+965",
    "CustomerEmail": "",
    "TransactionStatus": "SUCCESS",
    "UserDefinedField": "",
    "InvoiceValueInBaseCurrency": "150",
    "BaseCurrency": "KWD",
    "InvoiceValueInDisplayCurreny": "150",
    "DisplayCurrency": "KWD",
    "InvoiceValueInPayCurrency": "150",
    "PayCurrency": "KWD",
    "DisputeTransactionId": 114,
    "DisputeType": "CHARGEBACK",
    "DisputeStatus": "PENDING",
    "DisputeChargeBackType": "Trading",
    "DisputeReason": "MerchandiseServiceNotReceived",
    "InvoiceTransactionId": 2827625,
    "DisputeCreatedDate": "09072025170115",
    "InvoiceStatus": "PAID",
    "InvoiceCreationDate": "09072025170013",
    "InvoiceExternalIdentifier": "3zfFL6R2rwUadhU4ke3q24nos",
    "TransactionId": "519010001981715",
    "TransactionPaymentMethod": "KNET",
    "TransactionReferenceId": "519010001100",
    "TransactionECI": "",
    "TransactionDate": "09072025170034",
    "TransactionAuthorizationId": "B20080",
    "TransactionTrackId": "09-07-2025_2827625",
    "TransactionPaymentId": "100519010000017643",
    "ServiceChargeVAT": "0.45",
    "ServiceCharge": "3",
    "ReceivableAmount": "146.55"
  }
}
```

#### Webhook Signature

> 👍 Signature
>
> To generate the signature of this model, you should order only the **above parameters** alphabetic with its values then encrypt it by the secret key.

The following will be a sample string used in generating the signature.

```json Dispute
BaseCurrency=KWD,CustomerEmail=,CustomerMobile=+965,CustomerName=Anonymous,CustomerReference=3zfFL6R2rwUadhU4ke3q24nos,DisplayCurrency=KWD,DisputeChargeBackType=Trading,DisputeCreatedDate=09072025170115,DisputeReason=MerchandiseServiceNotReceived,DisputeStatus=PENDING,DisputeTransactionId=114,DisputeType=CHARGEBACK,InvoiceCreationDate=09072025170013,InvoiceExternalIdentifier=3zfFL6R2rwUadhU4ke3q24nos,InvoiceId=5901147,InvoiceReference=2025059473,InvoiceStatus=PAID,InvoiceTransactionId=2827625,InvoiceValueInBaseCurrency=150,InvoiceValueInDisplayCurreny=150,InvoiceValueInPayCurrency=150,PayCurrency=KWD,ReceivableAmount=146.55,ServiceCharge=3,ServiceChargeVAT=0.45,TransactionAuthorizationId=B20080,TransactionDate=09072025170034,TransactionECI=,TransactionId=519010001981715,TransactionPaymentId=100519010000017643,TransactionPaymentMethod=KNET,TransactionReferenceId=519010001100,TransactionStatus=SUCCESS,TransactionTrackId=09-07-2025_2827625,UserDefinedField=
```

***

## Supplier Update Request Data Model

*`https://docs.myfatoorah.com/docs/supplier-update-request-data-model` — updated 2026-02-16*

Data Model

#### **Overview**

The "Supplier Update Request Data Model" is a **Data** model type used in your webhook endpoint. This model provides information about any **SupplierUpdateRequestChanged** event.

The "**SupplierUpdateRequestChanged**" webhook is triggered whenever there is a request to edit a supplier that has been **approved** or **rejected**. This webhook ensures that your system is notified of the status of the update request, allowing for real-time updates and appropriate actions.

Your webhook endpoint will receive a POST request containing an event with **EventType** of value 7 and **Event** of value **SupplierUpdateRequestChanged**. This event is particularly useful for tracking the type of chargeback and its status.

#### **Request Model**

| Input Parameter | Type   | Description                                                 |
| :-------------- | :----- | :---------------------------------------------------------- |
| RequestStatus   | string | The status of the request. (`APPROVED `- `REJECTED`)        |
| Comments        | string | The comments entered by the concerned team for the request. |
| ActionDate      | string | The date and time when the decision on the request was made |
| SupplierCode    | string | The code of the supplier in MyFatoorah system.              |
| SupplierName    | string | The supplier name in MyFatoorah system.                     |
| SupplierMobile  | string | The mobile number of the supplier.                          |
| SupplierEmail   | string | The email of the supplier.                                  |
| SupplierStatus  | string | The status of the supplier. (`APPROVED `- `REJECTED`)       |

***

#### **Sample Message**

The below sample JSON message is sent by MyFatoorah to your endpoint.

```json JSON
{
  "EventType": 7,
  "Event": "SupplierUpdateRequestChanged",
  "DateTime": "18082025142539",
  "CountryIsoCode": "KWT",
  "Data": {
    "RequestStatus": "APPROVED",
    "Comments": "",
    "ActionDate": "18082025142539",
    "SupplierCode": 330,
    "SupplierName": "Ignacio Kling",
    "SupplierMobile": "2227887904",
    "SupplierEmail": "Cleora_Treutel70@yahoo.com",
    "SupplierStatus": "APPROVED"
  }
}
```

#### Webhook Signature

> 👍 Signature
>
> To generate the signature of this model, you should order only the **above parameters** alphabetic with its values then encrypt it by the secret key.

The following will be a sample string used in generating the signature.

```json Signature
ActionDate=18082025142539,Comments=,RequestStatus=APPROVED,SupplierCode=330,SupplierEmail=Cleora_Treutel70@yahoo.com,SupplierMobile=2227887904,SupplierName=Ignacio Kling,SupplierStatus=APPROVED
```

***

## Webhook V2

*`https://docs.myfatoorah.com/docs/webhook-v2` — updated 2026-02-16*

#### **Introduction**

In Webhook v2, we have restructured the webhook event format and enriched its content for each event type. Compared to Webhook v1, it now provides more detailed information. Additionally, the webhook structure has been redesigned to follow an object-based format.

With Webhook v2, you can configure a retry count for events that your server fails to receive and specify the delay between each retry. The maximum number of retries is **5**, and the maximum delay is **180 seconds**.

Furthermore, the use of the **webhook secret key** is now mandatory. This key is included in the headers of the webhook event as the value of the `myfatoorah-signature` header, ensuring secure event verification.

To know more about the signature, please refer to [MyFatoorah Signature](https://docs.myfatoorah.com/docs/webhook-signature)

> ❗️ SSL
>
> SSL is required. Ensure that your server is configured to support HTTPS, with a valid server certificate.

![](https://files.readme.io/c3203e1bd9897d99907809c3b02efd1a1e2faebc6e87c206d59acbe30cfd7c4d-image.png)

***

#### **How it Works**

> 📘 Portal account
>
> Before you build Your Endpoint, you need to set up your webhook information in your portal account as described in the [Webhook Information](webhook-information) section.

To receive and process events, you need to set up your server and provide a URL where the webhook can take place. The URL will be something like that:\
<https://www.yourwebsite.com/your-endpoint-name>

MyFatoorah webhook will request your endpoint with a RESTful call. It means your endpoint will receive a JSON body with event data that will be in the following structure.

| Input Parameter | Type | Description |
|---|---|---|
| **Event.Code** | number | **1** For **PAYMENT\_STATUS\_CHANGED**\ **2** For **REFUND\_STATUS\_CHANGED**\ **3** For **BALANCE\_TRANSFERRED**\ **4** For **SUPPLIER\_STATUS\_CHANGED**\ **5** For **RECURRING\_UPDATES**\ **6** For **DISPUTE\_STATUS\_CHANGED**\ **7** For **SUPPLIER\_UPDATE\_REQUEST\_CHANGED** |
| **Event.Name** | string | **PAYMENT\_STATUS\_CHANGED**\ **REFUND\_STATUS\_CHANGED**\ **BALANCE\_TRANSFERRED**\ **SUPPLIER\_STATUS\_CHANGED**\ **RECURRING\_UPDATES**\ **DISPUTE\_STATUS\_CHANGED**\ **SUPPLIER\_UPDATE\_REQUEST\_CHANGED** |
| **Event.CountryIsoCode** | string | The country for the event portal with [3 alpha ISO format](https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes) |
| **Event.CreationDate** | datetime | The event date time format is **ISO 8601** "yyyy-MM-ddTHH:mm:ssZ".\ The time is in **UTC**Time zone. |
| **Event.Reference** | string | The reference of the webhook event. Each webhook has its unique reference. |
| **Data** | object | Contains the content of the webhook event. |

For now, we support five types of events:

1. **PAYMENT STATUS CHANGED**:\
   This will notify your system of the transaction status (success, failed). You will find the needed information about this event in the [Payment Status Data Model](https://docs.myfatoorah.com/docs/webhook-v2-payment-status-data-model) section.

> 👍 Updating Order Status in your System
>
> As a best practice, we recommend relying on both the **webhook** and **[GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status)** to update your system with the latest status of the transaction.

> 🚧 Two Webhook Events for the same Transaction
>
> In some rare scenarios, you may receive two webhook events for the same transaction. If the TransactionStatus one of these webhook events is **SUCCESS**, ignore the other webhook event and mark the order as paid in your system.

2. **REFUND STATUS CHANGED**:\
   This will notify your system of the refund status (refunded, canceled). You will find the needed information about this event in the [Refund Data Model](https://docs.myfatoorah.com/docs/webhook-v2-refund-data-model) section.
3. **BALANCE TRANSFERRED**:\
   This will notify your system of the new deposit whenever Myfatoorah transfers the balance to your bank account or your suppliers. You will find the needed information about this event in the [Balance Transferred Data Model](https://docs.myfatoorah.com/docs/webhook-v2-balance-transferred-data-model) section.
4. **SUPPLIER STATUS CHANGED**:\
   This will notify your system of the supplier account status (approved, rejected). You will find the needed information about this event in the [Supplier Data Model](https://docs.myfatoorah.com/docs/webhook-v2-supplier-data-model) section.
5. **RECURRING UPDATES**:\
   This will send a webhook event for every deduction attempt on a Recurring Payment. You will find the needed information about this event in the [Recurring Data Model](https://docs.myfatoorah.com/docs/webhook-v2-recurring-data-model) section.
6. **DISPUTE\_STATUS\_CHANGED**:\
   This will send a webhook for every dispute made over any of your transactions. You will find the needed information about this event in the [Dispute Data Model](https://docs.myfatoorah.com/docs/webhook-v2-dispute-data-model) section.
7. **SUPPLIER\_UPDATE\_REQUEST\_CHANGED**:\
   This will send a webhook event for the status of the request for editing an approved supplier. You will find the needed information about this event in the [Supplier Update Request Data Model](https://docs.myfatoorah.com/docs/supplier-update-request-data-model) section.

## Payment Status Data Model

*`https://docs.myfatoorah.com/docs/webhook-v2-payment-status-data-model` — updated 2026-02-16*

> Data Model

#### **Overview**

The "Payment Status Changed" webhook is triggered whenever there is an update to the status of a payment transaction. This webhook ensures that your system is notified of any changes in the transaction status, allowing for real-time updates and appropriate actions.

Your webhook endpoint will receive a **POST** request containing an event with a **Code** of **1** and **Name** of **PAYMENT\_STATUS\_CHANGED**. This event is particularly useful for keeping track of failed, authorized, released, or successful payment transactions, enabling your system to handle displaying the payment attempt result on your page and update your system with the transaction status.

***

#### **Request Model**

The webhook payload consists of multiple nested objects containing essential transaction details.

#### **Event Object**

| Input Parameter      | Type     | Description                                                  |
| :------------------- | :------- | :----------------------------------------------------------- |
| Event.Code           | number   | 1 - PAYMENT\_STATUS\_CHANGED                                 |
| Event.Name           | string   | PAYMENT\_STATUS\_CHANGED                                     |
| Event.CountryIsoCode | string   | The country of your account with MyFatoorah.                 |
| Event.CreationDate   | datetime | The timestamp when the event was created in ISO 8601 format. |
| Event.Reference      | string   | Unique reference ID for the webhook event.                   |

***

#### **Data Object**

##### **Invoice Object**

| Input Parameter            | Type     | Description                                                            |
| :------------------------- | :------- | :--------------------------------------------------------------------- |
| Invoice.Id                 | string   | Unique identifier for the invoice.                                     |
| Invoice.Status             | string   | The current status of the invoice (PAID - PENDING).                    |
| Invoice.Reference          | string   | A unique reference number assigned to the invoice.                     |
| Invoice.CreationDate       | datetime | The date and time when the invoice was created, formatted in ISO 8601. |
| Invoice.ExpirationDate     | datetime | The expiration date of the invoice, formatted in ISO 8601.             |
| Invoice.UserDefinedField   | string   | Any custom user-defined field associated with the invoice.             |
| Invoice.ExternalIdentifier | string   | The value sent in the CustomerIdentifier field in the request.         |
| Invoice.MetaData           | Object   | Includes the metadata sent in the request up to 5 UDFs.                |

##### **Transaction Object**

| Input Parameter                | Type     | Description                                                               |
| :----------------------------- | :------- | :------------------------------------------------------------------------ |
| Transaction.Id                 | string   | Unique identifier for the transaction.                                    |
| Transaction.Status             | string   | The status of the transaction (SUCCESS - FAILED - AUTHORIZE - CANCELED).  |
| Transaction.PaymentMethod      | string   | The payment method used (e.g., VISA/MASTER (USD)).                        |
| Transaction.PaymentId          | string   | The unique payment ID associated with the transaction.                    |
| Transaction.ReferenceId        | string   | A unique identifier assigned to the transaction.                          |
| Transaction.TrackId            | string   | The tracking ID for the transaction.                                      |
| Transaction.AuthorizationId    | string   | A unique ID associated with the authorization of the payment transaction. |
| Transaction.TransactionDate    | datetime | The date and time when the transaction was processed.                     |
| Transaction.ECI                | string   | The Electronic Commerce Indicator (ECI) code.                             |
| Transaction.IP.Address         | string   | The IP address from which the transaction was initiated.                  |
| Transaction.IP.Country         | string   | The country associated with the IP address.                               |
| Transaction.Error.Code         | string   | Error code returned by the payment gateway.                               |
| Transaction.Error.Message      | string   | Detailed error message.                                                   |
| Transaction.Card.NameOnCard    | string   | The name of the cardholder entered by the payer.                          |
| Transaction.Card.Number        | string   | The masked credit/debit card number used.                                 |
| Transaction.Card.Token         | string   | The token of the card if tokenized                                        |
| Transaction.Card.PanHash       | string   | A unique identifier for the PAN.                                          |
| Transaction.Card.ExpiryMonth   | string   | Expiry month entered by the customer.                                     |
| Transaction.Card.ExpiryYear    | string   | Expiry year entered by the customer.                                      |
| Transaction.Card.Brand         | string   | The brand of the payment card.                                            |
| Transaction.Card.Issuer        | string   | The issuing bank or financial institution of the card.                    |
| Transaction.Card.IssuerCountry | string   | The issuer country of the card                                            |
| Transaction.Card.FundingMethod | string   | The funding method of the card (e.g.: debit, credit, prepaid)             |

##### **Customer Object**

| Input Parameter | Type   | Description                                  |
| :-------------- | :----- | :------------------------------------------- |
| Customer.Name   | string | Customer's name.                             |
| Customer.Mobile | string | The Customer Mobile provided in the request. |
| Customer.Email  | string | Customer's email address (masked).           |

##### **Amount Object**

| Input Parameter               | Type   | Description                                      |
| :---------------------------- | :----- | :----------------------------------------------- |
| Amount.BaseCurrency           | string | The base currency of the transaction.            |
| Amount.ValueInBaseCurrency    | string | The amount in the base currency.                 |
| Amount.ServiceCharge          | string | The service charge applied in base currency.     |
| Amount.ServiceChargeVAT       | string | The VAT applied on the service charge.           |
| Amount.ReceivableAmount       | string | The amount the vendor receives in base currency. |
| Amount.DisplayCurrency        | string | The currency used for display.                   |
| Amount.ValueInDisplayCurrency | string | The amount in display currency.                  |
| Amount.PayCurrency            | string | The currency used for payment.                   |
| Amount.ValueInPayCurrency     | string | The transaction value in payment currency.       |

##### **Suppliers Object** (If applicable)

| Input Parameter             | Type   | Description                                         |
| :-------------------------- | :----- | :-------------------------------------------------- |
| Suppliers\[ ].Code          | number | Unique identifier for the supplier.                 |
| Suppliers\[ ].Name          | string | The name of the supplier.                           |
| Suppliers\[ ].InvoiceShare  | string | The share of the supplier in the given invoice.     |
| Suppliers\[ ].ProposedShare | string | The ProposedShare for the supplier in the invoice.  |
| Suppliers\[ ].DepositShare  | string | The amount the supplier receives from this invoice. |

***

#### **Sample Event**

The below sample JSON message is sent by MyFatoorah to your endpoint.

```json
{
  "Event": {
    "Code": 1,
    "Name": "PAYMENT_STATUS_CHANGED",
    "CountryIsoCode": "KWT",
    "CreationDate": "2026-01-04T08:15:00.9500000Z",
    "Reference": "WH-626519"
  },
  "Data": {
    "Invoice": {
      "Id": "6409988",
      "Status": "PAID",
      "Reference": "2026000073",
      "CreationDate": "2026-01-04T08:14:49.897Z",
      "ExpirationDate": "2026-01-04T10:08:36Z",
      "UserDefinedField": "",
      "ExternalIdentifier": "asdqwd-f13sdf-fasjkz",
      "MetaData": {
        "UDF1": "dsa",
        "UDF2": "145",
        "UDF3": "8586",
        "UDF4": "12039",
        "UDF5": "748gsvf"
      }
    },
    "Transaction": {
      "Id": "86781",
      "Status": "SUCCESS",
      "PaymentMethod": "VISA/MASTER",
      "PaymentId": "07076409988323998875",
      "ReferenceId": "600408086781",
      "TrackId": "04-01-2026_3239988",
      "AuthorizationId": "086781",
      "TransactionDate": "2026-01-04T08:15:00.8834074Z",
      "ECI": "02",
      "IP": {
        "Address": "41.40.252.158",
        "Country": "Egypt"
      },
      "Error": {
        "Code": "",
        "Message": ""
      },
      "Card": {
        "NameOnCard": "",
        "Number": "512345xxxxxx0008",
        "Token": "TKN-ecbc9523-8f87-4b72-8009-9859d89d6b45",
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
      "Name": "Anonymous",
      "Mobile": "+965",
      "Email": ""
    },
    "Amount": {
      "BaseCurrency": "KWD",
      "ValueInBaseCurrency": "1",
      "ServiceCharge": "0.02",
      "ServiceChargeVAT": "0.003",
      "ReceivableAmount": "0.51",
      "DisplayCurrency": "KWD",
      "ValueInDisplayCurrency": "1",
      "PayCurrency": "KWD",
      "ValueInPayCurrency": "1"
    },
    "Suppliers": [
      {
        "Code": 212,
        "Name": "Jed Auer",
        "InvoiceShare": "1",
        "ProposedShare": "",
        "DepositShare": "0.467"
      }
    ]
  }
}
```

***

#### **Webhook Signature**

You will use the parameters mentioned below to generate the webhook signature for the `PAYMENT_STATUS_CHANGED` event. If a parameter has no value, set its value to an empty string.

```json Signature
Invoice.Id=6409988,Invoice.Status=PAID,Transaction.Status=SUCCESS,Transaction.PaymentId=07076409988323998875,Invoice.ExternalIdentifier=asdqwd-f13sdf-fasjkz

```

## Refund Data Model

*`https://docs.myfatoorah.com/docs/webhook-v2-refund-data-model` — updated 2026-02-16*

> Data Model

#### **Overview**

The "Refund Status Changed" webhook is triggered whenever there is an update to the status of a refund transaction. This webhook ensures that your system is notified of any changes in the refund status, allowing for real-time updates and appropriate actions.

Your webhook endpoint will receive a **POST** request containing an event with a **Code** of **2** and **Name** of **REFUND\_STATUS\_CHANGED**. This event is particularly useful for keeping track of refunded transactions, ensuring that your system updates the refund status accurately.

***

#### **Request Model**

The webhook payload consists of multiple nested objects containing essential refund details.

#### **Event Object**

| Input Parameter      | Type     | Description                                                                       |
| :------------------- | :------- | :-------------------------------------------------------------------------------- |
| Event.Code           | number   | 2 - REFUND\_STATUS\_CHANGED                                                       |
| Event.Name           | string   | REFUND\_STATUS\_CHANGED                                                           |
| Event.CountryIsoCode | string   | The country of your account with MyFatoorah.                                      |
| Event.CreationDate   | datetime | The timestamp when the event was created in ISO 8601 format. Time zone is in UTC. |
| Event.Reference      | string   | Unique reference ID for the webhook event.                                        |

***

#### **Data Object**

The Data Object contains several nested objects inside of it as below.

##### **Refund Object**

| Input Parameter      | Type     | Description                                                                   |
| :------------------- | :------- | :---------------------------------------------------------------------------- |
| Refund.Id            | number   | Unique identifier for the refund transaction.                                 |
| Refund.Reference     | string   | A unique reference number assigned to the refund transaction.                 |
| Refund.Status        | string   | The current status of the refund (REFUNDED - CANCELED).                       |
| Refund.InvoiceId     | string   | The invoice ID associated with the refund.                                    |
| Refund.CreationDate  | datetime | The date and time when the refund request was created, formatted in ISO 8601. |
| Refund.RefundDate    | datetime | The date and time when the refund was processed, formatted in ISO 8601.       |
| Refund.RRN           | string   | Retrieval Reference Number (RRN) for tracking the refund transaction.         |
| Refund.Comment       | string   | Optional comments provided by MyFatoorah during the refund process            |
| Refund.VendorComment | string   | Optional comment sent by the vendor to issue the refund.                      |

##### **Amount Object**

| Input Parameter            | Type   | Description                                       |
| :------------------------- | :----- | :------------------------------------------------ |
| Amount.BaseCurrency        | string | The base currency of your account with MyFatoorah |
| Amount.ValueInBaseCurrency | string | The refunded amount in the base currency.         |

###### **Amount Distribution**

| Input Parameter               | Type   | Description                                                        |
| :---------------------------- | :----- | :----------------------------------------------------------------- |
| Amount.Distribution.Vendor    | string | The portion of the refund amount allocated to the vendor.          |
| Amount.Distribution.Suppliers | array  | The portion of the refund amount allocated to suppliers. (if any). |

###### **Suppliers Array**

| Input Parameter                         | Type   | Description                            |
| :-------------------------------------- | :----- | :------------------------------------- |
| Amount.Distribution.Suppliers\[].Code   | number | Unique identifier for the supplier.    |
| Amount.Distribution.Suppliers\[].Name   | string | The name of the supplier.              |
| Amount.Distribution.Suppliers\[].Amount | string | The amount deducted from the supplier. |

##### **Referenced Invoice Object**

| Input Parameter                                | Type   | Description                                                               |
| :--------------------------------------------- | :----- | :------------------------------------------------------------------------ |
| ReferencedInvoice.Id                           | string | The InvoiceId of the original invoice associated with the refund.         |
| ReferencedInvoice.Reference                    | string | The reference number of the original invoice.                             |
| ReferencedInvoice.PaymentMethod                | string | The payment method used for the original transaction (e.g., VISA/MASTER). |
| ReferencedInvoice.BaseCurrency                 | string | The base currency of your account (e.g., KWD).                            |
| ReferencedInvoice.ValueInBaseCurrency          | string | The total value of the original invoice in base currency.                 |
| ReferencedInvoice.RemainingValueInBaseCurrency | string | The remaining amount in base currency after the refund.                   |

***

#### **Sample Event**

The below sample JSON message is sent by MyFatoorah to your endpoint.

```json
{
  "Event": {
    "Code": 2,
    "Name": "REFUND_STATUS_CHANGED",
    "CountryIsoCode": "KWT",
    "CreationDate": "2025-05-13T06:06:20.4000000Z",
    "Reference": "WH-128044"
  },
  "Data": {
    "Refund": {
      "Id": "111147",
      "Reference": "2025000058",
      "Status": "REFUNDED",
      "InvoiceId": "5620292",
      "CreationDate": "2025-05-13T06:06:19.247Z",
      "RefundDate": "2025-05-13T06:06:20.2019805Z",
      "RRN": "513306098825",
      "Comment": "",
      "VendorComment": "Supplier Refund"
    },
    "Amount": {
      "BaseCurrency": "KWD",
      "ValueInBaseCurrency": "30",
      "Distribution": {
        "Vendor": "10",
        "Suppliers": [
          {
            "Code": 1,
            "Name": "Hinds Hall",
            "Amount": "20"
          }
        ]
      }
    },
    "ReferencedInvoice": {
      "Id": "5620277",
      "Reference": "2025042457",
      "ExternalIdentifier": "1Q3bpLfxwqnTd3NtP3LELbCNi5oi4fZBU",
      "PaymentMethod": "VISA/MASTER",
      "BaseCurrency": "KWD",
      "ValueInBaseCurrency": "64.32",
      "RemainingValueInBaseCurrency": "34.32"
    }
  }
}
```

***

#### **Webhook Signature**

You will use the parameters mentioned below to generate the webhook signature for the `REFUND_STATUS_CHANGED` event.

```
Refund.Id=111147,Refund.Status=REFUNDED,Amount.ValueInBaseCurrency=30,ReferencedInvoice.Id=5620277
```

This webhook allows real-time tracking of refund transactions, ensuring accurate reconciliation and financial updates in your system.

## Balance Transferred Data Model

*`https://docs.myfatoorah.com/docs/webhook-v2-balance-transferred-data-model` — updated 2026-02-16*

> Data Model

#### **Overview**

The "Balance Transferred" webhook is triggered whenever a balance transfer is completed. This webhook ensures that your system is notified of bank deposits for suppliers or vendors, allowing for real-time updates and appropriate financial reconciliation.

Your webhook endpoint will receive a **POST** request containing an event with a **Code** of **3** and **Name** of **BALANCE\_TRANSFERRED**. This event is particularly useful for tracking deposits made to vendors or suppliers.

***

#### **Request Model**

The webhook payload consists of multiple nested objects containing essential balance transfer details.

#### **Event Object**

| Input Parameter      | Type     | Description                                                  |
| :------------------- | :------- | :----------------------------------------------------------- |
| Event.Code           | number   | 3 - BALANCE\_TRANSFERRED                                     |
| Event.Name           | string   | BALANCE\_TRANSFERRED                                         |
| Event.CountryIsoCode | string   | The country of your account with MyFatoorah.                 |
| Event.CreationDate   | datetime | The timestamp when the event was created in ISO 8601 format. |
| Event.Reference      | string   | Unique reference ID for the webhook event.                   |

***

#### **Data Object**

The Data Object contains multiple nested objects as below.

##### **Deposit Object**

| Input Parameter              | Type     | Description                                                         |
| :--------------------------- | :------- | :------------------------------------------------------------------ |
| Deposit.Reference            | string   | A unique reference number assigned to the deposit.                  |
| Deposit.BaseCurrency         | string   | The base currency of your MyFatoorah account (e.g., KWD).           |
| Deposit.ValueInBaseCurrency  | string   | The transferred balance amount in the base currency.                |
| Deposit.NumberOfTransactions | string   | The number of transactions included in the deposit.                 |
| Deposit.DepositDate          | datetime | The date and time when the deposit was made, formatted in ISO 8601. |

##### **Supplier Object** (If applicable)

| Input Parameter | Type   | Description                                                  |
| :-------------- | :----- | :----------------------------------------------------------- |
| Supplier.Code   | string | Unique identifier for the supplier who received the deposit. |
| Supplier.Name   | string | The name of the supplier.                                    |

**Note:** The `Supplier` object will be `null` for vendor deposits.

##### **Bank Object**

| Input Parameter    | Type   | Description                                      |
| :----------------- | :----- | :----------------------------------------------- |
| Bank.Name          | string | The name of the bank where the deposit was made. |
| Bank.IBAN          | string | The IBAN (International Bank Account Number).    |
| Bank.AccountNumber | string | The account number associated with the deposit.  |

***

#### **Sample Event**

```json Vendor Deposit
{
  "Event": {
    "Code": 3,
    "Name": "BALANCE_TRANSFERRED",
    "CountryIsoCode": "KWT",
    "CreationDate": "2025-05-13T06:12:24.8570000Z",
    "Reference": "WH-128055"
  },
  "Data": {
    "Deposit": {
      "Reference": "2025000006",
      "BaseCurrency": "KWD",
      "ValueInBaseCurrency": "15968.333",
      "NumberOfTransactions": "511",
      "DepositDate": "2025-05-13T06:12:00Z"
    },
    "Supplier": null,
    "Bank": {
      "Name": "Test NBK",
      "IBAN": "SA2405000068200013262001",
      "AccountNumber": "12345678"
    }
  }
}
```
```json Supplier Deposit
{
  "Event": {
    "Code": 3,
    "Name": "BALANCE_TRANSFERRED",
    "CountryIsoCode": "KWT",
    "CreationDate": "2025-05-13T06:14:35.8600000Z",
    "Reference": "WH-128058"
  },
  "Data": {
    "Deposit": {
      "Reference": "2025000302",
      "BaseCurrency": "KWD",
      "ValueInBaseCurrency": "28732.467",
      "NumberOfTransactions": "221",
      "DepositDate": "2025-05-13T06:14:00Z"
    },
    "Supplier": {
      "Code": "1",
      "Name": "Hinds Hall"
    },
    "Bank": {
      "Name": "Riyad Bank",
      "IBAN": "SA0330100827000000050061",
      "AccountNumber": "123456789"
    }
  }
}
```

***

#### **Webhook Signature**

You will use the parameters mentioned below to generate the webhook signature for the `BALANCE_TRANSFERRED` event.

```json Signature
Deposit.Reference=2025000006,Deposit.ValueInBaseCurrency=15968.333,Deposit.NumberOfTransactions=511
```

This webhook allows real-time tracking of balance transfers, ensuring accurate reconciliation and financial updates in your system.

## Supplier Data Model

*`https://docs.myfatoorah.com/docs/webhook-v2-supplier-data-model` — updated 2026-02-16*

> Data Model

#### **Overview**

The "Supplier Status Changed" webhook is triggered whenever there is an update to the status of a supplier in the system. This webhook ensures that your system is notified of any changes in supplier status, allowing for real-time updates and appropriate actions.

Your webhook endpoint will receive a **POST** request containing an event with a **Code** of **4** and **Name** of **SUPPLIER\_STATUS\_CHANGED**. This event is particularly useful for monitoring supplier verifications and KYC (Know Your Customer) decision updates.

***

#### **Request Model**

The webhook payload consists of multiple nested objects containing essential supplier status details.

#### **Event Object**

| Input Parameter      | Type     | Description                                                  |
| :------------------- | :------- | :----------------------------------------------------------- |
| Event.Code           | number   | 4 - SUPPLIER\_STATUS\_CHANGED                                |
| Event.Name           | string   | SUPPLIER\_STATUS\_CHANGED                                    |
| Event.CountryIsoCode | string   | The country of your account with MyFatoorah.                 |
| Event.CreationDate   | datetime | The timestamp when the event was created in ISO 8601 format. |
| Event.Reference      | string   | Unique reference ID for the webhook event.                   |

***

#### **Data Object**

The Data Object contains multiple nested objects as below.

##### **Supplier Object**

| Input Parameter | Type   | Description                              |
| :-------------- | :----- | :--------------------------------------- |
| Supplier.Code   | number | Unique identifier for the supplier.      |
| Supplier.Name   | string | The name of the supplier.                |
| Supplier.Mobile | string | The mobile phone number of the supplier. |
| Supplier.Email  | string | The email address of the supplier.       |

##### **KYC Decision Object**

| Input Parameter             | Type   | Description                                                                                |
| :-------------------------- | :----- | :----------------------------------------------------------------------------------------- |
| KycDecision.Status          | string | The current status of the supplier’s KYC verification (e.g., **APPROVED** - **REJECTED**). |
| KycDecision.Comments        | string | Any additional comments related to the KYC decision.                                       |
| KycDecision.RejectionReason | array  | If rejected, contains the reasons for rejection.                                           |

##### **Rejection Reasons Object**

You can find here all the [Rejection Reasons](https://docs.myfatoorah.com/docs/rejection-reasons).

| Input Parameter                 | Type   | Description                          |
| :------------------------------ | :----- | :----------------------------------- |
| RejectionReason\[ ].Code        | string | The rejection reason code.           |
| RejectionReason\[ ].Description | string | Description of the rejection reason. |

##### **Business Category Object**

| Input Parameter       | Type   | Description                               |
| :-------------------- | :----- | :---------------------------------------- |
| BusinessCategory.Code | string | The MCC Code for the supplier             |
| BusinessCategory.Name | string | The name of the MCC Code for the supplier |

***

#### **Sample Event**

The below sample JSON message is sent by MyFatoorah to your endpoint.

```json Supplier Approval
{
  "Event": {
    "Code": 4,
    "Name": "SUPPLIER_STATUS_CHANGED",
    "CountryIsoCode": "KWT",
    "CreationDate": "2025-05-13T06:25:26.7400000Z",
    "Reference": "WH-128071"
  },
  "Data": {
    "Supplier": {
      "Code": 279,
      "Name": "May Nicolas",
      "Mobile": "8237751513",
      "Email": "Kody79@yahoo.com"
    },
    "KycDecision": {
      "Status": "APPROVED",
      "Comments": "",
      "RejectionReason": []
    },
    "BusinessCategory": {
      "Code": "5814",
      "Name": "Cafes"
    }
  }
}
```
```json Supplier Rejection
{
  "Event": {
    "Code": 4,
    "Name": "SUPPLIER_STATUS_CHANGED",
    "CountryIsoCode": "KWT",
    "CreationDate": "2025-05-13T06:28:55.2600000Z",
    "Reference": "WH-128075"
  },
  "Data": {
    "Supplier": {
      "Code": 273,
      "Name": "Daryl Sauer",
      "Mobile": "5089201194",
      "Email": "Hunter_Haag0@hotmail.com"
    },
    "KycDecision": {
      "Status": "REJECTED",
      "Comments": "",
      "RejectionReason": [
        {
          "Code": "1",
          "Description": "Expired Company License"
        },
        {
          "Code": "7",
          "Description": "Expired Authorise Signatory - Kuwait"
        },
        {
          "Code": "30",
          "Description": "Unclear Bank approval letter- Home Business"
        },
        {
          "Code": "37",
          "Description": "Using Personal bank"
        },
        {
          "Code": "58",
          "Description": "Update Commercial Registeration to include the activity"
        },
        {
          "Code": "65",
          "Description": "Wrong email, require to register again"
        },
        {
          "Code": "75",
          "Description": "No Product"
        }
      ]
    },
    "BusinessCategory": {
      "Code": "5941",
      "Name": "Camping products"
    }
  }
}
```

***

#### **Webhook Signature**

You will use the parameters mentioned below to generate the webhook signature for the `SUPPLIER_STATUS_CHANGED` event.

```json Signature
Supplier.Code=279,KycDecision.Status=APPROVED
```

This webhook allows real-time tracking of supplier status changes, including verification and rejection updates, ensuring compliance and verification processes in your system.

***

## Recurring Data Model

*`https://docs.myfatoorah.com/docs/webhook-v2-recurring-data-model` — updated 2026-02-16*

> Data Model

#### **Overview**

The "Recurring Updates" webhook is triggered whenever there is an update to a recurring payment. This webhook ensures that your system is notified of recurring payment status changes, allowing for real-time updates and appropriate actions.

Your webhook endpoint will receive a **POST** request containing an event with a **Code** of **5** and **Name** of **RECURRING\_UPDATES**. This event is particularly useful for tracking active, canceled, or updated recurring payments and their associated transactions.

***

#### **Request Model**

The webhook payload consists of multiple nested objects containing essential recurring payment details.

#### **Event Object**

| Input Parameter      | Type     | Description                                                  |
| :------------------- | :------- | :----------------------------------------------------------- |
| Event.Code           | number   | 5 - RECURRING\_UPDATES                                       |
| Event.Name           | string   | RECURRING\_UPDATES                                           |
| Event.CountryIsoCode | string   | The country of your account with MyFatoorah.                 |
| Event.CreationDate   | datetime | The timestamp when the event was created in ISO 8601 format. |
| Event.Reference      | string   | Unique reference ID for the webhook event.                   |

***

#### **Data Object**

The Data Object contains multiple nested objects as below.

##### **Recurring Object**

| Input Parameter | Type | Description |
|---|---|---|
| Recurring.Id | string | Unique identifier for the recurring payment. |
| Recurring.NextPayDate | datetime | The scheduled date and time for the next recurring payment, formatted in ISO 8601 and always in UTC. |
| Recurring.Status | string | ACTIVE - The recurring payment is being processed normally, but its iterations have not been completed yet.\ UNCOMPLETED - MyFatoorah attempted to withdraw the recurring value, but the payment failed, and all retry attempts were unsuccessful.\ COMPLETED - The recurring payment has been executed successfully with all iterations completed; it will not be executed again. |
| Recurring.InitialInvoiceId | string | The invoice ID associated with the creation of the recurring payment. |

##### **Payment Object**

The Payment Object contains details about the latest processed recurring payment.

###### **Invoice Object**

| Input Parameter          | Type     | Description                                                            |
| :----------------------- | :------- | :--------------------------------------------------------------------- |
| Invoice.Id               | string   | Unique identifier for the invoice.                                     |
| Invoice.Status           | string   | The current status of the invoice (e.g., PAID).                        |
| Invoice.Reference        | string   | A unique reference number assigned to the invoice.                     |
| Invoice.CreationDate     | datetime | The date and time when the invoice was created, formatted in ISO 8601. |
| Invoice.ExpirationDate   | datetime | The expiration date of the invoice, formatted in ISO 8601.             |
| Invoice.UserDefinedField | string   | Any custom user-defined field associated with the invoice.             |

###### **Transaction Object**

| Input Parameter             | Type     | Description                                                                |
| :-------------------------- | :------- | :------------------------------------------------------------------------- |
| Transaction.Id              | string   | Unique identifier for the transaction.                                     |
| Transaction.Status          | string   | The status of the transaction (e.g., SUCCESS).                             |
| Transaction.PaymentMethod   | string   | The payment method used (e.g., VISA/MASTER).                               |
| Transaction.PaymentId       | string   | The unique payment ID associated with the transaction.                     |
| Transaction.ReferenceId     | string   | A unique identifier assigned to the transaction for tracking purposes.     |
| Transaction.TrackId         | string   | The tracking ID for the payment transaction.                               |
| Transaction.AuthorizationId | string   | A unique ID associated with the authorization of the payment.              |
| Transaction.TransactionDate | datetime | The date and time when the transaction was processed.                      |
| Transaction.ECI             | string   | The Electronic Commerce Indicator (ECI) code, representing security level. |
| Transaction.IP.Address      | string   | The IP address from which the transaction was initiated.                   |
| Transaction.IP.Country      | string   | The country associated with the IP address.                                |
| Transaction.Error.Code      | string   | Error code returned by the payment gateway, if applicable.                 |
| Transaction.Error.Message   | string   | Detailed error message, if applicable.                                     |
| Transaction.Card.Number     | string   | The masked credit/debit card number used for the transaction.              |
| Transaction.Card.Brand      | string   | The brand of the payment card, such as Visa or MasterCard.                 |
| Transaction.Card.Issuer     | string   | The issuing bank or financial institution of the card, if available.       |

###### **Amount Object**

| Input Parameter               | Type   | Description                                               |
| :---------------------------- | :----- | :-------------------------------------------------------- |
| Amount.BaseCurrency           | string | The base currency of your MyFatoorah account (e.g., KWD). |
| Amount.ValueInBaseCurrency    | string | The amount in the base currency.                          |
| Amount.ServiceCharge          | string | The service charge applied in base currency.              |
| Amount.ServiceChargeVAT       | string | The VAT applied to the service charge.                    |
| Amount.ReceivableAmount       | string | The amount the vendor receives in base currency.          |
| Amount.DisplayCurrency        | string | The currency used for display.                            |
| Amount.ValueInDisplayCurrency | string | The amount in display currency.                           |
| Amount.PayCurrency            | string | The currency used for payment (e.g., USD).                |
| Amount.ValueInPayCurrency     | string | The transaction value in payment currency.                |

***

#### **Sample Event**

The below sample JSON message is sent by MyFatoorah to your endpoint.

```json
{
  "Event": {
    "Code": 5,
    "Name": "RECURRING_UPDATES",
    "CountryIsoCode": "KWT",
    "CreationDate": "2025-02-09T01:16:11.9770000",
    "Reference": "WH-19916"
  },
  "Data": {
    "Recurring": {
      "Id": "RECUR1218312623",
      "NextPayDate": "2025-02-09T21:00:00Z",
      "Status": "ACTIVE",
      "InitialInvoiceId": "5047674"
    },
    "Payment": {
      "Invoice": {
        "Id": "5105602",
        "Status": "PAID",
        "Reference": "2025015455",
        "CreationDate": "2025-02-09T01:16:10.6259466Z",
        "ExpirationDate": "2025-02-10T01:16:10.6259466Z",
        "UserDefinedField": ""
      },
      "Transaction": {
        "Id": "121797",
        "Status": "SUCCESS",
        "PaymentMethod": "VISA/MASTER",
        "PaymentId": "07075105602247796773",
        "ReferenceId": "504001121797",
        "TrackId": "09-02-2025_2477967",
        "AuthorizationId": "262890",
        "TransactionDate": "2025-02-09T01:16:11.8837517Z"
      }
    }
  }
}
```

***

#### **Webhook Signature**

You will use the parameters mentioned below to generate the webhook signature for the `RECURRING_UPDATES` event.

```json Signature
Recurring.Id=RECUR1037225,Recurring.Status=UNCOMPLETED,Recurring.InitialInvoiceId=322242
```

This webhook allows real-time tracking of recurring payments, ensuring accurate financial management and automated processing in your system.

## Dispute Data Model

*`https://docs.myfatoorah.com/docs/webhook-v2-dispute-data-model` — updated 2026-02-16*

> Data Model

#### **Overview**

The "Dispute Status Changed" webhook is triggered whenever there is a dispute triggered for a payment on your account. This webhook ensures that your system is notified of the disputes taking place and their updates, allowing for real-time updates and appropriate actions.

Your webhook endpoint will receive a **POST** request containing an event with a **Code** of **6** and **Name** of **DISPUTE\_STATUS\_CHANGED**. This event is particularly useful for tracking the type of chargeback and its status.

***

#### **Request Model**

The webhook payload consists of multiple nested objects containing essential recurring payment details.

#### **Event Object**

| Input Parameter      | Type     | Description                                                  |
| :------------------- | :------- | :----------------------------------------------------------- |
| Event.Code           | number   | 6 - DISPUTE\_STATUS\_CHANGED                                 |
| Event.Name           | string   | DISPUTE\_STATUS\_CHANGED                                     |
| Event.CountryIsoCode | string   | The country of your account with MyFatoorah.                 |
| Event.CreationDate   | datetime | The timestamp when the event was created in ISO 8601 format. |
| Event.Reference      | string   | Unique reference ID for the webhook event.                   |

***

#### **Data Object**

The Data Object contains multiple nested objects as below.

##### **Dispute Object**

| Input Parameter              | Type     | Description                                                                                               |
| :--------------------------- | :------- | :-------------------------------------------------------------------------------------------------------- |
| Dispute.Type                 | string   | The type of dispute raised (e.g., `CHARGEBACK - DOCUMENTREQUEST - FRAUDALERT - UNVERIFY`).                |
| Dispute.Status               | string   | The current status of the dispute (e.g., `PENDING - RESOLVED - LOST`).                                    |
| Dispute.CreatedDate          | datetime | The date and time when the dispute was created, formatted in ISO 8601 UTC.                                |
| Dispute.ChargeBackType       | string   | The chargeback classification (e.g., `General - Trading - Airways`). Has value only if type is CHARGEBACK |
| Dispute.Reason               | string   | The reason provided for the dispute. Has value only if type is CHARGEBACK                                 |
| Dispute.DisputeTransactionId | integer  | Unique identifier for the dispute transaction.                                                            |
| Dispute.InvoiceTransactionId | integer  | Identifier of the invoice transaction associated with the dispute.                                        |

##### **Invoice Object**

| Input Parameter            | Type     | Description                                                                |
| :------------------------- | :------- | :------------------------------------------------------------------------- |
| Invoice.Id                 | string   | Unique identifier for the invoice.                                         |
| Invoice.Status             | string   | The current status of the invoice (e.g., `PAID`).                          |
| Invoice.Reference          | string   | A unique reference number assigned to the invoice.                         |
| Invoice.CreationDate       | datetime | The date and time when the invoice was created, formatted in ISO 8601 UTC. |
| Invoice.ExternalIdentifier | string   | The value sent in the CustomerIdentifier field in the request.             |

##### **Transaction Object**

| Input Parameter             | Type     | Description                                                                |
| :-------------------------- | :------- | :------------------------------------------------------------------------- |
| Transaction.Id              | string   | Unique identifier for the transaction.                                     |
| Transaction.Status          | string   | The status of the transaction (e.g., `SUCCESS`).                           |
| Transaction.PaymentMethod   | string   | The payment method used (e.g., `VISA/MASTER`).                             |
| Transaction.PaymentId       | string   | The unique payment ID associated with the transaction.                     |
| Transaction.ReferenceId     | string   | A unique identifier assigned to the transaction for tracking purposes.     |
| Transaction.TrackId         | string   | The tracking ID for the payment transaction.                               |
| Transaction.AuthorizationId | string   | A unique ID associated with the authorization of the payment.              |
| Transaction.TransactionDate | datetime | The date and time when the transaction was processed.                      |
| Transaction.ECI             | string   | The Electronic Commerce Indicator (ECI) code, representing security level. |

###### **Card Object**

| Input Parameter                | Type   | Description                                                          |
| :----------------------------- | :----- | :------------------------------------------------------------------- |
| Transaction.Card.NameOnCard    | string | Name printed on the card used for the transaction.                   |
| Transaction.Card.Number        | string | The masked credit/debit card number used for the transaction.        |
| Transaction.Card.Token         | string | Tokenized representation of the card, if available.                  |
| Transaction.Card.ExpiryMonth   | string | Expiry month of the card.                                            |
| Transaction.Card.ExpiryYear    | string | Expiry year of the card.                                             |
| Transaction.Card.Brand         | string | The brand of the payment card, such as Visa or MasterCard.           |
| Transaction.Card.Issuer        | string | The issuing bank or financial institution of the card, if available. |
| Transaction.Card.IssuerCountry | string | The country of the card issuer.                                      |
| Transaction.Card.FundingMethod | string | The funding method of the card (e.g., `credit`, `debit`).            |

##### **Customer Object**

| Input Parameter | Type   | Description               |
| :-------------- | :----- | :------------------------ |
| Customer.Name   | string | Name of the customer.     |
| Customer.Mobile | string | Customer's mobile number. |
| Customer.Email  | string | Customer's email address. |

##### **Amount Object**

| Input Parameter               | Type   | Description                                               |
| :---------------------------- | :----- | :-------------------------------------------------------- |
| Amount.BaseCurrency           | string | The base currency of your MyFatoorah account (e.g., KWD). |
| Amount.ValueInBaseCurrency    | string | The amount in the base currency.                          |
| Amount.ServiceCharge          | string | The service charge applied in base currency.              |
| Amount.ServiceChargeVAT       | string | The VAT applied to the service charge.                    |
| Amount.ReceivableAmount       | string | The amount the vendor receives in base currency.          |
| Amount.DisplayCurrency        | string | The currency used for display.                            |
| Amount.ValueInDisplayCurrency | string | The amount in display currency.                           |
| Amount.PayCurrency            | string | The currency used for payment (e.g., USD).                |
| Amount.ValueInPayCurrency     | string | The transaction value in payment currency.                |

***

#### **Sample Event**

The below sample JSON message is sent by MyFatoorah to your endpoint.

```json
{
  "Event": {
    "Code": 6,
    "Name": "DISPUTE_STATUS_CHANGED",
    "CountryIsoCode": "KWT",
    "CreationDate": "2025-07-08T11:48:50.4330000Z",
    "Reference": "WH-290725"
  },
  "Data": {
    "Dispute": {
      "Type": "CHARGEBACK",
      "Status": "PENDING",
      "CreatedDate": "2025-07-08T11:48:50.4005403Z",
      "ChargeBackType": "General",
      "Reason": "CreditNotProcessed",
      "DisputeTransactionId": 112,
      "InvoiceTransactionId": 2825348
    },
    "Invoice": {
      "Id": "5897264",
      "Status": "PAID",
      "Reference": "2025059298",
      "CreationDate": "2025-07-08T11:46:12.12Z",
      "ExternalIdentifier": "1hGonC7bf2vNuJWuTgCGURYzi6Yu"
    },
    "Transaction": {
      "Id": "203009",
      "Status": "SUCCESS",
      "PaymentMethod": "VISA/MASTER",
      "PaymentId": "07075897264282534874",
      "ReferenceId": "518911203009",
      "TrackId": "08-07-2025_2825348",
      "AuthorizationId": "993730",
      "TransactionDate": "2025-07-08T11:46:29.853Z",
      "ECI": "02",
      "Card": {
        "NameOnCard": "fa",
        "Number": "545454xxxxxx5454",
        "Token": "",
        "ExpiryMonth": "12",
        "ExpiryYear": "34",
        "Brand": "Mastercard",
        "Issuer": "Test Bank",
        "IssuerCountry": "KWT",
        "FundingMethod": "credit"
      }
    },
    "Customer": {
      "Name": "Rhianna",
      "Mobile": "+9715555555",
      "Email": "helsheikh@myfatoorah.com"
    },
    "Amount": {
      "BaseCurrency": "KWD",
      "ValueInBaseCurrency": "0.1",
      "ServiceCharge": "0.002",
      "ServiceChargeVAT": "0",
      "ReceivableAmount": "0.098",
      "DisplayCurrency": "KWD",
      "ValueInDisplayCurrency": "0.1",
      "PayCurrency": "KWD",
      "ValueInPayCurrency": "0.1"
    }
  }
}
```

***

#### **Webhook Signature**

You will use the parameters mentioned below to generate the webhook signature for the `DISPUTE_STATUS_CHANGED` event.

```json Signature
Dispute.DisputeTransactionId=112,Dispute.Status=PENDING,Invoice.Id=5897264,Invoice.Status=PAID,Transaction.Status=SUCCESS,Transaction.PaymentId=07075897264282534874,Invoice.ExternalIdentifier=1hGonC7bf2vNuJWuTgCGURYzi6Yu
```

This webhook allows real-time tracking of disputes, ensuring accurate financial management and automated processing in your system.

## Supplier Update Request Data Model

*`https://docs.myfatoorah.com/docs/webhook-v2-supplier-update-request-data-model` — updated 2026-02-16*

> Data Model

#### **Overview**

The "Supplier Update Request Status Changed" webhook is triggered whenever there is a request to edit a supplier that has been approved or rejected. This webhook ensures that your system is notified of the status of the update request, allowing for real-time updates and appropriate actions.

Your webhook endpoint will receive a **POST** request containing an event with a **Code** of **7** and **Name** of **SUPPLIER\_UPDATE\_REQUEST\_CHANGED**. This event is particularly useful for tracking the changes you have made to the suppliers.

***

#### **Request Model**

The webhook payload consists of multiple nested objects containing essential recurring payment details.

#### **Event Object**

| Input Parameter      | Type     | Description                                                  |
| :------------------- | :------- | :----------------------------------------------------------- |
| Event.Code           | number   | 7 - SUPPLIER\_UPDATE\_REQUEST\_CHANGED                       |
| Event.Name           | string   | SUPPLIER\_UPDATE\_REQUEST\_CHANGED                           |
| Event.CountryIsoCode | string   | The country of your account with MyFatoorah.                 |
| Event.CreationDate   | datetime | The timestamp when the event was created in ISO 8601 format. |
| Event.Reference      | string   | Unique reference ID for the webhook event.                   |

***

#### **Data Object**

The Data Object contains multiple nested objects as follows.

##### **Supplier Object**

| Input Parameter | Type     | Description                                           |
| :-------------- | :------- | :---------------------------------------------------- |
| Supplier.Code   | number   | The code of the supplier in MyFatoorah system.        |
| Supplier.Name   | string   | The supplier name in MyFatoorah system.               |
| Supplier.Mobile | datetime | The mobile number of the supplier.                    |
| Supplier.Email  | string   | The email of the supplier,                            |
| Supplier.Status | string   | The status of the supplier. (`APPROVED `- `REJECTED`) |

##### **RequestStatus Object**

| Input Parameter         | Type   | Description                                                                             |
| :---------------------- | :----- | :-------------------------------------------------------------------------------------- |
| RequestStatus.Status    | string | The status of the request. (`APPROVED `- `REJECTED`)                                    |
| RequestStatus.Comments  | string | The comments entered by the concerned team for the request.                             |
| RequestStatus.Reference | string | The date and time when the decision on the request was made, formatted in ISO 8601 UTC. |

#### **Sample Event**

The sample JSON message is sent by MyFatoorah to your endpoint.

```json
{
  "Event": {
    "Code": 7,
    "Name": "SUPPLIER_UPDATE_REQUEST_CHANGED",
    "CountryIsoCode": "SAU",
    "CreationDate": "2025-08-18T09:56:35.8500000Z",
    "Reference": "WH-10106895"
  },
  "Data": {
    "Supplier": {
      "Code": 236,
      "Name": "Bernita Bode",
      "Mobile": "6796176709",
      "Email": "Khalid_Mante@hotmail.com",
      "Status": "APPROVED"
    },
    "RequestStatus": {
      "Status": "APPROVED",
      "Comments": "Approve with comment",
      "ActionDate": "2025-08-18T09:56:35.8175777Z"
    }
  }
}
```

***

#### **Webhook Signature**

You will use the parameters mentioned below to generate the webhook signature for the `SUPPLIER_UPDATE_REQUEST_CHANGED` event.

```json Signature
Supplier.Code=236,RequestStatus.Status=APPROVED
```

This webhook allows real-time tracking of editing supplier requests, ensuring the supplier information is updated in your system.

## GetWebhooks

*`https://docs.myfatoorah.com/docs/getwebhooks` — updated 2026-02-16*

#### **Overview**

The `GetWebhooks` endpoint is a POST request. It is used to get all the webhook events that MyFatoorah has triggered to your endpoint based on the configuration of the webhook in your account and some details regarding their status.

This endpoint can be very powerful to help you get the webhook events that you haven't received if your server was down or slow, leading to you not getting the webhook event.

The endpoint on [Webhook\_GetWebhooks.](https://apitest.myfatoorah.com/swagger/ui/index#!/Webhook/Webhook_GetWebhooks)

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to the request header. The token of the demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following parameters:

| Parameter | Type | Optional? | Description |
|---|---|---|---|
| **Start** | string | Yes | Start date for filtering webhook events (format: ISO 8601) in UTC Time zone |
| **End** | string | Yes | End date for filtering webhook events (format: ISO 8601) in UTC Time zone |
| **Page** | integer | Yes | Page number for pagination (1-based). The number of events in a single page is 500 events. |
| **EventType** | string | Yes | **Webhook V1:**\ `TransactionsStatusChanged`, `RefundStatusChanged`, `RecurringStatusChanged`, `BalanceTransferred`, `SupplierStatusChanged`, `DisputeStatusChanged`\ **Webhook V2:**\ `PAYMENT_STATUS_CHANGED`, `REFUND_STATUS_CHANGED`, `RECURRING_UPDATES`, `BALANCE_TRANSFERED`, `SUPLIER_STATUS_CHANGED`, `DISPUTE_STATUS_CHANGED` |
| **Status** | string | Yes | Status of webhook attempts to filter: * \*Waiting:\*\* Webhook isn't yet triggered from MyFatoorah * \*Running:\*\* The webhook is triggered from MyFatoorah but we are still attempting to reach your endpoint till we get success status code or all the retries are consumed. * \*Succeeded:\*\* Webhook is successfully received on your end. * \*Failed:\*\* Your server failed to receive the webhook and all the retries are consumed. |
| **Key** | array | Yes | Array of Keys based on the KeyType |
| **KeyType** | string | Yes | * \*InvoiceId:\*\* This filters for the Payment Webhook with this InvoiceId * \*CustomerReference:\*\* This filters for the Payment Webhook with this CustomerReference. * \*WebhookReference:\*\* This filters for the webhook events that have this Webhook Reference |

***

#### **Response Model**

| Parameter            | Type    | Description                                                                                 |
| :------------------- | :------ | :------------------------------------------------------------------------------------------ |
| **IsSuccess**        | Boolean | Indicates if the request was successful                                                     |
| **Message**          | String  | Status or result message                                                                    |
| **ValidationErrors** | Array   | List of validation errors returned from the request - Always `null` in successful responses |
| **Data**             | Object  | Contains the response details                                                               |

##### Data Object

| Parameter      | Type   | Description                            |
| :------------- | :----- | :------------------------------------- |
| **Items**      | Array  | List of Webhook Event Log item objects |
| **Pagination** | Object | Pagination information                 |

##### Items Array

| Parameter | Type | Description |
|---|---|---|
| **EndPoint** | string | The URL where the webhook notification was sent |
| **Signature** | string | Signature for verifying the webhook payload |
| **EventCode** | integer | Numeric code for the event type |
| **EventName** | string | Name of the event. The name of the event is affected by the version of the webhook you are using.\ **Webhook V1:**\ `TransactionsStatusChanged`, `RefundStatusChanged`, `RecurringStatusChanged`, `BalanceTransferred`, `SupplierStatusChanged`, `DisputeStatusChanged`\ **Webhook V2:**\ `PAYMENT_STATUS_CHANGED`, `REFUND_STATUS_CHANGED`, `RECURRING_UPDATES`, `BALANCE_TRANSFERED`, `SUPLIER_STATUS_CHANGED`, `DISPUTE_STATUS_CHANGED` |
| **EventEntityId** | string | Identifier for the entity associated with the event |
| **WebhookReference** | string | Unique reference for the webhook event |
| **Data** | object | The Webhook Data Content |
| **Status** | string | Status of the webhook event delivery. * \*Waiting:\*\* Webhook isn't yet triggered from MyFatoorah * \*Running:\*\* The webhook is triggered from MyFatoorah but we are still attempting to reach your endpoint till we get success status code or all the retries are consumed. * \*Succeeded:\*\* Webhook is successfully received on your end. * \*Failed:\*\* Your server failed to receive the webhook and all the retries are consumed. |
| **Attempts** | array | List of delivery attempt objects |

##### Attempts Array

| Parameter        | Type    | Description                                                              |
| :--------------- | :------ | :----------------------------------------------------------------------- |
| Date             | string  | Datetime of the attempt (format: ISO 8601) in UTC Time zone              |
| Status           | integer | The status code we received from your server for the attempt             |
| Response Message | string  | The message corresponding to the status code we received.                |
| Duration         | string  | The number of seconds it took for your server to respond to the attempt. |

##### Pagination Object

| Parameter      | Type    | Descripton                                                    |
| :------------- | :------ | :------------------------------------------------------------ |
| **PageSize**   | integer | The size of the page (Fixed: 500)                             |
| **PageNumber** | integer | The page you are currently on.                                |
| **PagesCount** | integer | The number of pages with webhook events matching the filters. |
| **ItemsCount** | integer | The number of webhook events matching the filters.            |

#### Sample Request & Response

```json Sample Request
{
    "Start": "2024-02-12T00:16:29.650Z",
    "End": "2025-02-12T20:43:43.848Z",
    "Status": "Failed",
}
```
```json Sample Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "Items": [
            {
                "EndPoint": "https://webhook.site/2a9d88c8-1329-4ad4-a163-57cac6986547",
                "Signature": "N+TPuke9hEn963L1zQbXF3zSR2zx7O/RgM4eCavfP8A=",
                "EventCode": 1,
                "EventName": "TransactionsStatusChanged",
                "EventEntityId": "5131277",
                "WebhookReference": "WH-24349",
                "Data": {
                    "InvoiceId": 5131277,
                    "InvoiceReference": "2025000148",
                    "CreatedDate": "12022025154151",
                    "CustomerReference": "rLVgyA7C6v19bHwpXEIDrA5wW",
                    "CustomerName": "sdadsaas sad Ggaga",
                    "CustomerMobile": "+966",
                    "CustomerEmail": "test@test.com",
                    "TransactionStatus": "SUCCESS",
                    "PaymentMethod": "VISA/MASTER",
                    "UserDefinedField": "rLVgyA7C6v19bHwpXEIDrA5wW",
                    "ReferenceId": "504312229134",
                    "TrackId": "12-02-2025_2485915",
                    "PaymentId": "07075131277248591573",
                    "AuthorizationId": "229134",
                    "InvoiceValueInBaseCurrency": "112.185",
                    "BaseCurrency": "KWD",
                    "InvoiceValueInDisplayCurreny": "30.29",
                    "DisplayCurrency": "USD",
                    "InvoiceValueInPayCurrency": "112.19",
                    "PayCurrency": "SAR"
                },
                "Status": "Failed",
                "Attempts": [
                    {
                        "Date": "2025-02-12T12:42:16.1218556Z",
                        "Status": 404,
                        "ResponseMessage": "Not Found",
                        "Duration": "0.056"
                    },
                    {
                        "Date": "2025-02-12T12:42:51.2235299Z",
                        "Status": 404,
                        "ResponseMessage": "Not Found",
                        "Duration": "0.052"
                    },
                    {
                        "Date": "2025-02-12T12:43:21.2716528Z",
                        "Status": 404,
                        "ResponseMessage": "Not Found",
                        "Duration": "0.073"
                    },
                    {
                        "Date": "2025-02-12T12:43:51.2951604Z",
                        "Status": 404,
                        "ResponseMessage": "Not Found",
                        "Duration": "0.048"
                    }
                ]
            },
            {
                "EndPoint": "https://webhook.site/2a9d88c8-1329-4ad4-a163-57cac6986547",
                "Signature": "QcNL1eSkVJuXqsC5zF7bCzKfn0c+DuFWP/LFQlRkrjQ=",
                "EventCode": 1,
                "EventName": "TransactionsStatusChanged",
                "EventEntityId": "5131253",
                "WebhookReference": "WH-24344",
                "Data": {
                    "InvoiceId": 5131253,
                    "InvoiceReference": "2025000147",
                    "CreatedDate": "12022025153902",
                    "CustomerReference": "rt3V3n4nBpSdkF5HQLsRs52NV",
                    "CustomerName": "sdadsaas sad Ggaga",
                    "CustomerMobile": "+966",
                    "CustomerEmail": "test@test.com",
                    "TransactionStatus": "SUCCESS",
                    "PaymentMethod": "VISA/MASTER",
                    "UserDefinedField": "rt3V3n4nBpSdkF5HQLsRs52NV",
                    "ReferenceId": "504312227034",
                    "TrackId": "12-02-2025_2485907",
                    "PaymentId": "07075131253248590772",
                    "AuthorizationId": "227034",
                    "InvoiceValueInBaseCurrency": "260.444",
                    "BaseCurrency": "KWD",
                    "InvoiceValueInDisplayCurreny": "70.32",
                    "DisplayCurrency": "USD",
                    "InvoiceValueInPayCurrency": "260.45",
                    "PayCurrency": "SAR"
                },
                "Status": "Failed",
                "Attempts": [
                    {
                        "Date": "2025-02-12T12:39:16.9598573Z",
                        "Status": 400,
                        "ResponseMessage": "Bad Request",
                        "Duration": "0.066"
                    },
                    {
                        "Date": "2025-02-12T12:39:51.0342446Z",
                        "Status": 400,
                        "ResponseMessage": "Bad Request",
                        "Duration": "0.048"
                    },
                    {
                        "Date": "2025-02-12T12:40:36.0741091Z",
                        "Status": 400,
                        "ResponseMessage": "Bad Request",
                        "Duration": "0.073"
                    },
                    {
                        "Date": "2025-02-12T12:41:06.1305213Z",
                        "Status": 400,
                        "ResponseMessage": "Bad Request",
                        "Duration": "0.048"
                    }
                ]
            }
        ],
        "Pagination": {
            "PageSize": 500,
            "PageNumber": 1,
            "PagesCount": 1,
            "ItemsCount": 2
        }
    }
}

```
