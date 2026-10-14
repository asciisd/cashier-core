# MyFatoorah — Multi-vendor (suppliers)

## Multi-Vendors

*`https://docs.myfatoorah.com/docs/multiple-suppliers` — updated 2026-02-16*

> Multiple Suppliers

#### **Introduction**

Base on the integration types that **MyFatoorah** supports now, we have added a great feature that allows you to add **submerchants** (known as **suppliers** in MyFatoorah system) under your account. So that you will be able to add them, specify the rate of each supplier, and start collecting payments for them.

> ❗️ Approval is Needed
>
> Kindly contact your [account manager](https://www.myfatoorah.com/contact.html) or sales representative to activate the **Multi-Vendors** feature.

***

#### **How it works**

<Embed url="https://www.youtube.com/watch?v=T-3Gvm1Mio8" href="https://www.youtube.com/watch?v=T-3Gvm1Mio8" typeOfEmbed="youtube" html="%3Ciframe%20class%3D%22embedly-embed%22%20src%3D%22%2F%2Fcdn.embedly.com%2Fwidgets%2Fmedia.html%3Fsrc%3Dhttps%253A%252F%252Fwww.youtube.com%252Fembed%252FT-3Gvm1Mio8%253Ffeature%253Doembed%26display_name%3DYouTube%26url%3Dhttps%253A%252F%252Fwww.youtube.com%252Fwatch%253Fv%253DT-3Gvm1Mio8%26image%3Dhttps%253A%252F%252Fi.ytimg.com%252Fvi%252FT-3Gvm1Mio8%252Fhqdefault.jpg%26type%3Dtext%252Fhtml%26schema%3Dyoutube%22%20width%3D%22854%22%20height%3D%22480%22%20scrolling%3D%22no%22%20title%3D%22YouTube%20embed%22%20frameborder%3D%220%22%20allow%3D%22autoplay%3B%20fullscreen%3B%20encrypted-media%3B%20picture-in-picture%3B%22%20allowfullscreen%3D%22true%22%3E%3C%2Fiframe%3E" />

> 📘 Portal Account
>
> The below steps describe how to integrate the Multi-Vendors API feature into your application. Also, you can use your portal account to configure multiple suppliers (Multi-Vendors) as described in the [Supplier Information](https://docs.myfatoorah.com/docs/supplier-information) section.

Kindly follow the steps below to fulfill the multi-vendors integration in your system:

1. Call [CreateSupplier](https://docs.myfatoorah.com/docs/create-supplier) endpoint to create a new supplier record and provide the required parameters: **SupplierName**, **Mobile**, and **Email**.

2. After adding the supplier, you need to manage all related data. Call [UploadSupplierDocument](https://docs.myfatoorah.com/docs/upload-supplier-document) endpoint to upload the document files related to this supplier. The required documents to approve a supplier can be reviewed with the [account manager](https://www.myfatoorah.com/contact.html).

3. Once the documents are uploaded, the KYC team reviews them and makes a decision. You will get a [webhook ](https://docs.myfatoorah.com/docs/webhook-v2-supplier-data-model)with approval or rejection and the rejection reason.

4. Now, you can [create a supplier payment](#create-supplier-invoice) using either POST Sessions or POST Payments.

5. You can track the balance of each supplier by calling [GetSupplierDashboard](https://docs.myfatoorah.com/docs/get-supplier-dashboard) endpoint.

6. Finally, call [GetSupplierDeposits](https://docs.myfatoorah.com/docs/get-supplier-deposits) endpoint to check for the earned deposit from that supplier.

Furthermore, **MyFatoorah** developed additional API endpoints that help in fully integrating the  Multi-Vendors feature into your platform, as described next:

* Call [EditSupplier](https://docs.myfatoorah.com/docs/edit-supplier) endpoint if you need to update the supplier details.
* Call [GetSuppliers](https://docs.myfatoorah.com/docs/get-suppliers) endpoint to show a list of your suppliers.
* Call [GetSupplierDocuments](https://docs.myfatoorah.com/docs/get-supplier-documents) endpoint to list the uploaded documents.
* Call [TransferBalance](https://docs.myfatoorah.com/docs/transferbalance) endpoint to transfer a balance to or from the awaiting balance of a supplier.

***

#### **Create Supplier Invoice**

You can use [Create Session](https://docs.myfatoorah.com/reference/create-session) or [Create Payment](https://docs.myfatoorah.com/reference/create-payment) endpoints to create an invoice request with no suppliers, one supplier, or many suppliers. When enabling the multi-vendors feature, you can provide suppliers' information in the request body. To do so, use the **Suppliers** parameter as shown in the following sample object.

```json
  "Suppliers": [
    {
      "SupplierCode": 0,
      "ProposedDepositShare": 0, //Optional
      "InvoiceShare": 0
    }
  ]
```

**SupplierCode** Parameter:

* The code is on the supplier list page after creating a supplier at your **MyFatoorah** account.
* Also, you can use the [CreateSupplier](https://docs.myfatoorah.com/docs/create-supplier) endpoint to create the suppliers, and you will get the code from the response body.
* If you have already created the suppliers and need to get their codes through the API, you can use the [GetSuppliers](https://docs.myfatoorah.com/docs/get-suppliers) endpoint. It will retrieve all the suppliers you have in your account with all of their information.

**InvoiceShare** Parameter:

* It is the supplier's share of the invoice, and the sum of the suppliers' **InvoiceShare** must be equal to the **Order.Amount** parameter.
* For example, an invoice contains two suppliers. The first supplier products cost 30, and the second supplier products cost 70. Therefore the first supplier **InvoiceShare** should be set to 30, the second supplier **InvoiceShare** should be 70, and the Order Amount should be 100.

**ProposedDepositShare** Parameter:

* This parameter allows setting a specific amount that the supplier will get after paying the invoice.
* This parameter can be null or a value.
* If the **ProposedDepositShare** parameter is null, the system will calculate the vendor commission based on the fixed commission value and the commission percentage that the vendor added for this supplier. For example: If the **InvoiceShare** equals 100, the **vendor** will get ((**InvoiceShare** X **Commission Percentage**) + **Commission Value**).
* If the ProposedDepositShare is used instead of null, the supplier will get this amount, and the system will neglect the vendor fixed and percentage commission calculation.
* If the **ProposedDepositShare** parameter is a value, make sure that the value of the **ProposedDepositShare** parameter will be less than or equal to the **InvoiceShare** parameter.
* If **ProposedDepositShare** equals **InvoiceShare** parameter, the vendor will get a "zero" commission from the suppliers, and the supplier will gain the total amount of the **InvoiceShare** parameter after deducting the transaction fees.
* If **ProposedDepositShare** equals to **InvoiceShare** parameter, and the payment service charge is on the vendor, the last supplier - at the end of the supplier list - will get the amount of the **ProposedDepositShare** parameter minus the cost of the payment service charge.

***

#### **POST Payments with Suppliers Sample Message**

```json Request
{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 20
    },
    "Suppliers": [
        {
            "SupplierCode": 1,
            "ProposedDepositShare": 10,
            "InvoiceShare": 20
        }
    ]
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": "6389754",
        "PaymentId": null,
        "PaymentURL": "https://demo.MyFatoorah.com/KWT/ie/050712180638975463-d0cb4489",
        "PaymentCompleted": false,
        "TransactionDetails": null
    }
}
```

***

#### **POST Sessions with Suppliers Sample Message**

```json Request
{
    "PaymentMode": "COMPLETE_PAYMENT", 
    "Order": {
        "Amount": 20
    },
        "Suppliers": [
        {
            "SupplierCode": 1,
            "ProposedDepositShare": 10,
            "InvoiceShare": 20
        }
    ]
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "SessionId": "KWT-9f85547d-1a9e-4247-8511-e5818681e165",
        "SessionExpiry": "2025-12-24T19:38:07.1220714Z",
        "EncryptionKey": "Lr+AUZVza1OwbLNZ9z//6A4jsBnCEBtJqxhBdskr+KE=",
        "OperationType": "PAY",
        "Order": {
            "Amount": 20.0,
            "Currency": "KWD",
            "ExternalIdentifier": null
        },
        "Customer": {
            "Reference": null,
            "Cards": null
        }
    }
}
```

<br />

## Supplier Information

*`https://docs.myfatoorah.com/docs/supplier-information` — updated 2026-02-16*

> 🚧 Usage
>
> Please note that the Multi-Vendor feature can only be used through API communication to take place. The portal part is only designated to manage the suppliers' information and control. So, simply you can use the [SendPayment](https://docs.myfatoorah.com/docs/send-payment) and [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoints and passing the value of the supplier code in the **SupplierCode** parameter.

#### **Add a Supplier**

Kindly, follow the below instructions to add a new supplier (vendor) to your portal account.

1. Log in to the [Myfatoorah portal](https://portal.myfatoorah.com/) using your **Super Master Account**.
2. Navigate to **Suppliers** → **Suppliers List**.
3. Click on the **Add Supplier** button
4. Fill in the required information and click Create.

![](https://files.readme.io/fa3e8a255997be8c04984f2a119e10023c76b1b5726c5e2b8233fcc1e0a94e8a-image.png)

#### **Supplier Management**

From your admin account on the MyFatoorah portal, you can easily add, edit, and delete suppliers under your account. Once you create a supplier, you will be able to manage all related data as follows:

* **Bank Information:** The main supplier bank information to which MyFatoorah will transfer the due amount.
* **Deposit Terms:** You can specify the deposit interval for each supplier (Daily / Weekly /Monthly/ onDemand)

> 👍 Identify Supplier Invoice Practice
>
> To easily identify a supplier invoices, we would recommend you to add the supplier code as prefix in **CustomerReference** parameter in the request of the creation for both [Send Payment](https://docs.myfatoorah.com/docs/send-payment) and [Execute Payment](https://docs.myfatoorah.com/docs/execute-payment)

#### **Supplier Commission Rate and Deposits**

Once you have enabled the multi-vendor feature, you can control the commission rate for each supplier as well as the deposits. Please note that MyFatoorah is depositing the remaining invoice value after charging MyFatoorah commission, subsequently charging your commission from the supplier. Also, you can easily access the deposit list that has been associated with each supplier as per the screen below.

![](https://files.readme.io/495e34fc43507efba87da0cb7c6ae66aefe4694ab1d3cba2bf43d4d3c75edaf4-image.png)

> 📘 Low Value Invoice
>
> If you have created a low value invoice, MyFatoorah will charge its commission first, then yours. If nothing is remaining then **No Deposits** will be shown to the supplier

## Rejection Reasons

*`https://docs.myfatoorah.com/docs/rejection-reasons` — updated 2026-02-16*

#### **RejectReasons**

| Reason Id | Reason                                                 |
| :-------- | :----------------------------------------------------- |
| 1         | Expired Company License                                |
| 2         | Expired Commercial Registration CR                     |
| 3         | Expired Trade License                                  |
| 4         | Expired Owner Civil ID                                 |
| 5         | Expired Partner/BoD member Civil ID                    |
| 6         | Expired Freelance Certificate                          |
| 7         | Expired Authorise Signatory - Kuwait                   |
| 8         | Expired VAT Certificate                                |
| 9         | Missing Article of Association AOA                     |
| 10        | Missing Company License                                |
| 11        | Missing Commercial Registration                        |
| 12        | Missing Trade License                                  |
| 13        | Missing Owner Civil ID                                 |
| 14        | Missing Partner/BoD member Civil ID                    |
| 15        | Missing Freelance Certificate                          |
| 16        | Missing Bank approval letter- Home Business            |
| 17        | Missing Authorise Signatory - Kuwait                   |
| 18        | Missing Bank Certificate                               |
| 19        | Missing VAT certificate                                |
| 20        | Missing Charity Approval/ Certificate                  |
| 21        | Missing Contract                                       |
| 22        | Missing Regulation List -  KSA                         |
| 23        | Missing Establishment card/ Computer card              |
| 24        | Unclear Article of Association                         |
| 25        | Unclear Company License                                |
| 26        | Unclear Commercial Registration                        |
| 27        | Unclear Owner Civil ID                                 |
| 28        | Unclear Partner/BoD member Civil ID                    |
| 29        | Unclear Freelance Certificate                          |
| 30        | Unclear Bank approval letter- Home Business            |
| 31        | Unclear Authorise Signatory - Kuwait                   |
| 32        | Unclear Bank Certificate                               |
| 33        | Unclear Charity Approval/Certificate                   |
| 34        | Unclear contract                                       |
| 35        | Unclear Regulation List -  KSA                         |
| 36        | Wrong bank Account                                     |
| 37        | Using Personal bank                                    |
| 38        | Inaccurate Article of Association AOA                  |
| 39        | Inaccurate Company License                             |
| 40        | Inaccurate Commercial Registration                     |
| 41        | Inaccurate Owner Civil ID                              |
| 42        | Inaccurate Partner/BoD member Civil ID                 |
| 43        | Inaccurate Freelance Certificate                       |
| 44        | Inaccurate Authorise Signatory                         |
| 45        | Inaccurate VAT Certificate                             |
| 46        | Inaccurate Bank Certificate                            |
| 47        | Inaccurate BOD agreement                               |
| 48        | Inaccurate Charity Approval/ Certificate               |
| 49        | Inaccurate Regulation list                             |
| 50        | Contract signed by unauthorized signatory              |
| 51        | Banned Activity                                        |
| 52        | Activity is not matching Company License               |
| 53        | Activity is not matching Commercial Registeration      |
| 54        | Activity is not Matching the Freelance Certificate     |
| 55        | Activity requires License                              |
| 56        | The activity requires Commercial registration          |
| 57        | Update Company License to include the activity         |
| 58        | Update Commercial Registration to include the activity |
| 59        | Rejected by Risk/ Management                           |
| 60        | Matching with the Sanctions list                       |
| 61        | Suspicious nature of business                          |
| 62        | Business closed / not active                           |
| 63        | Registered twice by mistake                            |
| 64        | Business outside the country's jurisdiction            |
| 65        | Wrong email, require to register again                 |
| 66        | Inaccurate National Address                            |
| 67        | Missing National address                               |
| 68        | Missing Investment license                             |
| 69        | Expired Investment license                             |
| 70        | Missing BOD                                            |
| 71        | Expired BOD                                            |
| 72        | Missing Shareholders Register                          |
| 73        | Expired Shareholders Register                          |
| 74        | Inaccurate Shareholders Register                       |
| 75        | No Product                                             |
| 76        | Inaccurate Investment license                          |

***

## CreateSupplier

*`https://docs.myfatoorah.com/docs/create-supplier` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "CreateSupplier" endpoint is a POST request. It is used to add a new supplier to your **Myfatoorah** account. Detailed functionality of how to use this endpoint is explained in the [Multiple Suppliers](https://docs.myfatoorah.com/docs/multiple-suppliers) section.

The endpoint on Swagger is [CreateSupplier](https://apitest.myfatoorah.com/swagger/ui/index#!/Supplier/Supplier_CreateSupplier).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following parameters:

| Input Parameter | Type | Description |
|---|---|---|
| **SupplierName** | string |  |
| **Mobile** | string |  |
| **Email** | string |  |
| **CommissionValue** | number, optional | A fixed value which will be deducted from each transaction. |
| **CommissionPercentage** | number, optional | A percentage value which will be deducted from each transaction. |
| **IsPercentageOfNetValue** | boolean, optional | * **true**: deduct the percentage from the (total amount - transaction fees) * **false**: deduct the percentage from the total amount Affects only in case of one supplier in the request. |
| **DepositTerms** | string, optional | * **Daily** for daily deposits * **Weekly** for weekly deposits * **Monthly** for monthly deposits * **OnDemand** for OnDemand, i.e. on hold deposits |
| **DepositDay** | string, optional | You specify on which day you want the deposit to take place. It is effective only **weekly** and **monthly**. * **Weekly**: You can enter values between**1**and**5**. This represents Sunday to Thursday. You can enter multiple days if they are separated by a comma only. "1,3,5" * **Monthly**: You can enter values between**1 **and**30**. It accepts only a single value. |
| **BankId** | integer, optional | It must be from the bank list received from [GetBanks](https://docs.myfatoorah.com/docs/getbanks) |
| **BankAccountHolderName** | string, optional | It should be string without any special characters or numbers |
| **BankAccount** | string, optional | only numbers |
| **Iban** | string, optional | It should be valid [IBAN](https://en.wikipedia.org/wiki/International_Bank_Account_Number#IBAN_formats_by_country). |
| **IsActive** | boolean, optional |  |
| **LogoFile** | [HttpFile](#httpfile) Model, optional |  |
| **DisplaySupplierDetails** | boolean, optional | * **true**: The details of the suppliers will be displayed on the invoice page instead of the vendor. * **false**: The vendor details will be displayed on the invoice. This is effective only if there is **one **supplier in the invoice. |
| **BusinessName** | string, optional | The name of the business that will be displayed on the invoice. |
| **BusinessType** | number, optional | 1: Home Business 2: Company |

***

#### HttpFile

| Input Parameter | Type             | Description |
| :-------------- | :--------------- | :---------- |
| **FileName**    | string, optional |             |
| **MediaType**   | string, optional |             |
| **Buffer**      | string, optional |             |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field    | Type    | Description |
| :---------------- | :------ | :---------- |
| **SupplierCode**  | integer |             |
| **SupplierEmail** | string  |             |
| **Date**          | string  |             |

***

#### **Sample Message**

```json Request
{
  "SupplierName": "Lonny Williamson",
  "Mobile": "374-415-4939",
  "Email": "Carter.Hammes43@hotmail.com",
  "CommissionValue": 0.5,
  "IsPercentageOfNetValue": "true",
  "CommissionPercentage": 2,
  "DepositTerms": "Daily",
  "BankId": "1",
  "BankAccountHolderName": "Margarita Beer",
  "BankAccount": "12345",
  "Iban": "KW76KSFM1197681842334764641317",
  "IsActive": "false",
  "BusinessType": 1
}
```
```json Response
{
  "IsSuccess": true,
  "Message": "The Supplier Created Successfully!",
  "FieldsErrors": null,
  "Data": {
    "SupplierCode": 175,
    "SupplierEmail": "Carter.Hammes43@hotmail.com",
    "Date": "2024-03-20T12:04:49.3812998+03:00"
  }
}
```

***

## EditSupplier

*`https://docs.myfatoorah.com/docs/edit-supplier` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "EditSupplier" endpoint is a POST request. It is used to edit information about a certain supplier. Detailed functionality of how to use this endpoint is explained in the [Multiple Suppliers](https://docs.myfatoorah.com/docs/multiple-suppliers) section.

The endpoint on Swagger is [EditSupplier](https://apitest.myfatoorah.com/swagger/ui/index#!/Supplier/Supplier_EditSupplier).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

If the supplier is approved, the request to update the supplier will be reviewed first by MyFatoorah team before approving or rejecting it. While a request is under review, you cannot create another request.

Upon approval or rejection of the changes, you will receive a webhook. For more information, please check [Supplier Update Request Data Model](https://docs.myfatoorah.com/docs/webhook-v2-supplier-update-request-data-model)

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following parameters:

| Input Parameter | Type | Description |
|---|---|---|
| **SupplierCode** | integer |  |
| **SupplierName** | string |  |
| **Mobile** | string |  |
| **Email** | string |  |
| **CommissionValue** | number, optional | A fixed value that will be deducted from each transaction. |
| **CommissionPercentage** | number, optional | A percentage value that will be deducted from each transaction. |
| **IsPercentageOfNetValue** | boolean, optional | * \*true\*\*: deduct the percentage from the (total amount - transaction fees) * \*false\*\*: deduct the percentage from the total amount\ Affects only in case of one supplier in the request. |
| **DepositTerms** | string, optional | * \*Daily\*\* for daily deposits * \*Weekly\*\* for weekly deposits * \*Monthly\*\* for monthly deposits * \*OnDemand\*\* for OnDemand, i.e. on hold deposits |
| **DepositDay** | string, optional | It is accepted in case of **Weekly**and **Monthly** * \*Weekl&#x79;**: You can pass values between**1 **and**5\*\*. You can add multiple values. * \*Monthl&#x79;**: You can pass values between**1**and**30\*\*. It accepts only a single value. |
| **BankId** | integer, optional | It must be from the bank list received from [GetBanks](https://docs.myfatoorah.com/docs/getbanks) |
| **BankAccountHolderName** | string, optional | It should be string without any special characters or numbers |
| **BankAccount** | string, optional | only numbers |
| **Iban** | string, optional | It should be valid [IBAN](https://en.wikipedia.org/wiki/International_Bank_Account_Number#IBAN_formats_by_country). |
| **LogoFile** | [HttpFile](#httpfile) Model, optional |  |
| **BusinessName** | string, optional |  |
| **DisplaySupplierDetails** | boolean, optional |  |

***

#### HttpFile

| Input Parameter | Type             | Description |
| :-------------- | :--------------- | :---------- |
| **FileName**    | string, optional |             |
| **MediaType**   | string, optional |             |
| **Buffer**      | string, optional |             |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field    | Type    | Description |
| :---------------- | :------ | :---------- |
| **SupplierCode**  | integer |             |
| **SupplierEmail** | string  |             |
| **Date**          | string  |             |

***

#### **Sample Message**

```json Request
{
  "SupplierCode": 115,
  "SupplierName": "supplier_name",
  "Mobile": "string",
  "Email": "a@b.xyz",
  "CommissionValue": 0,
  "CommissionPercentage": 0,
  "DepositTerms": "Daily",
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "The Supplier Updated Successfully!",
    "FieldsErrors": null,
    "Data": {
        "SupplierCode": 115,
        "SupplierEmail": "a@b.xyz",
        "Date": "2020-11-24T11:08:00.2220936+03:00"
    }
}
```

***

## CustomizeSupplierCommissions

*`https://docs.myfatoorah.com/docs/customizesuppliercommissions` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "CustomizeSupplierCommissions" endpoint is a POST request. It is used to set a customized commission for the supplier based on the payment method that will be used to make the payment. Detailed functionality of how to use this endpoint is explained in the [Multiple Suppliers](https://docs.myfatoorah.com/docs/multiple-suppliers) section.

The endpoint on Swagger is [Supplier\_CustomizeSupplierCommissions](https://apitest.myfatoorah.com/swagger/ui/index#!/Supplier/Supplier_CustomizeSupplierCommissions).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following parameters:

| Input Parameter         | Type                                                                                                                      | Description |
| :---------------------- | :------------------------------------------------------------------------------------------------------------------------ | :---------- |
| **SupplierCode**        | integer                                                                                                                   |             |
| **SupplierCommissions** | [SupplierCommissions](https://docs.myfatoorah.com/docs/customizesuppliercommissions#SupplierCommissions) object, optional |             |

***

#### SupplierCommissions

This parameter is used for payment methods that have the **authorization and capture** feature enabled.

| Input Parameter | Type | Value |
|---|---|---|
| PaymentMethodId | integer |  |
| CommissionValue | integer | A fixed value that will be deducted from each transaction. |
| CommissionPercentage | integer | A percentage value that will be deducted from each transaction. |
| IsPercentageOfNetValue | Boolean | * \*true\*\*: deduct the percentage from the (total amount - transaction fees) * \*false\*\*: deduct the percentage from the total amount\ Affects only in case of one supplier in the request. |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field    | Type    | Description |
| :---------------- | :------ | :---------- |
| **SupplierCode**  | integer |             |
| **SupplierEmail** | string  |             |
| **Date**          | string  |             |

***

#### **Sample Message**

```json Request
{
    "SupplierCode": 11,
    "SupplierCommissions": [
        {
            "PaymentMethodId": 1,
            "CommissionValue": 1,
            "CommissionPercentage": 1,
            "IsPercentageOfNetValue": true
        },
        {
            "PaymentMethodId": 11,
            "CommissionValue": 0.1,
            "CommissionPercentage": 10,
            "IsPercentageOfNetValue": false
        }
    ]
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "The Supplier Updated Successfully!",
    "FieldsErrors": null,
    "Data": {
        "SupplierCode": 11,
        "SupplierEmail": "test@test.com",
        "Date": "2022-06-21T12:25:00.94"
    }
}
```

***

## Amount Distribution with Suppliers

*`https://docs.myfatoorah.com/docs/amount-distribution` — updated 2026-02-16*

> Describes how much the suppliers, the vendor, and MyFatoorah take from the invoice.

#### **Introduction**

In this section, we will illustrate how the amounts are distributed between the vendor and the suppliers added to the request. This will allow you to determine precisely your share of the invoice and how much each supplier will take.

***

#### **Types of amount distribution**

There are two ways of distributing the amount between you and the suppliers:

* **Commission Value & Commission Percentage** (If you have a fixed value, percentage, or both, use this as described below)
* **Proposed Deposit Share** (If the amount deducted varies from one case to another, use this as described below)

***

#### Commission Value & Commission Percentage

Commission Value: The amount that the vendor will take as a commission regardless of the value of the invoice.\
Commission Percentage: The percentage of the amount that the vendor will take from the value of the invoice.

Kindly notice that you can choose both of them, one of them, or neither of them (Set the unwanted parameter to 0)\
These amounts are determined during the creation of the supplier and can be edited again later either by using the APIs or from your MyFatoorah dashboard. [CreateSupplier](https://docs.myfatoorah.com/docs/create-supplier) & [EditSupplier](https://docs.myfatoorah.com/docs/edit-supplier)

> 🚧 Percentage from Net Value
>
> You can choose your percentage to be calculated from the net value of the invoice instead of the total value of the invoice.\
> *Net Value = Total Value - MyFatoorah Commissions - VAT Value*\
> **This feature is available if and only if there is one supplier in the invoice.**\
> In case there is more than one supplier in the invoice and the feature is activated, the commission percentage of the vendor will be calculated from the total value of the invoice.

##### Custom Commission for Payment Method

You have the flexibility to establish customized commission values and commission percentages for suppliers, tailored to the specific payment methods employed for transactions. If a customized commission has been configured for a specific payment method, and this payment method is utilized for the transaction, MyFatoorah will apply the specified customized commissions accordingly.

> 👍 Default Commissions
>
> In cases where the employed payment method is not included in the customized commission settings for the supplier, MyFatoorah will resort to utilizing the default commission value and percentage assigned to that particular supplier.

***

#### Proposed Deposit Share

Proposed Deposit Share is a parameter that you enter in the Suppliers parameter array in your request to MyFatoorah.\
The amount that you enter in the ProposedShare is the amount that the supplier will get and the rest of the amount will be transferred to the vendor.

> 🚧 Calculation of Vendor Commission
>
> If the ProposedShare parameter is entered, it will overwrite the Commission Value & Commission Percentage.\
> If the ProposedShare parameter is not entered or entered as null, the commission of the vendor will be calculated based on the Commission Value & Commission Percentage.

***

#### **MyFatoorah Commission Calculation**

In this section, we are going to clarify how MyFatoorah commission is calculated then we will demonstrate this by using examples.

> 📘 MyFatoorah Commission
>
> For the sake of the discussion, MyFatoorah commission = Transaction Fees + VAT value.

***

#### 1. Proposed Deposit Share = null, One supplier:

1. MyFatoorah commission will be deducted from the invoice value.\
   **Net Invoice Value = Invoice Value - MyFatoorah Commission**
2. Vendor commission will be deducted.\
   **Vendor Commission = Commission Value + Commission Percentage x Invoice Value**
3. The supplier receives the remaining amount.\
   **Supplier Deposit Share = New invoice Value - Vendor Commission**

**Example1**:\
Invoice Value = 100, Commission value = 0.5, Commission percentage = 2%,\
MyFatoorah commission value = 1, MyFatoorah commission percentage= 1%, VAT = 5%,\
Percentage from net value: false

**Deducting MyFatoorah commission**:\
Net Invoice Value = 100 - (1 + 0.01 x 100 + 0.05 x 100) = **93**\
**Vendor commission**:\
Vendor Commission = 0.5 + 0.02 x 100 = **2.5**\
**Supplier deposit share**:\
Supplier Deposit Share = 100 - (1 + 0.01 x 100 + 0.05 x 100) - (0.5 + 0.02 x 100) = **90.5**

***

**Example2**:\
Invoice Value = 100, Commission value = 0.5, Commission percentage = 2%,\
MyFatoorah commission value = 1, MyFatoorah commission percentage= 1%, VAT = 5%,\
Percentage from net value: true

**Deducting MyFatoorah commission**:\
Net Invoice Value = 100 - (1 + 0.01 x 100 + 0.05 x 100) = **93**\
**Vendor commission**:\
Vendor commission = 0.5 + 0.02 x 93 = **2.36**\
**Supplier deposit share**:\
Supplier Deposit Share = 100 - (1 + 0.01 x 100 + 0.05 x 100) - (0.5 + 0.02 x 93) = **90.64**

***

#### 2. Proposed Deposit Share = null, Multiple suppliers:

1. MyFatoorah commission will be deducted from each supplier in the same percentage of their invoice shares of the total invoice value respectively.\
   **For example** : Supplier 1 invoice share = 100, Supplier 2 invoice share = 200,\
   MyFatoorah Commission = 6\
   Deduction from Supplier 1 = 2, Deduction from Supplier 2 = 4\
   Please notice that for supplier 1: Invoice Share Percentage = Deduction Percentage = 33.33%\
   Supplier 2: Invoice Share Percentage = Deduction Percentage = 66.66%\
   **Supplier Amount = Invoice share - MyFatoorah commission from each supplier**

2. The vendor takes his commission from each supplier based on the Commission Percentage & Commission Value of each one\
   **Supplier Deposit Share = Supplier amount - Vendor Commission**

**Example3**:\
Invoice Value = 100, Supplier 1: Commission value = 0.5, Commission percentage = 2%, Invoice Share = 40\
Supplier 2: Commission value = 0.8, Commission percentage = 5%, Invoice Share = 60\
MyFatoorah Commission = 12.65

Deducting MyFatoorah Commission:\
Supplier1 MyFatoorah Commission = 40 - (12.65 x 2 / 5) = 34.94\
Supplier2 MyFatoorah Commission = 60 - (12.65 x 3 / 5) = 52.41

Vendor Commission:\
Supplier1 = 0.5 + 0.02 x 40 = 1.3\
Supplier2 = 0.8 + 0.05 x 60 = 3.8

Deducting Vendor Commission:\
Supplier1 Deposit Share = 40 - (12.65 x 2 / 5) - (0.5 + 0.02 x 40) = 33.64\
Supplier2 Deposit Share = 60 - (12.65 x 3 / 5) - (0.8 + 0.05 x 60) = 48.61

***

#### 3. Proposed Deposit Share = value, Single Supplier

1. The supplier receives the amount entered in the ProposedShare parameter.
2. The vendor receives the remaining part of the amount.
3. MyFatoorah commission is deducted from the vendor.

**Example3**:\
Invoice Value = 100, Proposed Deposit Share = 95, MyFatoorah Commission = 4

Vendor Deposit share = 100 - 95 - 4 = 1\
Supplier Deposit Share = 95

***

#### 4. Proposed Deposit Share = value, Multiple Suppliers

Same steps as a single supplier. So, we will give an example:

**Example4:**\
Invoice Value = 100,\
Supplier1: Invoice Share = 40, Proposed Deposit Share = 35\
Supplier2: Invoice Share = 60, Proposed Deposit Share = 55\
MyFatoorah Commission = 5

Vendor Deposit Share = 100 - 55 - 35 - 5 = 5\
Supplier1 Deposit Share = 35\
Supplier2 Deposit Share = 55

> 🚧 MyFatoorah Commission
>
> If the vendor doesn't have enough balance in the invoice for MyFatoorah commission, the rest of the amount is taken from the last supplier entered in the request.

***

#### **MyFatoorah Calculations of Suppliers Amount**

There are two ways to find out how much money was received by the suppliers, the vendor, and MyFatoorah for each order.\
**Orders List from MyFatoorah Portal**\
**GetPaymentStatus Endpoint**

#### 1. Orders List

* Go to Orders List in MyFatoorah portal.
* Open the order that you want to check the amounts of.
* At the bottom of the page, click on **Invoice Suppliers**
* The vendor share is: **Due Deposit**
* MyFatoorah commission is: **Vendor Service Charge + VAT amount**
* Each supplier share is: **Deposit Share** for each supplier

![](https://files.readme.io/c4e9af83241b6543f752131c0982b08b7d61ee83bac4db77e865f80a35928f02-image.png)

***

#### 2. GET Payments

* Send the order that you want to check in the Request to MyFatoorah.
* In the response, you will find the amount that each supplier took in the **suppliers** parameter.
* Check below for example:

```json GET Payments Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "Invoice": {
            "Id": "6389760",
            "Status": "PAID",
            "Reference": "2025060933",
            "CreationDate": "2025-12-24T17:41:37.7200000Z",
            "ExpirationDate": "2026-06-22T17:41:37.7200000Z",
            "ExternalIdentifier": null,
            "UserDefinedField": "",
            "MetaData": null
        },
        "Transaction": {
            "Id": "105741",
            "Status": "SUCCESS",
            "PaymentMethod": "VISA/MASTER",
            "PaymentId": "07076389760322480573",
            "ReferenceId": "535817105741",
            "TrackId": "24-12-2025_3224805",
            "AuthorizationId": "105741",
            "TransactionDate": "2025-12-24T17:41:55.0700000Z",
            "ECI": "02",
            "IP": {
                "Address": "41.35.105.183",
                "Country": "Egypt"
            },
            "Error": {
                "Code": "",
                "Message": ""
            },
            "Card": {
                "NameOnCard": "Ahmed",
                "Number": "512345xxxxxx0008",
                "Token": "",
                "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                "ExpiryMonth": "12",
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
            "ServiceCharge": "0.4",
            "ServiceChargeVAT": "0.06",
            "ReceivableAmount": "4.54",
            "DisplayCurrency": "KWD",
            "ValueInDisplayCurrency": "20",
            "PayCurrency": "KWD",
            "ValueInPayCurrency": "20"
        },
        "Suppliers": [
            {
                "Code": 1,
                "Name": "sss",
                "InvoiceShare": "10",
                "ProposedShare": "7",
                "DepositShare": "7"
            },
            {
                "Code": 2,
                "Name": "Bisan",
                "InvoiceShare": "10",
                "ProposedShare": "8",
                "DepositShare": "8"
            }
        ]
    }
}
```

#### 3. Webhook

```json Webhook Body
{
  "Event": {
    "Code": 1,
    "Name": "PAYMENT_STATUS_CHANGED",
    "CountryIsoCode": "KWT",
    "CreationDate": "2025-12-24T17:44:22.0570000Z",
    "Reference": "WH-615417"
  },
  "Data": {
    "Invoice": {
      "Id": "6389761",
      "Status": "PAID",
      "Reference": "2025060934",
      "CreationDate": "2025-12-24T17:44:10.2Z",
      "ExpirationDate": "2026-06-22T17:44:10.2Z",
      "UserDefinedField": "",
      "ExternalIdentifier": null,
      "MetaData": null
    },
    "Transaction": {
      "Id": "106821",
      "Status": "SUCCESS",
      "PaymentMethod": "VISA/MASTER",
      "PaymentId": "07076389761322480673",
      "ReferenceId": "535817106821",
      "TrackId": "24-12-2025_3224806",
      "AuthorizationId": "106821",
      "TransactionDate": "2025-12-24T17:44:22.0078323Z",
      "ECI": "02",
      "IP": {
        "Address": "41.35.105.183",
        "Country": "Egypt"
      },
      "Error": {
        "Code": "",
        "Message": ""
      },
      "Card": {
        "NameOnCard": "Khaled",
        "Number": "512345xxxxxx0008",
        "Token": "",
        "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
        "ExpiryMonth": "12",
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
      "ServiceCharge": "0.4",
      "ServiceChargeVAT": "0.06",
      "ReceivableAmount": "4.54",
      "DisplayCurrency": "KWD",
      "ValueInDisplayCurrency": "20",
      "PayCurrency": "KWD",
      "ValueInPayCurrency": "20"
    },
    "Suppliers": [
      {
        "Code": 1,
        "Name": "sss",
        "InvoiceShare": "10",
        "ProposedShare": "7",
        "DepositShare": "7"
      },
      {
        "Code": 2,
        "Name": "Bisan",
        "InvoiceShare": "10",
        "ProposedShare": "8",
        "DepositShare": "8"
      }
    ]
  }
}
```

<br />

## GetSuppliers

*`https://docs.myfatoorah.com/docs/get-suppliers` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "GetSuppliers" endpoint is a GET request. It is used to retrieve a list that contains full information about your suppliers. Detailed functionality of how to use this endpoint is explained in the [Multiple Suppliers](https://docs.myfatoorah.com/docs/multiple-suppliers) section.

The endpoint on Swagger is [GetSuppliers](https://apitest.myfatoorah.com/swagger/ui/index#!/Supplier/Supplier_GetSuppliers).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a GET request without any parameters.

***

#### **Response Model**

The response is an array of ManageSupplierResponse objects as follows:

| Response Field | Type | Description |
|---|---|---|
| **SupplierCode** | integer |  |
| **SupplierName** | string |  |
| **Mobile** | string |  |
| **Email** | string |  |
| **CommissionValue** | number |  |
| **CommissionPercentage** | number |  |
| **DepositTerms** | string | Daily - Weekly - Monthly - OnDemand |
| **DepositDay** | string | This represents the day on which the deposit is going to take place in the case of Weekly or Monthly DepositTerms. |
| **SupplierStatus** | string | "Active", "Pending", "Rejected",\ "Closed", "Dormant" |
| **Comment** | string | The reason for rejection (if the supplier is rejected) |
| **IsPercentageOfNetValue** | boolean | Represents whether the CommissionPercentage is taken from the total amount paid for the supplier or from the net value |
| **BusinessName** | string | The name displayed on the MyFataoorah invoices if the value of DisplaySupplierDetails is true |
| **DisplaySupplierDetails** | boolean | Show the supplier details on the invoices of MyFatoorah instead of the vendor details |
| **SupplierCommissions** | array of objects | Customized commissions made for the payment methods for the supplier |
| **BusinessCategory** | object | Shows the MCC details of the supplier |

***

##### SupplierCommissions Array

| Field                      | Type   | Description                |
| :------------------------- | :----- | :------------------------- |
| **PaymentMethodName**      | string | Name of the payment method |
| **CommissionValue**        | number |                            |
| **CommissionPercentage**   | number |                            |
| **IsPercentageOfNetValue** | string |                            |

##### BusinessCategory

| Field    | Type   | Description          |
| :------- | :----- | :------------------- |
| **Code** | string | MCC for the supplier |
| **Name** | string | Name of the MCC      |

#### **Sample Message**

```json Request
/v2/GetSuppliers
```
```json Response
[
    {
        "SupplierCode": 27,
        "SupplierName": "Jada Williamson",
        "Mobile": "863-322-8615",
        "Email": "Sigrid_Hudson6@gmail.com",
        "CommissionValue": 0.50,
        "CommissionPercentage": 1.000,
        "DepositTerms": "Daily",
        "DepositDay": null,
        "SupplierStatus": "Rejected",
        "Comment": "سيشسيشيشس",
        "IsPercentageOfNetValue": false,
        "BusinessName": null,
        "DisplaySupplierDetails": false,
        "SupplierCommissions": [],
        "BusinessCategory": {
            "Code": "5941",
            "Name": "Camping products"
        }
    },
    {
        "SupplierCode": 53,
        "SupplierName": "dasd sads",
        "Mobile": "0555555555",
        "Email": "test@test.cas",
        "CommissionValue": 1.00,
        "CommissionPercentage": 1.000,
        "DepositTerms": "Daily",
        "DepositDay": null,
        "SupplierStatus": "Pending",
        "Comment": "",
        "IsPercentageOfNetValue": false,
        "BusinessName": null,
        "DisplaySupplierDetails": false,
        "SupplierCommissions": [
            {
                "PaymentMethodName": "Apple Pay",
                "CommissionValue": 1.000,
                "CommissionPercentage": 3.000,
                "IsPercentageOfNetValue": "False"
            }
        ],
        "BusinessCategory": {
            "Code": null,
            "Name": null
        }
    },
    .....................
]
```

***

## GetSupplierDetails

*`https://docs.myfatoorah.com/docs/getsupplierdetails` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "GetSupplierDetails" endpoint is a GET request. It is used to retrieve the full information about your specific supplier. Detailed functionality of how to use this endpoint is explained in the [Multiple Suppliers](https://docs.myfatoorah.com/docs/multiple-suppliers) section.

The endpoint on Swagger is [GetSupplierDetails](https://apitest.myfatoorah.com/swagger/ui/index#!/Supplier/Supplier_GetSupplierDetails).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a GET request with the following parameters:

| Input Parameter  | Type    | Description |
| :--------------- | :------ | :---------- |
| **SupplierCode** | integer |             |

***

#### **Response Model**

| Response Field | Type | Description |
|---|---|---|
| **SupplierCode** | integer |  |
| **SupplierName** | string |  |
| **Mobile** | string |  |
| **Email** | string |  |
| **CommissionValue** | number |  |
| **CommissionPercentage** | number |  |
| **DepositTerms** | string | Daily - Weekly - Monthly - OnDemand |
| **DepositDay** | string | This represents the day on which the deposit is going to take place in the case of Weekly or Monthly DepositTerms. |
| **SupplierStatus** | string | "Active", "Pending", "Rejected",\ "Closed", "Dormant" |
| **Comment** | string | The reason for rejection (if the supplier is rejected) |
| **IsPercentageOfNetValue** | boolean | Represents whether the CommissionPercentage is taken from the total amount paid for the supplier or from the net value |
| **BusinessName** | string | The name displayed on the MyFataoorah invoices if the value of DisplaySupplierDetails is true |
| **DisplaySupplierDetails** | boolean | Show the supplier details on the invoices of MyFatoorah instead of the vendor details |
| **SupplierCommissions** | array of objects | Customized commissions made for the payment methods for the supplier |
| **BusinessCategory** | object | Shows the MCC details of the supplier |

##### SupplierCommissions Array

| Field                      | Type   | Description                |
| :------------------------- | :----- | :------------------------- |
| **PaymentMethodName**      | string | Name of the payment method |
| **CommissionValue**        | number |                            |
| **CommissionPercentage**   | number |                            |
| **IsPercentageOfNetValue** | string |                            |

##### BusinessCategory

| Field    | Type   | Description          |
| :------- | :----- | :------------------- |
| **Code** | string | MCC for the supplier |
| **Name** | string | Name of the MCC      |

#### **Sample Message**

```json Request
/v2/GetSupplierDetails?suppplierCode=1
```
```json Response
{
    "SupplierCode": 1,
    "SupplierName": "    Leuschke LLC",
    "Mobile": "3582726778",
    "Email": "Max_Hettinger@gmail.com",
    "CommissionValue": 0.00,
    "CommissionPercentage": 0.000,
    "DepositTerms": "Daily",
    "DepositDay": null,
    "SupplierStatus": "Active",
    "Comment": "",
    "IsPercentageOfNetValue": false,
    "BusinessName": null,
    "DisplaySupplierDetails": false,
    "SupplierCommissions": [
        {
            "PaymentMethodName": "Apple Pay",
            "CommissionValue": 0.500,
            "CommissionPercentage": 0.000,
            "IsPercentageOfNetValue": "False"
        }
    ],
    "BusinessCategory": {
        "Code": "5941",
        "Name": "Camping products"
    }
}
```

***

## GetSupplierDeposits

*`https://docs.myfatoorah.com/docs/get-supplier-deposits` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "GetSupplierDeposits" endpoint is a GET request. It is used to get the deposit records of a supplier. Detailed functionality of how to use this endpoint is explained in the [Multiple Suppliers](https://docs.myfatoorah.com/docs/multiple-suppliers) section.

The endpoint on Swagger is [GetSupplierDeposits](https://apitest.myfatoorah.com/swagger/ui/index#!/Supplier/Supplier_GetSupplierDeposits).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a GET request with the following parameters:

| Input Parameter   | Type              | Description |
| :---------------- | :---------------- | :---------- |
| **SupplierCode**  | integer           |             |
| **search**        | string, optional  |             |
| **start**         | integer, optional |             |
| **length**        | integer, optional |             |
| **sortColumn**    | string, optional  |             |
| **sortDirection** | string, optional  |             |

#### **Response Model**

| Response Field | Type | Description |
| :------------- | :--- | :---------- |
| Records        |      |             |
| Data           |      |             |

***

#### **Sample Message**

```json Request
/v2/GetSupplierDeposits?SupplierCode=69
```
```json Response
{
  "Records": 1,
  "Data": [
    {
      "Iban": "AL35202111090000000001234567",
      "DepositId": 183,
      "VendorId": 123,
      "DepositReference": "2022000183",
      "BankName": "NBK",
      "TotalValue": 542.575,
      "DepositDate": "16/10/02022"
    }
  ]
}
```

***

## GetSupplierDocuments

*`https://docs.myfatoorah.com/docs/get-supplier-documents` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "GetSupplierDocuments" endpoint is a GET request. It is used to get the supplier documents. Detailed functionality of how to use this endpoint is explained in the [Multiple Suppliers](https://docs.myfatoorah.com/docs/multiple-suppliers) section.

The endpoint on Swagger is [GetSupplierDocuments](https://apitest.myfatoorah.com/swagger/ui/index#!/Supplier/Supplier_GetSupplierDocuments).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a GET request with the following parameters:

| Input Parameter  | Type    | Description |
| :--------------- | :------ | :---------- |
| **SupplierCode** | integer |             |

***

#### **Response Model**

The response is an array of **SupplierFileView** objects as follows:

| Response Field | Type | Description |
|---|---|---|
| **FileUrl** | string | The document URL. |
| **FileType** | integer | 1 for Civil Id\ 2 for Commercial License\ 3 for Articles of Association\ 4 for Signature Authorization\ 5 for Others\ 6 for Civil ID Back\ 7 for Instagram\ 16 for Civil IDs of All Owners\ 17 for Civil ID of Manager\ 20 for Commercial Register\ 21 for Bank Account Letter\ 25 for Website\ 26 for 3rd Parties\ 27 for Basic regulations list (For charities only)\ 28 for Board of Directors Agreement (For charities only) |
| **FileTypeName** | string | It can be:\ Commercial License, Signature Authorisation, Articles of Association, Civil ID, Civil ID back, 3-parties contract/ agreement, and Others. |
| **ExpireDate** | string |  |

***

#### **Sample Message**

```json Request
/v2/GetSupplierDocuments?SupplierCode=69
```
```json Response
[
    {
        "FileUrl": "https://sa.myfatoorah.com/Files/Suppliers/13756/dbdfd02d-be0b-4edf-b751-914142aa5437.jpeg",
        "FileType": 5,
        "FileTypeName": null,
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 1,
        "FileTypeName": "Civil Id",
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 2,
        "FileTypeName": "Commercial License",
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 4,
        "FileTypeName": "Signature Authorization",
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 3,
        "FileTypeName": "Articles of Association",
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 20,
        "FileTypeName": "Commercial Register",
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 16,
        "FileTypeName": "Civil Ids Of All Owners",
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 17,
        "FileTypeName": "Civil Id Of Manager",
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 21,
        "FileTypeName": "Bank Account Letter",
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 25,
        "FileTypeName": "Website",
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 26,
        "FileTypeName": "3-parties contract/ agreement",
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 27,
        "FileTypeName": "Basic regulations list (For charities only) ",
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 28,
        "FileTypeName": "Board of Directors Agreement (For charities only)",
        "ExpireDate": null
    },
    {
        "FileUrl": null,
        "FileType": 30,
        "FileTypeName": "National address",
        "ExpireDate": null
    }
]
```

***

## GetSupplierDashboard

*`https://docs.myfatoorah.com/docs/get-supplier-dashboard` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "GetSupplierDashboard" endpoint is a GET request. It is used to get the supplier dashboard. Detailed functionality of how to use this endpoint is explained in the [Multiple Suppliers](https://docs.myfatoorah.com/docs/multiple-suppliers) section.

The endpoint on Swagger is [GetDashboard](https://apitest.myfatoorah.com/swagger/ui/index#!/Supplier/Supplier_GetDashboard).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a GET request with the following parameters:

| Input Parameter  | Type    | Description |
| :--------------- | :------ | :---------- |
| **SupplierCode** | integer |             |

***

#### **Response Model**

The response is a **SupplierDashBoard** object as follows:

| Response Field                | Type    | Description                                                                                                                                                                                                                                                                                                                         |
| :---------------------------- | :------ | :---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **TotalNumberOfTransactions** | integer | It is the count of transactions made for this supplier.                                                                                                                                                                                                                                                                             |
| **TotalValueOfTransactions**  | number  | It is the sum of the "InvoiceValue" parameter of transactions made for this supplier. It indicates the total sum of invoices of transactions before deducting MyFtoorah fees and vendor fees. This is calculated from the perspective of the vendor.                                                                                |
| **TotalSupplierInvoiceShare** | number  | It is the sum of the "InvoiceShare" parameter of transactions made for this supplier. It indicates the sum of all supplier share values of transactions made for this supplier.                                                                                                                                                     |
| **TotalDepositedAmount**      | number  | It indicates the actual amounts deposited into the supplier's bank account.                                                                                                                                                                                                                                                         |
| **TotalAwaitingBalance**      | number  | It is the current awaiting balance for a specific supplier. It is the sum of supplier share values after deducting the **MyFatoorah** fees and the vendor fees. This amount is used in refunding or transferring the balance between the vendor and supplier. This amount will be available until the deposit terms interval is up. |
| **TotalAwaitingToTransfer**   | number  | After the deposit terms interval ends, the "TotalAwaitingBalance" becomes the "TotalAwaitingToTransfer" amount to be deposited in the supplier bank account.                                                                                                                                                                        |
| **TotalBalance**              | number  | This represents the total amount in the supplier's MyFatoorah wallet. This amount includes amounts of invoices that are not approved yet.                                                                                                                                                                                           |
| **IsApproved**                | boolean | It is the supplier approval status. It will be true if **MyFatoorah** approves the provided supplier.                                                                                                                                                                                                                               |
| **IsActive**                  | boolean | It is the supplier activity status.                                                                                                                                                                                                                                                                                                 |

***

#### **Sample Message**

```json Request
/v2/GetSupplierDashboard?SupplierCode=69
```
```json Response
{
  "TotalAwaitingBalance": 44.758,
  "TotalNumberOfTransactions": 28,
  "TotalValueOfTransactions": 1766.736,
  "TotalSupplierInvoiceShare": 1239.456,
  "TotalDepositedAmount": 542.575,
  "TotalAwaitingToTransfer": 0,
  "TotalBalance": 44.758,
  "IsApproved": true,
  "IsActive": true
}
```

***

## UploadSupplierDocument

*`https://docs.myfatoorah.com/docs/upload-supplier-document` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "UploadSupplierDocument" endpoint is a PUT request. It is used to upload supplier documents. Detailed functionality of how to use this endpoint is explained in the [Multiple Suppliers](https://docs.myfatoorah.com/docs/multiple-suppliers) section.

The endpoint on Swagger is [UpdateSupplierDoc](https://apitest.myfatoorah.com/swagger/ui/index#!/Supplier/Supplier_UpdateSupplierDoc).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a PUT request with the following parameters:

| Input Parameter | Type | Description |
|---|---|---|
| **FileUpload** | [HttpFile](#httpfile) Model |  |
| **FileType** | integer | * **1** for Civil Id * **2** for Commercial License * **3** for Articles of Association * **4** for Signature Authorization * **5** for Others * **6** for Civil Id Back * **7** for Instagram * **16** for Civil Ids Of All Owners * **17** for Civil Id Of Manager * **20** for Commercial Register * **21** for Bank Account Letter * **25** for Website * **26** for 3rd Parties * **27** for Basic regulations list (For charities only) * **28** for Board of Directors Agreement (For charities only) * **30** for National address |
| **ExpireDate** | string, optional |  |
| **SupplierCode** | integer |  |

#### HttpFile

| Input Parameter | Type             | Description |
| :-------------- | :--------------- | :---------- |
| **FileName**    | string, optional |             |
| **MediaType**   | string, optional |             |
| **Buffer**      | string, optional |             |

***

#### **Response Model**

| Response Field | Type    | Description                                              |
| :------------- | :------ | :------------------------------------------------------- |
| **IsSuccess**  | boolean | "true" or "false" indicating the status of your request. |
| **Message**    | string  | The message response associated with the request done.   |

***

#### **Sample Message**

The request below is an example of the form-data object.

![upload-supplier-document.png](https://files.readme.io/374f863-upload-supplier-document.png)

> ❗️ Accepted Files
>
> Kindly make sure to upload the file according to the following conditions:
>
> * Maximum File Size: 5 MB
> * File Type is in the following formate: .jpg|.jpeg|.png|.bmp|.gif|.xls|.xlsx|.pdf|.doc|.docx

#### **Sample Code**

```php
<?php

/* For simplicity, check the PHP Library here: https://myfatoorah.readme.io/php-library */
/* ------------------------ Configurations ---------------------------------- */
//Test
$apiURL = 'https://apitest.myfatoorah.com';
$apiKey = ''; //Test token value to be placed here: https://myfatoorah.readme.io/docs/test-token

//Live
//$apiURL = 'https://api.myfatoorah.com';
//$apiKey = ''; //Live token value to be placed here: https://myfatoorah.readme.io/docs/live-token


/* ------------------------ Call UploadSupplierDocument Endpoint ------------------- */
//Fill POST fields array, check https://myfatoorah.readme.io/docs/upload-supplier-document#request-model
$file     = ''; //file url
$contents = file_get_contents($file); //read file using upload form or any way you like

$fileName  = basename($file);
$mediaType = mime_content_type($file);
$buffer    = base64_encode($contents);

//optional set expire date
$ExpireDate = new \DateTime('now', new \DateTimeZone('Asia/Kuwait'));
$ExpireDate->modify("+365 day");

$postFields = [
    //Fill required data
    'FileUpload'   => [
        'FileName'  => $fileName,
        'MediaType' => $mediaType,
        'Buffer'    => $buffer
    ],
    'FileType'     => 2, //check FileType values in documentationFileType
    'SupplierCode' => 3,
        //Fill optional data
        //'ExpireDate'   => $ExpireDate->format('Y-m-d\TH:i:s')
];

//Call endpoint
$link = uploadSupplierDocument($apiURL, $apiKey, $postFields);

//Display the result
echo "Click on <a href='$link' target='_blank'>$link</a> to see the uploaded file.";
die;

/* ------------------------ Functions --------------------------------------- */
/*
 * Upload Supplier Document Endpoint Function 
 */

function uploadSupplierDocument($apiURL, $apiKey, $postFields) {

    $json = callAPI("$apiURL/v2/UploadSupplierDocument", $apiKey, $postFields, 'PUT');
    return $json->Message;
}

//------------------------------------------------------------------------------
/*
 * Call API Endpoint Function
 */

function callAPI($endpointURL, $apiKey, $postFields = [], $requestType = 'POST') {

    $curl = curl_init($endpointURL);
    curl_setopt_array($curl, array(
        CURLOPT_CUSTOMREQUEST  => $requestType,
        CURLOPT_POSTFIELDS     => json_encode($postFields),
        CURLOPT_HTTPHEADER     => array("Authorization: Bearer $apiKey", 'Content-Type: application/json'),
        CURLOPT_RETURNTRANSFER => true,
    ));

    $response = curl_exec($curl);
    $curlErr  = curl_error($curl);

    if ($curlErr) {
        //Curl is not working in your server
        die("Curl Error: $curlErr");
    }

    $error = handleError($response);
    if ($error) {
        die("Error: $error");
    }

    return json_decode($response);
}

//------------------------------------------------------------------------------
/*
 * Handle Endpoint Errors Function 
 */

function handleError($response) {

    $json = json_decode($response);
    if (isset($json->IsSuccess) && $json->IsSuccess == true) {
        return null;
    }

    //Check for the errors
    if (isset($json->ValidationErrors) || isset($json->FieldsErrors)) {
        $errorsObj = isset($json->ValidationErrors) ? $json->ValidationErrors : $json->FieldsErrors;
        $blogDatas = array_column($errorsObj, 'Error', 'Name');

        $error = implode(', ', array_map(function ($k, $v) {
                    return "$k: $v";
                }, array_keys($blogDatas), array_values($blogDatas)));
    } else if (isset($json->Data->ErrorMessage)) {
        $error = $json->Data->ErrorMessage;
    }

    if (empty($error)) {
        $error = (isset($json->Message)) ? $json->Message : (!empty($response) ? $response : 'API key or API URL is not correct');
    }

    return $error;
}

/* -------------------------------------------------------------------------- */
```

***

## MakeSupplierRefund

*`https://docs.myfatoorah.com/docs/make-supplier-refund` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "MakeSupplierRefund" endpoint is a POST request. It is used to cancel the payment and return the funds to the customer if the invoice has supplier information. This request has been created specifically for the [Multiple Suppliers](https://docs.myfatoorah.com/docs/multiple-suppliers) feature. It accepts invoices that contain one supplier or more.

The endpoint on Swagger is [Refund\_MakeSupplierRefund](https://apitest.myfatoorah.com/swagger/ui/index#!/Refund/Refund_MakeSupplierRefund).

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

| Input Parameter        | Type                                                         | Description                                                                      |
| :--------------------- | :----------------------------------------------------------- | :------------------------------------------------------------------------------- |
| **KeyType**            | string                                                       | State either it's "InvoiceId" or "PaymentId"                                     |
| **Key**                | string                                                       | Value of the key type mentioned                                                  |
| **VendorDeductAmount** | number, optional                                             | The amount that the vendor will refund from their balance                        |
| **Comment**            | string                                                       | Extra comments for your reference                                                |
| **ExternalIdentifier** | string                                                       | External data associated with the refund, which will be received in the webhook. |
| **Suppliers**          | Array of [RefundSupplier](#refundsupplier) objects, optional |                                                                                  |

#### RefundSupplier

| Input Parameter            | Type              | Description                                                  |
| :------------------------- | :---------------- | :----------------------------------------------------------- |
| **SupplierCode**           | integer, optional | The supplier code you need to associate the invoice with.    |
| **SupplierDeductedAmount** | number, optional  | The amount that the supplier will send back to the customer. |

> ❗️ Currency Parameter
>
> In the request the currency parameter has been omitted, as the value passed for the refund would represent the account base currency based on the token you are using. So, please get sure of the amount and used token as they determine the exact amount to be refunded

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field         | Type   | Description                                                                         |
| :--------------------- | :----- | :---------------------------------------------------------------------------------- |
| **Key**                | string | The key value you have passed for the Request Transaction                           |
| **RefundId**           | number |                                                                                     |
| **RefundReference**    | string | The refund reference generated by MyFatoorah for following up with the finance team |
| **RefundInvoiceId**    | number | The InvoiceId of the refunded amount                                                |
| **Amount**             | string | The amount needed to be refunded                                                    |
| **Comment**            | string | The comments that you have passed in the request.                                   |
| **ExternalIdentifier** | string | The External Identifier you provided in the request                                 |

#### **Sample Message**

```json Make Refund Request
{
    "Key": 6424985,
    "KeyType": "InvoiceId",
    "VendorDeductAmount": 0,
     "ExternalIdentifier": "refund-external-id",
    "Comment": "refund-comment",
    "Suppliers": [
        {
            "SupplierCode": 2,
            "SupplierDeductedAmount": 5
        }
    ]
}
```
```json Make Refund Response
{
    "IsSuccess": true,
    "Message": "Refund Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "Key": "6424985",
        "RefundId": 246274,
        "RefundReference": "2026000011",
        "RefundInvoiceId": 6426234,
        "ExternalIdentifier": "refund-external-id",
        "Amount": 5.0,
        "Comment": "refund-comment"
    }
}
```

## TransferBalance

*`https://docs.myfatoorah.com/docs/transferbalance` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "TransferBalance" endpoint is a POST request. It is used to transfer a balance from or to the available balance of a supplier. This request has been created specifically for the [Multi-Vendors](https://docs.myfatoorah.com/docs/multiple-suppliers) feature. It works with a single supplier at each request.

The endpoint on Swagger is [Supplier\_TransferBalance](https://apitest.myfatoorah.com/swagger/ui/index#!/Supplier/Supplier_TransferBalance).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following parameters:

| Input Parameter | Type | Description |
|---|---|---|
| **SupplierCode** | integer | The supplier code you need to associate the invoice with. |
| **TransferAmount** | number | The amount that will be transferred to or from the supplier. |
| **TransferType** | string | **pull**\ The balance transfers from supplier to vendor.\ **push**\ the balance transfers from vendor to supplier. |
| **InternalNotes** | string, optional | Extra comments for your reference. |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field | Type | Description |
|---|---|---|
| **InvoiceId** | integer |  |
| **Date** | string |  |

#### **Sample Message**

Transferring the balance from supplier to vendor

```json Request
{
   "SupplierCode":33,
   "TransferAmount":100,
   "TransferType":"pull",
   "InternalNotes":"withdraw from the supplier 33 to the vendor."
}
```
```json Response
{
   "IsSuccess":true,
   "Message":"The balance transferred successfully",
   "FieldsErrors":null,
   "Data":{
      "InvoiceId":684994,
      "Date":"2021-06-29T12:46:55.7815943+03:00"
   }
}
```

Transferring the balance from vendor to supplier

```json Request
{
   "SupplierCode":33,
   "TransferAmount":100,
   "TransferType":"push",
   "InternalNotes":"withdraw from the vendor to the supplier 33."
}
```
```json Response
{
   "IsSuccess":true,
   "Message":"The balance transferred successfully",
   "FieldsErrors":null,
   "Data":{
      "InvoiceId":685852,
      "Date":"2021-06-30T11:43:13.2533227+03:00"
   }
}
```
