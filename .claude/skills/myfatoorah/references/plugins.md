# MyFatoorah — E-commerce plugins

## Overview

*`https://docs.myfatoorah.com/docs/plugin-overview` — updated 2025-11-11*

> Plugins and Addons

**MyFatoorah** provides ready-made API integration plugins on several **web** platforms. This section guides you through the step-by-step procedure to install a plugin and configure the merchant details.

We support the following e-commerce platforms:

* [CS-Cart](https://docs.myfatoorah.com/docs/cs-cart)
* [Drupal Commerce](https://docs.myfatoorah.com/docs/drupalcommerce)
* [Ecwid](https://docs.myfatoorah.com/docs/ecwid)
* [ExpandCart](https://docs.myfatoorah.com/docs/expandcart) (developed by the ExpandCart team)
* [GoDaddy](https://docs.myfatoorah.com/docs/godaddy)
* [Joomla 3.x](https://docs.myfatoorah.com/docs/joomla-3x)
* [Laravel](https://docs.myfatoorah.com/docs/laravel)
* [Magento2](https://docs.myfatoorah.com/docs/magento2)
* [nopCommerce](https://docs.myfatoorah.com/docs/nopcommerce)
* [Omnipay](https://docs.myfatoorah.com/docs/omnipay)
* [OpenCart](https://docs.myfatoorah.com/docs/opencart)
* [PrestaShop](https://docs.myfatoorah.com/docs/prestashop)
* [Shopify](https://docs.myfatoorah.com/docs/shopify)
* [Symfony](https://docs.myfatoorah.com/docs/symfony)
* [WHMCS](https://docs.myfatoorah.com/docs/whmcs)
* [WooCommerce](https://docs.myfatoorah.com/docs/woocommerce)
* [Zoho](https://docs.myfatoorah.com/docs/zoho)

Also, **MyFatoorah** provides the shipping API plugin for the below platforms:

* [Magento2](https://docs.myfatoorah.com/docs/magento2-shipping)
* [OpenCart](https://docs.myfatoorah.com/docs/opencart-shipping)
* [WooCommerce](https://docs.myfatoorah.com/docs/woocommerce-shipping)

## Plugin API Permissions

*`https://docs.myfatoorah.com/docs/plugin-api-permissions` — updated 2026-02-23*

Please check the documentation below for the correct permissions to use.
Kindly note that you can disable Refund, shipping, and the multi-suppliers permission if you don't need them. However, you should keep the others.

**Refund:** Make Refund and Make Supplier Refund( in case of multi-supplier)\
**Multi-suppliers or Multi-vendors:** Get Supplier Dashboard\
**Shipping:** Get Countries, Get Cities, and Calculate Shipping Charge

![](https://files.readme.io/df4ecf25cac29648bc360c59d4a88c2b5bf5ad9e12f383828b0ca4cdb5e6d3cc-image.png)

<br />

## CS-Cart

*`https://docs.myfatoorah.com/docs/cs-cart` — updated 2026-04-08*

### Source Files

* You can download the last plugin version from the official <a href="https://marketplace.cs-cart.com/myfatoorah-en-2.html" target="_blank">CS-Cart</a> site.

***

### Installation Steps

Kindly follow the below steps for installing the CS-Cart extension:

1. Login into your CS-Cart admin panel. Navigate to **Add-ons**, then **Manage add-ons**.
2. Click on the (**+**) icon in the upper right corner.
3. Choose **Local** and Select the **cs4-MyFatoorah-API-Ver2.0.zip** file.
4. Click on the **Upload & install** button.
5. The **MyFatoorah** plugin will appear on the **Add-ons** list
6. Click on the **Install** button.

![MyFatoorah-CS-Cart-Install.png](https://files.readme.io/414d904-MyFatoorah-CS-Cart-Install.png)

***

### Merchant Configurations

In the CS-Cart Admin Panel, configure the plugin with the credentials as follows:

1. Login into your **CS-Cart** admin panel and navigate to Add-ons → Manage add-ons → MyFatoorah.
2. Click on **MyFatoorah** Name to load the configuration page, then open the **Settings** tab.
3. Select the **Vendor's Country** Option.
4. Mark the **Test Mode** checkbox if you are in the test mode.
5. Fill in the **API Token Key**, and click on **Save**.

![step 2](https://files.readme.io/edfcff7-MyFatoorah-CS-Cart.png)

![step 3 and 4](https://files.readme.io/39f6f71-MyFatoorah_CS-Cart_Settings.png)

<br />

<ins>Demo Configuration:</ins>\
Please enable the test mode and use

[demo token](https://docs.myfatoorah.com/docs/test-token).\
You can use the list of [test cards](test-cards) to explore the payment process.

<br />

<ins>Live Configuration:</ins>\
Please disable the test mode and use

[your live token](https://docs.myfatoorah.com/docs/api-key).

***

#### Upgrade MyFatoorah Package

In the CS-Cart Admin Panel, configure the plugin with the credentials as follows:

1. Login to your **CS-Cart** admin panel and navigate to **Administration** → **Upgrade Center**.
2. In the **MyFatoorah** section, click the download button and wait to finish downloading.
3. Press the install button.
4. Click on **I agree and continue** from the **Warning** pop-up window.
5. The **Successful Add-on "MyFatoorah" has been upgraded successfully** message will show up, and the **MyFatoorah** upgrade package will be listed on the **Installed upgrades** tab.
6. Navigate to **Administration** → **Storefronts** and switch your site status to **ON**.

![step 2](https://files.readme.io/c442119-Download_MyFatoorah.png)

![step 3](https://files.readme.io/b56c90b-Install_MyFatoorah.png)

![step 4](https://files.readme.io/3ef8821-Warning_Pop-up_Window.png)

![step 5](https://files.readme.io/d80078a-Successful_Message.png)

![step 6](https://files.readme.io/60c62db-Enable_CS-Cart_site.png)

## Webhook

*`https://docs.myfatoorah.com/docs/cs-cart-webhook` — updated 2025-11-11*

#### Why Using Webhook

You can enable the Webhook feature to trigger events each time the order status changes at the MyFatoorah side. This feature will recover the lost orders due to connection loss or delayed callbacks.

***

#### **MyFatoorah Account**

1. Log in to your [MyFatoorah portal account](https://portal.myfatoorah.com/) and go to Integration Settings → Webhook Settings and configure your settings.
2. Enable the feature.
3. Add your **Endpoint** URL. Replace only the **\{example.com}** with your site URL.

```
https://{example.com}/index.php?dispatch=myfatoorah.webhook
```

4. Generate the **Webhook Secret Key** and copy it by clicking on the copy icon :clipboard: on the right side of the secret key box.
5. Select the **Transaction Status Changed** event type.
6. Click on the Save button.

![MyFatoorah Webhook.png](https://files.readme.io/5ef6304-MyFatoorah_Webhook.png)

***

#### **CS-Cart Account**

1. Login into your **CS-Cart** admin panel and navigate to Add-ons → Manage add-ons → MyFatoorah.
2. Click on **MyFatoorah** Name to load the configuration page then open the **Settings** tab.
3. Paste the secret key into the **Webhook Secret Key** text box.
4. Click on the **Save** button.

![CS-Cart Webhook.png](https://files.readme.io/b3a7952-CS-Cart_Webhook.png)

***

## Embedded Apple Pay

*`https://docs.myfatoorah.com/docs/cs-cart-embedded-apple-pay` — updated 2025-11-11*

> Cs-Cart

#### **Introduction**

To provide a better user experience to your Apple Pay users, **MyFatoorah** is providing the Apple Pay embedded payment.

The Apple Pay button can be placed on your checkout page. When your customers click the button,  **MyFatoorah** will direct the customers to the Apple Pay payment sheet page to authorize the payment.

![Apple Pay.png](https://files.readme.io/4f07adc-Apple_Pay.png)

![Apple Pay Payment Sheet.png](https://files.readme.io/db15645-Apple_Pay_Payment_Sheet.png)

***

#### **Apple Pay Activation Steps**

Add the Apple Pay verification file to your web domain.

1. Host your domain verification file.
2. Activate Apple Pay Embedded in the admin panel.

#### Step1: Host your domain verification file

Steps:\
&#x9;1- Create a folder in the root path, and name it ".well-known".\
&#x9;2- Paste the file inside the ".well-known" folder.\
&#x9;3- Test that the file is located in the correct place by opening the URL below. Replace only the \{example.com} with your site URL.\
&#x9;<https://{example.com}/.well-known/apple-developer-merchantid-domain-association>

```text Path
https://{example.com}/.well-known/apple-developer-merchantid-domain-association
```

> 🚧 Domain Verification File
>
> Do not remove the file from the server. Apple might need to re-validate the domain every year, and this file must be available for successful validation.

> ❗️ SSL
>
> Domain should be TLS (HTTPS) enabled.

#### Step2: Activate Apple Pay Embedded in the admin panel

From the MyFatoorah Plugin Setting in your admin, make sure to make Apple Pay Embedded Enabled.

![woo-applePay.png](https://files.readme.io/e946202-Manage_add-ons_-_Administration_panel.png)

***

## Drupal Commerce

*`https://docs.myfatoorah.com/docs/drupalcommerce` — updated 2026-04-08*

#### Source Files

Install the MyFatoorah Drupal Commerce module via [myfatoorah/myfatoorah\_drupal\_commerce](https://packagist.org/packages/myfatoorah/myfatoorah_drupal_commerce) composer.

```text
composer require myfatoorah/myfatoorah_drupal_commerce
```

Or you can check the last plugin version from the official <a href="https://www.drupal.org/project/myfatoorah" target="_blank">Drupal marketplace</a> site.

***

#### Requirements

* Drupal Commerce 2.0 up to the last updated version

***

#### Installation steps

In the admin panel, Extend tab, search for the Myfatoorah module, select it, and click the install button to install it.

![Installation steps](https://files.readme.io/5c6ee9c-Extend.png)

***

#### Merchant Configurations

In Drupal Admin Panel, follow the steps below:

1. Go to Commerce → Configuration → Payments → Payment Gateways section.
2. Fill in Gateway configuration and use the API key as below.

![Merchant Configurations](https://files.readme.io/4dbd9de-Add_payment_gateway.png)

<br />

<ins>Demo Configuration:</ins>\
Please, choose **test** Mode, and use

[demo token](https://docs.myfatoorah.com/docs/test-token) without the word **"bearer"**.\
You can use the list of [test cards](test-cards) to explore the payment process.

<br />

<ins>Live Configuration:</ins>\
Please, choose **live** Mode, and use

[your live token](https://docs.myfatoorah.com/docs/api-key).

<br />

## Ecwid

*`https://docs.myfatoorah.com/docs/ecwid` — updated 2025-11-11*

> Plugin

### Installation Steps

> 🚧 Upgrade your Ecwid Plan
>
> You should **upgrade** your Ecwid plan to be enabled to process your payments.

Kindly follow the below steps for installing the Ecwid extension:

1. Log into your Ecwid admin panel.
2. Install the **MyFatoorah** application from [here](https://my.ecwid.com/cp#apps:view=app\&name=my-fatoorah).
3. After installing the app, Go to Ecwid admin and check the **MyFatoorah** App in the Payments section. If you have not found the **MyFatoorah** App, contact Ecwid support to add an application to the "Payments" section.

***

### Merchant Configurations

In Ecwid Admin Panel, configure the plugin with API key credential as follows:

1. Log into your Ecwid admin panel, then navigate to **Payment** → **MyFatoorah**.
2. Select the **Vendor's Country**.
3. Mark the **Test Mode** checkbox if you are on the test mode.
4. Fill in the **API Token Key**.
5. Add the [Webhook Secret Key](https://docs.myfatoorah.com/docs/ecwid-webhook) (optional).
6. Click on **Save MyFatoorah Configuration**.

![](https://files.readme.io/7f06b89-Ecwid.png "Ecwid.png")

<ins>Demo Configuration:</ins>\
Please, enable the test mode and use the [test token](https://docs.myfatoorah.com/docs/test-token).\
You can use the list of [test cards](test-cards) to explore the payment process.

<ins>Live Configuration:</ins>\
Please, disable the test mode and use [your live token](live-token).

***

## Webhook

*`https://docs.myfatoorah.com/docs/ecwid-webhook` — updated 2025-11-11*

> Ecwid

#### Why Using Webhook

You can enable the Webhook feature to trigger events each time the order status changes at the MyFatoorah side. This feature will recover the lost orders due to connection loss or delayed callbacks.

***

#### **MyFatoorah Account**

1. Log in to your [Myfatoorah account](https://portal.myfatoorah.com/) and go to Integration Settings → Webhook Settings and configure your settings.
2. Enable the feature.
3. Add your **Endpoint** URL. Replace only the \{STORE\_ID} with your Ecwid store id.

```
https://myfatoorah.a2hosted.com/ecwid/api/webhook.php?SID={STORE_ID}&ANM=ecw
```

4. Generate the **Webhook Secret Key** and copy it by clicking on the copy icon :clipboard: on the right side of the secret key box.
5. Select the **Transaction Status Changed** event type.
6. Click on the Save button.

![Ecwid Endpoint.png](https://files.readme.io/6f288f8-Ecwid_Endpoint.png)

***

#### **Ecwid Account**

1. Login into your **Ecwid** admin panel and navigate to Payment → MyFatoorah.
2. Paste the secret key into the **MyFatoorah Webhook Secret Key** text box.
3. Click on the **Save MyFatoorah Configuration** button.

![Ecwid Webhook Secret Key.png](https://files.readme.io/9df411b-Ecwid_Webhook_Secret_Key.png)

***

## ExpandCart

*`https://docs.myfatoorah.com/docs/expandcart` — updated 2025-11-11*

> 📘 Myfatoorah ExpandCart Plugin
>
> This plugin was developed by the ExpandCart team.

### Installation Steps

Kindly, follow [the steps](https://support.expandcart.com/en/article/myfatoorah-v2-installation-guide-1eu5tqy/) provided by the ExpandCart team.

## GoDaddy

*`https://docs.myfatoorah.com/docs/godaddy` — updated 2025-11-11*

### Installation Steps

> ❗️ Godaddy (ECWID)
>
> This integration must be on Goddday ECWID website base only.

Kindly, follow the below steps for installing the GoDaddy extension:

1. Log into your GoDaddy admin panel, go to **ECOMMERCE**, then press the **Manage store** button.
2. Navigate to **Payment** and scroll down to **More options to accept online payments in \{the website country}** section.
3. Select **MyFatoorah** from the **Choose Payment Method** list.
4. From the **New Payment Method: MyFatoorah** popup window, click on **Add Payment Method**.
5. **MyFAtoorah** Configuration page will appear to enter your information as described in the next section.

![Choose MyFatoorah - GoDaddy.png](https://files.readme.io/ba31565-Choose_MyFatoorah_-_GoDaddy.png)

![Add MyFatoorah - GoDaddy.png](https://files.readme.io/273c54b-Add_MyFatoorah_-_GoDaddy.png)

***

### Merchant Configurations

In GoDaddy Admin Panel, configure the plugin with API key credentials as follows:

1. Log into your GoDaddy admin panel, go to **ECOMMERCE**, then press the **Manage store** button.
2. Navigate to **Payment** → **MyFatoorah**
3. Select the **Vendor's Country**.
4. Mark the **Test Mode** checkbox if you are on the test mode.
5. Fill in the **API Token Key**.
6. Add the [Webhook Secret Key](https://docs.myfatoorah.com/docs/godaddy-webhook) (optional).
7. Click on **Save MyFatoorah Configuration**.

![MyFatoorah - GoDaddy.png](https://files.readme.io/6a1b3a3-MyFatoorah_-_GoDaddy.png)

<ins>Demo Configuration:</ins>\
Please, enable the test mode and use the [test token](https://docs.myfatoorah.com/docs/test-token).\
You can use the list of [test cards](test-cards) to explore the payment process.

<ins>Live Configuration:</ins>\
Please, disable the test mode and use [your live token](live-token).

***

## Webhook

*`https://docs.myfatoorah.com/docs/godaddy-webhook` — updated 2025-11-11*

> GoDaddy

#### Why Using Webhook

You can enable the Webhook feature to trigger events each time the order status changes at the MyFatoorah side. This feature will recover the lost orders due to connection loss or delayed callbacks.

***

#### **MyFatoorah Account**

1. Log in to your [Myfatoorah account](https://portal.myfatoorah.com/) and go to Integration Settings → Webhook Settings and configure your settings.
2. Enable the feature.
3. Add your **Endpoint** URL. Replace only the \{STORE\_ID} with your GoDaddy store id.

```
https://myfatoorah.a2hosted.com/ecwid/api/webhook.php?SID={STORE_ID}&ANM=wl
```

4. Generate the **Webhook Secret Key** and copy it by clicking on the copy icon :clipboard: on the right side of the secret key box.
5. Select the **Transaction Status Changed** event type.
6. Click on the Save button.

![Godday_Endpoint.png](https://files.readme.io/94dfd2e-Godday_Endpoint.png)

***

#### **GoDaddy Account**

1. Log into your GoDaddy admin panel, go to **ECOMMERCE**, then press the **Manage store** button.
2. Navigate to **Payment** → **MyFatoorah**
3. Paste the secret key into the **MyFatoorah Webhook Secret Key** text box.
4. Click on the **Save MyFatoorah Configuration** button.

![GoDaddy Webhook Secret Key.png](https://files.readme.io/6ef0904-GoDaddy_Webhook_Secret_Key.png)

***

## Joomla 3.x

*`https://docs.myfatoorah.com/docs/joomla-3x` — updated 2025-11-11*

#### Source Files

[myfatoorah-2.0.x.x-joomla-virtuemart.zip](https://dev.azure.com/myfatoorahsc/_git/Public-Repo?path=/php/plugin/joomla)

***

#### Installation Steps

Kindly, follow the below steps for installing Joomla – VirtueMart extension:

1. We need to make sure the VirtueMart extension is installed in Joomla
2. Download the **Myfatoorah** Joomla payment Zip file extension.
3. From the Joomla admin panel, go to Extension → Manage → Install and choose the downloaded .zip file
4. After that, go to Virtuemart → payment methods → new, to add payment methods information, and choose Myfatoorah in the payment method dropdown.
5. Then, add the **Myfatoorah** API configuration in the configuration tab

![v2 joomla file.png](https://files.readme.io/1380c60-v2_joomla_file.png)

![VirtueMart Joomla Information Tab.png](https://files.readme.io/c9bc38e-VirtueMart_Joomla_Information_Tab.png)

![VirtueMart Joomla Configuration Tab.png](https://files.readme.io/5cf2ead-VirtueMart_Joomla_Configuration_Tab.png)

<ins>Demo Configuration:</ins>\
Please, choose **Yes** in Test Mode, and use [demo token](https://docs.myfatoorah.com/docs/test-token).\
You can use the list of [test cards](test-cards) to explore the payment process.

<ins>Live Configuration:</ins>\
Please, choose **No** in Test Mode, and use [your live token](live-token).

## Laravel

*`https://docs.myfatoorah.com/docs/laravel` — updated 2025-11-14*

> 2.2.4

#### Source Files

Install the MyFatoorah Laravel package via [myfatoorah/laravel-package](https://packagist.org/packages/myfatoorah/laravel-package) composer.

```c cmd
composer require myfatoorah/laravel-package
```

***

#### Installation steps

> ❗️ Important Note
>
> The MyFatoorah Laravel package provides examples of how to use the MyFatoorah Library and the MyFatoorah API endpoints. Any validations or security criteria must be taken from your side to ensure a seamless payment experience.

1. Publish the **MyFatoorah** provider using the following CLI command.

```c cmd
php artisan vendor:publish --provider="MyFatoorah\LaravelPackage\MyFatoorahServiceProvider" --tag="myfatoorah"
```

2. To test the payment cycle, type the below URL onto your browser. Replace only the **\{example.com}** with your site domain. You can use the test cards listed on the [Test Cards](https://docs.myfatoorah.com/docs/test-cards) page.

```
https://{example.com}/myfatoorah
Or
https://{example.com}/myfatoorah/checkout
```

3. Customize the **app/Http/Controllers/MyFatoorahController.php** file as per your site needs.

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Contracts\View\View;
use MyFatoorah\Library\MyFatoorah;
use MyFatoorah\Library\API\Payment\MyFatoorahPayment;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentEmbedded;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentStatus;
use Exception;

class MyFatoorahController extends Controller {

    /**
     * @var array
     */
    public $mfConfig = [];

//-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Initiate MyFatoorah Configuration
     */
    public function __construct() {
        $this->mfConfig = [
            'apiKey'      => config('myfatoorah.api_key'),
            'isTest'      => config('myfatoorah.test_mode'),
            'countryCode' => config('myfatoorah.country_iso'),
        ];
    }

//-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Redirect to MyFatoorah Invoice URL
     * Provide the index method with the order id and (payment method id or session id)
     *
     * @return Response
     */
    public function index() {
        try {
            //For example: pmid=0 for MyFatoorah invoice or pmid=1 for Knet in test mode
            $paymentId = request('pmid') ?: 0;
            $sessionId = request('sid') ?: null;

            $orderId  = request('oid') ?: 147;
            $curlData = $this->getPayLoadData($orderId);

            $mfObj   = new MyFatoorahPayment($this->mfConfig);
            $payment = $mfObj->getInvoiceURL($curlData, $paymentId, $orderId, $sessionId);

            return redirect($payment['invoiceURL']);
        } catch (Exception $ex) {
            $exMessage = __('myfatoorah.' . $ex->getMessage());
            return response()->json(['IsSuccess' => 'false', 'Message' => $exMessage]);
        }
    }

//-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Example on how to map order data to MyFatoorah
     * You can get the data using the order object in your system
     * 
     * @param int|string $orderId
     * 
     * @return array
     */
    private function getPayLoadData($orderId = null) {
        $callbackURL = route('myfatoorah.callback');

        //You can get the data using the order object in your system
        $order = $this->getTestOrderData($orderId);

        return [
            'CustomerName'       => 'FName LName',
            'InvoiceValue'       => $order['total'],
            'DisplayCurrencyIso' => $order['currency'],
            'CustomerEmail'      => 'test@test.com',
            'CallBackUrl'        => $callbackURL,
            'ErrorUrl'           => $callbackURL,
            'MobileCountryCode'  => '+965',
            'CustomerMobile'     => '12345678',
            'Language'           => 'en',
            'CustomerReference'  => $orderId,
            'SourceInfo'         => 'Laravel ' . app()::VERSION . ' - MyFatoorah Package ' . MYFATOORAH_LARAVEL_PACKAGE_VERSION
        ];
    }

//-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get MyFatoorah Payment Information
     * Provide the callback method with the paymentId
     * 
     * @return Response
     */
    public function callback() {
        try {
            $paymentId = request('paymentId');

            $mfObj = new MyFatoorahPaymentStatus($this->mfConfig);
            $data  = $mfObj->getPaymentStatus($paymentId, 'PaymentId');

            $message = $this->getTestMessage($data->InvoiceStatus, $data->InvoiceError);

            $response = ['IsSuccess' => true, 'Message' => $message, 'Data' => $data];
        } catch (Exception $ex) {
            $exMessage = __('myfatoorah.' . $ex->getMessage());
            $response  = ['IsSuccess' => 'false', 'Message' => $exMessage];
        }
        return response()->json($response);
    }

//-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Example on how to Display the enabled gateways at your MyFatoorah account to be displayed on the checkout page
     * Provide the checkout method with the order id to display its total amount and currency
     * 
     * @return View
     */
    public function checkout() {
        try {
            //You can get the data using the order object in your system
            $orderId = request('oid') ?: 147;
            $order   = $this->getTestOrderData($orderId);

            //You can replace this variable with customer Id in your system
            $customerId = request('customerId');

            //You can use the user defined field if you want to save card
            $userDefinedField = config('myfatoorah.save_card') && $customerId ? "CK-$customerId" : '';

            //Get the enabled gateways at your MyFatoorah acount to be displayed on checkout page
            $mfObj          = new MyFatoorahPaymentEmbedded($this->mfConfig);
            $paymentMethods = $mfObj->getCheckoutGateways($order['total'], $order['currency'], config('myfatoorah.register_apple_pay'));

            if (empty($paymentMethods['all'])) {
                throw new Exception('noPaymentGateways');
            }

            //Generate MyFatoorah session for embedded payment
            $mfSession = $mfObj->getEmbeddedSession($userDefinedField);

            //Get Environment url
            $isTest = $this->mfConfig['isTest'];
            $vcCode = $this->mfConfig['countryCode'];

            $countries = MyFatoorah::getMFCountries();
            $jsDomain  = ($isTest) ? $countries[$vcCode]['testPortal'] : $countries[$vcCode]['portal'];

            return view('myfatoorah.checkout', compact('mfSession', 'paymentMethods', 'jsDomain', 'userDefinedField'));
        } catch (Exception $ex) {
            $exMessage = __('myfatoorah.' . $ex->getMessage());
            return view('myfatoorah.error', compact('exMessage'));
        }
    }

//-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Example on how the webhook is working when MyFatoorah try to notify your system about any transaction status update
     */
    public function webhook(Request $request) {
        try {
            //Validate webhook_secret_key
            $secretKey = config('myfatoorah.webhook_secret_key');
            if (empty($secretKey)) {
                return response(null, 404);
            }

            //Validate MyFatoorah-Signature
            $mfSignature = $request->header('MyFatoorah-Signature');
            if (empty($mfSignature)) {
                return response(null, 404);
            }

            //Validate input
            $body  = $request->getContent();
            $input = json_decode($body, true);
            if (empty($input['Data']) || empty($input['EventType']) || $input['EventType'] != 1) {
                return response(null, 404);
            }

            //Validate Signature
            if (!MyFatoorah::isSignatureValid($input['Data'], $secretKey, $mfSignature, $input['EventType'])) {
                return response(null, 404);
            }

            //Update Transaction status on your system
            $result = $this->changeTransactionStatus($input['Data']);

            return response()->json($result);
        } catch (Exception $ex) {
            $exMessage = __('myfatoorah.' . $ex->getMessage());
            return response()->json(['IsSuccess' => false, 'Message' => $exMessage]);
        }
    }

//-----------------------------------------------------------------------------------------------------------------------------------------
    private function changeTransactionStatus($inputData) {
        //1. Check if orderId is valid on your system.
        $orderId = $inputData['CustomerReference'];

        //2. Get MyFatoorah invoice id
        $invoiceId = $inputData['InvoiceId'];

        //3. Check order status at MyFatoorah side
        if ($inputData['TransactionStatus'] == 'SUCCESS') {
            $status = 'Paid';
            $error  = '';
        } else {
            $mfObj = new MyFatoorahPaymentStatus($this->mfConfig);
            $data  = $mfObj->getPaymentStatus($invoiceId, 'InvoiceId');

            $status = $data->InvoiceStatus;
            $error  = $data->InvoiceError;
        }

        $message = $this->getTestMessage($status, $error);

        //4. Update order transaction status on your system
        return ['IsSuccess' => true, 'Message' => $message, 'Data' => $inputData];
    }

//-----------------------------------------------------------------------------------------------------------------------------------------
    private function getTestOrderData($orderId) {
        return [
            'total'    => 15,
            'currency' => 'KWD'
        ];
    }

//-----------------------------------------------------------------------------------------------------------------------------------------
    private function getTestMessage($status, $error) {
        if ($status == 'Paid') {
            return 'Invoice is paid.';
        } else if ($status == 'Failed') {
            return 'Invoice is not paid due to ' . $error;
        } else if ($status == 'Expired') {
            return $error;
        }
    }

//-----------------------------------------------------------------------------------------------------------------------------------------
}
```

> 📘 MyFtoorah Library
>
> **MyFatoorah** Laravel-Package uses the [MyFatoorah Library](https://packagist.org/packages/myfatoorah/library) composer package. Check the [PHP library](php-library) to help you Customize your Laravel website.

***

#### Merchant Configurations

Edit the **config/myfatoorah.php** file with your correct vendor data.

* Live Configuration: set "test\_mode" with "false" and use your [live token](live-token).
* Test Configuration: set "test\_mode" with "true" and use the [test token](https://docs.myfatoorah.com/docs/test-token). Also, use the list of [test cards](test-cards) to explore the payment process.

```php
<?php

return [
    /**
     * API Token Key (string)
     * Accepted value:
     * Live Token: https://myfatoorah.readme.io/docs/live-token
     * Test Token: https://myfatoorah.readme.io/docs/test-token
     */
    'api_key' => '',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'test_mode' => true,
    /**
     * Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY.
     */
    'country_iso' => 'KWT',
    /**
     * Save card (boolean)
     * Accepted value: true if you want to enable save card options.
     * You should contact your account manager to enable this feature in your MyFatoorah account as well.
     */
    'save_card' => true,
    /**
     * Webhook secret key (string)
     * Enable webhook on your MyFatoorah account setting then paste the secret key here.
     * The webhook link is: https://{example.com}/myfatoorah/webhook
     */
    'webhook_secret_key' => '',
    /**
     * Register Apple Pay (boolean)
     * Set it to true to show the Apple Pay on the checkout page.
     * First, verify your domain with Apple Pay before you set it to true.
     * You can either follow the steps here: https://docs.myfatoorah.com/docs/apple-pay#verify-your-domain-with-apple-pay or contact the MyFatoorah support team (tech@myfatoorah.com).
    */
    'register_apple_pay' => false
];
```

***

## Magento2

*`https://docs.myfatoorah.com/docs/magento2` — updated 2025-11-11*

#### Source Files

Install the MyFatoorah Magento2 Gateway via [myfatoorah/magento2-gateway](https://packagist.org/packages/myfatoorah/magento2-gateway) composer.

```text
composer require myfatoorah/magento2-gateway
```

Or you can check the last plugin version from the official <a href="https://marketplace.magento.com/myfatoorah-magento2-gateway.html/" target="_blank">Magento2 marketplace</a> site.

***

#### Installation steps

Kindly run the below Magento commands to enable MyFatoorah Plugin.

```text Multi Gateway 
php -f bin/magento module:enable --clear-static-content MyFatoorah_Gateway
```

```text cmd
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:clean
php bin/magento cache:flush
```

***

#### Merchant Configurations

In Magento Admin Panel Menu Stores → Configuration\
Expand Sales Menu → select Payment Methods → MyFatoorah Payment Gateway

![972](https://files.readme.io/87b5057-MyFatoorah_Configuration_Settings_at_Magento_Admin.png "MyFatoorah Configuration Settings at Magento Admin.png")

<ins>Demo Configuration:</ins>\
Please, set "Is Testing" with "Yes", and use [demo token](https://docs.myfatoorah.com/docs/test-token).\
You can use the list of [test cards](test-cards) to explore the payment process.

<ins>Live Configuration:</ins>\
Please, set "Is Testing" with "No", and use [your live token](live-token).

## Shipping

*`https://docs.myfatoorah.com/docs/magento2-shipping` — updated 2025-11-11*

> Magento2

### Source Files

Install the MyFatoorah Magento2 Gateway via [myfatoorah/magento2-gateway](https://packagist.org/packages/myfatoorah/magento2-gateway) composer

***

### Installation Steps

Kindly, follow the steps for installing and configuring the [Magento extension](https://myfatoorah.readme.io/docs/magento2).

***

### Shipping Configurations

> ❗️ Approval is needed!
>
> Kindly, contact your account manager or sales representative to activate the **Shipping** feature.

1. In Magento2 Admin Panel Menu Stores → Configuration
2. Expand Sales Menu → Shipping Settings → Shipping Methods
3. Select one of these shipping methods
   * "MyFatoorah" section.
   * "MyFatoorah"  section.
4. Select **Yes** to enable it

![Screenshot from 2022-10-24 13-35-45.png](https://files.readme.io/93daebd-Screenshot_from_2022-10-24_13-35-45.png)

![Screenshot from 2021-02-03 16-40-15.png](https://files.readme.io/e433053-Screenshot_from_2021-02-03_16-40-15.png)

> 🚧 Used Units
>
> cm, m, mm, in and yd are the dimension units accepted by the Myfatoorah plugin.\
> kg,  g, lbs, oz are the weight units accepted by the Myfatoorah plugin.

## Webhook

*`https://docs.myfatoorah.com/docs/magento2-webhook` — updated 2025-11-11*

#### Why Using Webhook

You can enable the Webhook feature to trigger events each time the order status changes at the MyFatoorah side. This feature will recover the lost orders due to connection loss or delayed callbacks.

***

#### **MyFatoorah Account**

1. Log in to your [Myfatoorah portal account](https://portal.myfatoorah.com/) and go to Integration Settings → Webhook Settings and configure your settings.
2. Enable the feature.
3. Add your **Endpoint** URL.

```
https://example.com/rest/all/V1/myfatoorah/webhook
```

4. Generate the **Webhook Secret Key** and copy it by clicking on the copy icon :clipboard: on the right side of the secret key box.
5. Select the **Transaction Status Changed** event type.
6. Click on the Save button.

![woocommerce-webhook.png](https://files.readme.io/86b1acf-woocommerce-webhook.png)

***

#### **Magento2 Account**

1. Login into your Magento2 admin panel and navigate to Stores → Configuration → Sales → Payment Methods → Other Payment Methods → MyFatoorah Payment Gateway.
2. Paste the secret key into the **Webhook Secret Key** text box.
3. Click on the **Save** button.

![Magento2 Webhook.png](https://files.readme.io/d874c5d-Magento2_Webhook.png)

***

## Embedded Apple Pay

*`https://docs.myfatoorah.com/docs/magento2-embedded-apple-pay` — updated 2025-11-11*

> Magento2

#### **Introduction**

To provide a better user experience to your Apple Pay users, **MyFatoorah** is providing the Apple Pay embedded payment.

The Apple Pay button can be placed on your checkout page. When your customers click the button, **MyFatoorah** will direct the customers to the Apple Pay payment sheet page to authorize the payment.

![Apple Pay.png](https://files.readme.io/4f07adc-Apple_Pay.png)

![Apple Pay Payment Sheet.png](https://files.readme.io/db15645-Apple_Pay_Payment_Sheet.png)

***

#### **Apple Pay Activation Steps**

To use Apple Pay Button, you need to add the Apple Pay verification file on your web domain.

1. Host your domain verification file.
2. Activate Apple Pay Embedded in the admin panel.

#### Step1: Host your domain verification file

Steps:\
&#x9;1- Create a folder in the root path and name it ".well-known"\
&#x9;2- Paste the file inside the ".well-known" folder.\
&#x9;3- Test that the file is located in the correct place by opening the below URL\
&#x9;<https://{example.com}/.well-known/apple-developer-merchantid-domain-association>\
&#x9;Replace only the \{example.com} with your site URL.

```text Path
https://{example.com}/.well-known/apple-developer-merchantid-domain-association
```

> 🚧 Domain Verification File
>
> Do not remove the file from the server. Apple might need to re-validate the domain on a yearly basis and this file must be available for successful validation.

> ❗️ SSL
>
> Domain should be TLS (HTTPS) enabled.

#### Step2: Activate Apple Pay Embedded in the admin panel

From MyFatoorah Plugin Setting in your admin, make sure to make Apple Pay Embedded Enabled.

![woo-applePay.png](https://files.readme.io/b25cb47-Magento2_ApplePay.png)

***

## nopCommerce

*`https://docs.myfatoorah.com/docs/nopcommerce` — updated 2025-11-11*

### Source Files

* You can download the last plugin version from the official <a href="https://www.nopcommerce.com/en/myfatoorah-payment-solution" target="_blank">nopCommerce</a> site.
* Or you can download the plugin from here <a href="https://dev.azure.com/myfatoorahsc/Public-Repo/_git/Public-Repo?version=GBmaster&path=/.Net/NopCommerce" target="_blank">here</a> .

***

### Installation Steps

Please follow the below steps for installing the nopCommerce extension:

1. Go to admin area → configuration → local plugins.
2. Upload the plugin archive using the "Upload plugin or theme" plugin.
3. Scroll down through the list of plugins to find the newly installed plugin. And click on the "Install" button to install the plugin.

***

### Merchant Configurations

In the nopCommerce Admin Panel, follow the steps below:

1. Go to Configuration → Local Plugins. The plugins list is displayed
2. Click the Edit button beside the Myfatoorah plugin. Edit plugin details

## Odoo

*`https://docs.myfatoorah.com/docs/odoo` — updated 2025-11-11*

#### Source Files

Install the MyFatoorah module via [Odoo Apps Marketplace](https://apps.odoo.com/apps/modules/16.0/myfatoorah_gateway/).

![](https://files.readme.io/3c22798-3-_Download_MyFatoorah.PNG)

> 📘 Requirements
>
> * Currently, our module supports Odoo version 16.
> * You have to enable the **Invoicing module**.

***

#### Installation steps

1. Extract the downloaded file
2. Add the folder "**myfatoorah\_gateway**" into your Odoo "**custom\_addons**" folder.
3. Enable **Debug mode** in Odoo.
4. Restart Odoo Service.
5. Click Update Apps List in the top menu.
6. MyFatoorah module will be displayed as shown below:

![](https://files.readme.io/c51c8a3-5.PNG)

7. Activate MyFatoorah module.

***

#### Merchant Configurations

In Odoo Admin Panel, follow the steps below:

1. Go to Invoicing → Configuration → Journals.
2. Select **Bank** from the Journals list.
3. Add **MyFatoorah** payment method into incoming and outgoing payments then save the changes.

![](https://files.readme.io/5d92c3f-9.PNG)

4. Go to Invoicing → Configuration → Payment Providers.
5. Select MyFatoorah module.
6. Enter the Myfatoorah configuration as shown below:

![](https://files.readme.io/e354e43-12-_Enter_MyFatoorah_Configuration.PNG)

7. Select State (**Enabled mode** for live environment and **Test mode** for demo environment).
8. Select Website.
9. In the credentials tab, enter your **Token and Country** based on the state you selected.
10. Optionally, you can add your webhook secret key.
11. Save the changes.

## Omnipay

*`https://docs.myfatoorah.com/docs/omnipay` — updated 2025-11-12*

#### **Requirements**

* Composer 2.0
* Omnipay 3.0
* An SSL certificate

***

#### **Installation Steps**

Run the following command to download the **MyFatoorah** package:

```text cmd
composer require league/omnipay:^3 myfatoorah/omnipay:dev-master
```

You can also include the package directly in the composer.json file:

```text composer.json
{
    "require": {
        "myfatoorah/omnipay": "dev-master"
    }
}
```

***

#### **Creating a Payment Link**

```php
use Omnipay\Omnipay;

$data                      = array();
$data['Amount']            = '50';
$data['OrderRef']          = 'orderId-123'; 
$data['Currency']          = 'KWD';
$data['returnUrl']         = 'http://websiteurl.com/callback.php';
$data['Card']['firstName'] = 'fname';
$data['Card']['lastName']  = 'lname';
$data['Card']['email']     = 'test@test.com';
//
// Do a purchase transaction on the gateway
$transaction               = $gateway->purchase($data)->send();
if ($transaction->isSuccessful()) {
    $invoiceId   = $transaction->getTransactionReference();
    echo "Invoice Id = " . $invoiceId . "<br>";
    $redirectUrl = $transaction->getRedirectUrl();
    echo "Redirect Url = <a href='$redirectUrl' target='_blank'>" . $redirectUrl . "</a><br>";
} else {
    echo $transaction->getMessage();
}
```

***

#### **Check Payment Status**

In the callback, Get Payment status for a specific Payment ID

```php
$callBackData = ['paymentId' => '100202113817903101'];
$callback     = $gateway->completePurchase($callBackData)->send();
if ($callback->isSuccessful()) {
    echo "<pre>";
    print_r($callback->getPaymentStatus('orderId-123', '100202113817903101'));
} else {
    echo $callback->getMessage();
}
```

***

#### **Make a Refund**

Refund a specific Payment ID

```php
$refundData = ['paymentId' => '100202113817903101', 'Amount'=>1];
$refund     = $gateway->refund($refundData)->send();
if ($refund->isSuccessful()) {
    echo "<pre>";
    print_r($refund->getRefundInfo());
} else {
    echo $refund->getMessage();
}

```

## OpenCart

*`https://docs.myfatoorah.com/docs/opencart` — updated 2026-04-08*

### Source Files

* You can download the last plugin version from the official <a href="https://www.opencart.com/index.php?route=marketplace/extension/info&extension_id=40652&filter_member=MyFatoorahPG" target="_blank">OpenCart</a> site.

***

### Installation Steps

Kindly, follow the below steps for installing the OpenCart extension:

1. Login into your OpenCart admin panel. Navigate to **Extensions** → **installer**.
2. Click on the **Upload** button, then select the MyFatoorah \***.ocmod.zip** file.
3. Wait until you see the green message "Success: You have modified extensions!" and see the file listed in the **Install History** section.
4. Navigate to **Extensions** → **installer** and be sure that the MyFatoorah plugin and appears is in the **Modification List** section.
5. Click on the blue refresh button on the upper right side to rebuild your modification cache and complete your installation.

![Step 2 & 3](https://files.readme.io/707ed56-MyFatoorah_-_OpenCart_-_Extension_-_Installer.png)

![Step 4](https://files.readme.io/d88c0bd-MyFatoorah_-_OpenCart_-_Extension_-_Modification.png)

***

### Merchant Configurations

In OpenCart Admin Panel, follow the steps below:

1. Expand the **Extensions** list on the left menu, then, click on the **Extensions**.
2. Choose **Payments** as the extension type.
3. Myfatoorah V2 and Myfatoorah Direct Payment Gateway will appear in the installed payment extensions list. If not, please clear the cache folder.
4. Click on the **Install** button to install the plugin into your system.
5. Click on the **Edit** button to configure Myfatoorah gateway with API key using a demo or live token.
6. Clear the site cache.

![steps 1 to 2](https://files.readme.io/e42fa81-payment.png)

![steps 3 to 5](https://files.readme.io/0f98d26-Screenshot_from_2020-11-17_15-50-35.png)

<br />

<ins>Demo Configuration:</ins>\
Please, enable the test mode and use

[demo token](https://docs.myfatoorah.com/docs/test-token).\
You can use the list of [test cards](test-cards) to explore the payment process.

<br />

<ins>Live Configuration:</ins>\
Please, disable the test mode and use

[your live token](https://docs.myfatoorah.com/docs/api-key).

***

> 👍 Your review is appreciated
>
> Kindly add your [review](https://www.opencart.com/index.php?route=marketplace/extension/info\&extension_id=40652\&filter_member=MyFatoorahPG)  to OpenCart  [official marketplace](https://www.opencart.com/index.php?route=marketplace/extension/info\&extension_id=40652\&filter_member=MyFatoorahPG) .

## Shipping

*`https://docs.myfatoorah.com/docs/opencart-shipping` — updated 2025-11-12*

> OpenCart

### Source Files

* You can download the last plugin version from the official <a href="https://www.opencart.com/index.php?route=marketplace/extension/info&extension_id=40652&filter_member=MyFatoorahPG" target="_blank">OpenCart</a> site.

***

### Installation Steps

Kindly, follow the steps mentioned in [the link](https://myfatoorah.readme.io/docs/opencart) to install and configure the OpenCart extension.

***

### Shipping Configurations

> ❗️ Approval is needed!
>
> Kindly, contact your account manager or sales representative to activate the **Shipping** feature.

In OpenCart Admin Panel, follow the steps below:

1. Expand the **Extensions** list on the left menu, then, click on the **Extensions**.
2. Choose **Shipping** as the extension type.
3. MyFatoorah Shipping Extention will appear in the installed shipping extensions list.
4. Click on the **Edit** button to configure Shipping Extention.
5. Select available shipping methods that were [enabled in your portal](https://docs.myfatoorah.com/docs/shipping-information):
   * "Aramex",
   * "DHL".
6. Clear the site cache

![ss.png](https://files.readme.io/8dec054-ss.png)

![Screenshot from 2021-02-15 16-39-50.png](https://files.readme.io/d7fda0c-Screenshot_from_2021-02-15_16-39-50.png)

***

### Weight and Dimensions

The shipping module requires weight and dimensions information for each product. Kindly follow the steps here to add them:

1. Log in to Admin Panel
2. Expand the **Catalog** list on the left menu, then, click on the **Products**.
3. Click on the **Edit** button of a product to add the dimensions and weight information.
4. Select the **Data** tab.
5. Navigate to **Dimensions (L x W x H)** and fill the fields in **cm**.
6. Navigate to **Weight** and fill it in **kg**.
7. Click on the **Save** icon in the upper right corner

> 🚧 Used Units
>
> **cm, m, mm, in, and yd** are the dimension units accepted by the Myfatoorah plugin.\
> **kg,  g, lbs, and oz** are the weight units accepted by the Myfatoorah plugin.
>
> Kindly, note that if you use an Arabic website, be sure that your units are set in English letters at your OpenCart unit's classes as described above.

## Webhook

*`https://docs.myfatoorah.com/docs/opencart-webhook` — updated 2025-11-12*

#### Why Using Webhook

You can enable the Webhook feature to trigger events each time the order status changes at the MyFatoorah side. This feature will recover the lost orders due to connection loss or delayed callbacks.

***

#### **MyFatoorah Account**

1. Log in to your [Myfatoorah portal account](https://portal.myfatoorah.com/) and go to Integration Settings → Webhook Settings and configure your settings.
2. Enable the feature.
3. Add your **Endpoint** URL. Replace only the **\{example.com}** with your site URL.

```Text OpenCart versions 4.0.2.x
https://{example.com}/index.php?route=extension/myfatoorah/payment/myfatoorah.webhook
```
```Text OpenCart versions 4.0.1.x
https://{example.com}/index.php?route=extension/myfatoorah/payment/myfatoorah|webhook
```
```text OpenCart versions 2.3.x & 3.0.x
https://{example.com}/index.php?route=extension/payment/myfatoorah/webhook
```
```text OpenCart versions 2.0.x & 2.2.x
https://{example.com}/index.php?route=payment/myfatoorah/webhook
```

4. Generate the **Webhook Secret Key** and copy it by clicking on the copy icon :clipboard: on the right side of the secret key box.
5. Select the **Transaction Status Changed** event type.
6. Click on the Save button.

![opencart-webhook.png](https://files.readme.io/16d69b9-opencart-webhook.png)

***

#### **OpenCart Account**

1. Log in to your OpenCart admin panel and navigate to Extensions → Extensions → Payments → Myfatoorah.
2. Paste the secret key into the **Webhook Secret Key** text box.
3. Click on the **Save** button.

![OpenCart Webhook Secret Key.png](https://files.readme.io/8a37d3b-OpenCart_Webhook_Secret_Key.png)

***

## Embedded Apple Pay

*`https://docs.myfatoorah.com/docs/opencart-embedded-apple-pay` — updated 2025-11-12*

> OpenCart

#### **Introduction**

To provide a better user experience to your Apple Pay users, **MyFatoorah** is providing the Apple Pay embedded payment.

The Apple Pay button can be placed on your checkout page. When your customers click the button,  **MyFatoorah** will direct the customers to the Apple Pay payment sheet page to authorize the payment.

![Apple Pay.png](https://files.readme.io/4f07adc-Apple_Pay.png)

![Apple Pay Payment Sheet.png](https://files.readme.io/db15645-Apple_Pay_Payment_Sheet.png)

***

#### **Apple Pay Activation Steps**

To use Apple Pay Button, you need to add the Apple Pay verification file on your web domain.

1. Host your domain verification file.
2. Activate Apple Pay Embedded in the admin panel.

#### Step1: Host your domain verification file

Steps:\
&#x9;1- Create a folder in the root path and name it ".well-known"\
&#x9;2- Paste the file inside the ".well-known" folder.\
&#x9;3- Test that the file is located in the correct place by opening the below URL\
&#x9;<https://{example.com}/.well-known/apple-developer-merchantid-domain-association>\
&#x9;Replace only the \{example.com} with your site URL.

```text Path
https://{example.com}/.well-known/apple-developer-merchantid-domain-association
```

> 🚧 Domain Verification File
>
> Do not remove the file from the server. Apple might need to re-validate the domain on a yearly basis and this file must be available for successful validation.

> ❗️ SSL
>
> Domain should be TLS (HTTPS) enabled.

#### Step2: Activate Apple Pay Embedded in the admin panel

From MyFatoorah Plugin Setting in your admin, make sure to make Apple Pay Embedded Enabled.

![woo-applePay.png](https://files.readme.io/8a5bf93-OpenCart_ApplePay.png)

***

## PrestaShop

*`https://docs.myfatoorah.com/docs/prestashop` — updated 2025-11-12*

#### Source Files

[myfatoorah-prestashop-latest-vesion.zip](https://dev.azure.com/myfatoorahsc/_git/Public-Repo?path=/php/plugin/prestaShop)

***

#### Installation Steps

For **PrestaShop v1.7 & v8**, please follow the below steps:

1. Log in to your PrestaShop admin panel.
2. Navigate to **Modules** then **Module Manager**.
3. Click on **Upload a module**.
4. A file dialog will open, then click on **select file**. After that, select the (\*.zip) file for the plugin provided by Myfatoorah.
5. Then click on the **configure** button to go to the Myfatoorah Gateway configuration page.

For **PrestaShop v1.6**, please follow the below steps:

1. Log in to your PrestaShop admin panel.
2. Navigate to **Modules and Services**.
3. Click on the **Add a new module** icon in the upper right corner. Then click on the **Choose a file** button.
4. A file dialog will open. After that, select the (\*.zip) file for the plugin provided by Myfatoorah. Finally, click on the **Upload this module** button.
5. MyFatoorah Payment Gateway will appear in the installed module list.
6. Then click on the **configure** button to go to the Myfatoorah Gateway configuration page.

***

#### Merchant Configurations

In the **PrestaShop** admin panel, follow those steps:

1. Navigate to **Modules** then **Module Manager** for  PrestaShop 1.7 or to **Modules and Services** PrestaShop 1.6.
2. Select **Payment** from the **Category** drop-down list or search for **MyFatoorah**.
3. Click on the **Configure** button of the MyFatoorah Payment Gateway plugin.
4. Enable the module, add the API token key, select your Test mode, and then click on the save icon.

<ins>Demo Configuration:</ins>\
Please, enable the test mode and use [demo token](https://docs.myfatoorah.com/docs/test-token).\
You can use the list of [test cards](test-cards) to explore the payment process.

<ins>Live Configuration:</ins>\
Please, disable the test mode and use [your live token](live-token).

## Webhook

*`https://docs.myfatoorah.com/docs/prestashop-webhook` — updated 2025-11-12*

#### Why Using Webhook

You can enable the Webhook feature to trigger events each time the order status changes at the MyFatoorah side. This feature will recover the lost orders due to connection loss or delayed callbacks.

***

#### **MyFatoorah Account**

1. Log in to your [Myfatoorah account](https://portal.myfatoorah.com/) and go to Integration Settings → Webhook Settings and configure your settings.
2. Enable the feature.
3. Add your **Endpoint** URL.

```text PrestaShop 1.7
https://example.com/module/myfatoorah/webhook
```
```text PrestaShop 1.6
https://example.com/index.php?fc=module&module=myfatoorah&controller=webhook
```

4. Generate the **Webhook Secret Key** and copy it by clicking on the copy icon :clipboard: on the right side of the secret key box.
5. Select the **Transaction Status Changed** event type.
6. Click on the Save button.

![PrestaShop Endpoint.png](https://files.readme.io/76b5917-PrestaShop_Endpoint.png)

***

#### **PrestaShop Account**

1. Login into your **PrestaShop** admin panel.
2. Navigate to **Modules** then **Module Manager** for  PrestaShop 1.7 or to **Modules and Services** PrestaShop 1.6.
3. Select **Payment** from the **Category** drop-down list or search for **MyFatoorah**.
4. Click on the **Configure** button of the MyFatoorah Payment Gateway plugin.
5. Paste the secret key into the **MyFatoorah Webhook Secret Key** text box.
6. Click on the **Save** button.

![PrestaShop Webhook Secret Key.png](https://files.readme.io/737a8a5-PrestaShop_Webhook_Secret_Key.png)

***

## Shopify

*`https://docs.myfatoorah.com/docs/shopify` — updated 2025-12-10*

> Installation Steps

> 🚧 Shopify
>
> Upgrade your Shopify Plan
>
> You should upgrade your **Shopify** plan to be enabled to process your payment.

> ❗️ Before You Start
>
> If you are not registered in MyFatoorah, you need to create an [portal account](https://portal.myfatoorah.com/). Then contact **MyFatoorah** [support team/account manager/sales representative](https://www.myfatoorah.com/en/contact-us/) to activate your account and API as well.

Check the [official plugin](https://apps.shopify.com/myfatoorah) on the Shopify marketplace.

##### Step 1:

From [this link](https://apps.shopify.com/myfatoorah), click on the **Add app** button. Then, click on the **Install app** button.

![step 1](https://files.readme.io/127878b-Installation_Step.png)

***

##### Step 2:

After installation, it will redirect you to the **MyFatoorah** setting page.

![step 2](https://files.readme.io/816f334-Activation_Step.png)

***

##### Step 3:

From Shopify - MyFatoorah login page, please sign in using the MyFatoorah registered email/username, password and the country (**Note: You need to use your MyFatoorah master user**).

![step 3](https://files.readme.io/055ed67-shopify_login.png)

If you have a sandbox/demo account, and you need to do some testing before live. Please sign in using the MyFatoorah demo registered email/username, password, choose Kuwait as a country, and enable Sandbox Mode.

***

##### Step 4:

Please, choose the desired payment icons to display them on the checkout page.

![step 4](https://files.readme.io/3eade3d-Payment_Icons.png)

***

##### Step 5:

Click on **Activate MyFatoorah** button.

![](https://files.readme.io/42cf637-Activate_Button.png)

***

##### Step 6:

Finally,  **MyFatoorah** appears in your Settings → payments section. Note that the **Shopify** transaction fees change depending on your **Shopify** plan.

![step 6](https://files.readme.io/b13b005-Payment_Section.png)

***

> 🚧 MyFatoorah SandBox Mode
>
> You need to create a [demo account](https://demo.myfatoorah.com/). Then contact **MyFatoorah Technical Support Team**  (<tech@myfatoorah.com>) to activate the demo account and API as well. Then follow steps described [here](#step-1).

In MyFatoorah - Shopify application, enabling the **Test mode** option will **only**  mark the orders in the **Shopify** system as test orders.

![Test Mode.png](https://files.readme.io/aaae25a-Test_Mode.png)

> 👍 Your review is appreciated
>
> Kindly add your [review](https://apps.shopify.com/myfatoorah/reviews)  to the Shopify [official marketplace](https://apps.shopify.com/myfatoorah) .

## Shopify (Direct)

*`https://docs.myfatoorah.com/docs/shopify-direct` — updated 2025-11-12*

> Installation Steps

> 🚧 Shopify
>
> Upgrade your Shopify Plan
>
> You should upgrade your **Shopify** plan to be enabled to process your payment.

> ❗️ Before You Start
>
> If you are not registered in MyFatoorah, you need to create an [portal account](https://portal.myfatoorah.com/). Then contact **MyFatoorah** [support team/account manager/sales representative](https://myfatoorah.com/contact.html) to activate your account and API as well.

Check the [official plugin](https://apps.shopify.com/myfatoorah-1) on the Shopify marketplace.

##### Step 1:

From [this link](https://apps.shopify.com/myfatoorah-1), click on the **Install** button.

![](https://files.readme.io/87c0cd5-image.png)

***

##### Step 2:

After installation, it will redirect you to your **Shopify Store** settings page to complete installation. Click on **install** once again.

![](https://files.readme.io/5f77fe7-image.png)

***

##### Step 3:

You will then be redirected to MyFatoorah login page. Here you will enter your login credentials to your MyFatoorah account.

![step 3](https://files.readme.io/055ed67-shopify_login.png)

> 👍 Login Credentials
>
> Make sure to enter your correct **email** and **password**, and to choose the correct **country** of registration.

> 🚧 Sandbox Mode
>
> Select the Sandbox Mode if and only if you are logging in with your **demo** account.

***

##### Step 4:

Please, choose the desired payment icons to display them on the checkout page. After you choose the payment icons. Click on save.

![](https://files.readme.io/e6d35b3-image.png)

***

##### Step 5:

Finally,  **MyFatoorah** appears in your Settings → payments section. Note that the **Shopify** transaction fees change depending on your **Shopify** plan.

***

![](https://files.readme.io/c16688e-image.png)

#### Checkout Design:

This is how MyFatoorah will look on your checkout page:

![](https://files.readme.io/4fa4701-image.png)

> 🚧 MyFatoorah SandBox Mode
>
> You need to create a [demo account](https://demo.myfatoorah.com/). Then contact **MyFatoorah Technical Support Team**  (<tech@myfatoorah.com>) to activate the demo account and API as well. Then follow steps described [here](#step-1).

In MyFatoorah - Shopify application, enabling the **Test mode** option will **only**  mark the orders in the **Shopify** system as test orders. However, deductions will be made if you're using live credentials for your MyFatoorah account.

![](https://files.readme.io/fb5c074-image.png)

> 👍 Your review is appreciated
>
> Kindly add your [review](https://apps.shopify.com/myfatoorah/reviews)  to the Shopify [official marketplace](https://apps.shopify.com/myfatoorah-1) .

## Symfony (New)

*`https://docs.myfatoorah.com/docs/symfony` — updated 2025-11-14*

#### Source Files

Install the MyFatoorah Symfony bundle via [myfatoorah/symfony-bundle](https://packagist.org/packages/myfatoorah/symfony-bundle) composer.

```c cmd
composer require myfatoorah/symfony-bundle
```

***

#### Installation steps

> ❗️ Important Note
>
> The MyFatoorah Symfony Bundle provides examples of how to use the MyFatoorah Library and the MyFatoorah API endpoints. Any validations or security criteria must be taken from your side to ensure a seamless payment experience.

1. Import MyFatoorahSymfonyBundle routing files by adding the below route in the **config/routes.yaml** file of your project:

```yaml config/routes.yaml
myfatoorah_symfony:
    resource: '@MyFatoorahSymfonyBundle/Resources/config/routing.yaml'
```

2. Only for Applications that don't use Symfony Flex, you will need to enable the bundle manually by adding it to the list of registered bundles in the **config/bundles.php** file of your project:

```php config/bundles.php
return [
    // ...
    MyFatoorah\SymfonyBundle\MyFatoorahSymfonyBundle::class => ['all' => true]
];
```

3. To test the payment cycle, type the below URL onto your browser. Replace only the \{example.com} with your site domain. You can use the test cards listed on the [Test Cards](https://docs.myfatoorah.com/docs/test-cards) page.

```
https://{example.com}/myfatoorah/create
```

4. Copy the **vendor/myfatoorah/symfony-bundle/src/Controller/InvoiceController.php** file to your controller, then customize it as per your site needs.

```php
<?php

namespace MyFatoorah\SymfonyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use MyFatoorah\Library\PaymentMyfatoorahApiV2;

//class InvoiceController  {
class InvoiceController extends AbstractController {

    public $mfObj;

    public const VERSION = '2.0.0';

//-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * create MyFatoorah object
     */
    public function __construct(ContainerBagInterface $params) {

        $apiKey      = $params->get('myfatoorah.apiKey');
        $countryCode = $params->get('myfatoorah.countryCode');
        $isTest      = $params->get('myfatoorah.isTest');
        $this->mfObj = new PaymentMyfatoorahApiV2($apiKey, $countryCode, $isTest);
    }

//-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Create MyFatoorah invoice
     *
     * @Route("/myfatoorah/create", name="myfatoorah_symfony_create")
     * 
     * @return JsonResponse
     */
    public function create() {
        try {

            $paymentMethodId = 0; // 0 for MyFatoorah invoice or 1 for Knet in test mode

            $curlData = $this->getPayLoadData();

            $data = $this->mfObj->getInvoiceURL($curlData, $paymentMethodId);

            $response = ['IsSuccess' => 'true', 'Message' => 'Invoice created successfully.', 'Data' => $data];
        } catch (\Exception $e) {
            $response = ['IsSuccess' => 'false', 'Message' => $e->getMessage()];
        }
        return $this->json($response);
    }

//-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * 
     * @param int|string $orderId
     * @return array
     */
    private function getPayLoadData($orderId = null) {
        $callbackURL = 'http:' . $this->generateUrl('myfatoorah_symfony_callback', [], UrlGeneratorInterface::NETWORK_PATH);

        return [
            'CustomerName'       => 'FName LName',
            'InvoiceValue'       => '10',
            'DisplayCurrencyIso' => 'KWD',
            'CustomerEmail'      => 'test@test.com',
            'CallBackUrl'        => $callbackURL,
            'ErrorUrl'           => $callbackURL,
            'MobileCountryCode'  => '+965',
            'CustomerMobile'     => '12345678',
            'Language'           => 'en',
            'CustomerReference'  => $orderId,
            'SourceInfo'         => 'Symfony ' . \Symfony\Component\HttpKernel\Kernel::VERSION . ' - MyFatoorah ' . self::VERSION
        ];
    }

//-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get MyFatoorah payment information
     * 
     * @Route("/myfatoorah/callback", name="myfatoorah_symfony_callback")
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function callback(Request $request): JsonResponse {
        //http://127.0.0.1:8000/myfatoorah/callback?paymentId=100202217186102325
        try {
            $paymentId = $request->query->get('paymentId');

            $data = $this->mfObj->getPaymentStatus($paymentId, 'PaymentId');
            if ($data->InvoiceStatus == 'Paid') {
                $msg = 'Invoice is paid.';
            } else if ($data->InvoiceStatus == 'Failed') {
                $msg = 'Invoice is not paid due to ' . $data->InvoiceError;
            } else if ($data->InvoiceStatus == 'Expired') {
                $msg = 'Invoice is expired.';
            }

            $response = ['IsSuccess' => 'true', 'Message' => $msg, 'Data' => $data];
        } catch (\Exception $e) {
            $response = ['IsSuccess' => 'false', 'Message' => $e->getMessage()];
        }
        return $this->json($response);
    }

//-----------------------------------------------------------------------------------------------------------------------------------------
}
```

> 📘 MyFtoorah Library
>
> **MyFatoorah** Symfony-Bundle uses the [MyFatoorah Library](https://packagist.org/packages/myfatoorah/library) composer package. Check the [PHP library](php-library) to help you Customize your Symfony website.

***

#### Merchant Configurations

Edit the **vendor/myfatoorah/symfony-bundle/src/Resources/config/services.yaml** file with your correct vendor data.

* Live Configuration: set "isTest" with "false" and use  your [live token](live-token).
* Test Configuration: set "isTest" with "true" and use the [test token](https://docs.myfatoorah.com/docs/test-token). Also, use the list of [test cards](test-cards) to explore the payment process.

After that,  run the below cmd to reload the new configurations.

```text cmd
./bin/console cache:clear
```

```php
#File: vendor/myfatoorah/symfony-bundle/src/Resources/config/services.yaml
parameters:
    
    #API Token Key
    #Live Token: https://myfatoorah.readme.io/docs/live-token
    #Test Token: https://myfatoorah.readme.io/docs/test-token
    myfatoorah.apiKey: 'rLtt6JWvbUHDDhsZnfpAhpYk4dxYDQkbcPTyGaKp2TYqQgG7FGZ5Th_WD53Oq8Ebz6A53njUoo1w3pjU1D4vs_ZMqFiz_j0urb_BH9Oq9VZoKFoJEDAbRZepGcQanImyYrry7Kt6MnMdgfG5jn4HngWoRdKduNNyP4kzcp3mRv7x00ahkm9LAK7ZRieg7k1PDAnBIOG3EyVSJ5kK4WLMvYr7sCwHbHcu4A5WwelxYK0GMJy37bNAarSJDFQsJ2ZvJjvMDmfWwDVFEVe_5tOomfVNt6bOg9mexbGjMrnHBnKnZR1vQbBtQieDlQepzTZMuQrSuKn-t5XZM7V6fCW7oP-uXGX-sMOajeX65JOf6XVpk29DP6ro8WTAflCDANC193yof8-f5_EYY-3hXhJj7RBXmizDpneEQDSaSz5sFk0sV5qPcARJ9zGG73vuGFyenjPPmtDtXtpx35A-BVcOSBYVIWe9kndG3nclfefjKEuZ3m4jL9Gg1h2JBvmXSMYiZtp9MR5I6pvbvylU_PP5xJFSjVTIz7IQSjcVGO41npnwIxRXNRxFOdIUHn0tjQ-7LwvEcTXyPsHXcMD8WtgBh-wxR8aKX7WPSsT1O8d8reb2aR7K3rkV3K82K_0OgawImEpwSvp9MNKynEAJQS6ZHe_J_l77652xwPNxMRTMASk1ZsJL'
    
    #Country ISO Code
    #Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY.
    myfatoorah.countryCode: 'KWT'
    
    #Test Mode
    #Accepted value: 'true' for the test mode or 'false' for the live mode
    myfatoorah.isTest: true
    
services:
    _defaults:
        autowire: true      # Automatically injects dependencies in your services.
        autoconfigure: true # Automatically registers your services as commands, event subscribers, etc.
        
    MyFatoorah\SymfonyBundle\:
        resource: '../../'
```

***

## Whautomate

*`https://docs.myfatoorah.com/docs/whautomate` — updated 2025-11-12*

> 📘 MyFatoorah Whautomate plugin
>
> This integration was developed by the [Whautomate team](https://help.whautomate.com/product-guides/integrations/myfatoorah).

### Installation Steps

##### Step 1:

Go to the Integrations page in the Whautomate platform then scroll down and select the MyFatoorah connect button to continue.

![](https://files.readme.io/c68f759-image.png)

##### Step 2:

Follow the below instructions to configure MyFatoorah account.

![](https://files.readme.io/83cbb4e-image.png)

##### Step 3:

Get your MyFatoorah API Key by following [these steps](https://docs.myfatoorah.com/docs/live-token#api-token-key). Add your MyFatoorah API Key to Whautomate configuration.

![](https://files.readme.io/69d3cbd-image.png)

##### Step 4:

Select the **Vendor's Country** from the dropdown menu.

![](https://files.readme.io/02a2709-image.png)

##### Step 5:

Configure the **Webhook Settings** by following these steps:

1. Copy the Webhook URL as in the screenshot below:

   ![](https://files.readme.io/5d0ca03-image.png)
2. Navigate to Integration Settings in MyFatoorah portal -> Webhook Settings.
3. Enable Webhook.
4. Paste the Webhook URL on your Myfatoorah account.
5. Enable Secret Key.
6. Generate Webhook Secret Key.
7. Copy the Webhook Secret Key.
8. Select Transaction Status Changed Webhook Event and finally select the Save button.

![](https://files.readme.io/31145ac-image.png)

9. Paste the Webhook Secret Key in Whautomate platform and click **save** to connect your account to MyFatoorah.

![](https://files.readme.io/b37d897-image.png)

##### Step 6:

After successfully connecting your MyFatoorah account, you can create an invoice and Choose **"Request payment via WhatsApp"** option to test your payment.

![](https://files.readme.io/d5014ef-image.png)

##### Step 7:

Open your **WhatsApp** account and Select the **Make Payment**button to continue.

![](https://files.readme.io/bebf361-image.png)

##### Step 8:

Select a payment method and click the **Pay** button.

![](https://files.readme.io/f383578-image.png)

##### Step 9:

Enter your card details to complete the payment.

![](https://files.readme.io/e0bdc06-image.png)

## WHMCS

*`https://docs.myfatoorah.com/docs/whmcs` — updated 2025-11-12*

#### Source Files

You can download the last plugin version from the official <a href="https://marketplace.whmcs.com/product/5855-myfatoorah-payment" target="_blank">WHMCS</a> site.

***

#### Installation Steps

Please follow the below steps for installing the WHMCS extension:

1. Extract the .zip file provided by MyFatoorah into the WHMCS stor directory.
2. Open the admin panel, and navigate to Setup → Payment Gateways → All Payment Gateways.
3. Then, select **MyFatoorah payment - API Ver 2.0** to activate.

***

#### Merchant Configurations

In WHMCS Admin Panel, follow the steps below:

1. Log in to your **WHMCS** admin panel.
2. Click on the **wrench** icon at the upper right corner, then click on **System Settings**.
3. Under the **Payment** section, click on the **Payment Gateways** link.
4. Navigate to the **“Manage Existing Gateways”** tab.
5. Fill in the gateway configuration as described below.
6. Click on the **Save Changes** button.

![MyFatoorah WHMCS Configurations.png](https://files.readme.io/92b2699-MyFatoorah_WHMCS_Configurations.png)

<ins>Demo Configuration:</ins>\
Please, enable the test mode and use the [demo token](https://docs.myfatoorah.com/docs/test-token).\
You can use the list of [test cards](test-cards) to explore the payment process.

<ins>Live Configuration:</ins>\
Please, disable the test mode and use [your live token](live-token).

## Webhook

*`https://docs.myfatoorah.com/docs/whmcs-webhook` — updated 2025-11-12*

> WHMCS

#### Why Using Webhook

You can enable the Webhook feature to trigger events each time the order status changes on the MyFatoorah side. This feature will recover the lost orders due to connection loss or delayed callbacks.

***

#### **MyFatoorah Account**

1. Log in to your [Myfatoorah account](https://portal.myfatoorah.com/), go to Integration Settings → Webhook Settings, and configure your settings.
2. Enable the feature.
3. Add your **Endpoint** URL. Replace only the \{example.com} with your WHMCS store domain.

```
https://{example.com}/modules/gateways/myfatoorah/webhook.php
```

4. Generate the **Webhook Secret Key** and copy it by clicking on the copy icon (:clipboard:) on the right side of the secret key box.
5. Select the **Transaction Status Changed** event type.
6. Click on the Save button.

![WHMCS Endpoint.png](https://files.readme.io/fac3e4c-WHMCS_Endpoint.png)

***

#### **WHMCS Account**

1. Log in to your **WHMCS** admin panel.
2. Click on the **wrench** icon at the upper right corner, then click on **System Settings**.
3. Under the **Payment** section, click on the **Payment Gateways** link.
4. Navigate to the **“Manage Existing Gateways”** tab.
5. Paste the secret key into the **Webhook Secret Key** text box.
6. Click on the **Save Changes** button.

![WHMCS Webhook Secret Key.png](https://files.readme.io/225b4d1-WHMCS_Webhook_Secret_Key.png)

***

## WooCommerce

*`https://docs.myfatoorah.com/docs/woocommerce` — updated 2025-11-12*

### Source Files

* You can download the last plugin version from the official <a href="https://wordpress.org/plugins/myfatoorah-woocommerce/" target="_blank">WordPress</a> site.

> 🚧 Older Version
>
> If your older version was not downloaded from the official site, kindly completely remove it and install the official version from the above link to get the last updates.

***

### Requirements

* WooCommerce 3.0.9 up to last updated version
* PHP 5.6 up to PHP 7.4 (you can view this under WooCommerce → Status)
* An SSL certificate

***

### Installation Steps

Kindly, follow the below steps for installing the WooCommerce extension:

1. You should install, enable, and configure the WooCommerce plugin first before dealing with Myfatoorah.
2. Login into your WordPress admin panel. Select **Plugins** and click **Add New**.
3. Type **MyFatoorah**  in the **Keyword** search field. The **MyFatoorah – WooCommerce** plugin will be listed in the search result.
4. Click on the **Install Now** button.
5. Then, click on the **Activate** button.
6. After that, navigate to **Plugins** → **Installed Plugins**, the **MyFatoorah - WooCommerce** plugin module will appear in the **Installed Plugins** list.
7. Finally, click on the **Cards** link to add the merchant configuration.

![Add MyFatoorah Plugin WooCommerce.png](https://files.readme.io/7a2dbed-Add_MyFatoorah_Plugin_WooCommerce.png)

![MyFatoorah - WooCommerce.png](https://files.readme.io/aefc85a-MyFatoorah_-_WooCommerce.png)

![Activated MyFatoorah - WooCommerce.png](https://files.readme.io/06553b4-Activated_MyFatoorah_-_WooCommerce.png)

***

### Merchant Configurations

In WordPress Admin Panel, configure the plugin with API key credentials under WooCommerce → Settings → Payments

![Payment_Methods.png](https://files.readme.io/3488017-Payment_Methods.png)

If You enable the new design feature your payment list will be shown similar to the below screenshot.

![screenshot-1.png](https://files.readme.io/06fc7b0-screenshot-1.png)

<ins>Demo Configuration:</ins>\
Please, enable the test mode and use [demo token](https://docs.myfatoorah.com/docs/test-token).\
You can use the list of [test cards](test-cards) to explore the payment process.

<ins>Live Configuration:</ins>\
Please, disable the test mode and use [your live token](live-token).

***

> 👍 Your review is appreciated
>
> Kindly add your [review](https://wordpress.org/support/plugin/myfatoorah-woocommerce/reviews/)  to WordPress  [official marketplace](https://wordpress.org/plugins/myfatoorah-woocommerce/) .

#### FAQs

> 🥇 **What's the difference between the  MyFatoorah Cards and MyFatoorah - Embedded payment methods?**
>
> * **MyFatoorah Cards:**  This uses the default gateways that were enabled in your portal account. You should enable only this payment method.
> * **MyFatoorah - OnSite:** This is used only if you have any gateway that has the embedded payment feature in it at your portal account.

> 🥈 **What is the difference between the List Payment Options?**
>
> * **MyFatoorah Invoice Page (Redirect):** It will redirect the buyer to the MyFatoorah invoice page that contains a list of all payment gateways with their service charges.
> * **List All Enabled Gateways in Checkout Page:** It will list the payment gateways inside your website and then will redirect the buyer straightway to the payment gateway page.

> 🥉 **What is the Single Gateway View?**
>
> This option is used in case of having only one gateway enabled in your portal account.  Select this option and the name and the icon of this gateway will be displayed on the checkout page instead of the MyFatoorah default icon.

> 🏅 **What is the Webhook Secret Key?**
>
> This option enables triggering events each time the order status changes at the MyFatoorah portal side. This feature recovers the lost orders due to connection loss or delay called back. Kindly refer to the [https://myfatoorah.readme.io/docs/woocommerce-webhook](https://myfatoorah.readme.io/docs/woocommerce-webhook).

## Shipping

*`https://docs.myfatoorah.com/docs/woocommerce-shipping` — updated 2025-11-12*

> WooCommerce

### Source Files

* You can download the last plugin version from the official <a href="https://wordpress.org/plugins/myfatoorah-woocommerce/" target="_blank">WordPress</a> site.

***

### Installation Steps

Kindly, follow the steps mentioned in [the link](https://myfatoorah.readme.io/docs/woocommerce) to install and configure the WooCommerce extension.

***

### Shipping Configurations

> ❗️ Approval is needed!
>
> Kindly, contact your account manager or sales representative to activate the **Shipping** feature.

In WordPress Admin Panel, under WooCommerce → Settings → Shipping → MyFatoorah Shipping API Ver 2.0

1. Enable the plugin
2. Add the Shipping method name that will be shown on the checkout page.
3. Select available shipping methods that are [enabled in your portal](https://docs.myfatoorah.com/docs/shipping-information):
   * "Aramex",
   * "DHL".
4. If you have any country that you don't want to use the Myfatoorah shipping module with, You will select it from the **"Exclude countries from shipping rates"**

![Screenshot from 2022-10-24 14-18-49.png](https://files.readme.io/c8f3c4f-Screenshot_from_2022-10-24_14-18-49.png)

***

### Arabic Item Names Configuration

> 🚧 Arabic Letters
>
> DHL and Aramex does not accept Arabic letters in Item names, descriptions, customer names, and adress.

To avoid the above shipping restrictions, you should add a new attribute to your product as follows:

1. In WordPress Admin Panel, navigate to Products.
2. After selecting any desired product, go to the **Product data** section and click the **Attributes** tab.
3. Select **Custom product attribute** from the dropdown list, then click the **Add** button.
4. Type **mf\_shipping\_english\_name** as the attribute **Name**.
5. Type the product's English name as the attribute **Value**.
6. Click on the **Update** button on the right panel.

***

### Dimensions and  Weight Configurations

1. In WordPress Admin Panel, navigate to WooCommerce → Settings → Products.
2. Select your product **Weight unit**  and **Dimensions unit**.
3. In WordPress Admin Panel, navigate to Products.
4. After selecting any desired product, go to the **Product data** section and click the **Shipping** tab.
5. Add the **Weight** and **Dimensions** data.
6. Click on the **Update** button on the right panel.

> 🚧 Used Units
>
> **cm, m, mm, in, and yd** are the dimension units accepted by the Myfatoorah plugin.\
> **kg,  g, lbs, and oz** are the weight units accepted by the Myfatoorah plugin.
>
> Kindly, note that if you use an Arabic website, be sure that your units are set in English letters as described above.

## Webhook

*`https://docs.myfatoorah.com/docs/woocommerce-webhook` — updated 2025-11-12*

#### Why Using Webhook

You can enable the Webhook feature to trigger events each time the order status changes at the MyFatoorah side. This feature will recover the lost orders due to connection loss or delayed callbacks.

***

#### **MyFatoorah Account**

1. Log in to your [MyFatoorah portal account](https://portal.myfatoorah.com/) and go to Integration Settings → Webhook Settings and configure your settings.
2. Enable the feature.
3. Add your **Endpoint** URL. Replace only the **\{example.com}** with your site URL.

```
https://{example.com}/?wc-api=myfatoorah_webhook
```

4. Generate the **Webhook Secret Key** and copy it by clicking on the copy icon :clipboard: on the right side of the secret key box.
5. Select the **Transaction Status Changed** event type.
6. Click on the Save button.

![woocommerce-webhook.png](https://files.readme.io/86b1acf-woocommerce-webhook.png)

***

#### **WordPress Account**

1. Login into your **WordPress** admin panel and navigate to WooCommerce → Settings → Payments → MyFatoorah.
2. Paste the secret key into the **Webhook Secret Key** text box.
3. Click on the **Save changes** button.

![WooCommerce Webhook](https://files.readme.io/bf3e4d9159655fa80169afba4775499a782815e34bdef8070552430b8aa3c73c-image.png)

***

## Embedded Apple Pay

*`https://docs.myfatoorah.com/docs/woo-embedded-apple-pay` — updated 2025-11-12*

> WooCommerce

#### **Introduction**

To provide a better user experience to your Apple Pay users, **MyFatoorah** is providing the Apple Pay embedded payment.

The Apple Pay button can be placed on your checkout page. When your customers click the button, **MyFatoorah** will direct the customers to the Apple Pay payment sheet page to authorize the payment.

![Apple Pay.png](https://files.readme.io/4f07adc-Apple_Pay.png)

![Apple Pay Payment Sheet.png](https://files.readme.io/db15645-Apple_Pay_Payment_Sheet.png)

***

#### **Apple Pay Activation Steps**

To use Apple Pay Button, you need to add the Apple Pay verification file on your web domain.

1. Host your domain verification file.
2. Activate Apple Pay Embedded in the admin panel.

#### Step1: Host your domain verification file

Steps:\
&#x9;1- Create a folder in the root path and name it ".well-known"\
&#x9;2- Paste the file inside the ".well-known" folder.\
&#x9;3- Test that the file is located in the correct place by opening the below URL\
&#x9;<https://{example.com}/.well-known/apple-developer-merchantid-domain-association>\
&#x9;Replace only the \{example.com} with your site URL.

```text Path
https://{example.com}/.well-known/apple-developer-merchantid-domain-association
```

> 🚧 Domain Verification File
>
> Do not remove the file from the server. Apple might need to re-validate the domain on a yearly basis and this file must be available for successful validation.

> ❗️ SSL
>
> Domain should be TLS (HTTPS) enabled.

#### Step2: Activate Apple Pay Embedded in the admin panel

From MyFatoorah Plugin Setting in your admin, make sure to make Apple Pay Embedded Enabled.

![woo-applePay.png](https://files.readme.io/5038bbd-woo-applePay.png)

***

## Zoho (New)

*`https://docs.myfatoorah.com/docs/zoho` — updated 2026-03-28*

#### Register for the MyFatoorah Zoho Application:

1. Click the [application link](https://myfatoorah.a2hosted.com/manage/zoho/public/register/).
2. Fill in the required information.
3. Go to the Settings page.
4. In the Zoho Information section:
   * Fill in your organization ID and name.
   * Generate a new Account ID and Password. These will be used later in the MyFatoorah Zoho Books extension.
5. In the MyFatoorah Information section:
   * Select your MyFatoorah vendor country.
   * Switch to Live/Test Mode.
   * Enter the Live/Test [API token](https://docs.myfatoorah.com/docs/live-token/).
   * Follow the steps in the [MyFatoorah Webhook Settings](#webhook-configuration) section to generate a webhook secret key and paste it into the correct field.
   * Copy the auto-generated Webhook URL to be used in the [MyFatoorah Webhook Settings](#webhook-configuration).
6. Save your settings.

#### Set Up the MyFatoorah extension into Zoho Books Organization:

1. Click the [installation link](https://marketplace.zoho.com/app/books/myfatoorah-payment-gateway-for-zoho-books), to install the MyFatoorah extension.
2. Log in to your Zoho Books organization.
3. Go to Settings > Payment Gateways.
4. Click the "Set up Now" button for MyFatoorah.
5. Use the Account ID and Password you generated from the MyFatoorah Zoho Application.

![Set up](https://files.readme.io/4a09cf5-Zoho_Gateway_-_MyFatoorah.png)

#### Webhook Configuration:

1. Log in to your [MyFatoorah portal account](https://portal.myfatoorah.com/) and go to Integration Settings → Webhook Settings.
2. Enable the feature.
3. Add your **Endpoint URL** (the one shown in the MyFatoorah Zoho Books Application).
4. Set the version to V1.
5. Generate the **Webhook Secret Key** and copy it by clicking the copy icon :clipboard: next to it.
6. Select the **Transaction Status Changed** event type.
7. Click the Save button.

![MyFatoorah Webhook](https://files.readme.io/93bd7d9-small-Webhook.jpg)

For more information check [MyFatoorah webhook settings](https://docs.myfatoorah.com/docs/webhook-information).

> 📘 Old Integration
>
> In case of you use old integration, you can find the documentation here: [Zoho (Old)](https://docs.myfatoorah.com/update/docs/zoho-old/)

> 👍 Your review is appreciated
>
> Kindly add your [review](https://marketplace.zoho.com/app/commerce/myfatoorah-for-zoho-commerce#ratingsReview/)  to the Zoho [official marketplace](https://marketplace.zoho.com/app/commerce/myfatoorah-for-zoho-commerce/) .
