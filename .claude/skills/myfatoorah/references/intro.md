# MyFatoorah — Introduction

## Get Started

*`https://docs.myfatoorah.com/docs/get-started` — updated 2026-02-15*

> Build your integration and start accepting payments online

#### Introduction

In this section of the documentation, we will explain the different types of integration with MyFatoorah. This guide helps developers to get a better understanding of the exact technical endpoints and functions needed. It will serve the business accordingly and save development time and efforts to project the required business needs on the desired API and integration.

#### Integration Methods

- [Embedded Payment (Recommended)](https://docs.myfatoorah.com/docs/embedded-payment-v3) — Allow customers to complete the payment directly on your checkout page, while supporting hosted payment methods through a single integration.

- [Hosted Payment Page](https://docs.myfatoorah.com/docs/v3-hosted-payment-page) — You are able to redirect the client to the payment page based on the payment method he chose to pay from his side.

- [Invoicing](https://docs.myfatoorah.com/docs/v3-invoicing) — You are able to create a payment link and send it to your customers via email, SMS, or both. The customer can pay with any of the activated payment methods to your account.

- [Direct Payment](https://docs.myfatoorah.com/docs/v3-direct-payment) — Collect card details or decrypt wallet tokens and send them to us. **PCI Certificate is required**

***

#### Updating Your System

- [Webhook](https://docs.myfatoorah.com/docs/webhook) — Keeps your system instantly updated through server-to-server notifications.

- [Get Payment Details](https://docs.myfatoorah.com/docs/get-payment-details) — GET Payments keeps your system updated with the transaction status changes.

> 📘 Recommended Action
>
> Kindly review the following section for more understanding of the best way to update your system with the payment status: [Updating Payment Status Guidelines](https://docs.myfatoorah.com/docs/v3-updating-payment-status-guidelines)

***

#### Demo Environment

- [Test Cards](https://docs.myfatoorah.com/docs/test-cards) — Perform virtual transactions and make sure everything is perfectly working before going live.

- [Test Token](https://docs.myfatoorah.com/docs/api-key#test-demo-token) — Simulate live production without the need for actual payment.

> 📘 Demo Account Registration
>
> To test your integration without real transactions, create a demo account by registering at <https://registertest.myfatoorah.com/en/> , selecting Kuwait as the country, and skipping the bank details step. After completing the registration, email <tech@myfatoorah.com> to activate your demo account and enable the required features.

> 📘 API & Portal URLs
>
> To identify the correct API Base URL and Portal URL for your environment, please refer to this [table](https://docs.myfatoorah.com/docs/api-key#api--portal-urls).

## Overview

*`https://docs.myfatoorah.com/docs/technical-guide-overview` — updated 2026-02-16*

About Integration Types

#### Introduction

In this section of the documentation, we will explain the different types of integration with **MyFatoorah**. This guide helps developers to get a better understanding of the exact technical end-points and functions needed. It will serve the business accordingly and save development time and efforts to project the required business needs on the desired API and integration.

The integration can be done through different technical interfaces such as API, [SDK](https://docs.myfatoorah.com/docs/sdk-overview), or [plugins](https://docs.myfatoorah.com/docs/plugin-overview).

#### **Prerequisites**

This document is intended for use by technical teams responsible for developing, executing, and integrating their system with the **MyFatoorah** payment platform. It's strongly recommended to have sturdy knowledge about the different [HTTP methods](https://www.w3schools.com/tags/ref_httpmethods.asp) (Get/Post) and how they work before proceeding with your integration. For more information about this, you can check this [link](https://www.w3schools.com/tags/ref_httpmethods.asp). In addition, you must have technical knowledge about [API integrations](https://en.wikipedia.org/wiki/Application_programming_interface), [JSON](https://json.org/) messages, and how to call and consume APIs.

> ❗️ Minimum TLS version.
>
> The minimum supported version is TLS 1.2.  You have to use [TLS V1.2 protocol](https://tecadmin.net/enable-tls-on-windows-server-and-iis/) or above.

***

#### **Integration Types**

We are listing below the different types of payment with **MyFatoorah**; this list is expandable by time:

* [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-payment-v3)
* [Hosted Payment Page](https://docs.myfatoorah.com/docs/v3-hosted-payment-page)
* [Invoicing](https://docs.myfatoorah.com/docs/v3-invoicing)

#### Embedded Payment

In **Embedded Payment**, you can add a form to your website in which the customers will be able to enter their card information and complete the payment on your website.\
Embedded payment doesn't require you to be PCI DSS certified.

**Card View:** You will add the form below to your checkout page which will enable the customers to enter their card data and MyFatoorah will process the card information and redirect the customers to the OTP challenge page.\
Available for: Visa/Master, Mada, and Amex.

![Screenshot_20230327_085948.png](https://files.readme.io/b6dda52-Screenshot_20230327_085948.png)

**Apple Pay:** You can embed the Apple Pay button on your checkout page. The customers will choose the card they want to pay with, confirm their identity and the payment will be done.

**Google Pay:** You can also include Google Pay button directly on your checkout page.

![Embedded View.png](https://files.readme.io/630220d-Embedded_View.png)

\*\*Hosted Payment Methods:\*\*In the embedded payment, we list all the available hosted payment methods. If a customer chooses to pay with it, we will redirect them to the hosted payment page.

We strongly recommend that you use the Embedded Payment for a straightforward and seamless payment scenario for all payment options.

***

#### Hosted Payment Page

In **Hosted Payment**, you are able to redirect the client to the payment page based on the payment method he chose to pay from his side. You view on your website the payment methods that the customers can pay with. Once the customer makes their choice,  you redirect them to the gateway to complete their payment.

![Gateway Redirection View](https://files.readme.io/336316f-MicrosoftTeams-image_View.png)

*You can customize how the design looks on your own website.*

***

#### Invoicing

In **Invoicing**, you are able to create a payment link and send it to your customers via email, SMS, or both. In the Invoice, the customer can pay with any of the activated payment methods to your account.

You can both create the Invoice and send it to your customer by sending 1 request to the [Create Payment](https://docs.myfatoorah.com/update/reference/create-payment) endpoint.

![](https://files.readme.io/b83ce57bd9992754511d5f1bf68d8827f5248764ba80931aea48d0d37dd59203-image.png)

***

#### Updating System with Transactions

There are two ways to update your system when a payment is made:

1. [Webhook](https://docs.myfatoorah.com/docs/webhook):\
   The webhook keeps your system updated with changes in the following:(Transaction Status - Refund Status - Balance Transfer - Supplier Status - Recurring Status). Webhook is a **server-to-server** update where it makes your system updated instantaneously.

   ![](https://files.readme.io/45ca8d2d57886873948b2fd1cfcf27292f75be22a5cabf3d3a7ff81fdf5af3b1-image.png)
2. [Get Payment Details](https://docs.myfatoorah.com/docs/get-payment-details):\
   GET Payments keeps your system updated only with the transaction status changes.

In some cases, the redirection process is not completed to the end because the customer closed the page, because of connection issues, or any other reason. In this case, your system will not call GET Payments and will not receive updates on the order. This is when the webhook comes in handy.

> 📘 Recommended Action
>
> Kindly review the following section for more understanding of the best way to update your system with the payment status: [Updating Payment Status Guidelines](https://docs.myfatoorah.com/docs/v3-updating-payment-status-guidelines)

***

#### **Supporting Features**

MyFatoorah enables its users to use its features that open up a lot of business gates in which you can use them to enable you to do much more with very simple steps. We will discuss them briefly now and later in the documentation, you will find the details for each feature.\
**Save Cards**:\
This feature enables customers to save their card data in MyFatoorah. This gives a better user experience to your regular customers. This feature is enabled in both the Gateway Redirection and Embedded Payment.\
After the customer has saved his card data, the next time he makes payments, he will only have to enter his CVV

![Screenshot_20230327_103015.png](https://files.readme.io/9ab12f9-Screenshot_20230327_103015.png)

**Refund**:\
This feature enables you to fully or partially refund an amount to your end customer either from your balance at MyFatoorah or from your personal bank account. You can do that either by the APIs or from your MyFatoorah portal.

**Authorization & Capture**:\
This feature enables you to either **Capture** the amount the customer paid **fully/partially** or **Release** the amount back to the customer.

**Multi-Vendor**:\
This feature enables you to connect the merchants/vendors/suppliers to your account at MyFatoorah and control the amount between them and you.

**Card Verification**:\
Verify the card information of the customer and tokenize it to use it for future payments.

**Shipping**:\
This feature connects you to DHL & Aramex for faster shipping of your products. You can use this feature in invoices created either from your MyFatoorah Portal or the APIs.

**Recurring**:\
This feature facilitates the process of collecting the same amount over a fixed period of time from your customers. This feature is accessible from both the MyFatoorah portal and the APIs.

***

<br />

In the upcoming sections, we will discuss MyFatoorah features in detail and how to integrate them in simple steps.

## Live Account

*`https://docs.myfatoorah.com/docs/live-account` — updated 2026-02-06*

> How to create your account with MyFatoorah?

To use MyFatoorah API in a live environment, you need two main steps:

1. Register a new account.
2. Activate the account.

***

#### **Account Registration**

To register a new account to the **MyFatoorah Portal System**, kindly follow the below steps:

1. Go to the [MyFatoorah Registration](https://register.myfatoorah.com/) website, then click on **Register**.
2. Choose your country and fill out the required information to create your account.

***

#### **Account Activation**

After registering a new account, select your country from the [country list](https://www.myfatoorah.com/en/contact-us/) and call an **account manager** or **sales representative** to:

* Activate your account.
* Activate the **API role** which you will use within the integration.

The [account manager](https://www.myfatoorah.com/en/contact-us/) will also introduce the features provided by **MyFatoorah**, like [Direct Payment](https://docs.myfatoorah.com/docs/direct-payment), [Tokenization](https://docs.myfatoorah.com/docs/tokenization), [Shipping](https://docs.myfatoorah.com/docs/shipping), and [Multiple Suppliers](https://docs.myfatoorah.com/docs/multiple-suppliers) features.

***

#### **Multi-Country Account**

To obtain better service over the MyFatoorah platform, we designed one dedicated system to serve multiple countries. We provide support for the following countries:

* Kuwait
* Saudi Arabia
* Bahrain
* Emirates
* Qatar
* Egypt
* Oman
* Jordan

> 🚧 Multi-Country Account
>
> If your business is running over one or more countries listed above, you don't have to create an account for each country. You only need to add another country to your account.
>
> Kindly contact your account manager or sales representative to activate the country as per your needs.

> 📘 Demo Information
>
> Now, don't worry about the above points. They will not be a blocker for you to proceed with your technology integration and development. We have a [Test Token](https://docs.myfatoorah.com/docs/api-key#test-demo-token) and [Test Cards](https://docs.myfatoorah.com/docs/test-cards) for you to progress through.

***

## Account Information

*`https://docs.myfatoorah.com/docs/account-information` — updated 2025-12-24*

You can change your account information, such as the work email, phone, and the logo that appears on the invoice page, by following the below steps:

1. Log in to the [Myfatoorah account](https://portal.myfatoorah.com/) using your **Super Master Email**.
2. Select the **"Business Profile"** button from the left menu.
3. Choose the **"Update Profile"** button from the drop-down menu.
4. Edit the needed data.
5. Click on the **"Save"** icon to save your changes.

![](https://files.readme.io/08ec01f7dbf6b496b24b7ddd47169b1615d132a539a9c7b3430f4dfab5953168-image.png)

## Orders Information

*`https://docs.myfatoorah.com/docs/orders-information` — updated 2025-12-24*

You can check your orders by following the steps below:

1. Log in to the [Myfatoorah account](https://portal.myfatoorah.com/) using your **Super Master Email**.
2. Select the **"Orders List"** button from the left menu.
3. Click on the **"Filters"** link.
4. Select the Invoice Status as All, Unpaid, or Paid.
5. You can filter using your website order ID as the Customer Reference.
6. You can filter using the Invoice ID as the Order ID.
7. Click on the **"Refresh"** button to filter your orders.

![](https://files.readme.io/3c9dba948af769f9546dfb3decbb18493a2b6f01bbed0bc6428dbea1acc77895-image.png)

## API Key

*`https://docs.myfatoorah.com/docs/api-key` — updated 2026-02-17*

#### Overview

The API Key is required to authenticate your application with the MyFatoorah API.\
Use this token in your API requests to ensure secure communication between your system and MyFatoorah services.

#### API & Portal URLs

| **Country / Environment**         | **API URL**                       | **Portal URL**                                                                   |
| --------------------------------- | --------------------------------- | -------------------------------------------------------------------------------- |
| **Test (Sandbox)**                | <https://apitest.myfatoorah.com/> | [https://demo.myfatoorah.com/](https://demo.myfatoorah.com/En/All/Account/LogIn) |
| **Kuwait, Bahrain, Jordan, Oman** | <https://api.myfatoorah.com/>     | <https://portal.myfatoorah.com/>                                                 |
| **UAE**                           | <https://api-ae.myfatoorah.com/>  | <https://ae.myfatoorah.com/>                                                     |
| **Saudi Arabia**                  | <https://api-sa.myfatoorah.com/>  | <https://sa.myfatoorah.com/>                                                     |
| **Qatar**                         | <https://api-qa.myfatoorah.com/>  | <https://qa.myfatoorah.com/>                                                     |
| **Egypt**                         | <https://api-eg.myfatoorah.com/>  | <https://eg.myfatoorah.com/>                                                     |

#### How to Generate Your API Token Key

**You can get your API key as follows:**

* Log in to your MyFatoorah account using your Super Master Account.
* From the side menu, select **Integration Settings → API Key**
* Click the Add button to generate a new key.
* You will be redirected to a page where you can configure the key settings:
  * **Name:** A custom label for your token.
  * **Expiry Date:** Define when this key will expire.
  * **Active Status:** Choose whether the key is active or inactive.
  * **Permissions:** Select which API endpoints the key can access, and you can assign custom permissions like Super Rules, Create Payments, Update Payments, etc.
* After filling in the details, scroll down and click Create to generate the new API key.
* Once created, your key will appear in the list.
* Click on the Copy icon next to the key to copy it and start using this key in your integration.

![](https://files.readme.io/04f3c4ee07034ca0c83689bf50a43d8cf95313e782bae4dadf7d4347a1085963-newkey.PNG)

![](https://files.readme.io/64708704d4bf88b3109ecb3bc59ad6607d7e5e53d80289c072392e53c28e77b5-image.png)

> 🚧 **Important Notes**
>
> * You can **create up to 5 API keys** only.
>
> * If you use a token to access an endpoint **without the required permissions**,
>   you will receive the following error:
>   `Status Code: 401
>     , Message: "The token does not have the required permissions!"`
>
> * You can edit an existing API key by clicking the Edit icon next to it. This allows you to update the name, permissions, status, or **delete** the key.

> 📘 **Request Header**
>
> Add **"Authorization": "Bearer token"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/api-key#test-demo-token).

> ❗️ **Legacy API Key**
>
> If you are using a legacy API Key, it will remain valid until it expires. After that, you will be forced to use the new version of the API Keys.
>
> ![](https://files.readme.io/1f16ba20cf7a1ea5c86ced8167fadf0c6673542e4b467e52d8af5ab6182deea1-image.png)

> 📘 **Multi-Country Account**
>
> Each enabled country in your portal account has a different API token key. You have to use the API token key of each country in your integration to allow your customer to pay in their local currency.

> ❗️ **Disable user**
>
> Please, avoid disabling the user who created the token, as it will disable the token key.

#### Test (Demo) Token

To test your integration without real transactions, create a demo account by registering at <https://registertest.myfatoorah.com/en/> , selecting Kuwait as the country, and skipping the bank details step. After completing the registration, email <tech@myfatoorah.com> to activate your demo account and enable the required features.

**Public Test API Token Key**

You can also use the public test token provided by MyFatoorah.\
Click the **copy icon** 📋 next to the token box to copy it.

```
SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx
```

<br />

## Test Token

*`https://docs.myfatoorah.com/docs/test-token` — updated 2025-12-24*

The following is access information used within your testing/sandbox environment. It would be helpful to proceed with your integration to ensure that your application is properly communicating with **MyFatoorah** API. All that you need is the test API URL and a test API Key.

**Test API URL:** <https://apitest.myfatoorah.com/>\
**Test API Key:** Follow the below steps to obtain your test API token key.<hr />

#### **API Token Key**

Click on the copy icon :clipboard: on the right side of the token box to copy it into your clipboard.

```
SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx
```

***

> 📘 Test Currency
>
> As long as you are in test mode, the currency will be in KWD. Once you switch to live mode, the currency will be the same as your portal account currency. Also, the invoice value will be converted to KWD using the MyFatoorah exchange rate.

> 📘 Demo Account
>
> To create a separate demo account on **MyFatoorah** to help you during the system integration, you can register a new account on <https://demo.myfatoorah.com/>. After that, email MyFatoorah technical team <tech@myfatoorah.com> to activate it with your required features.

## Live Token

*`https://docs.myfatoorah.com/docs/live-token` — updated 2026-01-01*

The following is access information used within your live environment. All you need is the live API URL and a live API Key.

**Live API URL For Kuwait, Bahrain, Jordan, and Oman:**<https://api.myfatoorah.com/>\
**Live API URL for UAE:** <https://api-ae.myfatoorah.com/>\
**Live API URL for Saudi Arabia:**<https://api-sa.myfatoorah.com/>\
**Live API URL for Qatar:**<https://api-qa.myfatoorah.com/>\
**Live API URL for Egypt:**<https://api-eg.myfatoorah.com/>

**Live API Key:** Follow the below steps to obtain your live API token key.<hr />

#### **API Token Key**

You can get the live API token key as follows:

1. Log in to [your Myfatoorah account](https://portal.myfatoorah.com/) using your **Super Master Account**.
2. Select the **"Integration Settings"** button from the left menu.
3. Choose the **"API Key"** button from the drop-down menu.
4. Press on the **"Create"** button to generate a new API token key.
5. Click on the **"Copy"** icon on the lower right corner of the textbox to copy and use it in your integration.

![](https://files.readme.io/3cc7c8f49ab2b1b03b63fe8ce819739287226bfc7e21bc20fad777fe25423026-image.png)

> ❗️ Disable user
>
> Please, avoid disabling the user who created the token, as it will disable the token key.

> 🚧 Expired Token
>
> Kindly note that the API token key is expired every five years. After that, you should regenerate a new API token key.

> 🚧 New Token
>
> If you regenerate a new API token key, the old one will be expired.

> 📘 Multi-Country Account
>
> Each enabled country in your portal account has a different API token key. You have to use the API token key of each country in your integration to allow your customer to pay in their local currency.

***

## Test Cards

*`https://docs.myfatoorah.com/docs/test-cards` — updated 2026-07-09*

> Payment Gateways Test Cards Details

After Implementing The API integration, you can use the below test cards to perform virtual transactions and make sure everything is perfectly working before going live.

Please, find below the test card details for the various payment options we offer.

> 🚧 Test Cards Availability
>
> **Not all gateways** provide test cards to be used during the development phase. We will keep this list updated as soon as we get any new pieces of information.

> 🚧 Knet Availability
>
> Not all countries support the Knet test gateway.

#### **Test Cards**

| **Card Type** | **Card Number** | **Expiry Date** | **CVC** | **Result** |
|---|---|---|---|---|
| **Knet** | 8888880000000001 | 09/30 | Any 4 digit | Captured |
| **Knet** | 8888880000000001 | Any | Any 4 digit | Not Captured |
| **Visa/Master** | 4508750015741019 | Any | Any |  |
| **Visa/Master** | 2223000000000007 | 01/39 | 100 |  |
| **Visa/Master** | 5453010000095539 | 12/25 | 300 |  |
| **Visa/Master** | 5123450000000008 | 01/39 | 100 |  |
| **Visa/Master** | 5457210001000019 | 12/25 | 212 |  |
| **Visa/Master** | 4012001037141112 | 12/24 | 207 |  |
| **Apple Pay** | Check this link: [Apple Pay - Test Cards](https://developer.apple.com/apple-pay/sandbox-testing/) |  |  |  |
| **Benefit** | 4600410123456789 | Any | Any 6 digit | Captured |
| **Benefit** | 4550120123456789 | Any | Any 6 digit | Expired card |
| **Benefit** | 4845550123456789 | Any | Any 6 digit | Incorrect PIN |
| **Benefit** | 4575550123456789 | Any | Any 6 digit | Refer to Issuer |
| **Benefit** | 4895550123456789 | Any | Any 6 digit | Please contact issuer |
| **AMEX** | 345678901234564 | 05/21 | 1000 | Unspecified Failure |
| **AMEX** | 345678901234564 | 04/37 | 1000 | Declined |
| **Mada** | 4464040000000007 | 02/29 | 123 | Capture |
| **Mada** | 5297412542005689 | 05/25 | 350 |  |
| **Jaywan** | 6690109900000010 | 01/39 | 100 |  |
| **Jaywan** | 6690109000011016 | 01/39 | 100 |  |
| **STC Pay** | Mobile: 0557877988 |  |  | OTP: 1234 |
| **STC Pay** | Mobile: 0548220713 |  |  | OTP: 1234 |

> 👍 Card Holder's Name
>
> Use any two-part name like "test test".

## Payment Methods

*`https://docs.myfatoorah.com/docs/payment-methods` — updated 2026-02-16*

All available payment methods and their description

#### **Payment Methods**

Here we are listing all supported payment methods at MyFatoorah along with their Arabic names.

| Payment Method Name (English) | Payment Method Name (Arabic)  |
| :---------------------------- | :---------------------------- |
| KNET                          | كي نت                         |
| VISA/MASTER                   | فيزا / ماستر                  |
| AMEX                          | اميكس                         |
| Benefit                       | بنفت                          |
| MADA                          | مدى                           |
| UAE Debit Cards               | كروت الدفع المدينة (الامارات) |
| Qatar Debit Cards             | كروت الدفع المدينة (قطر)      |
| Apple Pay                     | ابل باي                       |
| Google Pay                    | جوجل باي                      |
| STC Pay                       | STC Pay                       |
| Samsung Pay                   | سامسونج باي                   |
| Mobile Wallet (Egypt)         | محفظة إلكترونية (مصر)         |
| Meeza                         | ميزة                          |

> 📘 Availability
>
> Based on your account and agreement with MyFatoorah, not all payment methods will be available for your account. If a specific payment method is needed, please, contact your [account manager](https://www.myfatoorah.com/en/contact-us/). Kindly read [Gateway Integration](https://myfatoorah.readme.io/v2.0/docs/gateway-integration) for more information about it.

<br />

## ISO Lookups

*`https://docs.myfatoorah.com/docs/iso-lookups` — updated 2025-12-24*

Below, we are listing all possible values for the ISO lookups used during the integration development. Based on the endpoint and its description, you can select the desired value accordingly.

| Country                    | Country ISO Code | Currency ISO Code | Mobile Country Code |
| :------------------------- | :--------------- | :---------------- | :------------------ |
| Kuwait                     | KWT              | KWD               | +965                |
| Saudi Arabia               | SAU              | SAR               | +966                |
| Bahrain                    | BHR              | BHD               | +973                |
| United Arab Emirates (UAE) | ARE              | AED               | +971                |
| Qatar                      | QAT              | QAR               | +974                |
| Oman                       | OMN              | OMR               | +968                |
| Jordan                     | JOR              | JOD               | +962                |
| Egypt                      | EGY              | EGP               | +20                 |

## Postman

*`https://docs.myfatoorah.com/docs/postman` — updated 2025-12-24*

**MyFatoorah** provides some Postman Collections to help you understand and test the API endpoints

* [FullAPI](https://myfatoorahkw-my.sharepoint.com/:u:/g/personal/tech_myfatoorah_com/EZ98hMcP2ZREu6xf-c33JOMBXD2DLUGVaEuTHRHw1Q7jkA?e=yC4wgb)

Follow the below steps to configure any of the above collections into Postman:

1. Navigate to **File** → **Import**.

![postman-step-1.png](https://files.readme.io/5430bdf-postman-step-1.png)

2. From the **File** tab, press **Upload Files**.

![postman-step-2.png](https://files.readme.io/18ee3c3-postman-step-2.png)

3. Select both **environment** and **collection** files.

![postman-step-3.png](https://files.readme.io/39a093b-postman-step-3.png)

4. Click on the **Import** button.

![postman-step-4.png](https://files.readme.io/5091616-postman-step-4.png)

5. On the upper right corner select the correct environment file.

![postman-step-5.png](https://files.readme.io/6e49c2c-postman-step-5.png)

6. Navigate to **File** → **New Runner Window**.
7. The Collection runner window will pop up.
8. Now you can explore the **MyFatoorah** API endpoints.
