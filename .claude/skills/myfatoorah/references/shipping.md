# MyFatoorah — Shipping module

## Shipping

*`https://docs.myfatoorah.com/docs/shipping` — updated 2026-02-16*

> Create Shipping Invoice

#### **Introduction**

The shipping feature is an added value that allows you to simply sell and ship your product through the **MyFatoorah** platform with only one payment invoice for your customer. You can create your shipping invoice directly without having your products predefined at the **MyFatoorah** portal account. You just need to share your product information with our API with the exact dimensions and weight, then we take care of the rest.

Currently, we support both DHL and Aramex.

![dhl and aramex.PNG](https://files.readme.io/4ec7c1c-dhl_and_aramex.PNG)

> ❗️ Approval is needed!
>
> Kindly, contact your account manager or sales representative to activate the **Shipping** feature.

> 🚧 Arabic Letters
>
> DHL and Aramex do not support Arabic item names or descriptions.

***

> 📘 Availability
>
> This is only available with APIs V2 payment flow.

#### **How it works**

> 📘 Portal account
>
> Before you start, you need to set up your company shipping information in your portal account as described in the [Shipping Information](shipping-information) section.

Kindly, follow the below steps to fulfill the shipping integration in your system:

1. Call [GetCountries](https://docs.myfatoorah.com/docs/get-countries) endpoint to get a list of all countries that are supported by the shipping module.

2. Call [GetCities](https://docs.myfatoorah.com/docs/get-cities) endpoint to get a list of all cities of a given country code obtained from the step 1 response and the selected shipping method. Example of a GET request is "/v2/GetCities?shippingMethod=1\&countryCode=US".

3. Now after you have the country code and city details, you can use them in:

```
* [SendPayment](https://docs.myfatoorah.com/docs/send-payment) POST request to create a **MyFatoorah** shipping Invoice. Check the below [send payment example](#sendpayment-with-shipping-sample-message).
* [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) POST request to create an invoice of a selected gateway. Check the below [execute payment example](#executepayment-with-shipping-sample-message).
* [CalculateShippingCharge](https://docs.myfatoorah.com/docs/calculate-shipping-charge) POST request to display the shipping charge to your customers at cart details page.
```

1. Use the RedirectURL/PaymentURL from the previous step to make the payment.

2. Once the payment is done successfully, you will be able to download DHL/Aramex **AWBl** as a PDF in the download section of the receipt.

3. Call [GetShippingOrderList](https://docs.myfatoorah.com/docs/get-shipping-order-list) endpoint to get a list of order invoice IDs of a given order status and a used shipping method. Example of a GET request is "GetShippingOrderList?shippingMethod=1\&orderStatus=0".

4. Call **UpdateShippingStatus** endpoint, if you need to update the invoice status with the parameter explained in [Update Shipping Status](https://docs.myfatoorah.com/docs/update-shipping-status). After a successful payment, you have to change the order shipping status to “Prepared” to initiate the shipping. Note that: you can’t change the status to RequestPickup in this API call. If you want to change RequestPickup please follow the below step.

5. Call *ApiShipping/RequestPickup?shippingMethod=1* endpoint to get the details about updated order numbers with status. Now it's ready for order status changes. For more details, follow the steps in [Request Pickup](https://docs.myfatoorah.com/docs/request-pickup).

6. Next, use the **UpdateShippingStatus** endpoint to change another status like "Picked/ Delivered".

***

#### **SendPayment with Shipping Sample Message**

```json Request
{
  "CustomerName": "name",
  "NotificationOption": "ALL",
  "MobileCountryCode": "965",
  "CustomerMobile": "12345678",
  "CustomerEmail": "mail@company.com",
  "InvoiceValue": 100,
  "DisplayCurrencyIso": "kwd",
  "CallBackUrl": "https://yoursite.com/success",
  "ErrorUrl": "https://yoursite.com/error",
  "Language": "en",
  "CustomerAddress": {
    "Block": "string",
    "Street": "string",
    "HouseBuildingNo": "string",
    "Address": "address",
    "AddressInstructions": "string"
  },
  "InvoiceItems": [
    {
      "ItemName": "string",
      "Quantity": 20,
      "UnitPrice": 5,
      "Description": "string",
      "Weight": 0.5,
      "Width": 10,
      "Height": 15,
      "Depth": 19
    }
  ],
  "SourceInfo": "string",
  "ShippingMethod": 1,
  "ShippingConsignee": {
    "PersonName": "name",
    "Mobile": "12345678",
    "EmailAddress": "a@b.com",
    "LineAddress": "address",
    "CityName": "DUBAI",
    "PostalCode": "12345",
    "CountryCode": "AE"
  }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Invoice Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": 300050,
        "InvoiceURL": "https://demo.myfatoorah.com/ie/0106230005034",
        "CustomerReference": null,
        "UserDefinedField": null
    }
}
```

***

#### **ExecutePayment with Shipping Sample Message**

```json Request
{
  "PaymentMethodId": 1,
  "CustomerName": "test",
  "DisplayCurrencyIso": "kwd",
  "MobileCountryCode": "965",
  "CustomerMobile": "12345678",
  "CustomerEmail": "mail@mail.com",
  "InvoiceValue": 100,
  "CallBackUrl": "https://yoursite.com/success",
  "ErrorUrl": "https://yoursite.com/error",
  "Language": "en",
  "CustomerReference": "string",
  "CustomerCivilId": "string",
  "UserDefinedField": "string",
  "CustomerAddress": {
    "Block": "string",
    "Street": "string",
    "HouseBuildingNo": "string",
    "Address": "Address",
    "AddressInstructions": "Address"
  },
  "InvoiceItems": [
    {
      "ItemName": "name",
      "Quantity": "1",
      "UnitPrice": 100,
      "Description": "string",
      "Weight": 0.5,
      "Width": 10,
      "Height": 15,
      "Depth": 17
    }
  ],
  "ShippingMethod": 1,
  "ShippingConsignee": {
    "PersonName": "name",
    "Mobile": "12345678",
    "EmailAddress": "mail@mail.com",
    "LineAddress": "address",
    "CityName": "DUBAI",
    "PostalCode": "12345",
    "CountryCode": "AE"
  },
  "SourceInfo": "string"
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Invoice Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": 568119,
        "IsDirectPayment": false,
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/Checkout?invoiceKey=030675256811947&paymentGatewayId=20",
        "CustomerReference": "string",
        "UserDefinedField": "string"
    }
}
```

***

## Shipping Information

*`https://docs.myfatoorah.com/docs/shipping-information` — updated 2026-02-16*

> ❗️ Approval is needed!
>
> Kindly, contact your account manager or sales representative to activate the **Shipping** feature.

Kindly follow the instructions below to configure the needed information that will be used by DHL or ARAMEX

1. Log in to the [Myfatoorah portal](https://portal.myfatoorah.com/) using your **Super Master Account**.
2. Navigate to **Business Profile** → **Update Profile**.
3. Click on the **Shipping Settings** tab and fill in the form.

> 👍 DHL Configuration
>
> Please, ensure that the DHL timing must be between 9:00 to 13:00 and the line address should be with a max of three lines.

![](https://files.readme.io/e6e791c16f4607c8cb4296042b2465df5214713032a854e303e1bdd3a5e86db6-image.png)

## GetCountries

*`https://docs.myfatoorah.com/docs/get-countries` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "GetCountries" endpoint is a GET request. It is used to retrieve a list of countries with their codes. Detailed functionality of how to use this endpoint is explained in the [Shipping](https://docs.myfatoorah.com/docs/shipping) section.

The endpoint on Swagger is: [Shipping\_GetCountries](https://apitest.myfatoorah.com/swagger/ui/index#!/Shipping/Shipping_GetCountries).

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

| Response Field  | Type   | Description                                                |
| :-------------- | :----- | :--------------------------------------------------------- |
| **CountryCode** | string | The country code that will be used in shipping addresses.  |
| **CountryName** | string | The country name that will be displayed for your customer. |

***

#### **Sample Message**

```json Request
/v2/GetCountries
```
```json Response
{
    "IsSuccess": true,
    "Message": null,
    "FieldsErrors": null,
    "Data": [
        {
            "CountryCode": "KW",
            "CountryName": "KUWAIT"
        },
        {
            "CountryCode": "KY",
            "CountryName": "CAYMAN ISLANDS"
        },
       .......
    ]
}
```

***

## GetCities

*`https://docs.myfatoorah.com/docs/get-cities` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "GetCities" endpoint is a GET request. It is used to retrieve a list of cities that belong to a certain county. Also, you can search for a specific city name in a certain country. Detailed functionality of how to use this endpoint is explained in the [Shipping](https://docs.myfatoorah.com/docs/shipping) section.

The endpoint on Swagger is: [Shipping\_GetCities](https://apitest.myfatoorah.com/swagger/ui/index#!/Shipping/Shipping_GetCities).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a GET request with the following parameters:

| Input Parameter | Type | Description |
|---|---|---|
| **shippingMethod** | integer | * \*1\*\* for DHL * \*2\*\* for ARAMEX |
| **countryCode** | string | The country code retrieved from the [GetCountries](https://docs.myfatoorah.com/docs/get-countries) endpoint. |
| **searchValue** | string, optional | The search key that will filter the cities by names |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field | Type | Description |
|---|---|---|
| **CountryCode** | string | The country code of the retrieved cities. |
| **CityNames** | Array of strings | List of cities names provided for the specified shipping method. |

***

#### **Sample Message**

```json Request
/v2/Getcities?shippingMethod=1&countryCode=ae&searchValue=ABU
```
```json Response
{
  "IsSuccess": true,
  "Message": null,
  "FieldsErrors": null,
  "Data": {
    "CountryCode": "AE",
    "CityNames": [
      "ABU DHABI",
      "ABU DHABI CITY",
      "ABU HAIL, DUBAI",
      "AL MARKAZ IND. PARK ABU DHABI",
      "BADA ZAYED, ABU DHABI",
      "BARAKA, ABU DHABI",
      "GHAYATHI, ABU DHABI",
      "GHUWAIFAT, ABU DHABI",
      "HAMIM, ABU DHABI",
      "JEBEL DHANNA, ABU DHABI",
      "MIRFA, ABU DHABI",
      "RUWAIS, ABU DHABI",
      "SILA, ABU DHABI",
      "SWEIHAN, ABU DHABI",
      "TARIF, ABU DHABI"
    ]
  }
}
```

***

## CalculateShippingCharge

*`https://docs.myfatoorah.com/docs/calculate-shipping-charge` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "CalculateShippingCharge" endpoint is a POST request. It is used to calculate the charge of the Shipping supported by the **MyFatoorah** Shipping Module that belongs to either DHL or ARAMEX. You should provide the **CityName** and **CountryCode** parameters which will be retrieved by the [GetCities](https://docs.myfatoorah.com/docs/get-cities) and [GetCountries](https://docs.myfatoorah.com/docs/get-countries) endpoints respectively. Detailed functionality of how to use this endpoint is explained in the [Shipping](https://docs.myfatoorah.com/docs/shipping) section.

The endpoint on Swagger is: [Shipping\_CalculateShippingCharge](https://apitest.myfatoorah.com/swagger/ui/index#!/Shipping/Shipping_CalculateShippingCharge).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following parameters:

| Input Parameter | Type | Description |
|---|---|---|
| **ShippingMethod** | integer | * \*1\*\* for DHL * \*2\*\* for ARAMEX |
| **Items** | Array[ShippingItem], optional | Check the model details [after ](#shippingitem-model)this table |
| **CityName** | string |  |
| **PostalCode** | string, optional |  |
| **CountryCode** | string |  |

#### ShippingItem Model

| Input Parameter | Type | Description |
|---|---|---|
| **ProductName** | string |  |
| **Description** | string |  |
| **Weight** | number | 100 >= Weight > 0\ Weight in kg |
| **Width** | number | 200 >= Width > 0\ Width in cm |
| **Height** | number | 160 >= Height > 0\ Height in cm |
| **Depth** | number | 200 >= Depth > 0\ Depth in cm |
| **Quantity** | integer |  |
| **UnitPrice** | number |  |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field | Type | Description |
|---|---|---|
| **Currency** | string |  |
| **Fees** | number |  |

***

#### **Sample Message**

```json Request
{
  "ShippingMethod": 1,
  "Items": [
    {
      "ProductName": "name",
      "Description": "name",
      "Weight": 0.5,
      "Width": 10,
      "Height": 15,
      "Depth": 19,
      "Quantity": 20,
      "UnitPrice": 5
    }
  ],
  "CityName": "DUBAI",
  "PostalCode": "12345",
  "CountryCode": "AE"
}
```
```json Response
{
    "IsSuccess": true,
    "Message": null,
    "FieldsErrors": null,
    "Data": {
        "Currency": "KD",
        "Fees": 29.993
    }
}
```

***

## UpdateShippingStatus

*`https://docs.myfatoorah.com/docs/update-shipping-status` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "UpdateShippingStatus" endpoint is a POST request. It is used to Update the Status of the Shipping supported by the **MyFatoorah** Shipping Module that belongs to either DHL or ARAMEX. Detailed functionality of how to use this endpoint is explained in the [Shipping](https://docs.myfatoorah.com/docs/shipping) section.

The endpoint on Swagger is: [Shipping\_UpdateShippingStatus](https://apitest.myfatoorah.com/swagger/ui/index#!/Shipping/Shipping_UpdateShippingStatus).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following parameters:

| Input Parameter | Type | Description |
|---|---|---|
| **ShippingMethod** | integer | * \*1\*\* for DHL * \*2\*\* for ARAMEX |
| **InvoiceNumbers** | Array[integer], optional | A list of the invoice IDs to update their status |
| **OrderStatusChangedTo** | integer, optional | The range is from 0 to 4 as follows: * \*0\*\* for Pending Status * \*1\*\* for Prepared Status * \*2\*\* for RequestPickup Status * \*3\*\* for Picked Status * \*4\*\* for Delivered Status |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field    | Type                           | Description |
| :---------------- | :----------------------------- | :---------- |
| **ShippingOrder** | array of ShippingOrderStatuses |             |

#### ShippingOrderStatuses

| Response Field  | Type    | Description |
| :-------------- | :------ | :---------- |
| **OrderNumber** | integer |             |
| **OrderStatus** | string  |             |

***

#### **Sample Message**

```json Request
{
  "ShippingMethod":1,
  "InvoiceNumbers": [
  	40481,40480
  ],
  "OrderStatusChangedTo": 1
}
```
```json Response
{
    "IsSuccess": true,
    "Message": null,
    "FieldsErrors": null,
    "Data": {
        "ShippingOrder": [
            {
                "OrderNumber": 40480,
                "OrderStatus": "Prepared"
            },
            {
                "OrderNumber": 40481,
                "OrderStatus": "Prepared"
            }
        ]
    }
}
```

***

## RequestPickup

*`https://docs.myfatoorah.com/docs/request-pickup` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "RequestPickup" endpoint is a GET request. It is used to request a pickup for orders with **Prepared** status to be delivered via DHL or ARAMEX. Detailed functionality of how to use this endpoint is explained in the [Shipping](https://docs.myfatoorah.com/docs/shipping) section.

The endpoint on Swagger is [Shipping\_RequestPickup](https://apitest.myfatoorah.com/swagger/ui/index#!/Shipping/Shipping_RequestPickup).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a GET request with the following parameters:

| Input Parameter | Type | Description |
|---|---|---|
| **ShippingMethod** | integer | * \*1\*\* for DHL * \*2\*\* for ARAMEX |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model)  that you will get as a result of your request, **the Data Model will be an array of ShippingOrderStatus objects**. This array lists the shipping order numbers whose status has changed from **Prepared** status to **RequestPickup** status within a specific shipping method.

| Response Field | Type | Description |
|---|---|---|
| **OrderNumber** | integer | * \*MyFatoorah\*\* order ID. |
| **OrderStatus** | string | The order status. |

***

#### **Sample Message**

```json Request
/v2/RequestPickup?ShippingMethod=1
```
```json Response 1
{
  "IsSuccess": true,
  "Message": null,
  "FieldsErrors": null,
  "Data": [
    {
      "OrderNumber": 105039,
      "OrderStatus": "RequestPickup"
    },
    {
      "OrderNumber": 301761,
      "OrderStatus": "RequestPickup"
    }
  ]
}
```
```json Response 2
{
    "Id": 0,
    "IsSuccess": false,
    "Message": "No prepared Items",
    "FieldsErrors": null
}
```

***

## GetShippingOrderList

*`https://docs.myfatoorah.com/docs/get-shipping-order-list` — updated 2026-02-16*

> Endpoint

#### **Overview**

The "GetShippingOrderList" endpoint is a GET request. It is used to retrieve the shipping orders list that belongs to either DHL or Aramex. Detailed functionality of how to use this endpoint is explained in the [Shipping](https://docs.myfatoorah.com/docs/shipping) section.

The endpoint on Swagger is [Shipping\_GetShippingOrderList](https://apitest.myfatoorah.com/swagger/ui/index#!/Shipping/Shipping_GetShippingOrderList).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 Request Header
>
> Add **"Authorization": "Bearer \{Token}"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a GET request with the following parameters:

| Input Parameter | Type | Description |
|---|---|---|
| **ShippingMethod** | integer | * \*1\*\* for DHL * \*2\*\* for ARAMEX |
| **orderStatus** | integer | The range is from 0 to 4 as follows: * \*0\*\* for Pending Status * \*1\*\* for Prepared Status * \*2\*\* for RequestPickup Status * \*3\*\* for Picked Status * \*4\*\* for Delivered Status |
| **start** | integer, optional |  |
| **length** | integer, optional |  |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field | Type | Description |
|---|---|---|
| **ShippingMethod** | string | * \*1\*\* for DHL * \*2\*\* for ARAMEX |
| **OrderStatus** | string |  |
| **TotalOrders** | integer | The numbers of total shipping orders. |
| **OrderNumbers** | array of integers | A list of **MyFatoorah** order IDs |
| **ShippingOrders** | array of [ShippingOrder](#shippingorder) objects |  |

#### ShippingOrder

| Response Field | Type | Description |
|---|---|---|
| **OrderNumber** | integer | The MyFatoorah order ID |
| **OrderType** | string |  |
| **OrderStatus** | string | The range is from 0 to 4 as follows:\ 0 for Pending Status\ 1 for Prepared Status\ 2 for RequestPickup Status\ 3 for Picked Status\ 4 for Delivered Status |
| **CustomerName** | string |  |
| **ShippingMethod** | string | * \*1\*\* for DHL * \*2\*\* for ARAMEX |
| **ShippingValue** | number |  |

***

#### **Sample Message**

```json Request
/v2/GetShippingOrderList?ShippingMethod=1&orderStatus=0
```
```json Response
{
    "IsSuccess": true,
    "Message": null,
    "FieldsErrors": null,
    "Data": {
        "ShippingMethod": "DHL",
        "OrderStatus": "Pending",
        "TotalOrders": 98,
        "OrderNumbers": [
            299466,
            299462,
            286498,
            .............
        ],
        "ShippingOrders": [
            {
                "OrderNumber": 299466,
                "OrderType": "Shipping",
                "OrderStatus": "Pending",
                "CustomerName": "admin@hardtask.com",
                "ShippingMethod": "DHL",
                "ShippingValue": 3.669
            },
            {
                "OrderNumber": 299462,
                "OrderType": "Shipping",
                "OrderStatus": "Pending",
                "CustomerName": "Other country",
                "ShippingMethod": "DHL",
                "ShippingValue": 3.669
            },
            {
                "OrderNumber": 286498,
                "OrderType": "Shipping",
                "OrderStatus": "Pending",
                "CustomerName": "test",
                "ShippingMethod": "DHL",
                "ShippingValue": 27.385
            },
            .............
        ]
    }
}
```

***

## Sample Code

*`https://docs.myfatoorah.com/docs/shipping-sample-code` — updated 2026-02-16*

> Shipping

#### **Overview**

In this section, we provide sample codes for:

* [Get Shipping Countries](#get-shipping-countries)
* [Get Shipping Cities](#get-shipping-cities)
* [Calculate Shipping Charge](#calculate-shipping-charge)
* [Create Invoice Link with Shipping](#create-invoice-link-with-shipping)
* [Gateway Integration with Shipping](#gateway-integration-with-shipping)
* [Direct Payment with Shipping](#direct-payment-with-shipping)

***

#### **Get Shipping Countries**

```php
<?php

/* For simplicity check our PHP SDK library here https://myfatoorah.readme.io/php-library */

//PHP Notice:  To enable MyFatoorah auto-update, kindly give the write/read permissions to the library folder
//use zip file
include 'myfatoorah-library-2.2/MyfatoorahLoader.php';
include 'myfatoorah-library-2.2/MyfatoorahLibrary.php';

//use composer
//require 'vendor/autoload.php';
//use MyFatoorah\Library\API\MyFatoorahShipping;

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
     * Vendor Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'vcCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest'      => true,
];

/* --------------------------- GetCountries Endpoint ------------------------ */

//------------- Call the Endpoint -------------------------
try {
    $mfObj = new MyFatoorahShipping($mfConfig);
    $data  = $mfObj->getShippingCountries();

    //Display the result to your customer
    echo '<h3><u>GetCountries Response Data:</u></h3><pre>';
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

namespace ShippingCountries
{
    class Program
    {
        // You can get test token from this page  https://myfatoorah.readme.io/docs/test-token
        static string token = "";
        static string baseURL = "https://apitest.myfatoorah.com";
        static async Task Main(string[] args)
        {
            var getShippingCountriesResponse = await GetShippingCountries().ConfigureAwait(false);
            Console.WriteLine("Get Shipping Countries Response :");
            Console.WriteLine(getShippingCountriesResponse);
            Console.ReadLine();
        }
        public static async Task<string> GetShippingCountries()
        {
            string url = baseURL + $"/v2/GetCountries";

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
```python
### Get Countries API

### Import required libraries (make sure it is installed!)
import requests
import json
import sys

### Define Functions

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
    elif check_data("Data", response_data):
        error = response_data["Data"]["ErrorMessage"]
    elif check_data("ErrorMessage", response_data["Data"]):
        error = response_data["Data"]["ErrorMessage"]
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



def get_countries():
    api_url = base_url + "/v2/GetCountries"
    countries_response = call_api(api_url, api_key, None, request_type = "GET").json()
    countries = countries_response["Data"]
    print(countries)
    return countries


### Test Environment
base_url = "https://apitest.myfatoorah.com"
api_key = "MyTokenValue"  # Test token value to be placed here: https://myfatoorah.readme.io/docs/test-token

### Live Environment
### base_url = "https://api.myfatoorah.com"
### api_key = "mytokenvalue" #Live token value to be placed here: https://myfatoorah.readme.io/docs/live-token


try:
    get_countries()

    countries_list = [el["CountryName"] for el in get_countries()]
    print(countries_list)
except:
    ex_type, ex_value, ex_traceback = sys.exc_info()
    print("Exception type : %s " % ex_type.__name__)
    print("Exception message : %s" % ex_value)
```

***

#### **Get Shipping Cities**

```php
<?php

/* For simplicity check our PHP SDK library here https://myfatoorah.readme.io/php-library */

//PHP Notice:  To enable MyFatoorah auto-update, kindly give the write/read permissions to the library folder
//use zip file
include 'myfatoorah-library-2.2/MyfatoorahLoader.php';
include 'myfatoorah-library-2.2/MyfatoorahLibrary.php';

//use composer
//require 'vendor/autoload.php';
//use MyFatoorah\Library\API\MyFatoorahShipping;

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
     * Vendor Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'vcCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest'      => true,
];

/* --------------------------- GetCities Endpoint --------------------------- */

//Shipping Method
//1 for DHL and 2 for Aramex
$shipingMethod = 1;

//Country Code
$countryCode = 'KW';

//optional
//$searchValue  = 'Ku';
$searchValue = '';

//------------- Call the Endpoint -------------------------
try {
    $mfObj = new MyFatoorahShipping($mfConfig);
    $data  = $mfObj->getShippingCities($shipingMethod, $countryCode, $searchValue);

    //Display the result to your customer
    echo '<h3><u>GetCities Response Data:</u></h3><pre>';
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

namespace ShippingCities
{
    class Program
    {
        // You can get test token from this page  https://myfatoorah.readme.io/docs/test-token
        static string token = "";
        static string baseURL = "https://apitest.myfatoorah.com";
        static async Task Main(string[] args)
        {
            var getShippingCitiesResponse = await GetShippingCities().ConfigureAwait(false);
            Console.WriteLine("Get Shipping cities Response :");
            Console.WriteLine(getShippingCitiesResponse);
            Console.ReadLine();
        }
        public static async Task<string> GetShippingCities()
        {
            string shippingMethod = "1";
            string countryCode = "kw";
            string searchValue = "";
            string url = baseURL + $"/v2/GetCities?shippingMethod={shippingMethod}&countryCode={countryCode}&searchValue={searchValue}";

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
```python
### Get Cities API

### Import required libraries (make sure it is installed!)
import requests
import json
import sys
### Define Functions

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
    elif check_data("Data", response_data):
        error = response_data["Data"]["ErrorMessage"]
    elif check_data("ErrorMessage", response_data["Data"]):
        error = response_data["Data"]["ErrorMessage"]
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



def get_countries():
    api_url = base_url + "/v2/GetCountries"
    countries_response = call_api(api_url, api_key, None, request_type = "GET").json()
    countries = countries_response["Data"]
    return countries


def get_cities(country_code, search_value):
    while True:
        api_url = base_url + "/v2/Getcities?shippingMethod=1&countryCode=" + country_code + "&searchValue=" + search_value
        cities_response = call_api(api_url, api_key, None, request_type = "GET").json()
        cities = cities_response["Data"]["CityNames"]
        if cities != []:
            break
        else:
            search_value = str(input("Try Another Search Value: ")).upper()
    print(cities)
    return cities

### Test Environment
base_url = "https://apitest.myfatoorah.com"
api_key = "MyTokenValue"  # Test token value to be placed here: https://myfatoorah.readme.io/docs/test-token

### Live Environment
### base_url = "https://api.myfatoorah.com"
### api_key = "mytokenvalue" #Live token value to be placed here: https://myfatoorah.readme.io/docs/live-token

try:
    countries = get_countries()
    countries_list = [el["CountryName"] for el in countries]
    print(countries_list)

    while True:
        x = str(input("Enter a country name from the list: ")).upper()
        if x in countries_list:
            break
        else:
            print("Enter a value from the lis")

    country_code = [el for el in countries if el["CountryName"] == x][0]["CountryCode"]

    search_value = str(input("Enter your search value for cities (Click enter for all cities): "))

    cities = get_cities(country_code, search_value)

    while True:
        y = str(input("Enter you city value from the list (All CAPITAL letters):"))
        if y in cities:
            break
        else:
            print("Select a correct city!")

    city_value = y

    print(city_value)
except:
    ex_type, ex_value, ex_traceback = sys.exc_info()
    print("Exception type : %s " % ex_type.__name__)
    print("Exception message : %s" % ex_value)
```

***

#### **Calculate Shipping Charge**

```php
<?php

/* For simplicity check our PHP SDK library here https://myfatoorah.readme.io/php-library */

//PHP Notice:  To enable MyFatoorah auto-update, kindly give the write/read permissions to the library folder
//use zip file
include 'myfatoorah-library-2.2/MyfatoorahLoader.php';
include 'myfatoorah-library-2.2/MyfatoorahLibrary.php';

//use composer
//require 'vendor/autoload.php';
//use MyFatoorah\Library\API\MyFatoorahShipping;

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
     * Vendor Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'vcCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest'      => true,
];

/* --------------------------- CalculateShippingCharge Endpoint ------------- */

//------------- Post Fields -------------------------
//Check https://docs.myfatoorah.com/docs/calculate-shipping-charge#request-model
$postFields = [
    'ShippingMethod' => 1, //1 for DHL and 2 for Aramex
    'Items'          => [
        [
            'ProductName' => 'Product Name (In English)', //ISBAN, or SKU
            'Description' => 'Description (In English)',
            'Quantity'    => 2, //Item's quantity
            'UnitPrice'   => 25, //Price per item
            'Weight'      => 0.250, //Weight must be in kg.
            'Width'       => 11.4, //It must be in cm.
            'Height'      => 2.6, //It must be in cm.
            'Depth'       => 3.2, //It must be in cm.
        ]
    ],
    'CityName'       => 'DUBAI',
    'PostalCode'     => '12345',
    'CountryCode'    => 'AE'
];

//------------- Call the Endpoint -------------------------
try {
    $mfObj = new MyFatoorahShipping($mfConfig);
    $data  = $mfObj->calculateShippingCharge($postFields);

    //Display the result to your customer
    echo '<h3><u>Summary:</u></h3>';
    echo 'Shipping Charge = <b>' . $data->Fees . $data->Currency . '</b>';

    echo '<h3><u>CalculateShippingCharge Response Data:</u></h3><pre>';
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

namespace CalculateShippingCharge
{
    class Program
    {
        // You can get test token from this page  https://myfatoorah.readme.io/docs/test-token
        static string token = "";
        static string baseURL = "https://apitest.myfatoorah.com";
        static async Task Main(string[] args)
        {

            var calculateResponse = await CalculateShippingCharge().ConfigureAwait(false);
            Console.WriteLine("Calculate Shipping Charge Response :");
            Console.WriteLine(calculateResponse);

            Console.ReadLine();
        }
        public static async Task<string> CalculateShippingCharge()
        {

            var calculateShippingRequest = new
            {
                ShippingMethod = 1,
                CityName = "DUBAI",
                PostalCode = "12345",
                CountryCode = "AE",
                Items = new[] {
                        new {
                          ProductName = "item1",Description="Product Desc", Quantity = 2, UnitPrice = 500, Weight = 1, Width = 1, Height = 1, Depth = 1
                        }
                 }

            };
            var executeRequestJSON = JsonConvert.SerializeObject(calculateShippingRequest);
            return await PerformRequest(executeRequestJSON, endPoint: "CalculateShippingCharge").ConfigureAwait(false);
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
### Calculate Shipping Charge API

### Import required libraries (make sure it is installed!)
import requests
import json
import sys

### Define Functions

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
    elif check_data("Data", response_data):
        error = response_data["Data"]["ErrorMessage"]
    elif check_data("ErrorMessage", response_data["Data"]):
        error = response_data["Data"]["ErrorMessage"]
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


def calculate_shipping_charge(shipping_charge_request):
    api_url = base_url + "/v2/CalculateShippingCharge"
    shipping_charge_response = call_api(api_url, api_key, shipping_charge_request).json()
    shipping_charge = shipping_charge_response["Data"]
    print(shipping_charge)
    return shipping_charge


### Test Environment
base_url = "https://apitest.myfatoorah.com"
api_key = "MyTokenValue"  # Test token value to be placed here: https://myfatoorah.readme.io/docs/test-token

### Live Environment
### base_url = "https://api.myfatoorah.com"
### api_key = "mytokenvalue" #Live token value to be placed here: https://myfatoorah.readme.io/docs/live-token


country_code = "EG"
city_value = "5TH SETTLEMENT"

shipping_charge_request = {
                      "ShippingMethod": 1, # 1 for DHL, 2 for Aramex
                      "Items": [
                                {
                                "ProductName": "name",
                                "Description": "name",
                                "Weight": 0.5,
                                "Width": 10,
                                "Height": 15,
                                "Depth": 19,
                                "Quantity": 20,
                                "UnitPrice": 5
                            }
                            ],
                      "CityName": city_value,
                      "PostalCode": "12345",
                      "CountryCode": country_code
                    }

try:
    calculate_shipping_charge(shipping_charge_request)
except:
    ex_type, ex_value, ex_traceback = sys.exc_info()
    print("Exception type : %s " % ex_type.__name__)
    print("Exception message : %s" % ex_value)
```

***

#### **Create Invoice Link with Shipping**

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
     * Vendor Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'vcCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest'      => true,
];

/* --------------------------- SendPayment Endpoint ------------------------- */
$invoiceValue       = 50;
$displayCurrencyIso = 'KWD';

//Fill customer address array
/* $customerAddress = array(
  'Block'               => 'Blk #', //optional
  'Street'              => 'Str', //optional
  'HouseBuildingNo'     => 'Bldng #', //optional
  'Address'             => 'Addr', //optional
  'AddressInstructions' => 'More Address Instructions', //optional
  ); */

//Fill invoice item array
$invoiceItems[] = [
    'ItemName'  => 'Item Name', //ISBAN, or SKU
    'Quantity'  => '2', //Item's quantity
    'UnitPrice' => '25', //Price per item
    'weight'    => 0.250, //Weight must be in kg.
    'Width'     => 11.4, //It must be in cm.
    'Height'    => 2.6, //It must be in cm.
    'Depth'     => 3.2, //It must be in cm.
];

//Shipping Consignee
$shippingConsignee = array(
    //Fill required data
    'PersonName'  => 'fname lname',
    'Mobile'      => '1234567890',
    'LineAddress' => 'Address',
    'CityName'    => 'DUBAI',
    'PostalCode'  => '12345',
    'CountryCode' => 'AE'
        //Fill optional data
        //'EmailAddress' => 'email@example.com',
);

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
//Check https://docs.myfatoorah.com/docs/send-payment#request-model
$postFields = [
    //Fill required data
    'InvoiceValue'       => $invoiceValue,
    'CustomerName'       => 'fname lname',
    'NotificationOption' => 'LNK', //'SMS', 'EML', or 'ALL'
    //Fill optional data
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
    'InvoiceItems'       => $invoiceItems,
    'ShippingConsignee'  => $shippingConsignee,
    'ShippingMethod'     => 1, //1 for DHL and 2 for Aramex
    //'Suppliers'          => $suppliers,
];

//------------- Call the Endpoint -------------------------
try {
    $mfObj = new MyFatoorahPayment($mfConfig);
    $data  = $mfObj->sendPayment($postFields);

    //You can save payment data in database as per your needs
    $invoiceId   = $data->InvoiceId;
    $paymentLink = $data->InvoiceURL;

    //Display the result to your customer
    //Redirect your customer to complete the payment process
    echo '<h3><u>Summary:</u></h3>';
    echo "To pay the invoice ID <b>$invoiceId</b>, click on:<br>";
    echo "<a href='$paymentLink' target='_blank'>$paymentLink</a><br><br>";

    echo '<h3><u>SendPayment Response Data:</u></h3><pre>';
    print_r($data);
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

namespace SendPaymentShipping
{
    class Program
    {
        // You can get test token from this page  https://myfatoorah.readme.io/docs/test-token
        static string token = "";
        static string baseURL = "https://apitest.myfatoorah.com";

        static async Task Main(string[] args)
        {
            var response = await SendPaymentWithShipping().ConfigureAwait(false);
            Console.WriteLine("Send Payment with shipping Response :");
            Console.WriteLine(response);

            Console.ReadLine();
        }
        public static async Task<string> SendPaymentWithShipping()
        {
            var sendPaymentRequest = new
            {
                //required fields
                CustomerName = "Customer Name",
                NotificationOption = "LNK",
                InvoiceValue = 100,
                //optional fields 
                DisplayCurrencyIso = "KWD",
                MobileCountryCode = "965",
                CustomerMobile = "12345678",
                CustomerEmail = "email@example.com",
                CallBackUrl = "https://example.com/callback",
                ErrorUrl = "https://example.com/error",
                Language = "En",
                CustomerReference = "",
                CustomerCivilId = "",
                UserDefinedField = "",
                ExpiryDate = DateTime.Now.AddYears(1),
                ShippingMethod = 1,
                CustomerAddress = new
                {
                    Block = "Bl",
                    AddressInstructions = "instr",
                    HouseBuildingNo = "11",
                    Street = "Street1"
                },
                InvoiceItems = new[] {
                        new {
                          ItemName = "item1", Quantity = 2, UnitPrice = 50, Weight = 1, Width = 1, Height = 1, Depth = 1
                        }
                 },
                ShippingConsignee = new
                {
                    PersonName = "PN",
                    CityName = "ABBASIYA",
                    CountryCode = "kw",
                    EmailAddress = "Email@example.com",
                    LineAddress = "Address",
                    Mobile = "01000013",
                    PostalCode = "1111"
                }

            };
            var sendPaymentRequestJSON = JsonConvert.SerializeObject(sendPaymentRequest);
            return await PerformRequest(sendPaymentRequestJSON, "SendPayment").ConfigureAwait(false);

        }
        public static async Task<string> PerformRequest(string requestJSON, string endPoint)
        {
            string url = baseURL + $"/v2/{endPoint}";
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
### Send Payment API

### Import required libraries (make sure it is installed!)
import requests
import json
import sys


### Define Functions

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
    elif check_data("ErrorMessage", response_data["Data"]):
        error = response_data["Data"]["ErrorMessage"]
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


### Send Payment endpoint Function
def send_payment(sendpay_data):
    api_url = base_url + "/v2/SendPayment"
    sendpay_response = call_api(api_url, api_key, sendpay_data).json()  # RReceiving the response of MyFatoorah

    invoice_id = sendpay_response["Data"]["InvoiceId"]
    invoice_url = sendpay_response["Data"]["InvoiceURL"]
    # Send Payment output if successful
    print("InvoiceId: ", invoice_id,
          "\nInvoiceURL: ", invoice_url)
    return invoice_id, invoice_url


### Test Environment
base_url = "https://apitest.myfatoorah.com"
api_key = "MyTokenValue"  # Test token value to be placed here: https://myfatoorah.readme.io/docs/test-token

### Live Environment
### base_url = "https://api.myfatoorah.com"
### api_key = "mytokenvalue" #Live token value to be placed here: https://myfatoorah.readme.io/docs/live-token


invoice_items = [
                {
                    "ItemName": "string",
                    "Quantity": 20,
                    "UnitPrice": 5,
                    "Description": "string",
                    "Weight": 0.5,
                    "Width": 10,
                    "Height": 15,
                    "Depth": 19
                }
                ]


shipping_consignee = {
                     "PersonName": "fname lname",
                     "Mobile": "1234567890",
                     "LineAddress": "Address",
                     "CityName": "DUBAI",
                     "PostalCode": "12345",
                     "CountryCode": "AE"
}

shipping_method = 1  # 1 for DHL, 2 for Aramex

### SendPayment Request
sendpay_data = {
                "CustomerName": "name",  # Mandatory Field ("string")
                "NotificationOption": "LNK",  # Mandatory Field ("LNK", "SMS", "EML", or "ALL")
                "InvoiceValue": 100,  # Mandatory Field (Number)
                "InvoiceItems": invoice_items,
                "ShippingConsignee": shipping_consignee,
                "ShippingMethod": shipping_method,
            # Optional Fields
                # "MobileCountryCode": "965",
                # "CustomerMobile": "12345678", #Mandatory if the NotificationOption = SMS or ALL
                # "CustomerEmail": "mail@company.com", #Mandatory if the NotificationOption = EML or ALL
                # "DisplayCurrencyIso": "kwd",
                # "CallBackUrl": "https://yoursite.com/success",
                # "ErrorUrl": "https://yoursite.com/error",
                # "Language": "en",
                # "CustomerReference": "noshipping-nosupplier",
                # "CustomerAddress": {
                #     "Block": "string",
                #     "Street": "string",
                #     "HouseBuildingNo": "string",
                #     "Address": "address",
                #     "AddressInstructions": "string"
                #     },
                # "InvoiceItems": [
                #     {
                #     "ItemName": "string",
                #     "Quantity": 20,
                #     "UnitPrice": 5
                #     }
                #     ]
            }


try:
    send_payment(sendpay_data)
except:
    ex_type, ex_value, ex_traceback = sys.exc_info()
    print("Exception type : %s " % ex_type.__name__)
    print("Exception message : %s" % ex_value)
```

***

#### **Gateway Integration with Shipping**

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
     * Vendor Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'vcCode' => 'KWT',
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
$paymentMethodId = 2;
//foreach ($paymentMethods as $pm) {
//    if ($pm->PaymentMethodEn == 'VISA/MASTER') {
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
$invoiceItems[] = [
    'ItemName'  => 'Item Name', //ISBAN, or SKU
    'Quantity'  => '2', //Item's quantity
    'UnitPrice' => '25', //Price per item
    'weight'    => 0.250, //Weight must be in kg.
    'Width'     => 11.4, //It must be in cm.
    'Height'    => 2.6, //It must be in cm.
    'Depth'     => 3.2, //It must be in cm.
];

//Shipping Consignee
$shippingConsignee = array(
    //Fill required data
    'PersonName'  => 'fname lname',
    'Mobile'      => '1234567890',
    'LineAddress' => 'Address',
    'CityName'    => 'DUBAI',
    'PostalCode'  => '12345',
    'CountryCode' => 'AE'
        //Fill optional data
        //'EmailAddress' => 'email@example.com',
);

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
    'InvoiceValue'      => $invoiceValue,
    'PaymentMethodId'   => $paymentMethodId,
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
    'InvoiceItems'      => $invoiceItems,
    'ShippingConsignee' => $shippingConsignee,
    'ShippingMethod'    => 1, //1 for DHL and 2 for Aramex
    //'Suppliers'          => $suppliers,
];

//------------- Call the Endpoint -------------------------
try {
    $mfObj = new MyFatoorahPayment($mfConfig);
    $data  = $mfObj->executePayment($postFields);

    //You can save payment data in database as per your needs
    $invoiceId   = $data->InvoiceId;
    $paymentLink = $data->PaymentURL;

    //Display the result to your customer
    //Redirect your customer to complete the payment process
    echo '<h3><u>Summary:</u></h3>';
    echo "To pay the invoice ID <b>$invoiceId</b>, click on:<br>";
    echo "<a href='$paymentLink' target='_blank'>$paymentLink</a><br><br>";

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
using System;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Threading.Tasks;

namespace ExecutePaymentShipping
{
    class Program
    {
        // You can get test token from this page  https://myfatoorah.readme.io/docs/test-token
        static string token = "";
        static string baseURL = "https://apitest.myfatoorah.com";

        static async Task Main(string[] args)
        {
            var executeResponse = await ExecutePayment().ConfigureAwait(false);
            Console.WriteLine("Execute Payment Response :");
            Console.WriteLine(executeResponse);

            Console.ReadLine();
        }

        public static async Task<string> ExecutePayment()
        {
            var executePaymentRequest = new
            {
                //required fields
                PaymentMethodId = "20",
                InvoiceValue = 1000,
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
                ShippingMethod = 1,
                CustomerAddress = new
                {
                    Block = "Bl",
                    AddressInstructions = "instr",
                    HouseBuildingNo = "11",
                    Street = "Street1"
                },
                InvoiceItems = new[] {
                        new {
                          ItemName = "item1", Quantity = 2, UnitPrice = 500, Weight = 1, Width = 1, Height = 1, Depth = 1
                        }
                 },
                ShippingConsignee = new
                {
                    PersonName = "PN",
                    CityName = "ABBASIYA",
                    CountryCode = "kw",
                    EmailAddress = "Email@example.com",
                    LineAddress = "Address",
                    Mobile = "01000013",
                    PostalCode = "1111"
                }

            };
            var executeRequestJSON = JsonConvert.SerializeObject(executePaymentRequest);
            return await PerformRequest(executeRequestJSON, "ExecutePayment").ConfigureAwait(false);
        }
        public static async Task<string> PerformRequest(string requestJSON, string endPoint)
        {
            string url = baseURL + $"/v2/{endPoint}";
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
#Execute Payment API

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
    elif check_data("ErrorMessage", response_data["Data"]):
        error = response_data["Data"]["ErrorMessage"]
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


### Initiate Payment endpoint Function
def initiate_payment(initiatepay_request):
    api_url = base_url + "/v2/InitiatePayment"
    initiatedpay_response = call_api(api_url, api_key, initiatepay_request).json()
    payment_methods = initiatedpay_response["Data"]["PaymentMethods"]
    # Initiate Payment output if successful
    #print("Payment Methods: ", payment_methods)
    return payment_methods


### Execute Payment endpoint Function
def execute_payment(executepay_request):
    api_url = base_url + "/v2/ExecutePayment"
    executepay_response = call_api(api_url, api_key, executepay_request).json()
    invoice_id = executepay_response["Data"]["InvoiceId"]
    invoice_url = executepay_response["Data"]["PaymentURL"]
    # Execute Payment output if successful
    print("InvoiceId: ", invoice_id,
          "\nInvoiceURL: ", invoice_url)
    return invoice_id, invoice_url



### Test Environment
base_url = "https://apitest.myfatoorah.com"
api_key = "MyTokenValue"  # Test token value to be placed here: https:#myfatoorah.readme.io/docs/test-token

### Live Environment
### base_url = "https:#api.myfatoorah.com"
### api_key = "mytokenvalue" #Live token value to be placed here: https:#myfatoorah.readme.io/docs/live-token


### Initaite Payment request data
initiatepay_request = {
                    "InvoiceAmount": 100,
                    "CurrencyIso": "KWD"
                    }

invoice_items = [
                {
                    "ItemName": "string",
                    "Quantity": 20,
                    "UnitPrice": 5,
                    "Description": "string",
                    "Weight": 0.5,
                    "Width": 30,
                    "Height": 15,
                    "Depth": 30
                }
            ]

shipping_consignee = {
                     "PersonName": "fname lname",
                     "Mobile": "1234567890",
                     "LineAddress": "Address",
                     "CityName": "DUBAI",
                     "PostalCode": "12345",
                     "CountryCode": "AE"}

shiping_method = 1 # 1 for DHL, 2 for Aramex


### Getting the value of payment Method Id
payment_method = initiate_payment(initiatepay_request)
### payment_method_id = payment_method[-1]["PaymentMethodId"]

try:
    # Getting the value of payment Method Id
    payment_method = initiate_payment(initiatepay_request)

    # Creating a simplified list for payment methods
    payment_method_list = []
    for item in range(len(payment_method)):
        if payment_method[item]["IsDirectPayment"] == False:
            y = [payment_method[item].get(key) for key in ["PaymentMethodEn", "PaymentMethodId"]]
            payment_method_list.append(y)
    print(payment_method_list)


    # Get the payment method key.
    while True:
        payment_method_id = input("Kindly enter the number equivalent to the required payment method: ")
        try:
            if int(payment_method_id) in [el[1] for el in payment_method_list]:
                break
            else:
                print("Kindly enter a correct payment method id")
        except:
            print("The input must be a number")

    # Based on the initiate payment response, we select the value of reference number to choose payment method

    # Execute Payment Request
    executepay_request = {
                         "paymentMethodId" : payment_method_id,
                         "InvoiceValue"    : 100,
                         "CallBackUrl"     : "https://example.com/callback.php",
                         "ErrorUrl"        : "https://example.com/callback.php",
                         "InvoiceItems": invoice_items,
                         "ShippingConsignee": shipping_consignee,
                         "ShippingMethod": shiping_method,
                    # Fill optional data
                         #"CustomerName"       : "fname lname",
                         #"DisplayCurrencyIso" : "KWD",
                         #"MobileCountryCode"  : "+965",
                         #"CustomerMobile"     : "1234567890",
                         #"CustomerEmail"      : "email@example.com",
                         #"Language"           : "en", #or "ar"
                         #"CustomerReference"  : "orderId",
                         #"CustomerCivilId"    : "CivilId",
                         #"UserDefinedField"   : "This could be string, number, or array",
                         #"ExpiryDate"         : "", #The Invoice expires after 3 days by default. Use "Y-m-d\TH:i:s" format in the "Asia/Kuwait" time zone.
                         #"SourceInfo"         : "Pure PHP", #For example: (Laravel/Yii API Ver2.0 integration)
                         #"CustomerAddress"    : "customerAddress",
                         #"InvoiceItems"       : "invoiceItems",
                    }

    execute_payment(executepay_request)
except:
    ex_type, ex_value, ex_traceback = sys.exc_info()
    print("Exception type : %s " % ex_type.__name__)
    print("Exception message : %s" % ex_value)
```

***

#### **Direct Payment with Shipping**

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
     * Vendor Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'vcCode' => 'KWT',
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
$invoiceItems[] = [
    'ItemName'  => 'Item Name', //ISBAN, or SKU
    'Quantity'  => '2', //Item's quantity
    'UnitPrice' => '25', //Price per item
    'weight'    => 0.250, //Weight must be in kg.
    'Width'     => 11.4, //It must be in cm.
    'Height'    => 2.6, //It must be in cm.
    'Depth'     => 3.2, //It must be in cm.
];

//Shipping Consignee
$shippingConsignee = array(
    //Fill required data
    'PersonName'  => 'fname lname',
    'Mobile'      => '1234567890',
    'LineAddress' => 'Address',
    'CityName'    => 'DUBAI',
    'PostalCode'  => '12345',
    'CountryCode' => 'AE'
        //Fill optional data
        //'EmailAddress' => 'email@example.com',
);

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
    'InvoiceItems'      => $invoiceItems,
    'ShippingConsignee' => $shippingConsignee,
    'ShippingMethod'    => 1, //1 for DHL and 2 for Aramex
        //'Suppliers'          => $suppliers,
];

//------------- Call the Endpoint -------------------------
try {
    $mfObj = new MyFatoorahPayment($mfConfig);
    $data  = $mfObj->executePayment($postFields);

    //You can save payment data in database as per your needs
    $invoiceId   = $data->InvoiceId;
    $paymentLink = $data->PaymentURL;
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
```python
### Direct Payment End Point

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
    elif check_data("ErrorMessage", response_data["Data"]):
        error = response_data["Data"]["ErrorMessage"]
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


### Initiate Payment endpoint Function
def initiate_payment(initiatepay_request):
    api_url = base_url + "/v2/InitiatePayment"
    initiatepay_response = call_api(api_url, api_key, initiatepay_request).json()
    payment_methods = initiatepay_response["Data"]["PaymentMethods"]
    # Initiate Payment output if successful
    #print("Payment Methods: ", payment_methods)
    return payment_methods


### Execute Payment endpoint Function
def execute_payment(executepay_request):
    api_url = base_url + "/v2/ExecutePayment"
    executepay_response = call_api(api_url, api_key, executepay_request).json()
    invoice_id = executepay_response["Data"]["InvoiceId"]
    invoice_url = executepay_response["Data"]["PaymentURL"]
    # Execute Payment output if successful
    #print("InvoiceId: ", invoice_id,
    #      "\nInvoiceURL: ", invoice_url)
    return invoice_id, invoice_url


### Direct Payment endpoint Function
### The payment link from execute payment is used as the API for direct payment
def direct_payment(directpay_request, invoice_url):
    directpay_response = call_api(invoice_url, api_key, directpay_request).json()
    directpay_status = directpay_response["Data"]
    # Direct Payment output if successful
    print("Direct Payment Status: ", directpay_status)
    return directpay_status


### Test Environment
base_url = "https://apitest.myfatoorah.com"
api_key = "MyTokenValue"  # Test token value to be placed here: https:#myfatoorah.readme.io/docs/test-token

### Live Environment
### base_url = "https:#api.myfatoorah.com"
### api_key = "mytokenvalue" #Live token value to be placed here: https:#myfatoorah.readme.io/docs/live-token


### Initaite Payment request data
initiatepay_request = {
                      "InvoiceAmount": 100,
                      "CurrencyIso": "KWD"
                    }

invoice_items = [
                {
                    "ItemName": "string",
                    "Quantity": 20,
                    "UnitPrice": 5,
                    "Description": "string",
                    "Weight": 0.5,
                    "Width": 10,
                    "Height": 15,
                    "Depth": 19
                }
            ]

shipping_consignee = {
                     "PersonName": "fname lname",
                     "Mobile": "1234567890",
                     "LineAddress": "Address",
                     "CityName": "DUBAI",
                     "PostalCode": "12345",
                     "CountryCode": "AE"}


shipping_method = 1 # 1 for DHL, 2 for Aramex


try:
    # Getting the value of payment Method Id
    payment_method = initiate_payment(initiatepay_request)

    # Creating a simplified list for payment methods
    payment_method_list = []
    for item in range(len(payment_method)):
        if payment_method[item]["IsDirectPayment"] == True:
            y = [payment_method[item].get(key) for key in ["PaymentMethodEn", "PaymentMethodId"]]
            payment_method_list.append(y)
    print(payment_method_list)


    # Get the payment method key.
    while True:
        payment_method_id = input("Kindly enter the number equivalent to the required payment method: ")
        try:
            if int(payment_method_id) in [el[1] for el in payment_method_list]:
                break
            else:
                print("Kindly enter a correct payment method id")
        except:
            print("The input must be a number")

    # Based on the initiate payment response, we select the value of reference number to choose payment method

    # Execute Payment Request
    executepay_request = {
                         "paymentMethodId" : payment_method_id,
                         "InvoiceValue"    : 100,
                         "CallBackUrl"     : "https://example.com/callback.php",
                         "ErrorUrl"        : "https://example.com/callback.php",
                         "InvoiceItems": invoice_items,
                         "ShippingConsignee": shipping_consignee,
                         "ShippingMethod": shipping_method,
                    # Fill optional data
                         # "CustomerName"       : "fname lname",
                         # "DisplayCurrencyIso" : "KWD",
                         # "MobileCountryCode"  : "+965",
                         # "CustomerMobile"     : "1234567890",
                         # "CustomerEmail"      : "email@example.com",
                         # "Language"           : "en", #or "ar"
                         # "CustomerReference"  : "orderId",
                         # "CustomerCivilId"    : "CivilId",
                         # "UserDefinedField"   : "This could be string, number, or array",
                         # "ExpiryDate"         : "", # The Invoice expires after 3 days by default. Use "Y-m-d\TH:i:s" format in the "Asia/Kuwait" time zone.
                         # "SourceInfo"         : "Pure PHP", #For example: (Laravel/Yii API Ver2.0 integration)
                         # "CustomerAddress"    : $customerAddress,
                         # "InvoiceItems"       : $invoiceItems,
                         }
### Execute payment t get Invoice Id and Invoice URL
    invoice_id, invoice_url = execute_payment(executepay_request)

    # Required Data for direct Payment
    directpay_request = {
                            "PaymentType": "card",
                            "Bypass3DS": False,
                            "SaveToken": "false",
                            "Token": "string",
                            "Card": {
                                "Number": "5123450000000008",
                                "ExpiryMonth": "05",
                                "ExpiryYear": "21",
                                "SecurityCode": "100",
                                "CardHolderName": "fname lname"
                            }
                         }

    direct_payment(directpay_request, invoice_url)
except:
    ex_type, ex_value, ex_traceback = sys.exc_info()
    print("Exception type : %s " % ex_type.__name__)
    print("Exception message : %s" % ex_value)



### Test Card Data for Visa/Master
### {
### "PaymentType": "card",
### "Bypass3DS": False,
### "SaveToken": False,
### "Card": {
###       "Number": "5453010000095539",
###       "ExpiryMonth": "12",
###       "ExpiryYear": "25",
###       "SecurityCode": "300",
###      }
###      }
```

***
