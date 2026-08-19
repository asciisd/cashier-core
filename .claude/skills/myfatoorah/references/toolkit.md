# MyFatoorah — Toolkit

## Overview

*`https://docs.myfatoorah.com/docs/toolkit-overview` — updated 2025-11-11*

> Useful Toolkit

A toolkit is a set of guidelines that suggests how a practical example of the implementation can be accomplished. Toolkits chart out a plan of action that explains the topic at hand and offers a set of suggestions that can be followed to achieve a particular goal.

**MyFatoorah** is pleased to provide practical tools and some useful toolkits to help you examining and understanding the [API endpoints](https://docs.myfatoorah.com/docs/technical-guide-overview) as well as to support you in speeding your integration with **MyFatoorah**.

* [Postman](https://docs.myfatoorah.com/docs/postman)
* [PHP Library](https://docs.myfatoorah.com/docs/php-library)
* [PHP ToolKit](https://myfatoorah.a2hosted.com/toolkit). Also, You can [download](https://myfatoorahkw-my.sharepoint.com/:u:/g/personal/rsaeed_myfatoorah_com/EYQMIsfUBHhJtbPWy6G9p-YBu5oD6yUfhCYbnq2VQeNP8A?e=YDo8bz) it and host it in your webserver to examine it.
* [Omnipay ToolKit](https://myfatoorah.a2hosted.com/omnipay/gateways/Myfatoorah). Also, You can use it directly in your project as explained in [Omnipay](https://docs.myfatoorah.com/docs/omnipay) section.
* [Asp.net Core C# ToolKit](https://myfatoorahkw-my.sharepoint.com/:u:/g/personal/rsaeed_myfatoorah_com/EcUNw30bxERIoiJ7of5qhoYBwusz2ZHBzkfM2P_limqbVw?e=dNUVmq), download it and examine it in your local environment.

## PHP Library

*`https://docs.myfatoorah.com/docs/php-library` — updated 2025-11-11*

> Current Version: 2.2

#### Installation Steps

Install the PHP library package via [myfatoorah/library](https://packagist.org/packages/myfatoorah/library) composer by running the below command:

```text
composer require myfatoorah/library
```

> 👍 Library as a Zip File
>
> If your web application does not support the composer package, be free to download the library as a zip file from [here](https://dev.azure.com/myfatoorahsc/Public-Repo/_git/Public-Repo?path=/php/library/myfatoorah-library-2.2.zip\&version=GBmaster).

***

#### Payment Operations

To handle a payment operation, create an object of the **MyFatoorahPayment** class. Then you can use one of the following functions.

###### <ins>The **getInvoiceURL** function</ins>

It gets the invoice or payment URL as well as the invoice id as it appliances for both [SendPayment](https://docs.myfatoorah.com/docs/send-payment) and [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoints. Moreover, It implements the [embedded payment](https://docs.myfatoorah.com/docs/embedded-payment).

```php
<?php
$config = [
    'apiKey' => '',
    'vcCode' => 'KWT',
    'isTest' => false,
];

$paymentMethodId = 0; //to be redirect to MyFatoorah invoice page
//$paymentMethodId = 1; //to be redirect to Knet payment page if you are using test API token key
$postFields      = [
    'InvoiceValue' => '50',
    'CustomerName' => 'fname lname',
];

try {
  	$mfObj = new MyFatoorahPayment($config);
    $data  = $mfObj->getInvoiceURL($postFields, $paymentMethodId);

    $invoiceId   = $data->InvoiceId;
    $paymentLink = $data->InvoiceURL;

    echo "Click on <a href='$paymentLink' target='_blank'>$paymentLink</a> to pay with invoiceID $invoiceId.";
} catch (Exception $ex) {
    echo $ex->getMessage();
}
```

###### <ins>The **initiatePayment** function</ins>

It lists the available payment gateways and implements the [InitiatePayment](https://docs.myfatoorah.com/docs/initiate-payment) endpoint.

```php
<?php

try {
    $mfObj = new MyFatoorahPayment($config);
    $paymentMethods = $mfObj->initiatePayment();

    print_r($paymentMethods);
} catch (Exception $ex) {
    echo $ex->getMessage();
}
```

###### <ins>The **getPaymentStatus** function</ins>

It gets the payment transaction status and implements the [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status)  endpoint.

```php
<?php

$keyId   = '32090';
$KeyType = 'invoiceid';

try {
    $mfObj = new MyFatoorahPaymentStatus($config);
    $data  = $mfObj->getPaymentStatus($keyId, $KeyType);

    print_r($data);
} catch (Exception $ex) {
    echo $ex->getMessage();
}
```

***

#### Shipping Operations

To handle any shipping operation, create an object of the **MyFatoorahShipping** class. Then you can use one of the following functions:

###### <ins>The **calculateShippingCharge** function</ins>

It calculates the shipping charge and implements the [CalculateShippingCharge](https://docs.myfatoorah.com/docs/calculate-shipping-charge) endpoint.

```php
<?php
$config = [
    'apiKey' => '',
    'vcCode' => 'KWT',
    'isTest' => false,
];

$invoiceItems[] = [
    'ProductName' => 'Product Name',
    "Description" => 'Description',
    'weight'      => 5,
    'Width'       => 10,
    'Height'      => 15,
    'Depth'       => 20,
    'Quantity'    => 1,
    'UnitPrice'   => 123,
];

$curlData = [
    'ShippingMethod' => 1,
    'Items'          => $invoiceItems,
    'CountryCode'    => 'EG',
    'CityName'       => 'Alexandria',
    'PostalCode'     => '12345',
];

try {
    $mfObj = new MyFatoorahShipping($config);
    $json  = $mfObj->calculateShippingCharge($curlData);

    print_r($json);
} catch (Exception $ex) {
    echo $ex->getMessage();
}
```

###### <ins>The **getShippingCountries** function</ins>

It gets **MyFatoorah** shipping countries and implements the [GetCountries](https://docs.myfatoorah.com/docs/get-countriesthe) endpoint.

```php
<?php

try {
    $mfObj = new MyFatoorahShipping($config);
    $data  = $mfObj->getShippingCountries();

    print_r($data);
} catch (Exception $ex) {
    echo $ex->getMessage();
}
```

###### <ins>The **getShippingCities** function</ins>

It gets **MyFatoorah** shipping cities and implements the [GetCities](https://docs.myfatoorah.com/docs/get-cities) endpoint.

```php
<?php

$method      = 1;
$countryCode = 'EG';
$searchValue = 'Alex';

try {
    $mfObj = new MyFatoorahShipping($config);
    $data  = $mfObj->getShippingCities($method, $countryCode, $searchValue);

    print_r($data);
} catch (Exception $ex) {
    echo $ex->getMessage();
}
```

***

#### General Operations

Some functions handle some useful operations like:

###### <ins>The **getCurrencyRate** function</ins>

It gets the rate of a given currency according to the default currency of the MyFatoorah portal account.

```php
<?php

try {
    $mfObj = new MyFatoorahList($config);
    $rate  = $mfObj->getCurrencyRate('EGP');

    echo($rate);
} catch (Exception $ex) {
    echo $ex->getMessage();
}
```

###### <ins>The static **getPhone** function</ins>

It gets the country code and the phone after applying the MyFatoorah restriction.

```php
<?php

try {
    $phoneArr = MyFatoorah::getPhone('+2123456789');

    print_r($phoneArr);
} catch (Exception $ex) {
    echo $ex->getMessage();
}
```

###### <ins>The static **getWeightRate** function</ins>

It gets the rate to convert the given weight unit to MyFatoorah's default weight unit.

```php
<?php

try {
    $weightRate = MyFatoorah::getWeightRate('lbs');

    echo($weightRate);
} catch (Exception $ex) {
    echo $ex->getMessage();
}
```

###### <ins>The static **getDimensionRate** function</ins>

It gets the rate to convert the given dimension unit to MyFatoorah's default dimension unit.

```php
<?php

try {
    $dimensionRate = MyFatoorah::getDimensionRate('in');

    echo($dimensionRate);
} catch (Exception $ex) {
    echo $ex->getMessage();
}
```

***
