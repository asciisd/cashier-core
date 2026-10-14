# MyFatoorah — Payment flows

## Choose Your Payment Integration

*`https://docs.myfatoorah.com/docs/choose-your-payment-integration` — updated 2026-05-05*

#### Introduction

MyFatoorah provides multiple payment solution options to meet different business needs, from fully embedded checkout experiences to simple payment links. Each option offers a different level of control, development effort, and user experience.

| Solution                           | Description                                                                                                                                              |
| ---------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Embedded Payment (Recommended)** | Embed the payment directly on your system with a customizable payment interface. Customers can enter payment details without leaving your checkout flow. |
| Hosted Payment Page                | Redirect customers to a secure MyFatoorah‑hosted page to complete payments.                                                                              |
| Invoicing / Payment Links          | Generate and share payment links via Email and SMS.                                                                                                      |

> 🥇 **Direct Integration**
>
> If you are **PCI certified**, you can use [direct integration](https://docs.myfatoorah.com/docs/v3-direct-payment).

#### Payments Comparison

| Feature                        | Embedded Payment                                                                     | Hosted Payment Page   | Invoicing / Payment Links |
| ------------------------------ | ------------------------------------------------------------------------------------ | --------------------- | ------------------------- |
| **Embed on Your website**      | ✅                                                                                    | ❌                     | ❌                         |
| **Redirection to Hosted Page** | Only for some methods                                                                | ✅                     | ✅                         |
| **PCI Compliance**             | Managed by MyFatoorah                                                                | Managed by MyFatoorah | Managed by MyFatoorah     |
| **Payment Methods**            | Cards & wallets, with automatic redirection to the hosted gateway page when required | All enabled methods   | All enabled methods       |
| **Save Card Details**          | Yes                                                                                  | Yes                   | Yes                       |
| **UI Customization**           | Customizable styling                                                                 | Limited customization | Limited customization     |

#### UI Preview

##### [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-payment-v3)

![Embedded Integration View](https://files.readme.io/159ed36679a5b0660322db9c58b6f6649f55f7a668e9d24cca2b8f36bdf95927-image.png)

##### [Hosted Payment Page](https://docs.myfatoorah.com/docs/v3-hosted-payment-page)

![](https://files.readme.io/5c360323f7d00021cfdd91ffff52439c4df9339ccd96164e4a18d3582c869f2a-image.png)

#### [Invoicing / Payment Links](https://docs.myfatoorah.com/docs/v3-invoicing)

![](https://files.readme.io/7cd3e733df79836605d10741a0b419f0127015accc2cd9251f2c934d9025193b-Macbook-Air-demo.myfatoorah.com.png)

#### Payment Methods

| Payment Method Name (English) | Payment Method Name (Arabic)  |
| :---------------------------- | :---------------------------- |
| KNET                          | كي نت                         |
| VISA/MASTER                   | فيزا / ماستر                  |
| AMEX                          | اميكس                         |
| Benefit                       | بنفت                          |
| Benefit Pay                   | بنفت باي                      |
| MADA                          | مدى                           |
| UAE Debit Cards               | كروت الدفع المدينة (الامارات) |
| Qatar Debit Cards             | كروت الدفع المدينة (قطر)      |
| Apple Pay                     | ابل باي                       |
| Google Pay                    | جوجل باي                      |
| STC Pay                       | STC Pay                       |
| Tamara                        | تمارا                         |
| Samsung Pay                   | سامسونج باي                   |
| Fawry                         | فوري                          |
| Mobile Wallet (Egypt)         | محفظة إلكترونية (مصر)         |
| Meeza                         | ميزة                          |

<br />

> 📘 Availability
>
> Based on your account and agreement with MyFatoorah, not all payment methods will be available for your account. If a specific payment method is needed, please, contact your [account manager](https://www.myfatoorah.com/en/contact-us/).

<br />

## Embedded Payment

*`https://docs.myfatoorah.com/docs/embedded-payment-v3` — updated 2026-02-16*

### Embedded Integration

The Embedded Integration allows you to integrate a seamless payment experience directly on your website, enabling customers to choose from payment methods that are enabled for your account. With a single integration, you can offer a unified checkout interface that blends perfectly with your website’s design, providing a consistent and user-friendly experience. This ensures that customers can easily select their preferred payment method and complete transactions in just a few clicks.

#### **Key Features**

* **PCI DSS Compliance**: MyFatoorah handles all PCI DSS requirements; no certification needed on your end
* **Unified Integration**: A Single JavaScript library supports all payment methods
* **Flexible Payment Options**: Embedded and Hosted Methods Together
* **Customizable UI**: Control which payment methods to display and handle redirections

#### **Supported Payment Methods**

**Embedded Payment Methods** (processed on your page without redirection):

* Cards (Visa / Mastercard / AMEX, etc.)
* Apple Pay
* Google Pay
* STC Pay

**Other Payment Methods (Hosted):**

* MyFatoorah automatically shows all payment methods enabled in your account. Payments not supported in embedded will redirect to the hosted payment page.

![Embedded Integration View](https://files.readme.io/9ed0c580476c2cc88b3b646dd192711f3c3b807876df5284578a89a3feaab7ae-embedded.PNG)

> 📘 Enable Payment Methods
>
> To enable payment methods on your Live account, please contact your [Account Manager](https://www.myfatoorah.com/en/contact-us/).\
> To enable payment methods on your Demo account, kindly send a request to <tech@myfatoorah.com> .

#### **Before You Start**

##### **Prerequisites**

1. **MyFatoorah Demo Account**: Create your test account at [MyFatoorah Portal](https://registertest.myfatoorah.com/en/)
2. **API Key**: Generate your test API key from the portal (or you can also use a test token [here](https://docs.myfatoorah.com/docs/test-token))
3. **Webhook Configuration**: Set up a [webhook](https://docs.myfatoorah.com/docs/webhook-information) endpoint to receive payment status notifications

##### **Environment URLs**

| Environment                       | API Base URL                      |
| --------------------------------- | --------------------------------- |
| **Sandbox**                       | `https://apitest.myfatoorah.com/` |
| **Kuwait, Bahrain, Jordan, Oman** | `https://api.myfatoorah.com/`     |
| **UAE**                           | `https://api-ae.myfatoorah.com/`  |
| **Saudi Arabia**                  | `https://api-sa.myfatoorah.com/`  |
| **Qatar**                         | `https://api-qa.myfatoorah.com/`  |
| **Egypt**                         | `https://api-eg.myfatoorah.com/`  |

> 🚧 Important
>
> Always use the **/v3/endpoint** for this integration.\
> For example: <https://apitest.myfatoorah.com/v3/sessions>\
> Older versions /v2 should not be used for new integrations.

> 🚧 Apple Pay Prerequisites
>
> To enable Apple Pay, you must verify your domain. For detailed instructions, please refer to our [Apple Pay Domain Verification Guide.](https://docs.myfatoorah.com/docs/apple-pay-domain-verification)

#### **Integration Modes**

MyFatoorah Embedded integration offers two distinct integration modes to suit different business needs:

##### **1. Complete Payment Mode** (Default)

* Use when you want the **entire payment process** to happen in **one API call**.
* Ideal for simple checkout flows.

##### **2. Collect Details Mode**

* Use when you want to collect payment details first, and then complete payment.
* Ideal for complex pricing scenarios or when the final amount may change.

### How it works

#### **Mode 1: Complete Payment**

This mode handles the entire payment process in a single flow, ideal for straightforward payment scenarios.

##### **Step 1: Create Payment Session**

Make a server-side API call to create a new payment session. You need to do this for each payment separately. SessionId is valid for only one payment.

**Endpoint:**`POST /v3/sessions` ([Create Session](https://docs.myfatoorah.com/reference/create-session))

```json Request Example
{
    "PaymentMode": "COMPLETE_PAYMENT",
    "Order": {
        "Amount": 10
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "SessionId": "KWT-68814db6-7510-4005-ada9-408aae9f373c",
        "SessionExpiry": "2025-10-02T00:52:35.6000597Z",
        "EncryptionKey": "m2QTkGqSxy24hpRGmoJ50vk6cfz4VJITNxGe5/uO+Qo=",
        "OperationType": "PAY",
        "Order": {
            "Amount": 10.0,
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

##### **Step 2: Initialize Client-Side Integration**

**Include JavaScript Library:**

```html
// Test Environment
<script src="https://demo.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For Kuwait, Bahrain, Jordan, and Oman
<script src="https://portal.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For UAE
<script src="https://ae.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For Saudi Arabia
<script src="https://sa.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For Qatar
<script src="https://qa.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For Egypt
<script src="https://eg.myfatoorah.com/sessions/v1/session.js"></script>
```

**Create Container Element:**

You need to define a div element with a unique id attribute. The Unified Session will be loaded inside this div after passing the div id to the configuration variable.

```html
<div id="embedded-sessions"></div>
```

**Configure and Initialize:**

Now you need to add the configuration variables. Place the following snippet in a new script tag after loading the JavaScript library, and replace the **sessionId** parameter with the "SessionId" you receive from the `POST /v3/sessions` Endpoint.

```javascript
var config = {
  sessionId: "KWT-68814db6-7510-4005-ada9-408aae9f373c", // Add the "SessionId" you received from POST Session Endpoint.
  callback: payment, // MyFatoorah triggers this callback after the customer completes payment, either by submitting card details, finishing Google Pay / Apple Pay / STC Pay, or choosing any hosted payment method.
  containerId: "embedded-sessions", //Enter the div id you created in previous step.
  shouldHandlePaymentUrl: true // Default true
};

myfatoorah.init(config);

function payment(response) {
  console.log(JSON.stringify(response));
}
```

##### **Step 3: Handle Payment Response**

The callback function response depends on `shouldHandlePaymentUrl` setting in the config object and the payment method chosen.

* **If shouldHandlePaymentUrl: true (default):**

  * Embedded Methods (Card/Apple/Google/STC Pay):\
    MyFatoorah handles the OTP page. After that, you will receive `paymentCompleted: true` (This does not indicate the payment status, only that the payment flow was completed successfully). To retrieve the actual payment status, use the `paymentData` received in the callback function along with the `EncryptionKey` obtained from the POST session endpoint to decrypt the response and determine the final payment status.

    ```json Callback Response
    {
    	"isSuccess": true,
    	"sessionId": "KWT-82b22745-c777-436a-a30f-dc89c06f333b",
    	"paymentCompleted": true,
    	"paymentData": "GA7UWznbZznsnkm5CILO0CRoYF0z1Fbnk+5Yp7Hb53+dRsAMrZE3SlDAveN/g6hnv3zhQKWMPxbn73ul7//Db2uMiA2QOTeXMJi+vkjxX4uynpF4mcY/EOU29ZExpM/FfCQe1p34NJjaPEJ8APvqM0s1NPWLV0Qg8eNnrEMKbTAFUeYuAWmaGa5gfdDdVHvcksCw4SQTs1MCCTxM2sdOeBKGz/FD75nmsphrhl8bTrDWzVMx6JvalOTONg9rg2gVsXDwnpwT6CTbR9mlkW1UHsxecOXcGPYBfVh8LhuaLCQmKw6msPEoaVvJs02cQALTAOOZdQOEMRxk3hgUrdlTWwgzRs/Ud+XYK4ZRH4r6VRSM3S8oVbZs4WVuPZAKcRqqRaxT/quiEZmrnZbGE+XWpAl8qCE0idN9xYGGtmBQXTxQQUThLGtNirSUlLNUT7sTVwb5qHjhw9zSQTc01nO8unBvZFrBfavymsg7dLmBTeF1rGxsCkp4s72yXgPCsLS+XlVCQdOz1d1kEAx8ZkGAvAh7tPS+bA1d3YghRco4JOpYD+sTpGJM72ngsKGst+PiyQbvxoMuKMnN7fCU8ZAPEy9CRWi3DqIM2mHKrF6qo9kLaxD1LqD1FJUZ4oK6ueh5bFWFGatbg4QW5hYiZ/hkd4UBNfSl0RygU6u/UpnwAeIBzN9i+cSTa+QDylKmxnDUK+MVI8PzT7dq9tkNYBpuK/az/bG1DgqD5UTGDINBIHlWtgZ0c0OzkR8Xto7MA/VpAdi0BSAEr5lmpjZ/LDSr1j3rxjvdRi92Uockp9iFGZiYi8JabYikZhb/AlXa1fhmwcvXuGQmZqbFwNI8zyqX5O7JEuD7UDZ3GQiXk4gQl1Vn/1T6eiH0dTfQ+EMczdFvnpCGzdt2j6jJ17AEjVV814UjY4spI6Hii1uKTQEuY8ftXXqpyHD5CROhBCmsIrOrR1HXrP58y7D8FmeMOy4IhtUmO/fDj3JjSJ2oDvMRZsTzaYMUyhzfOZXZ9vl8hNY61ObgMh9EXsoSX5zRjsRfabi+0GG//+Efm/5ahmBKFj+RuGtBrpttB75M1h4LsWnBa7iBeq3cqOSzvuqlJZPeWaN1MTrdeH+lyWciMzlRIEXuIWWGqRpEpjOKKfpdpx6LPdYYWtGJwgctKvrftEqxEyWPabBAvCNq/lzxZJpn5WBjhaZn0h8h+yazcWBwzYDoxktvm9nY8cPvk9RQvcp3CPvYfSviSFnMtkd/LAVsi2pTOUik0Xg3vvLNWGTVc6USjWIo+sZoFI4Q3GaVW8d3L0twI1N0l7YMVzK/xL0rx2fcFMLuXMcY6kGC3npTQSH603CjO+6adqnhoJ7Ah3eFidBvqsItaTUR9QRc396Sa52uRp7KPiRqkTqy03jFEQ611X4KCa+ZRbw1oZmpLcUdsOmfl7ETbmcPBzKF0aXlbBArCddv0YPQK1OrxsquV/9mZI5AAVDDXJShaYrlQ9+CKgGuLDp6hGX1mYEjWEc+yR5iAm/T7nrfHKZz/DuFD3V+ok0Upni1HQbHJ3AQj6qhwQ==",
    	"paymentType": "CARD",
    	"redirectionUrl": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/Result?paymentId=07076389179322432673"
    }
    ```

    Here is a sample code snippet showing how to decrypt `paymentData` and get the actual payment status:

    ```python
    from Crypto.Cipher import AES
    from Crypto.Util.Padding import unpad
    import base64

    def decrypt_with_key(encrypted_text, encryption_key):
        try:
            # Decode base64 encrypted text and key
            encrypted_text_bytes = base64.b64decode(encrypted_text)
            pass_bytes = encryption_key.encode('utf-8')

            # Prepare 16-byte key (128-bit)
            encryption_key_bytes = bytearray(16)
            len_pass = min(len(pass_bytes), len(encryption_key_bytes))
            encryption_key_bytes[:len_pass] = pass_bytes[:len_pass]

            # Initialize AES cipher in CBC mode
            cipher = AES.new(bytes(encryption_key_bytes), AES.MODE_CBC, iv=bytes(encryption_key_bytes))

            # Decrypt and unpad
            decrypted_bytes = cipher.decrypt(encrypted_text_bytes)
            decrypted_text = unpad(decrypted_bytes, AES.block_size)

            return decrypted_text.decode('utf-8')
        except Exception:
            return "FAILED"

    # Input values
    encryption_key = "m2QTkGqSxy24hpRGmoJ50vk6cfz4VJITNxGe5/uO+Qo="

    encrypted_text = "AycwHsyRddykBFQ1Nojm+E/tQLa0eUiHDBTBjC+QUz5zWANVbCtQV4aOZZd4FBcUGv3fG3wJHTs4C0G1JoiZS49c4KCY1ePKWLUV14ZWcn2VCUU5KtWkJI3WTVxUBJgXKK1gzTVH7KMVSLCgnjaO5cBm+GNMBohn929BUi8BSJX8LvSJDwq8a1mFmdCgbLuZ7ozqCcJchlPRj4/Pfx1ihjyzhrOyY2q9610t+QjdXKokON31EFBRQK6LIbaYBi9fghMpbxrrDIBAbCdnKhN+sWuWD6/AD7KSbYS+A0OzsbZ/EDvCrk7H+IkvxsrgnaiFPwb7pa83ZfXJUsLDG7fB3NK9m3KqaG0ScVQZ8hMVKS3NQi+kHoMZmuNEhvCM8XNWQE82AR92MrDpCMhMLShxwpU+d+/ArF2hF/tYY2ersrgpWrNW6jUawcSGoiJtAyHAIt4EV5S1Qp6UrwcEbC4SSuF3cD0aLSP1GZ/KSBFJLsahydHyJkMp/xn+hr7ICmMq0LqdtdR0Uh9sToQT+3UCIHofjhiwVXVUkKCJqDqKR3rYOYARXmnkA0GIwPhRctQgTYYvmcubUTssc3faV5vrm8sAyowaHDCt3kLvhJjQek/qO0BapqY3SSJBN6xc1uDwND0VyPe2/TaGrzjqPrT7bTVsLmiX/f1oatV8GsW3oRzfNK9nfgAQfWcWjGPZ5Yqmz3ayLUtOZotIIar3240Obli/nUVeK3ev0h7pWK6rEKLP4S/8Dhz4T4fqv8z8CnJ2VWIkTU9Cx1xtJLnsRwXZJnO8tlfiq3/XrcS8SBaNQ8YdLuXsw083HVXCR8addSqUAYu8rn7dQ3o3CnkecxfPGhj+aZ+n/cRHUBuM47Svn4JMFdZF3Fz3iTlWqU7JFNNYAHaXlnLx+/klCgvwIxi2/puoLGSkIpR9hXG1ARQpNwGB5JGRlo4yG5jhlp5FeWDFFKn0O9p2JlRCyhZH9H16WjKFXyt2nlgNDr2J/WrCQ0lzfVYBxIa3r6gRMOcWVw0rTyaFHWTmHr6VhNYFn2gX9Ii4riu5bRPYyjGe6DkGVLuvIRPFaYaRe/UhyaosAv8IWSQDuERE7qijW1a/bLxqmSXf4kwytNbfDjJpf4Pb3srC2m6TfiOS0BcX6VAALH0q97D005Eb4ksc9Oy+HVofpA+cNKrKftrySBsUqnLhT532E9AnShAKBGoIG//+CTWmVvF4aCzMdkaHBAkkaGYl/ugy2bdEq69J+ZqcRW35cRqJdKHdmQhq31oVdUqgcYVTrNCc4rnZc2bgVvjAw0gNCcQHJXRFkz7kzBOW+FoYBnWteUvy9Emf+FIw3gNgjKRaMRbeUB/QHVkccLn/KaTigxlmDBzS6H1gM7YMhXdaPlxw9TCGbt07DmSuupIk9S3GzaZHC4RSpU2gjMcXiyJMY/iZSG88BJvayWWEnFiF72dj9NvNl9zGEJlBv6vpVzVjFO45hVHaACe/M+j8tTfJg4+duW+k1CXDk5gbjW+TBGde0uBc/9wxkw77CFB65g/dc4449HpkoEKmI3DCOtqChcfIuFXNJI6zfH4aXYfyM7OoRDRFOe2MvBZqy8i7W8unAZ6xXXrOKBZ1gIdKOOqjWQ=="
    # Decrypt and print result
    result = decrypt_with_key(encrypted_text, encryption_key)
    print(result)
    ```
    ```csharp
    using System;
    using System.Security.Cryptography;
    using System.Text;
     
    namespace Decrypt_New_API
    {
        internal class Program
        {
            static void Main(string[] args)
            {
                var result = DecryptWithKey(
    "AycwHsyRddykBFQ1Nojm+E/tQLa0eUiHDBTBjC+QUz5zWANVbCtQV4aOZZd4FBcUGv3fG3wJHTs4C0G1JoiZS49c4KCY1ePKWLUV14ZWcn2VCUU5KtWkJI3WTVxUBJgXKK1gzTVH7KMVSLCgnjaO5cBm+GNMBohn929BUi8BSJX8LvSJDwq8a1mFmdCgbLuZ7ozqCcJchlPRj4/Pfx1ihjyzhrOyY2q9610t+QjdXKokON31EFBRQK6LIbaYBi9fghMpbxrrDIBAbCdnKhN+sWuWD6/AD7KSbYS+A0OzsbZ/EDvCrk7H+IkvxsrgnaiFPwb7pa83ZfXJUsLDG7fB3NK9m3KqaG0ScVQZ8hMVKS3NQi+kHoMZmuNEhvCM8XNWQE82AR92MrDpCMhMLShxwpU+d+/ArF2hF/tYY2ersrgpWrNW6jUawcSGoiJtAyHAIt4EV5S1Qp6UrwcEbC4SSuF3cD0aLSP1GZ/KSBFJLsahydHyJkMp/xn+hr7ICmMq0LqdtdR0Uh9sToQT+3UCIHofjhiwVXVUkKCJqDqKR3rYOYARXmnkA0GIwPhRctQgTYYvmcubUTssc3faV5vrm8sAyowaHDCt3kLvhJjQek/qO0BapqY3SSJBN6xc1uDwND0VyPe2/TaGrzjqPrT7bTVsLmiX/f1oatV8GsW3oRzfNK9nfgAQfWcWjGPZ5Yqmz3ayLUtOZotIIar3240Obli/nUVeK3ev0h7pWK6rEKLP4S/8Dhz4T4fqv8z8CnJ2VWIkTU9Cx1xtJLnsRwXZJnO8tlfiq3/XrcS8SBaNQ8YdLuXsw083HVXCR8addSqUAYu8rn7dQ3o3CnkecxfPGhj+aZ+n/cRHUBuM47Svn4JMFdZF3Fz3iTlWqU7JFNNYAHaXlnLx+/klCgvwIxi2/puoLGSkIpR9hXG1ARQpNwGB5JGRlo4yG5jhlp5FeWDFFKn0O9p2JlRCyhZH9H16WjKFXyt2nlgNDr2J/WrCQ0lzfVYBxIa3r6gRMOcWVw0rTyaFHWTmHr6VhNYFn2gX9Ii4riu5bRPYyjGe6DkGVLuvIRPFaYaRe/UhyaosAv8IWSQDuERE7qijW1a/bLxqmSXf4kwytNbfDjJpf4Pb3srC2m6TfiOS0BcX6VAALH0q97D005Eb4ksc9Oy+HVofpA+cNKrKftrySBsUqnLhT532E9AnShAKBGoIG//+CTWmVvF4aCzMdkaHBAkkaGYl/ugy2bdEq69J+ZqcRW35cRqJdKHdmQhq31oVdUqgcYVTrNCc4rnZc2bgVvjAw0gNCcQHJXRFkz7kzBOW+FoYBnWteUvy9Emf+FIw3gNgjKRaMRbeUB/QHVkccLn/KaTigxlmDBzS6H1gM7YMhXdaPlxw9TCGbt07DmSuupIk9S3GzaZHC4RSpU2gjMcXiyJMY/iZSG88BJvayWWEnFiF72dj9NvNl9zGEJlBv6vpVzVjFO45hVHaACe/M+j8tTfJg4+duW+k1CXDk5gbjW+TBGde0uBc/9wxkw77CFB65g/dc4449HpkoEKmI3DCOtqChcfIuFXNJI6zfH4aXYfyM7OoRDRFOe2MvBZqy8i7W8unAZ6xXXrOKBZ1gIdKOOqjWQ==",
                    "m2QTkGqSxy24hpRGmoJ50vk6cfz4VJITNxGe5/uO+Qo="
                );
     
                Console.WriteLine(result);
            }
     
            public static string DecryptWithKey(string EncryptedText, string Encryptionkey)
            {
                try
                {
                    RijndaelManaged objrij = new RijndaelManaged();
                    objrij.Mode = CipherMode.CBC;
                    objrij.Padding = PaddingMode.PKCS7;
     
                    objrij.KeySize = 0x80;
                    objrij.BlockSize = 0x80;
                    byte[] encryptedTextByte = Convert.FromBase64String(EncryptedText);
                    byte[] passBytes = Encoding.UTF8.GetBytes(Encryptionkey);
                    byte[] EncryptionkeyBytes = new byte[0x10];
                    int len = passBytes.Length;
                    if (len > EncryptionkeyBytes.Length)
                    {
                        len = EncryptionkeyBytes.Length;
                    }
                    Array.Copy(passBytes, EncryptionkeyBytes, len);
                    objrij.Key = EncryptionkeyBytes;
                    objrij.IV = EncryptionkeyBytes;
                    byte[] TextByte = objrij.CreateDecryptor().TransformFinalBlock(encryptedTextByte, 0, encryptedTextByte.Length);
                    return Encoding.UTF8.GetString(TextByte);  //it will return readable string 
                }
                catch (Exception)
                {
     
                    return "";
                }
     
            }
        }
    }
    ```
    ```node Node.js
    import { createDecipheriv } from "crypto";

    function decryptWithKey(encryptedText, encryptionKey) {
      try {
        const encryptedTextBytes = Buffer.from(encryptedText, "base64");

        const passBytes = Buffer.from(encryptionKey, "utf-8");

        let encryptionKeyBytes = Buffer.alloc(16);
        passBytes.copy(encryptionKeyBytes, 0, 0, Math.min(passBytes.length, 16));

        const decipher = createDecipheriv(
          "aes-128-cbc",
          encryptionKeyBytes,
          encryptionKeyBytes
        );

        let decrypted = decipher.update(encryptedTextBytes);
        decrypted = Buffer.concat([decrypted, decipher.final()]);

        return decrypted.toString("utf-8");
      } catch (err) {
        return "FAILED";
      }
    }

    const encryptionKey = "m2QTkGqSxy24hpRGmoJ50vk6cfz4VJITNxGe5/uO+Qo=";

    const encryptedText = "AycwHsyRddykBFQ1Nojm+E/tQLa0eUiHDBTBjC+QUz5zWANVbCtQV4aOZZd4FBcUGv3fG3wJHTs4C0G1JoiZS49c4KCY1ePKWLUV14ZWcn2VCUU5KtWkJI3WTVxUBJgXKK1gzTVH7KMVSLCgnjaO5cBm+GNMBohn929BUi8BSJX8LvSJDwq8a1mFmdCgbLuZ7ozqCcJchlPRj4/Pfx1ihjyzhrOyY2q9610t+QjdXKokON31EFBRQK6LIbaYBi9fghMpbxrrDIBAbCdnKhN+sWuWD6/AD7KSbYS+A0OzsbZ/EDvCrk7H+IkvxsrgnaiFPwb7pa83ZfXJUsLDG7fB3NK9m3KqaG0ScVQZ8hMVKS3NQi+kHoMZmuNEhvCM8XNWQE82AR92MrDpCMhMLShxwpU+d+/ArF2hF/tYY2ersrgpWrNW6jUawcSGoiJtAyHAIt4EV5S1Qp6UrwcEbC4SSuF3cD0aLSP1GZ/KSBFJLsahydHyJkMp/xn+hr7ICmMq0LqdtdR0Uh9sToQT+3UCIHofjhiwVXVUkKCJqDqKR3rYOYARXmnkA0GIwPhRctQgTYYvmcubUTssc3faV5vrm8sAyowaHDCt3kLvhJjQek/qO0BapqY3SSJBN6xc1uDwND0VyPe2/TaGrzjqPrT7bTVsLmiX/f1oatV8GsW3oRzfNK9nfgAQfWcWjGPZ5Yqmz3ayLUtOZotIIar3240Obli/nUVeK3ev0h7pWK6rEKLP4S/8Dhz4T4fqv8z8CnJ2VWIkTU9Cx1xtJLnsRwXZJnO8tlfiq3/XrcS8SBaNQ8YdLuXsw083HVXCR8addSqUAYu8rn7dQ3o3CnkecxfPGhj+aZ+n/cRHUBuM47Svn4JMFdZF3Fz3iTlWqU7JFNNYAHaXlnLx+/klCgvwIxi2/puoLGSkIpR9hXG1ARQpNwGB5JGRlo4yG5jhlp5FeWDFFKn0O9p2JlRCyhZH9H16WjKFXyt2nlgNDr2J/WrCQ0lzfVYBxIa3r6gRMOcWVw0rTyaFHWTmHr6VhNYFn2gX9Ii4riu5bRPYyjGe6DkGVLuvIRPFaYaRe/UhyaosAv8IWSQDuERE7qijW1a/bLxqmSXf4kwytNbfDjJpf4Pb3srC2m6TfiOS0BcX6VAALH0q97D005Eb4ksc9Oy+HVofpA+cNKrKftrySBsUqnLhT532E9AnShAKBGoIG//+CTWmVvF4aCzMdkaHBAkkaGYl/ugy2bdEq69J+ZqcRW35cRqJdKHdmQhq31oVdUqgcYVTrNCc4rnZc2bgVvjAw0gNCcQHJXRFkz7kzBOW+FoYBnWteUvy9Emf+FIw3gNgjKRaMRbeUB/QHVkccLn/KaTigxlmDBzS6H1gM7YMhXdaPlxw9TCGbt07DmSuupIk9S3GzaZHC4RSpU2gjMcXiyJMY/iZSG88BJvayWWEnFiF72dj9NvNl9zGEJlBv6vpVzVjFO45hVHaACe/M+j8tTfJg4+duW+k1CXDk5gbjW+TBGde0uBc/9wxkw77CFB65g/dc4449HpkoEKmI3DCOtqChcfIuFXNJI6zfH4aXYfyM7OoRDRFOe2MvBZqy8i7W8unAZ6xXXrOKBZ1gIdKOOqjWQ==";
    const result = decryptWithKey(encryptedText.trim(), encryptionKey);
    console.log(result);

    ```
    ```json Payment Result
    {
    	"Invoice": {
    		"Id": "6389179",
    		"Status": "PAID",
    		"Reference": "2025060888",
    		"CreationDate": "2025-12-24T12:36:57.5330000Z",
    		"ExpirationDate": "2025-12-24T14:35:15.0000000Z",
    		"ExternalIdentifier": null,
    		"UserDefinedField": "",
    		"MetaData": null
    	},
    	"Transaction": {
    		"Id": "103610",
    		"Status": "SUCCESS",
    		"PaymentMethod": "VISA/MASTER",
    		"PaymentId": "07076389179322432673",
    		"ReferenceId": "535812103610",
    		"TrackId": "24-12-2025_3224326",
    		"AuthorizationId": "103610",
    		"TransactionDate": "2025-12-24T12:37:05.8136549Z",
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
    			"NameOnCard": "Hosam",
    			"Number": "512345xxxxxx0008",
    			"Token": "",
    			"PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
    			"ExpiryMonth": "12",
    			"ExpiryYear": "34",
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
    		"ServiceCharge": "0.2",
    		"ServiceChargeVAT": "0.03",
    		"ReceivableAmount": "9.77",
    		"DisplayCurrency": "KWD",
    		"ValueInDisplayCurrency": "10",
    		"PayCurrency": "KWD",
    		"ValueInPayCurrency": "10"
    	},
    	"Suppliers": []
    }
    ```

    ![Complete Payment Mode (Embedded Methods, shouldHandlePaymentUrl = true)](https://files.readme.io/f5e903c9ea0c3bfd3ab6fa5f75a46f3e3d69749d17768ef9e72c8c0cd85b5191-CP_EM_TRUE.png)
  * Hosted Methods (e.g., KNET):\
    MyFatoorah automatically redirects the customer to the payment page. After the payment is completed, use the `paymentId` from the redirection URL to retrieve the payment status.\
    `https://your-website.com/payment-callback?paymentId=07076127942302114071&Id=07076127942302114071`

    ![Complete Payment Mode (Hosted Methods, shouldHandlePaymentUrl = true)](https://files.readme.io/029832feae333f19d570b495fa98a5a0b0e92802b593f7f0a3e65c82e1abf854-CM_HO_TRUE.png)
* **If shouldHandlePaymentUrl: false:**

  * Embedded Methods (Card/Apple/Google/STC Pay):\
    You must handle the OTP page either by displaying it in an iframe or by redirecting the user to it.

    ```json Callback Response
    {
    	"isSuccess": true,
    	"paymentType": "CARD",
    	"sessionId": "KWT-4f88aa92-94d1-433c-8043-2f7768c1a12a",
    	"paymentCompleted": false,
    	"card": {
    		"brand": "Mastercard",
    		"panHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
    		"token": "",
    		"number": "512345xxxxxx0008",
    		"nameOnCard": "Hosam",
    		"expiryYear": "34",
    		"expiryMonth": "12",
    		"issuer": "Test Bank",
    		"issuerCountry": "KWT",
    		"fundingMethod": "credit",
    		"productName": "Mastercard Titanium"
    	},
    	"redirectionUrl": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/MpgsAuthentication?paymentId=07076389193322434173&sessionId=SESSION0002674591675H70711641I9&mfSessionId=4f88aa92-94d1-433c-8043-2f7768c1a12a",
    	"paymentId": "07076389193322434173"
    }
    ```
  * Hosted Methods (e.g., KNET):\
    You receive the `redirectionUrl` in the callback, and you must redirect the user to it to complete the payment.

    ```json Callback Response
    {
    	"isSuccess": true,
    	"paymentType": "HOSTED_PAYMENT",
    	"hostedPaymentName": "BENEFIT",
    	"sessionId": "KWT-ac4ed8bb-0978-4cd5-883c-843ad4b82c65",
    	"paymentCompleted": false,
    	"redirectionUrl": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/Checkout?invoiceKey=050712180638919562-fbd66d4f&paymentGatewayId=116"
    }
    ```

  ![Complete Payment Mode (shouldHandlePaymentUrl = false)](https://files.readme.io/ca62b61e5d1ade1f13c5d3963312065f724654e7f1c46dd360639f2bf621f34e-CP_FALSE.png)

> 📘 OTP Page in an Iframe
>
> To know how to show the OTP page in an iframe, kindly check the following link: <https://docs.myfatoorah.com/docs/otp-page-in-an-iframe/>

#### **Mode 2: Collect Details**

This mode is a two-step payment integration approach that provides flexibility for complex pricing scenarios where you need to collect payment information first before processing the actual payment.

##### **Step 1: Create Payment Session**

Make a server-side API call to create a new payment session with `PaymentMode: “COLLECT_DETAILS”`\
You need to do this for each payment separately. **SessionId** is valid for only one payment.

**Endpoint:** `POST /v3/sessions` ([Create Session](https://docs.myfatoorah.com/reference/create-session))

```json Request Example
{
    "PaymentMode": "COLLECT_DETAILS",
    "Order": {
        "Amount": 10
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "Created Successfully!",
    "ValidationErrors": null,
    "Data": {
        "SessionId": "KWT-7b540311-6fcd-417d-b3a8-d7166cbbf773",
        "SessionExpiry": "2025-09-29T00:13:14.4074096Z",
        "EncryptionKey": "jWdKKGPFYmVykT6lpUIpKfuqQRKYraienXtLqhwGHRI=",
        "OperationType": "PAY",
        "Order": {
            "Amount": 10.0,
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

##### **Step 2: Initialize Client-Side Integration**

**Include JavaScript Library:**

```html
// Test Environment
<script src="https://demo.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For Kuwait, Bahrain, Jordan, and Oman
<script src="https://portal.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For UAE
<script src="https://ae.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For Saudi Arabia
<script src="https://sa.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For Qatar
<script src="https://qa.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For Egypt
<script src="https://eg.myfatoorah.com/sessions/v1/session.js"></script>

```

**Create Container Element:**

You need to define a div element with a unique id attribute. The Unified Session will be loaded inside this div after passing the div id to the configuration variable.

```html
<div id="embedded-sessions"></div>

```

**Configure and Initialize:**

Now you need to add the configuration variables. Place the following snippet in a new script tag after loading the JavaScript library, and replace the **sessionId** parameter with the "SessionId" you receive from the `POST /v3/sessions` Endpoint.

```javascript
var config = {
  sessionId: "KWT-56956bac-0bbe-437f-bf61-e629bd4aed4d", // Add the "SessionId" you received from POST Session Endpoint.
  callback: payment, // MyFatoorah triggers this callback after the customer completes payment, either by submitting card details, finishing Google Pay / Apple Pay / STC Pay, or choosing any hosted payment method.
  containerId: "embedded-sessions", //Enter the div id you created in previous step.
};

myfatoorah.init(config);

function payment(response) {
  console.log(JSON.stringify(response));
}
```

##### **Step 3: Handle Payment Response**

The callback function response depends on the chosen payment method.

```json Embedded Payment Methods
{
	"isSuccess": true,
	"paymentType": "CARD",
	"sessionId": "KWT-56956bac-0bbe-437f-bf61-e629bd4aed4d",
	"paymentCompleted": false,
	"card": {
		"brand": "Mastercard",
		"panHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
		"token": "",
		"number": "512345xxxxxx0008",
		"nameOnCard": "Adel",
		"expiryYear": "34",
		"expiryMonth": "12",
		"issuer": "Test Bank",
		"issuerCountry": "KWT",
		"fundingMethod": "credit",
		"productName": "Mastercard Titanium"
	}
}
```
```json Hosted Payment Methods
{
  "isSuccess": true,
  "paymentType": "HOSTED_PAYMENT",
  "hostedPaymentName": "KNET",
  "sessionId": "KWT-7b540311-6fcd-417d-b3a8-d7166cbbf773",
  "paymentCompleted": false
}
```

##### **Step 4: Process Payment**

**Make a second API call to process the payment using the same`SessionId`**\
and then redirect the customer to the `PaymentURL` to complete the payment process (OTP for cards or hosted payment page).

Endpoint: `POST /v3/payments` ([Create Payment](https://docs.myfatoorah.com/reference/create-payment))

```json Request Example
{
    "SourceOfFund": {
        "SessionId": "KWT-7b540311-6fcd-417d-b3a8-d7166cbbf773"
    },
    "Order": {
        "Amount": 23 // here you can update the amount of payment
    }
}
```
```json Embedded Methods Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": "6522848",
        "PaymentId": "07076522848332706072",
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/MpgsAuthentication?paymentId=07076522848332706072&sessionId=SESSION0002142880473I8690260J63&mfSessionId=282901aa-1fcb-4832-b181-d14021d87e9f",
        "PaymentCompleted": false,
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
            "NameOnCard": "test",
            "IssuerCountry": "KWT",
            "FundingMethod": "credit",
            "ProductName": "Mastercard Titanium",
            "IsValidCard": null,
            "Is3DSVerified": false
        }
    }
}
```
```json Hosted Methods Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": "6522896",
        "PaymentId": null,
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/Checkout?invoiceKey=050754719652289664-ea785323&paymentGatewayId=1121",
        "PaymentCompleted": false,
        "TransactionDetails": null,
        "Card": {
            "Number": "",
            "ExpiryMonth": "",
            "ExpiryYear": "",
            "Brand": "",
            "PanType": "",
            "Issuer": "",
            "PanHash": "",
            "Token": "",
            "NameOnCard": "",
            "IssuerCountry": "",
            "FundingMethod": "",
            "ProductName": "",
            "IsValidCard": null,
            "Is3DSVerified": false
        }
    }
}
```

![Collect Details Mode](https://files.readme.io/5afc2cb6c44893e4539bc79b7835b90a47a17f7580f56d3a9c0296e81e266471-CD_MODE.png)

<br />

> 🚧 Note
>
> The following parameters cannot change between POST /v3/sessions and POST /v3/payments:
>
> * OperationType
> * 3ds model

> 📘 Payment Status
>
> Always use [Webhook ](https://docs.myfatoorah.com/edit/webhook) with the [Get Payment Details](https://docs.myfatoorah.com/reference/get-payment-details) endpoint to confirm the final payment status.

> 📘 Old Integration (V2)
>
> In case you are using the integration for v2, you can find the documentation here:
>
> * [Introduction](https://docs.myfatoorah.com/docs/embedded-payment)
> * [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-integration-steps)
> * [Customizing Embedded Payment](https://docs.myfatoorah.com/docs/unified-session-customization)
> * [Tokenized Embedded Payments](https://docs.myfatoorah.com/docs/tokenized-embedded)
> * [Sample Code](https://docs.myfatoorah.com/docs/unified-sample-code)

> 📘 Native Integration
>
> If you need to integrate **Wallets** using Native Integration, please refer to the following guide: [Native Wallet Integration](https://docs.myfatoorah.com/docs/v3-native-wallets)

## Embedded Payment

*`https://docs.myfatoorah.com/docs/embedded-integration-steps` — updated 2026-02-16*

#### **Introduction**

The Embedded Payment allows you to integrate a seamless payment experience directly on your website, enabling customers to choose from Card payments, Apple Pay, Google Pay, or STC Pay without leaving your site. With a single integration, you can offer a unified checkout interface that blends perfectly with your website’s design, providing a consistent and user-friendly experience. This ensures that customers can easily select their preferred payment method and complete transactions in just a few clicks.

In addition to the flexible payment options, the Embedded Payment takes care of all PCI DSS compliance requirements, so you don't need to worry about certification or security concerns. MyFatoorah handles the heavy lifting, allowing you to focus on delivering a smooth and secure payment process while maintaining full control over the checkout experience.

**MyFatoorah's Embedded Payment** is a JavaScript library that offers a single integration point for handling payments. With this library, customers can enter their card details directly into a form, and MyFatoorah takes care of processing the payment. Additionally, it supports **Apple Pay** and **Google Pay** with just a click, allowing MyFatoorah to smoothly handle the entire payment flow. It also supports **STC Pay** where the customer can enter his Mobile Number and the PIN number directly on your checkout page enabling you to either use MyFatoorah's UI or your fully customized UI.

![Default view of Embedded Payment](https://files.readme.io/7ae97e8ab46176299aef1ca802cfa68fc026b3d694d27c5b7a1fce69cdb32f5a-image.png)

***

#### **How it Works**

##### 1. Call InitiateSession endpoint

After you call the InitiateSession endpoint, you will get a SessionId and the CountryCode.

```json
{
    "IsSuccess": true,
    "Message": "Initiated Successfully!",
    "ValidationErrors": null,
    "Data": {
        "SessionId": "dfc6a3c3-df09-44cb-9c6a-0a6375752da6",
        "CountryCode": "KWT",
        "CustomerTokens": []
    }
}
```

##### 2. Include the Javascript library

Choose the test or the live library according to your working environment.

```html
// Test Environment
<script src="https://demo.myfatoorah.com/payment/v1/session.js"></script>

// Live Environment For Kuwait, Bahrain, Jordan, and Oman
<script src="https://portal.myfatoorah.com/payment/v1/session.js"></script>

// Live Environment For UAE
<script src="https://ae.myfatoorah.com/payment/v1/session.js"></script>

// Live Environment For Saudi Arabia
<script src="https://sa.myfatoorah.com/payment/v1/session.js"></script>

// Live Environment For Qatar
<script src="https://qa.myfatoorah.com/payment/v1/session.js"></script>

// Live Environment For Egypt
<script src="https://eg.myfatoorah.com/payment/v1/session.js"></script>
```

##### 3. Define a div element

You need to define a div element with a unique id attribute. The Unified Session will be loaded inside this div after passing the div id to the configuration variable.

```html
<div id="embedded-payment"></div>
```

> 📘 div elements
>
> If you want to display each method (Card, GooglePay, ApplePay, STCPay) in a separate div, you can create a div element for each method and display the methods in their corresponding div elements by adding it in the configuration of the payment method in Embedded Payment.

##### 4. Configure the Embedded Payment

Now you need to add the configuration variables. Place the following snippet in a new script tag after loading the library in step 1, and replace the **sessionId** parameter with the "SessionId" you receive from the InitiateSession Endpoint.

For the **countryCode** parameter check our list of [ISO Lookups](https://docs.myfatoorah.com/docs/iso-lookups).

```javascript
var config = {
	sessionId: "", //Add the "SessionId" you received from InitiateSession Endpoint.
	countryCode: "", //Add the "CountryCode" you received from InitiateSession Endpoint.
	currencyCode: "", //Here, you add the value of "PaymentCurrencyIso" for the gateways enabled that you received from InitiatePayment endpoint
	amount: "", //This amount will be displayed on GooglePay, ApplePay and STC Pay.
	callback: payment,//MyFatoorah calls the callback function after the customer fills in the card and clicks pay or when the customer finishes the steps with GooglePay, STC Pay and Apple Pay.
	containerId: "embedded-payment",//Enter the div id you created in Step 2
	paymentOptions: ["ApplePay", "GooglePay", "Card", "STCPay"],//Enter the payment methods you want to display, Default value is "Card"
};
myfatoorah.init(config);
```

> 🚧 Apple Pay Prerequisites
>
> To enable Apple Pay, you must verify your domain. For detailed instructions, please refer to our [Apple Pay Domain Verification Guide.](https://docs.myfatoorah.com/docs/apple-pay-domain-verification)

##### 5. Handle the callback function

After the customer completes the payment details entry, MyFatoorah will trigger the CallBack function. MyFatoorah returns to you the SessionId and details about the card used. You should send the SessionId to your backend to call ExecutePayment to complete the payment

```javascript
function payment(response) {
    //Pass session id to your backend here
  if (response.isSuccess) {
    switch (response.paymentType) {
        case "ApplePay":
            console.log("response >> " + JSON.stringify(response));
            break;
        case "GooglePay":
            console.log("response >> " + JSON.stringify(response));
            break;
        case "Card":
            console.log("response >> " + JSON.stringify(response));
            break;
      case "StcPay":
        	console.log("response >> " + JSON.stringify(response));
        	break;
        default:
            console.log("Unknown payment type");
            break;
    }
  }
};
```

##### 6. Call the ExecutePayment Endpoint

Then, you need to send the **SessionId** to your server to process the actual transaction, which should be done in your backend environment using [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint.

> ❗️ Parameters conflict
>
> Do not pass the **PaymentMethodId** parameter in the [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) request and use the **SessionId** parameter instead. As **PaymentMethodId** overwrite **SessionId**.
>
> The "SessionId" you receive from InitiateSession Endpoint can not be used directly in [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) Endpoint.

You will get the PaymentURL in the [Response Model](https://docs.myfatoorah.com/docs/response-model). This payment URL is the **3D Secure** page URL.

For Card and Google Pay, you can either redirect the customer to the PaymentUrl or open it in an iframe.

For Apple Pay, you can either invoke the PaymentUrl in the backend and get the status of the transaction either by the webhook or GetPaymentStatus, or you can open the PaymentUrl and the customer will be redirected to the CallBackUrl/ErrorUrl sent in the request to ExecutePayment.

For STC Pay, open the PaymentUrl to redirect the customer to your CallBackUrl - ErrorUrl based on the status of the transaction.

```json ExecutePayment Request
{
   "SessionId":"36c1bab2-1e21-ec11-bae9-000d3aaca798",
   "InvoiceValue":10,
}
```
```json ExecutePayment Response
{
   "IsSuccess":true,
   "Message":"Invoice Created Successfully!",
   "ValidationErrors":null,
   "Data":{
      "InvoiceId":1040089,
      "IsDirectPayment":false,
      "PaymentURL":"https://demo.MyFatoorah.com/En/KWT/PayInvoice/MpgsAuthentication?paymentId=0706104008982520266&sessionId=SESSION0002087297105E3203998J76",
      "CustomerReference":"",
      "UserDefinedField":null,
      "RecurringId":""
   }
}
```

> 👍 Payment Status
>
> To update your system automatically instead of manually following up with your customers via [your portal account](https://portal.myfatoorah.com/), you can use the [Webhook](https://docs.myfatoorah.com/docs/webhook) feature or/and set call GetPaymentStatus after the customer is redirected to the CallBackUrl/ErrorUrl page. We recommend using the **webhook** and **GetPaymentStatus** together as a best practice.

***

#### Open the OTP page in an iframe

This feature enables you to open the OTP page in an iframe without external redirection. This will enable you to keep your customers on your website during the whole payment process.

#### **How it Works**

##### 1. Create the iframe on your website.

The design and creation of the iframe are done from your side. You will open the PaymentUrl in the iframe so that the customer can enter their OTP.

> 📘 Handling the iframe
>
> You will handle fully the iframe from your side. You need to create the iframe and open the PaymentUrl for the OTP in it. You should also handle the cancelation if the customer wants to cancel the payment after the OTP is open and so on.

##### 2. Get the redirection URL after the customer enters his OTP

After the customer completes the 3DS challenge, you will need to use an **event listener** to get the redirection URL. The event listener needs to be added to the page (In a javascript section) on which you will open the iframe.

The name of the sender of the message will be exactly **"MF-3DSecure"**

The redirection URL will be the **CallBackUrl** /**ErrorUrl** that you sent to ExecutePayment in the request appended to it the **PaymentId**.

```coffeescript Redirection URL Format
https://{{Your_CallBackURL}}/?paymentId={{PaymentId}}&Id={{ID}}
```

You will have multiple options to choose from for the next action, whether you want to open the redirection URL in the iframe or close the iFrame and show the result page directly on your website.

```php Retrieve the redirection URL
//The event listener is used to listen to the Redirection URL after the  customer completes the 3DS Challenge
window.addEventListener("message", function (event) { 
        if (!event.data) return;
        try {
            //The redirection URL is returned in the message
            var message = JSON.parse(event.data);
          
            //Proceed only with the following steps if the sender is exactly "MF-3DSecure"
            if (message.sender == "MF-3DSecure") {
              var url = message.url;
            //Here, you need to handle the next action, and here are some suggestions:
            //Redirect the full page to the received URL.
            //Load the received URL in your iframe.
            //Close the iframe and display the result on the same page. You can use AJAX requests to your server with the payment ID to confirm the transaction status (invoke GetPaymentStatus) and display the result accordingly.
            }
        } catch (error) {
            return;
        }
    }, false);	
```

***

> 📘 Old embedded integration
>
> If you have integrated with MyFatoorah using the old implementation where each payment method requires a different integration, this flow will still be available. Please find the documentation here:
>
> * [Card View](https://docs.myfatoorah.com/docs/card-view-form)
> * [Apple Pay](https://docs.myfatoorah.com/docs/apple-pay)
> * [Google Pay™](https://docs.myfatoorah.com/docs/google-pay-embedded)
> * [STC Pay](https://docs.myfatoorah.com/docs/stcpay)
> * [Sample code](https://docs.myfatoorah.com/docs/embedded-payment-sample-code)

## Customizing Embedded Payment

*`https://docs.myfatoorah.com/docs/customizing-embedded-payment` — updated 2026-02-16*

#### Introduction

You can customize the look and feel of the Embedded Payment and utilize different features available for different payment methods. This gives you full control over how each payment method appears and behaves inside your embedded checkout flow.

#### General Customizations

<br />

| Parameter              | Type                                | Description                                                                                              |
| :--------------------- | :---------------------------------- | :------------------------------------------------------------------------------------------------------- |
| subscribedEvents       | Array of Strings of the event names | The names of the events to which you want to subscribe                                                   |
| eventListener          | Function                            | A function that describes the action you want to take once an event is received to handle it accordingly |
| shouldHandlePaymentUrl | Bool                                | Controls the payment flow for embedded payment methods                                                   |

##### Event Types

| Event Name                | Description                                                                                                                                                                                                     | Example                                                                                                                                                                                                                                       |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| VIEW\_READY               | This event is received once the embedded view is ready to be rendered on the page. You can hide it to display your loader until you get the event that the view is ready.                                       | `{"name": "VIEW_READY", "id": 1, "paymentMethodName": "ALL", "data": {}}`                                                                                                                                                                     |
| CARD\_IDENTIFIED          | This event is triggered once the user enters the first 10 digits of his card and the event includes the card information of the customer. **This needs to be enabled for your account by the account manager.** | `{"name": "CARD_IDENTIFIED", "id": 4, "paymentMethodName": "CARD", "data": {"cardBin": "512345", "cardBrand": "Mastercard", "issuer": "Test Bank", "issuerCountry": "KWT", "fundingMethod": "credit", "productName": "Mastercard Titanium"}}` |
| PAYMENT\_STARTED          | This event is triggered once the customer submits his card information or selects a specific payment method to make the payment.                                                                                | `{"name": "PAYMENT_STARTED", "id": 2, "paymentMethodName": "CARD", "data": {}}`                                                                                                                                                               |
| PAYMENT\_COMPLETED        | This event is triggered when the customer completes the payment flow using the card. It will get triggered only in the case of shouldHandlePaymentUrl set to true.                                              | `{"name": "PAYMENT_COMPLETED", "id": 3, "paymentMethodName": "CARD", "data": {}}`                                                                                                                                                             |
| SESSION\_STARTED          | This event is triggered when the user opens Apple Pay payment sheet after clicking on the button.                                                                                                               | `{"name": "SESSION_STARTED", "id":5, "paymentMethodName": "APPLE_PAY", "data": {}}`                                                                                                                                                           |
| SESSION\_CANCELED         | This event is triggered when the user closes Apple Pay payment sheet.                                                                                                                                           | `{"name": "SESSION_CANCELED", "id":6, "paymentMethodName": "APPLE_PAY", "data": {}}`                                                                                                                                                          |
| 3DS\_CHALLENGE\_INITIATED | This event is triggered when the OTP page is displayed to the user. It will get triggered only in the case of shouldHandlePaymentUrl set to true.                                                               | `{"name": "3DS_CHALLENGE_INITIATED", "id": 7, "paymentMethodName": "CARD", "data": {}}`                                                                                                                                                       |
| OTP\_REQUESTED            | This event will be triggered only in STC Pay when the customer receives the OTP.                                                                                                                                | `{"name": "OTP_REQUESTED", "id": 8, "paymentMethodName": "STC_PAY", "data": {"mobileNumber": "0548220713"}}`                                                                                                                                  |

#### Customizations per Payment Method

You can customize each payment method individually, which will not apply these changes to the rest of the payment methods. You can customize each payment method using the same flow clarified in the sample code below.

```javascript Config
var config = {
    sessionId: sessionId,
    callback: payment,
    containerId: "unified-session",
 		settings: {
        loader: { //This hides the loader that appears after the user submits his card information
            display: 'none'
        },
        card: {
            // Add your customization for card here
        },
        applePay: {
            // Add your customization for Apple Pay here
        },
        googlePay: {
            // Add your customization for Google Pay here
        },
        stcPay: {
            // Add your customization for STC Pay here
        }
    }
};
```

***

#### Card Customizations

| Parameter                | Type    | Description                                                                                                                                         |
| :----------------------- | :------ | :-------------------------------------------------------------------------------------------------------------------------------------------------- |
| language                 | String  | This specifies the language in which Card text is displayed. This will override the one in the general config when the session created. *(English)* |
| style                    | Object  | You can manage the look and feel of the card from this object.                                                                                      |
| style.hideCardIcons      | Boolean | You can manage if you want to display the available card brands in the Card form or not. *(False)*                                                  |
| style.showCardholderName | Boolean | Enables or disables the Cardholder Name input field in the card payment form. *(True)*                                                              |
| style.cardHeight         | String  | The height of the card form when the fields for card entry are displayed                                                                            |
| style.tokenHeight        | String  | The height of the card form when the saved cards are displayed                                                                                      |
| style.input              | Object  | This customizes the text entered by the customer, the form borders, and the placeholders in the card form.                                          |
| style.text               | Object  | This customizes the text written on the card form for managing saving cards.                                                                        |
| style.label              | Object  | This customizes the labels that could be added over the fields of the card form.                                                                    |
| style.error              | Object  | This customizes the look of the fields when there is an error in the input of the customer.                                                         |
| style.button             | Object  | You can manage the button on which the customer clicks on after he fills in the card information. Please check below for more information           |
| style.separator          | Object  | This enables you to customize the separator displayed on the Embedded Payment before the Card form. Please check below for more information.        |

##### style.button:

Customize the look and feel of the button. There are two scenarios that you can rely on to enable your customer to click on a button to complete the payment after filling in the card information:

1. **Using MyFatoorah's default behavior:**
   In this case, MyFatoorah manages all the functionalities of the button and returns to you the payment data in the callback function.

```javascript
button: {
    useCustomButton: false,
    textContent: "Pay",
    fontSize: "16px",
    fontFamily: "Times",
    color: "white",
    backgroundColor: "black",
    height: "30px",
    borderRadius: "8px",
    width: "70%",
    margin: "0 auto",
    cursor: "pointer"
},
```

2. **Using your button with its functionalities**\
   In this case, you will be creating the button on your side and will manage its functionalities fully. It is mandatory to call the function **myfatoorah.submitCardPayment()** when the customer clicks on the payment button.

```javascript style.button
//Create the button on which the customer will click after entering the card information
//Define the function to call after the customer clicks on the button
<button onclick="customSubmit()"
        style="display: block; margin: 0 auto; width: 400px; height: 30px; cursor: pointer; background-color: #008CBA; border: none; color: white; font-size: 16px; border-radius: 8px;">
    Pay Now
</button>

// =================================
// Don't use MyFatoorah button
var button = {
    useCustomButton: true,
};

// =================================
// Define the function customSubmit to handle the payment process
function customSubmit() {
    // Here you can add the steps you want to do when the button is clicked.
    myfatoorah.submitCardPayment(); // This function is mandatory
};
```

##### myfatoorah.submitCardPayment()

This function can accept an object as an argument. The argument enables you to control the payment flow. The object can currently have two properties, currency and skipTokenSave.

```javascript
var options = {
  currency: "USD",
  skipTokenSave: true
}

myfatoorah.submitCardPayment(options);
```

##### Validate card inputs

This function enables you to validate the input of the customer in the card form before calling MyFatoorah function to submit the card information. This also enables you to let your customers know which input field is incorrect by a customized error message on your end.

```javascript
function customSubmit() {
    myfatoorah.validateCardInputs()
        .then(result => {
            console.log("success" + JSON.stringify(result)); // "Data fetched successfully!"
            myfatoorah.submitCardPayment(); //It is mandatory to call this function
        })
        .catch(error => {
            console.log("failed" + JSON.stringify(error)); // "Error fetching data."
        });
}
```

##### style.separator

From this object, you can customize the display of the separator before the Card to make it fit your website.

```javascript Separator
separator: {
    useCustomSeparator: false,
    textContent: "Pay with Card",
    fontSize: "14px",
    color: "#4daee0",
    fontFamily: "sans-serif",
    textSpacing: "5px",
    lineStyle: "dashed",
    lineColor: "black",
    lineThickness: "2px"
}
```

##### Card Sample Code

Below is a sample code clarifying the customizations of the card:

```javascript card customizations
var config = {
    sessionId: sessionId,
    callback: payment,
  	containerId: "unified-session",
  	subscribedEvents: ['VIEW_READY', "CARD_IDENTIFIED", 'PAYMENT_STARTED', 'PAYMENT_COMPLETED', 'SESSION_STARTED', 'SESSION_CANCELED', '3DS_CHALLENGE_INITIATED', 'OTP_REQUESTED'],
		eventListener: eventHandler,
		shouldHandlePaymentUrl: true,
    settings: {
        card: {
						language: "ar",
            style: {
                showCardholderName: true,
        				hideCardIcons: true,
                cardHeight: "200px",
                tokenHeight: "230px",
                input: {
                    color: "black",
                    fontSize: "15px",
                    fontFamily: "courier",
                    inputHeight: "32px",
                    inputMargin: "3px",
                    borderColor: "black",
                    backgroundColor: "green",
                    borderWidth: "1px",
                    borderRadius: "30px",
                    outerRadius: "10px",
                    //boxShadow: "0 0 10px 5px purple, 0 0 15px 10px lightblue",
                  placeHolder: {
												color: "#DCEBFF",
                        holderName: "Name On Card",
                        cardNumber: "Number",
                        expiryDate: "MM/YY",
                        securityCode: "CVV",
                    }
                },
                text: {
                    saveCard: "Save card info for future payments",
                    addCard: "Use another Card!",
                    deleteAlert: {
                        title: "Delete",
                        message: "Are you sure?",
                        confirm: "YES",
                        cancel: "NO"
                    }
                },
                label: {
                    display: true,
                    color: "black",
                    fontSize: "13px",
                    fontWeight: "bold",
                    fontFamily: "Times",
                    text: {
                        holderName: "Card Holder Name",
                        cardNumber: "Card Number",
                        expiryDate: "Expiry Date",
                        securityCode: "Security Code",
                    },
                },
                error: {
                    borderColor: "red",
                    borderRadius: "8px",
                    //boxShadow: "0 0 10px 5px purple, 0 0 15px 10px lightblue",
                },
                button: {
                    textContent: "Pay",
                    fontSize: "16px",
                    fontFamily: "Times",
                    color: "white",
                    backgroundColor: "#4daee0",
                    height: "30px",
                    borderRadius: "8px",
                    width: "70%",
                    margin: "0 auto",
                    cursor: "pointer"
                },
                separator: {
                    useCustomSeparator: false,
                    textContent: "Enter your card",
                    fontSize: "20px",
                    color: "#4daee0",
                    fontFamily: "sans-serif",
                    textSpacing: "2px",
                    lineStyle: "dashed",
                    lineColor: "black",
                    lineThickness: "3px"
                }
            }
        }
    }
};
```

***

#### Apple Pay Customizations

| **Parameter**                 | **Type** | **Description**                                                                                                                                                                                                             |
| ----------------------------- | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| requiredBillingContactFields  | Array    | This will force the customer to enter the fields that you pass in the array in his Apple Pay wallet to be able to proceed with the payment. The information that he enters is then returned to you in the callback function |
| requiredShippingContactFields | Array    | This will force the customer to enter the fields that you pass in the array in his Apple Pay wallet to be able to proceed with the payment. The information that he enters is then returned to you in the callback function |
| language                      | String   | This specifies the language in which Apple Pay text is displayed. This will override the one in the general config when the session created.                                                                                |
| style                         | Object   | You can manage the look and feel of the Apple Pay button from here.                                                                                                                                                         |

##### requiredShippingContactFields

If you're using this field, your customer cannot pay via Apple Pay unless he has entered his Shipping Information. You can retrieve the shipping information that the customer has in his Apple Pay wallet from the shippingContact object in the response of the callback function.

```javascript Config
requiredShippingContactFields: ["postalAddress", "name", "phone", "email"],//You can remove what you don't need
```
```javascript shippingContact
"shippingContact": {
        "addressLines": ["ElIkhlas", "13"],
        "administrativeArea": "",
        "country": "Saudi Arabia",
        "countryCode": "SA",
        "emailAddress": "test@gmail.com",
        "familyName": "MyFatoorah",
        "givenName": "Tech",
        "locality": "Haram",
        "phoneNumber": "00201111111111",
        "phoneticFamilyName": "",
        "phoneticGivenName": "",
        "postalCode": "33651",
        "subAdministrativeArea": "",
        "subLocality": "Mariotia"
    }
```

##### requiredBillingContactFields

If you're using this field, your customer cannot pay via Apple Pay unless he has entered his Billing Information. You can retrieve the billing information that the customer has in his Apple Pay wallet from the billingContact object in the response to the callback function

```javascript Config
requiredBillingContactFields: ["postalAddress", "name", "phone"],//You can remove what you don't need
```
```javascript billingContact
"billingContact": {
        "addressLines": ["ElIkhlas", "13"],
        "administrativeArea": "",
        "country": "Saudi Arabia",
        "countryCode": "SA",
        "familyName": "MyFatoorah",
        "givenName": "Tech",
        "locality": "Riyadh",
        "phoneticFamilyName": "",
        "phoneticGivenName": "",
        "postalCode": "33651",
        "subAdministrativeArea": "",
        "subLocality": "As Sulimaniyah"
    }
```

##### style

This enables you to customize the display of the Apple Pay button.

```javascript Config
style: {
    frameHeight: "40px",
    frameWidth: "100%",
    button: {
        height: "40px",
        type: "checkout", //["plain", "buy", "pay", "checkout", "continue", "book", "donate", "subscribe", "reload", "add", "topup", "order", "rent", "support", "contribute", "setup", "tip"]
        borderRadius: "20px",
    }
},
```

##### Apple Pay Sample Code

Below is a sample code clarifying the customizations of Apple Pay

```javascript Apple Pay Customization
var config = {
    sessionId: sessionId,
    callback: payment,
  	containerId: "unified-session",
		shouldHandlePaymentUrl: true,
    settings: {
        applePay: {
            //language: "ar",
            style: {
                frameHeight: "40px",
                frameWidth: "100%",
                button: {
                    height: "40px",
                    type: "buy", //["plain", "buy", "pay", "checkout", "continue", "book", "donate", "subscribe", "reload", "add", "topup", "order", "rent", "support", "contribute", "setup", "tip"]
                    borderRadius: "20px",
                }
            },
            requiredShippingContactFields: ["postalAddress", "name", "phone", "email"],
            requiredBillingContactFields: ["postalAddress", "name", "phone"],
        },
    }
};
```

***

#### Google Pay Customizations

| **Parameter** | **Type** | **Description**                                                                                                                               |
| :------------ | :------- | :-------------------------------------------------------------------------------------------------------------------------------------------- |
| language      | String   | This specifies the language in which Google Pay text is displayed. This will override the one in the general config when the session created. |
| style         | Object   | You can manage the look and feel of the Google Pay button from here.                                                                          |

##### **style**

This enables you to customize the display of the Google Pay button

```javascript style
style: {
    frameHeight: "55px",
    frameWidth: "100%",
    button: {
        height: "40px",
        type: "pay", // Accepted texts: ["book", "buy", "checkout", "donate", "order", "pay", "plain", "subscribe"]
        borderRadius: "8px",
        color: "black" //["white", "black"]
    }
}
```

##### **Google Pay Sample Code**

Below is a sample code clarifying the customizations of Google Pay

```javascript Google Pay Customization
var config = {
  sessionId: "KWT-e33b7416-8e3c-4d6e-a967-28c09d33e888", 
  callback: payment, 
  containerId: "unified-session",
  shouldHandlePaymentUrl: true, 
  settings: {
    googlePay: {
      //language: "ar",
      //callback: paymentGP,
      style: {
        frameHeight: "55px",
        frameWidth: "100%",
        button: {
          height: "40px",
          type: "pay", // Accepted texts: ["book", "buy", "checkout", "donate", "order", "pay", "plain", "subscribe"]
          borderRadius: "8px",
          color: "black",
        },
      },
    },
  },
};
```

***

#### STC Pay Customizations

| **Parameter** | **Type** | **Description**                                                                                                                     |
| :------------ | :------- | :---------------------------------------------------------------------------------------------------------------------------------- |
| mobileNumber  | string   | You can add here a mobile number to be displayed by default and the customer can change it if he wants to use a different currency. |
| style         | object   | This enables you to customize the look and feel for STC Pay                                                                         |

##### **mobileNumber:**

The value of the mobile that you add here will be auto-filled in STC Pay by default. The customer can change or use the same mobile number that you have filled in here.

##### **style**

You can use these parameters to customize the view of STC Pay.

```javascript style
style: {
    frameHeight: "40px",
    frameWidth: "100%",
    input: {
        color: "#582490",
        fontSize: "14px",
        fontFamily: "'roboto', sans-serif",
        inputHeight: "40px",
        borderColor: "black",
        borderWidth: "2px",
        borderRadius: "9px",
        placeHolder: {
            mobileNumber: "05________",
            otpValue: "Enter OTP!"
        }
    },
    button: {
        borderRadius: "9px",
        height: "40px"
    }
}
```

##### **STC Pay Sample Code**

Below is a sample code clarifying the customizations of STC Pay

```javascript STC Pay Customization
var config = {
  sessionId: sessionId,
  callback: payment, 
  containerId: "unified-session",
  shouldHandlePaymentUrl: true,
  settings: {
    stcPay: {
      mobileNumber: "0548220713",
      // language: "en",
      style: {
        frameHeight: "40px",
        frameWidth: "100%",
        input: {
          color: "#582490",
          fontSize: "14px",
          fontFamily: "'roboto', sans-serif",
          inputHeight: "40px",
          borderColor: "black",
          borderWidth: "2px",
          borderRadius: "9px",
          placeHolder: {
            mobileNumber: "05________",
            otpValue: "Enter OTP!",
          }
        },
        button: {
          borderRadius: "9px",
          height: "40px"
        }
      }
    }
  }
};
```

<br />

## Customizing Embedded Payment

*`https://docs.myfatoorah.com/docs/unified-session-customization` — updated 2026-02-16*

#### Introduction

You can customize the look and feel of the Embedded Payment and utilize different features available for different payment methods.

#### General Customizations

The customizations shown here will affect all the payment methods displayed. They are added to the **config** variable as a parameter and its value.

| Parameter | Type | Description |
|---|---|---|
| paymentOptions | Array | Here you pass the required payment methods that you want to be displayed on the Embedded Payment. The default value for the paymentOption is **Card** only. Possible values are_["ApplePay", "GooglePay", "STCPay", "Card"]_ The display of the payment methods is made in the same order in which you enter to the paymentOptions parameter. |
| supportedNetworks | Array | Only the specified card brands will be accepted as a source of payment. The default value is **all**the card brands Possible values are _["visa", "masterCard", "mada", "amex"]_ |
| language | String | This specifies the language in which the methods will be displayed. The default language is **English**. Possible values are **"en"** and **"ar"** |

```javascript config Sample Code
var config = {
	sessionId: sessionId,
	countryCode: countryCode,
	currencyCode: currencyCode,
	amount: amount,
	callback: payment,
	containerId: "unified-session",
	paymentOptions: ["GooglePay", "ApplePay", "STCPay", "Card"], //"GooglePay", "ApplePay", "Card", "STCPay"
	supportedNetworks: ["visa", "masterCard", "mada", "amex"], //"visa", "masterCard", "mada", "amex"
	language: "en",
}
```

![](https://files.readme.io/3e1d47e924db98beb7068eef6c1ca72fd95e4ea1df91ffad37825796ae64c6a0-image.png)

#### Customizations per Payment Method

You can customize each payment method alone in specific which will not apply these changes to the rest of the payment methods. Customizations made to the payment methods override the general customizations. You can customize each payment method using the same flow clarified in the sample code below.

```javascript Config
var config = {
    sessionId: sessionId,
    countryCode: countryCode,
    currencyCode: currencyCode,
    amount: amount,
    callback: payment,
    containerId: "unified-session",
    paymentOptions: ["GooglePay", "ApplePay", "STCPay", "Card"]
    supportedNetworks: ["visa"], //["visa", "masterCard", "mada", "amex"]
    language: "ar",
    settings: {
        card: {
            // Add your customization for card here
        },
        applePay: {
            // Add your customization for Apple Pay here
        },
        googlePay: {
            // Add your customization for Google Pay here
        },
        stcPay: {
            // Add your customization for STC Pay here
        }
    }
};

```

> 📘 Payment Method Customization
>
> If you made any customization for a specific payment method that is different from the general customization, the one for the specific payment method overrides the general configuration for the customized payment method.

***

##### Card Customizations

| Parameter              | Type     | Description                                                                                                                                                                                                                                                    |
| :--------------------- | :------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| onCardBinChanged       | Function | MyFatoorah triggers this function when the customer enters his BIN number in the card form. The BIN number is returned in the function. You can use this for further business decisions. For example, apply a discount for BINs that belong to a specific bank |
| style                  | Object   | You can manage the look and feel of the card from this object.                                                                                                                                                                                                 |
| style.hideNetworkIcons | Boolean  | You can manage if you want to display the available card brands in the Card form or not.                                                                                                                                                                       |
| style.cardHeight       | String   | The height of the card form when the fields for card entry are displayed                                                                                                                                                                                       |
| style.tokenHeight      | String   | The height of the card form when the saved cards are displayed                                                                                                                                                                                                 |
| style.input            | Object   | This customizes the text entered by the customer, the form borders, and the placeholders in the card form.                                                                                                                                                     |
| style.text             | Object   | This customizes the text written on the card form for managing saving cards.                                                                                                                                                                                   |
| style.label            | Object   | This customizes the labels that could be added over the fields of the card form.                                                                                                                                                                               |
| style.error            | Object   | This customizes the look of the fields when there is an error in the input of the customer.                                                                                                                                                                    |
| style.button           | Object   | You can manage the button on which the customer clicks on after he fills in the card information. Please check below for more information                                                                                                                      |
| style.separator        | Object   | This enables you to customize the separator displayed on the Embedded Payment before the Card form. Please check below for more information.                                                                                                                   |

###### style.button:

There are three scenarios that you can rely on to enable your customer to click on a button to complete the payment after he filled in his card information:

1. **Using MyFatoorah's default behavior**:

In this case, MyFatoorah manages all the functionalities of the button and returns to you the SessionId in the callback function to call ExecutePayment.

```javascript style.button
button: {
    useCustomButton: false,
    textContent: "Pay",
    fontSize: "16px",
    fontFamily: "Times",
    color: "white",
    backgroundColor: "black",
    height: "30px",
    borderRadius: "8px",
    width: "70%",
    margin: "0 auto",
    cursor: "pointer"
},
```

2. **Using MyFatoorah's button with customized functionalities**

In this case, you will be using the MyFatoorah payment button. However, you can handle the functionality of the button. In the function definition, you have to call the function **myfatoorah.submitCardPayment()**. You can add other functionalities when the button is clicked.

```javascript style.button
//This is the style.button object
button: {
    useCustomButton: false,
    onButtonClicked: submit,//This is the function that will trigger when the button is clicked.
    textContent: "Pay",
    fontSize: "16px",
    fontFamily: "Times",
    color: "white",
    backgroundColor: "#cc3366",
    height: "30px",
    borderRadius: "8px",
    width: "70%",
    margin: "0 auto",
    cursor: "pointer"
},
// =================================
// Define the function submit
function submit() {
    // Here you can add the steps you want to do when the button is clicked.
    myfatoorah.submitCardPayment(); // This function is mandatory
};
```

3. **Using your button with its functionalities**

In this case, you will be creating the button on your side and will manage its functionalities fully. It is mandatory to call the function **myfatoorah.submitCardPayment()** when the customer clicks on the payment button.

```javascript style.button
//Create the button on which the customer will click after entering the card information
//Define the function to call after the customer clicks on the button
<button onclick="customSubmit()"
        style="display: block; margin: 0 auto; width: 400px; height: 30px; cursor: pointer; background-color: #008CBA; border: none; color: white; font-size: 16px; border-radius: 8px;">
    Pay Now
</button>

// =================================
// Don't use MyFatoorah button
var button = {
    useCustomButton: true,
};

// =================================
// Define the function customSubmit to handle the payment process
function customSubmit() {
    // Here you can add the steps you want to do when the button is clicked.
    myfatoorah.submitCardPayment(); // This function is mandatory
};

```

###### style.separator

From this object, you can customize the display of the separator before the Card to make it fit your website.

```javascript Separator
separator: {
    useCustomSeparator: false,
    textContent: "Pay with Card",
    fontSize: "14px",
    color: "#4daee0",
    fontFamily: "sans-serif",
    textSpacing: "5px",
    lineStyle: "dashed",
    lineColor: "black",
    lineThickness: "2px"
}

```

###### Card Sample Code

Below is a sample code clarifying the customizations of the card:

```javascript card customizations
var config = {
    sessionId: sessionId,
    countryCode: countryCode,
    currencyCode: currencyCode,
    amount: amount,
    callback: payment,
    containerId: "unified-session",
    paymentOptions: ["ApplePay", "GooglePay", "Card"], //["GooglePay", "ApplePay", "Card"]
    supportedNetworks: ["visa"], //["visa", "masterCard", "mada", "amex"]
    language: "en",
    settings: {
        card: {
            onCardBinChanged: handleCardBinChanged,
            style: {
                hideNetworkIcons: false,
                cardHeight: "200px",
                tokenHeight: "230px",
                input: {
                    color: "black",
                    fontSize: "15px",
                    fontFamily: "courier",
                    inputHeight: "32px",
                    inputMargin: "3px",
                    borderColor: "black",
                    backgroundColor: "green",
                    borderWidth: "1px",
                    borderRadius: "30px",
                    outerRadius: "10px",
                    //boxShadow: "0 0 10px 5px purple, 0 0 15px 10px lightblue",
                    placeHolder: {
                        holderName: "Name On Card",
                        cardNumber: "Number",
                        expiryDate: "MM/YY",
                        securityCode: "CVV",
                    }
                },
                text: {
                    saveCard: "Save card info for future payments",
                    addCard: "Use another Card!",
                    deleteAlert: {
                        title: "Delete",
                        message: "Are you sure?",
                        confirm: "YES",
                        cancel: "NO"
                    }
                },
                label: {
                    display: true,
                    color: "black",
                    fontSize: "13px",
                    fontWeight: "bold",
                    fontFamily: "Times",
                    text: {
                        holderName: "Card Holder Name",
                        cardNumber: "Card Number",
                        expiryDate: "Expiry Date",
                        securityCode: "Security Code",
                    },
                },
                error: {
                    borderColor: "red",
                    borderRadius: "8px",
                    //boxShadow: "0 0 10px 5px purple, 0 0 15px 10px lightblue",
                },
                button: {
                    useCustomButton: false,
                    //onButtonClicked: submit,
                    textContent: "Pay",
                    fontSize: "16px",
                    fontFamily: "Times",
                    color: "white",
                    backgroundColor: "#4daee0",
                    height: "30px",
                    borderRadius: "8px",
                    width: "70%",
                    margin: "0 auto",
                    cursor: "pointer"
                },
                separator: {
                    useCustomSeparator: false,
                    textContent: "Enter your card",
                    fontSize: "20px",
                    color: "#4daee0",
                    fontFamily: "sans-serif",
                    textSpacing: "2px",
                    lineStyle: "dashed",
                    lineColor: "black",
                    lineThickness: "3px"
                }
            }
        }
    }
};

```

***

##### Apple Pay Customizations:

| Parameter                     | Type     | Description                                                                                                                                                                                                                                                                                                                                      |
| :---------------------------- | :------- | :----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| supportedNetworks             | Array    | The card brands that will be accepted for Apple Pay. This will override the one in the general config.                                                                                                                                                                                                                                           |
| containerId                   | String   | The id of the div element in which Apple Pay will be displayed alone                                                                                                                                                                                                                                                                             |
| callback                      | Function | This function is triggered once the customer completes the steps on the Apple Payment sheet. MyFatoorah returns to you the SessionId with some details about the card used for payment.                                                                                                                                                          |
| useCustomButton               | Boolean  | If set to true, you will have to add your button for Apple Pay. Please make sure to follow [Apple Pay guidelines](https://developer.apple.com/design/human-interface-guidelines/apple-pay#Button-styles) for the design of the Apple Pay button. You must call this function when using your custom button: **myfatoorah.initApplePayPayment()** |
| sessionStarted                | Function | This method is called once the customer clicks on the Apple Pay button.                                                                                                                                                                                                                                                                          |
| requiredBillingContactFields  | Array    | This will force the customer to enter the fields that you pass in the array in his Apple Pay wallet to be able to proceed with the payment. The information that he enters is then returned to you in the callback function                                                                                                                      |
| sessionCanceled               | Function | This method is called once the customer cancels the payment via Apple Pay.                                                                                                                                                                                                                                                                       |
| requiredShippingContactFields | Array    | This will force the customer to enter the fields that you pass in the array in his Apple Pay wallet to be able to proceed with the payment. The information that he enters is then returned to you in the callback function                                                                                                                      |
| language                      | String   | This specifies the language in which Apple Pay text is displayed. This will override the one in the general config.                                                                                                                                                                                                                              |
| style                         | Object   | You can manage the look and feel of the Apple Pay button from here.                                                                                                                                                                                                                                                                              |

###### callback:

You can set a different function to handle the callback function for Apple Pay alone by adding it here. This function will only get triggered if the payment is coming through Apple Pay since it is added to the applePay object. If you're using the callback in the general config and the callback inside of the settings.applePay, the callback function in applePay will be triggered if the payment is done through Apple Pay.

###### supportedNetworks:

This enables you to specify the card brands from which you want to accept payments through Apple Pay. This overrides the ones specified in the general config.

###### requiredShippingContactFields:

If you're using this field, your customer cannot pay via Apple Pay unless he has entered his Shipping Information. You can retrieve the shipping information that the customer has in his Apple Pay wallet from the **shippingContact** object in the response of the callback function.

```json config
requiredShippingContactFields: ["postalAddress", "name", "phone", "email"],//You can remove what you don't need
```
```json shippingContact
"shippingContact": {
        "addressLines": ["ElIkhlas", "13"],
        "administrativeArea": "",
        "country": "Saudi Arabia",
        "countryCode": "SA",
        "emailAddress": "test@gmail.com",
        "familyName": "MyFatoorah",
        "givenName": "Tech",
        "locality": "Haram",
        "phoneNumber": "00201111111111",
        "phoneticFamilyName": "",
        "phoneticGivenName": "",
        "postalCode": "33651",
        "subAdministrativeArea": "",
        "subLocality": "Mariotia"
    }
```

###### requiredBillingContactFields

If you're using this field, your customer cannot pay via Apple Pay unless he has entered his Billing Information. You can retrieve the billing information that the customer has in his Apple Pay wallet from the **billingContact** object in the response to the callback function

```javascript config
requiredBillingContactFields: ["postalAddress", "name", "phone"],//You can remove what you don't need
```
```json billingContact
"billingContact": {
        "addressLines": ["ElIkhlas", "13"],
        "administrativeArea": "",
        "country": "Saudi Arabia",
        "countryCode": "SA",
        "familyName": "MyFatoorah",
        "givenName": "Tech",
        "locality": "Riyadh",
        "phoneticFamilyName": "",
        "phoneticGivenName": "",
        "postalCode": "33651",
        "subAdministrativeArea": "",
        "subLocality": "As Sulimaniyah"
    }
```

###### Style

This enables you to customize the display of the Apple Pay button.

```javascript config
style: {
    frameHeight: "40px",
    frameWidth: "100%",
    button: {
        height: "40px",
        type: "checkout", //["plain", "buy", "pay", "checkout", "continue", "book", "donate", "subscribe", "reload", "add", "topup", "order", "rent", "support", "contribute", "setup", "tip"]
        borderRadius: "20px",
    }
},
```

###### Apple Pay Sample Code

Below is a sample code clarifying the customizations of Apple Pay

```javascript Apple Pay Customization
var config = {
    sessionId: sessionId,
    countryCode: countryCode,
    currencyCode: currencyCode,
    amount: amount,
    callback: payment,
    containerId: "unified-session",
    paymentOptions: ["GooglePay", "ApplePay", "STCPay", "Card"]
    supportedNetworks: ["visa", "masterCard"], //["visa", "masterCard", "mada", "amex"]
    language: "en",
    settings: {
        applePay: {
            //supportedNetworks: [],
            //containerId: "",
            //callback: ,
            //language: "ar",
            style: {
                frameHeight: "40px",
                frameWidth: "100%",
                button: {
                    height: "40px",
                    type: "buy", //["plain", "buy", "pay", "checkout", "continue", "book", "donate", "subscribe", "reload", "add", "topup", "order", "rent", "support", "contribute", "setup", "tip"]
                    borderRadius: "20px",
                }
            },
            useCustomButton: false,
            sessionStarted: sessionStarted,
            sessionCanceled: sessionCanceled,
            requiredShippingContactFields: ["postalAddress", "name", "phone", "email"],
            requiredBillingContactFields: ["postalAddress", "name", "phone"],
        },
    }
};
		function sessionCanceled() {
			console.log("Failed")
		};

		function sessionStarted() {
			console.log("Start")
		};
```

***

##### Google Pay Customizations

| Parameter         | Type     | Description                                                                                                                                                                              |
| :---------------- | :------- | :--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| supportedNetworks | Array    | The card brands that will be accepted for Google Pay. This will override the one in the general config.                                                                                  |
| containerId       | String   | The id of the div element in which Google Pay will be displayed alone                                                                                                                    |
| callback          | Function | This function is triggered once the customer completes the steps on the Google Payment sheet. MyFatoorah returns to you the SessionId with some details about the card used for payment. |
| style             | Object   | You can manage the look and feel of the Google Pay button from here.                                                                                                                     |

###### callback:

You can set a different function to handle the callback function for Google Pay alone by adding it here, similar to Apple Pay's separate callback. This function will only get triggered if the payment comes through Google Pay since it is added to the googlePay object. If you're using the callback in the general config and the callback inside of the settings.googlePay, the callback function in googlePay will be triggered if the payment is made through Google Pay.

###### supportedNetworks:

This enables you to specify the card brands from which you want to accept payments through Google Pay. This overrides the ones specified in the general config.

###### style

This enables you to customize the display of the Google Pay button

```javascript style
style: {
    frameHeight: "55px",
    frameWidth: "100%",
    button: {
        height: "40px",
        type: "pay", // Accepted texts: ["book", "buy", "checkout", "donate", "order", "pay", "plain", "subscribe"]
        borderRadius: "8px",
        color: "black", //["white", "black"]
    }
}

```

###### Google Pay Sample Code

Below is a sample code clarifying the customizations of Google Pay

```javascript Google Pay Customization
var config = {
    sessionId: sessionId,
    countryCode: countryCode,
    currencyCode: currencyCode,
    amount: amount,
    callback: payment,
    containerId: "unified-session",
    paymentOptions: ["GooglePay", "ApplePay", "STCPay", "Card"]
    supportedNetworks: ["visa", "masterCard"], //["visa", "masterCard", "mada", "amex"]
    language: "en",
    settings: {
        googlePay: {
            //supportedNetworks: [],
            //containerId: "",
            //callback: ,
            //language: "ar",
            style: {
                frameHeight: "55px",
                frameWidth: "100%",
                button: {
                    height: "40px",
                    type: "pay", // Accepted texts: ["book", "buy", "checkout", "donate", "order", "pay", "plain", "subscribe"]
                    borderRadius: "8px",
                    color: "white",
                }
            },
        },
    }
};

function sessionStarted() {
    console.log("Start");
}

```

***

##### STC Pay Customizations

| Parameter       | Type     | Description                                                                                                                         |
| :-------------- | :------- | :---------------------------------------------------------------------------------------------------------------------------------- |
| callback        | Function | The card brands that will be accepted for Google Pay. This will override the one in the general config.                             |
| containerId     | string   | The id of the div element in which Google Pay will be displayed alone                                                               |
| mobileNumber    | string   | You can add here a mobile number to be displayed by default and the customer can change it if he wants to use a different currency. |
| useCustomButton | bool     | You can manage the look and feel of the Google Pay button from here.                                                                |
| sessionStarted  | Function | This function is used in case you set useCustomButton set to true.                                                                  |
| style           | object   | This enables you to customize the look and feel for STC Pay                                                                         |

> 🚧 STC Pay Amount & Currency
>
> Use STC Pay if your account's base currency is in SAR. Make sure that the amount that you add in the configuration for STC Pay and amount you send in ExecutePayment is the same and both in SAR currency.

###### mobileNumber:

The value of the mobile that you add here will be auto-filled in STC Pay by default. The customer can change or use the same mobile number that you have filled in here.

If you are setting **useCustomButton** to **true**, the **mobileNumber field becomes mandatory**.

###### useCustomButton:

If you set this value to true, you must add the **mobileNumber** to the configuration variable.\
MyFatoorah will then trigger the **sessionStarted** function for STC Pay. At this point, the customer will receive the PIN and in the response, MyFatoorah will let you know the expiry duration of the OTP in seconds.

```json sessionStarted
{
    "isSuccess": true,
    "sessionId": "dea3ecd0-66e9-40e9-80da-3a97babc6dc5",
    "mobileNumber": "0557877988",
    "expiryDuration": 120
}
```

You will collect the PIN from the customer and send it to MyFatoorah using myfatoorah.submitStcOtp();

After that, MyFatoorah will trigger the callback function for you to take the next step and call ExecutePayment.

```javascript Submit OTP
myfatoorah.submitStcOtp("1234");
```
```json callback after submitting OTP
{
    "isSuccess": true,
    "paymentType": "StcPay",
    "sessionId": "dea3ecd0-66e9-40e9-80da-3a97babc6dc5",
    "mobileNumber": "0557877988"
}
```

###### Style:

You can use theese parameters to customize the view of STC Pay.

```javascript Style
style: {
    frameHeight: "40px",
    frameWidth: "100%",
    language: "en",
    input: {
        color: "#582490",
        fontSize: "14px",
        fontFamily: "'roboto', sans-serif",
        inputHeight: "40px",
        borderColor: "black",
        borderWidth: "2px",
        borderRadius: "9px",
        placeHolder: {
            mobileNumber: "05________",
            otpValue: "Enter OTP!"
        }
    },
    button: {
        borderRadius: "9px",
        height: "40px"
    }
}
```

###### STC Pay Sample Code

Below is a sample code clarifying the customizations of STC Pay

```javascript STC Pay
var config = {
    sessionId: sessionId,
    countryCode: countryCode,
    currencyCode: currencyCode,
    amount: amount,
    callback: payment,
    containerId: "unified-session",
    paymentOptions: ["GooglePay", "ApplePay", "STCPay", "Card"],
    supportedNetworks: ["visa"], // ["visa", "masterCard", "mada", "amex"]
    language: "ar",
    settings: {
        stcPay: {
            // containerId: "stc-pay",
            // callback: paymentSTC,
            mobileNumber: "0557877988",
            useCustomButton: false,
            sessionStarted: stcSessionStarted,
            style: {
                frameHeight: "40px",
                frameWidth: "100%",
                language: "en",
                input: {
                    color: "#582490",
                    fontSize: "14px",
                    fontFamily: "'roboto', sans-serif",
                    inputHeight: "40px",
                    borderColor: "black",
                    borderWidth: "2px",
                    borderRadius: "9px",
                    placeHolder: {
                        mobileNumber: "05________",
                        otpValue: "Enter OTP!"
                    }
                },
                button: {
                    borderRadius: "9px",
                    height: "40px"
                }
            }
        }
    }
};

function stcSessionStarted(data) {
    console.log("STC Session Started\n" + JSON.stringify(data));
}

```

## Tokenized Embedded Payments

*`https://docs.myfatoorah.com/docs/v3-token-payments` — updated 2026-02-16*

#### **Introduction**

Explore an extra layer of functionality with our optional feature designed to securely store your customers' cards. With this feature, you receive a unique token for each card, providing an added layer of convenience for your users. For each customer, MyFatoorah effortlessly retrieves the masked card number along with the corresponding token for all saved cards. Now, you can seamlessly showcase saved cards separate from MyFatoorah's Embedded Integration. Enhance the user experience by displaying masked card numbers while ensuring secure payment transactions through the associated card tokens.

> ❗️ Availability
>
> In order to enable this feature, you need to contact your [account manager](https://www.myfatoorah.com/en/contact-us/).

> 👍 Tokenized Embedded Payments
>
> You do not need to be PCI DSS certified to use this feature.

#### How it works:

##### How to save the token?

You need to call `POST /v3/sessions` Endpoint to get the **SessionId** to be used in your configuration to display the Card View. You need to do this for each payment separately. **SessionId** is valid for only one payment.\
In the request to initiate a session to save the token for the card that the customer will enter, you need to pass the value of SaveToken to be **true** and a unique Reference for each customer. If you send the value of SaveToken as false or don't send it at all, it will work as in the normal flow of the embedded payment without tokenizing the card.

**After the customer submits his card information, we will return to you the token for the card in the callback function.**

**Endpoint:** `POST /v3/sessions`

```json Request Example
{
    "PaymentMode": "COMPLETE_PAYMENT",
    "Order": {
        "Amount": 10
    },
    "SaveCardOptions": {
        "SaveToken": true,
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

> 📘 Customer Reference
>
> It is mandatory to specify a Customer.Reference when the value of SaveToken is true. Each customer should have a unique Reference.

The next steps for the first payments will be the same as in [Embedded Integration](https://docs.myfatoorah.com/docs/embedded-payment-v3).

***

#### How to make payments using the token?

##### **1- Call** POST /v3/sessions **using the same Customer Reference**

Initiate a session by calling the POST /v3/sessions API with the same **Customer Reference and make sure to set RetrieveSavedTokens: "true"**. The response will include a unique SessionId and tokenized cards associated with that Customer **Reference**. Each card's details, such as the **Masked Card**, **Token**, and **Card Brand**, will be provided.

```json Request Example
{
    "PaymentMode": "COMPLETE_PAYMENT",
    "Order": {
        "Amount": 10
    },
    "SaveCardOptions": {
        "SaveToken": true,
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
        "SessionId": "KWT-76a635c8-066e-4d41-ae3e-5d530c55784b",
        "SessionExpiry": "2025-10-26T16:50:49.3473139Z",
        "EncryptionKey": "w1jxizxjHRk5fs0toaqELL++N2P1JBn+lFY5r2FOgD0=",
        "OperationType": "PAY",
        "Order": {
            "Amount": 10.0,
            "Currency": "KWD",
            "ExternalIdentifier": null
        },
        "Customer": {
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
}
```

##### **2- Display the Card View and the Tokenized Cards**

Utilize the masked card numbers from the response to present the saved cards to your customers. Enable them to choose from the list of saved cards. Once a customer selects a card, prompt them to enter the CVV for the chosen card.

##### **3- Collect Customer's CVV Using MyFatoorah Component**

This step involves utilizing the MyFatoorah JavaScript component to securely collect the customer's CVV. By leveraging this component, the CVV is managed directly by MyFatoorah, ensuring secure handling and eliminating the need for you application to be PCI DSS certified for this process.

###### **Steps:**

###### **1- Include the JavaScript library:**

Choose the test or the live library according to your working environment. Please note that this is the same JavaScript for the embedded payment.

```javascript
// Test Environment
<script src="https://demo.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For Kuwait, Bahrain, Jordan, and Oman
<script src="https://portal.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For UAE
<script src="https://ae.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For Saudi Arabia
<script src="https://sa.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For Qatar
<script src="https://qa.myfatoorah.com/sessions/v1/session.js"></script>

// Live Environment For Egypt
<script src="https://eg.myfatoorah.com/sessions/v1/session.js"></script>
```

###### **2- Define a div element**

You need to define a div element with a unique id attribute. The CVV component will be displayed inside this div after passing the div id to the configuration variable.

```html
<div id="cvv-component"></div>
```

###### **3- Configure the CVV Component**

Create a configuration variable for the CVV component and include in it the sessionId, countryCode, containerId, and **callback**

```javascript
var configCvvView = {
	sessionId: "KWT-ca809438-7de1-463a-b5ad-7b273c845252",
  containerId: "cvv-component",
	shouldHandlePaymentUrl: true,
  callback: proceedCvv,
  style: {
    direction: "ltr",
    cardHeight: 90,
    error: {
      borderColor: "red",
      borderRadius: "0px",
      boxShadow: "1px",
    },
    input: {
      color: "black",
      fontSize: "13px",
      fontFamily: "Sofia",
      inputHeight: "30px",
      inputMargin: "-1px",
      borderColor: "c7c7c7",
      borderWidth: "1px",
      borderRadius: "0px",
      boxShadow: "",
      //placeHolder: "security code(cvv)"
    },
    label: {
      display: true,
      color: "green",
      fontSize: "25px",
      fontWeight: "normal",
      fontFamily: "Sofia",
      text: "cvv",
    },
  },
};

myfatoorah.initCvv(configCvvView);

function proceedCvv(response) {
  console.log("proceedCvv");
  if (response.isSuccess) {
    console.log("response >> " + JSON.stringify(response));
    alert(JSON.stringify(response));
  } else {
    alert(JSON.stringify(response));
    console.log(response);
  }
}
```

This will make the CVV component load so that the customer can enter their CVV in the view.

###### **4- Handle the submit button**

After that, you need to handle your submit button to call **myfatoorah.submitCvv()** using the token of the card that the customer chose to pay with.

```html
<button onclick="submitCvv()">Submit CVV</button>

<script>
  function submitCvv() {
    myfatoorah.submitCvv("TKN-0fb06aac-634d-418a-bf78-953202b67b53")
    //Pass the token of the card the customer chose to pay with.
  }
</script>
```

##### 4- Handle Payment Response

After the customer enters their CVV and the payment is submitted using myfatoorah.submitCvv(), you will receive a callback response in the callback function. The content of this response depends on the PaymentMode value you passed when initiating the session via the `POST /v3/sessions` API.

###### Mode 1: PaymentMode = COMPLETE\_PAYMENT

In this mode, MyFatoorah handles the OTP page. After that, you will receive `paymentCompleted: true` (This does not indicate the payment status, only that the payment flow was completed successfully). To retrieve the actual payment status, use the `paymentData` received in the callback function along with the `EncryptionKey` obtained from the POST session endpoint to decrypt the response and determine the final payment status.

```json Callback Response
{
  "isSuccess": true,
  "sessionId": "KWT-ce7d4888-4797-4123-9c30-0ed41d73a531",
  "paymentCompleted": true,
  "paymentData": "RKMzF47Jm2+LTETqjVXH7a6sWw06UfZ68106udI7VVr8LJ4G0bUfw+JvVU4uhFmn2BEAmtEy9azgXii4p9xcItRLhoV0KsrXLXNKl6GoRkn4jQm8/9K7eSwcIKdrV+Emw2nrjvro8tWzAOXbBLPXfy9PfmPRmFf7zRLPt1WZxO8wsRiaw8K3JmAeqlm6JRmC02m4FCZxmafCK4AzCri4XUxBYma7Uru4flX41KKwa9qZOkiigw5jY+gDxzBB4NxeLTdKhKaNbK7eTufy34ksRQboDACOGV/5GBshSnPTZf+q1tz3qv5wDFJwkly0s6lsq5bMi2MgNdSkjb1d+uiABV3wT0jhvnVQypXorV+JbYPBuBo2h/10KRUfQ2j4uHQ5vhdHVCpv4bGUCUY0VRAU8n7NkjTadPrbmsmI+qDQNCY82XhfB6GCyxC+4QIjDXgTls+Sqffp1PTDmKSOiJ/SKpIO6zGc90ZWjAy4AgneXOU7NaH4FbUuh7Z2y9wuKjfBEktobkOKB8OoCRe9RzXr4wjhrEoTWKfzSw5plO4jQIpkUyijBLgZhexL1quSPN6yw85fo3ZS9nvOiEzeyrQXxcs/FUgjx6tELedKQSFQsBIRkuyWtpqaJKgGWVJltoYeHl6lhwjKygyzpNGsk0HOd3OOvFyiW9k5yQjuskHXia/+kTwel5z41To/JNAz02bdu2sfVaEJMO87z0Scklcv/L+W0YlWXFdF6ZGv5hfLHRIEDxR5mmyZagtimMXL5iFYeKzajiCUcUjg3HIRFnZrNfuUC2FODHKTR+n3iT7NOv5iddzJXn11qMMuD1UQGpceO/rSyPClUvLE6NiNrxcXKxry4IQ4L+MlP2XDZzye+h4CrKEwmjWVFEMySOldgR5+/M8Wx39ELiapkKBUdrifrOZpLwD4D1FugK5nDzm01eOAhaJY/DawXlvWSPaLpgudY3L9Zltx4VdYUbXcqOSGgNoNDW+d8KVU2nrIpUf6bnmBHyyczkp8JjFxIE1PQi0SfwS41Pca9CAJO7UQkwh07frGPJaFWXs6T2Kjyc6YUGfWmaC+giu9Rz5DOC3S/AYf027KU2bIO4ySZs9hDxfiegCBMrv82uUZ3PulywqVfSpr1Wb3c95HHEcAdoyJlA5k0VxqEpoRJMiGTatRxjoqAG9vEYqINeuxm66kinA9SEC/dDK2knLE/SXa34UGT5esljPnTj0J7pnmizy4QfgBVQiJgJ9Is63lguIFdJSL4VnTepd7QDscIMvDHls9dToa3ehULT8wVaqgu7HF31OUoecdA7LsRl27XA0hVGzjZNHc39aGswXpjjycLjhC4YlAfTeONN8aBfWafxQXzT5QS+sNs7T1Op8FYmh9a0fg6Ac8mPzrFW0wfS8/nGFP/Yr/mXuFxkGVD/4vLUDZvfhFV6uEubmSPt6hs2qrngASGUXSY36wlW7YZWmvabpvKtVsaCk6iAs2Dl19JrAnN+IApNJt4P9k89VZEwjSUT9lRt7yWW5qBhfsTZdFhaHLOo342YoxT9MVxf+uCp2iPqBKvA==",
  "paymentType": "CARD",
  "redirectionUrl": "https://your-website.com/payment-callback?paymentId=07076252374312536672&Id=07076252374312536672"
}
```

**Next Step: Decrypt Payment Data**\
To get the **final payment result**, decrypt the `paymentData` using the `EncryptionKey` from the session creation response.\
You can use the sample decryption code provided here: [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-payment-v3#step-3-handle-payment-response)

###### **Case 2: PaymentMode = COLLECT\_DETAILS**

In this mode, the first callback returns the card details, but the payment is **not yet completed**.\
You will need to make another API call to **process the payment** using the same `SessionId`.

```json Callback Response
{
    "isSuccess": true,
    "paymentType": "CVV",
    "sessionId": "KWT-f2589d21-11fb-4b91-8b7a-4d3866837645",
    "paymentCompleted": false,
    "card": {
        "brand": "Mastercard",
        "panHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
        "token": "TKN-aa7e19a1-eba6-4896-91a2-ccd35159c4b3",
        "number": "512345xxxxxx0008",
        "nameOnCard": "das",
        "expiryYear": "36",
        "expiryMonth": "12",
        "issuer": "Test Bank",
        "issuerCountry": "KWT",
        "fundingMethod": "credit",
        "productName": "Mastercard Titanium"
    }
}
```

**Next Step: Process the Payment**\
Use the `SessionId` from the response to make a payment request through the following API:\
**Endpoint:** `POST /v3/payments`

```json Request Example
{
    "SourceOfFund": {
        "SessionId": "KWT-f2589d21-11fb-4b91-8b7a-4d3866837645"
    },
    "Order": {
        "Amount": 23
    }
}
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": "6522912",
        "PaymentId": "07076522912332712772",
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/MFAuthentication?gateway=CS&paymentId=07076522912332712772&gatewayKey=eyJraWQiOiIwOHlGQUY1V2xNdzlUTEZ3ZGp1WE5xTXZoTWZ3OVgzVyIsImFsZyI6IlJTMjU2In0.eyJpc3MiOiJGbGV4LzA4IiwiZXhwIjoxNzcxMjU4NzI4LCJ0eXBlIjoiYXBpLTAuMS4wIiwiaWF0IjoxNzcxMjU3ODI4LCJqdGkiOiIxRTNYWlpOV1U5Rks2NFlTMFZaOVpLUDlWRjhTTk1VUjlQSExBNjdIU1pLMFY1OFFBU0pCNjk5MzQzNjhCREMwIiwiY29udGVudCI6eyJwYXltZW50SW5mb3JtYXRpb24iOnsiY2FyZCI6eyJleHBpcmF0aW9uWWVhciI6eyJ2YWx1ZSI6IjIwMzkifSwibnVtYmVyIjp7Im1hc2tlZFZhbHVlIjoiWFhYWFhYWFhYWFhYMDAwOCIsImJpbiI6IjUxMjM0NSJ9LCJzZWN1cml0eUNvZGUiOnt9LCJleHBpcmF0aW9uTW9udGgiOnsidmFsdWUiOiIwMSJ9LCJ0eXBlIjp7InZhbHVlIjoiMDAyIn19fX19.ZYoiCU_U-KBWV-IsKrwQrJx-X8pk5xOHnB3o9apK0x_3jgOEEadWghbRfhF-TMGaOjU_6wFjRYDtffdZeNjNzgyhTj8Z9SH_PZ9V061yYnuzHo22k0CgWm4gTrGCkJtUDHgnzPdE8D6pfwZVI4r595vhJV8_sYOmunlGwU8a-y432wZheBdbofNLjILeDH0av9gAKHAQPLGhq1cnFoc7JKdFQMPq5SsfM0JrYxQMFfVs8X9qZ70TmbhQHRmK-Ba7A6RPVESOf463nZneF1ek4lyHAwLybdKohroBaCYCGr9ZIInLWP21g99JM07lndd_i8WJUR2gHUoayPB9xlxm7w&mfSessionId=f2589d21-11fb-4b91-8b7a-4d3866837645",
        "PaymentCompleted": false,
        "TransactionDetails": null,
        "Card": {
            "Number": "512345xxxxxx0008",
            "ExpiryMonth": "01",
            "ExpiryYear": "39",
            "Brand": "Mastercard",
            "PanType": "Card",
            "Issuer": "Test Bank",
            "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
            "Token": "TKN-aa7e19a1-eba6-4896-91a2-ccd35159c4b3",
            "NameOnCard": "Test Card",
            "IssuerCountry": "KWT",
            "FundingMethod": "credit",
            "ProductName": "Mastercard Titanium",
            "IsValidCard": null,
            "Is3DSVerified": true
        }
    }
}
```

Finally, redirect the customer to the `PaymentURL` to complete the payment process.

> 📘 Note
>
> If you are using COLLECT\_DETAILS mode, the card information will be saved against the Customer.Reference in the POST /v3/sessions API.

## Tokenized Embedded Payments

*`https://docs.myfatoorah.com/docs/tokenized-embedded` — updated 2026-02-16*

### **Introduction**

Explore an extra layer of functionality with our optional feature designed to securely store your customers' cards. With this feature, you receive a unique token for each card, providing an added layer of convenience for your users. For each customer, MyFatoorah effortlessly retrieves the masked card number along with the corresponding token for all saved cards. Now, you can seamlessly showcase saved cards separate from MyFatoorah's [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-integration-steps). Enhance the user experience by displaying masked card numbers while ensuring secure payment transactions through the associated card tokens.

> ❗️ Availability
>
> In order to enable this feature, you need to contact your [account manager.](https://myfatoorah.com/contact.html)

> 👍 Tokenized Embedded Payments
>
> You do not need to be PCI DSS certified to use this feature.

***

### How it works:

#### How to save the token?

You need to call InitiateSession Endpoint to get the **SessionId** and **CountryCode** to be used in your configuration to display the Card View. You need to do this for each payment separately. **SessionId** is valid for only one payment.

In the request to InitiateSession to save the token for the card that the customer will enter, you need to pass the value of **SaveToken** to be **true** and a unique CustomerIdentifier for each of the customers. If you send the value of SaveToken as false or don't send it all, it will work as in the normal flow of the embedded payment without tokenizing the card.

The endpoint on Swagger is [InitiateSession](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_InitiateSession)

```json InitiateSession Request
{
  "CustomerIdentifier": "Tokenized",
  "SaveToken": true
}
```
```json InitiateSession Response
{
  "IsSuccess": true,
  "Message": "Initiated Successfully!",
  "ValidationErrors": null,
  "Data": {
    "SessionId": "eb4b5a89-41e7-475d-9d52-101952440cf7",
    "CountryCode": "KWT",
    "CustomerTokens": []
  }
}
```

> 📘 CustomerIdentifier
>
> It is mandatory to specify a CustomerIdentifier when the value of SaveToken is true. Each customer should have a unique CustomerIdentifier.

The next steps for the first payments will be the same as in [Card Embedded Payments](https://docs.myfatoorah.com/docs/embedded-integration-steps)

***

#### How to make payments using the token?

##### **1- Call InitiateSession using the same CustomerIdentifier**

Initiate a session by calling the **InitiateSession** API with the same **CustomerIdentifier**. The response will include a unique SessionId and tokenized cards associated with that CustomerIdentifier. Each card's details, such as the **Masked Card**, **Token**, and **Card Brand**, will be provided.

```json InitiateSession Request
{
  "CustomerIdentifier": "Tokenized",
  "SaveToken": true
}
```
```json InitiateSession Response
{
    "IsSuccess": true,
    "Message": "Initiated Successfully!",
    "ValidationErrors": null,
    "Data": {
        "SessionId": "56d62a79-f631-443c-ab5d-838180ca867e",
        "CountryCode": "KWT",
        "CustomerTokens": [
            {
                "Token": "Token0505121807676252",
                "CardNumber": "450875xxxxxx1019",
                "CardBrand": "Visa",
                "Is3DSVerified": true
            },
            {
                "Token": "Token0505121807676151",
                "CardNumber": "545454xxxxxx5454",
                "CardBrand": "Master",
                "Is3DSVerified": true
            }
        ]
    }
}
```

##### **2- Display the Card View and the Tokenized Cards**

Utilize the masked card numbers from the response to present the saved cards to your customers. Enable them to choose from the list of saved cards. Once a customer selects a card, prompt them to enter the CVV for the chosen card.

##### **3- Collect Customer's CVV Using MyFatoorah Component**

This step involves utilizing the MyFatoorah JavaScript component to securely collect the customer's CVV. By leveraging this component, the CVV is managed directly by MyFatoorah, ensuring secure handling and eliminating the need for you application to be PCI DSS certified for this process.

##### Steps:

###### 1- Include the Javascript library:

Choose the test or the live library according to your working environment. Please note that this is the same JavaScript for the embedded payment.

```html
// Test Environment
<script src="https://demo.myfatoorah.com/payment/v1/session.js"></script>

// Live Environment For Kuwait, Bahrain, Jordan, and Oman.
<script src="https://portal.myfatoorah.com/payment/v1/session.js"></script>

// Live Environment For UAE
<script src="https://ae.myfatoorah.com/payment/v1/session.js"></script>

// Live Environment For Saudi Arabia
<script src="https://sa.myfatoorah.com/payment/v1/session.js"></script>

// Live Environment For Qatar
<script src="https://qa.myfatoorah.com/payment/v1/session.js"></script>

// Live Environment For Egypt
<script src="https://eg.myfatoorah.com/payment/v1/session.js"></script>
```

###### 2- Define a div element

You need to define a div element with a unique id attribute. The CVV component will be displayed inside this div after passing the div id to the configuration variable.

```html
<div id="cvv-component"></div>
```

###### 3- Configure the CVV Component

Create a configuration variable for the CVV component and include in it the **countryCode**, **currencyCode**, **sessionId**, **containerId** and **callback**

```javascript
var configCvvView = {
  countryCode: countryCode,
  currencyCode: currencyCode,
  sessionId: sessionId,
  containerId: "cvv-component",
  callback: proceedCvv, //MyFatoorah triggers this function after the CVV is submitted to MyFatoorah
}

myfatoorah.initCvv(configCvvView);

function proceedCvv(response) {
  if (response.isSuccess) {
    //Pass the SessionId to your backend to call ExecutePayment
    console.log("proceedCvv response >>\n" + JSON.stringify(response));
  } else {
    console.error("proceedCvv Error >> " + JSON.stringify(response));
  }
}
```

This will cause make the CVV component load so that the customer can enter their CVV in the view.

> 📘 Common Parameters
>
> If you initialized the Embedded Payment before initializing the CVV component, you don't need to send the common parameters again to the CVV configuration variable and vice versa.
>
> For example:
>
> ```javascript Initialize Embedded Payment then CVV
> var config = {
>   sessionId: sessionId,
>   countryCode: countryCode,
>   currencyCode: currencyCode,
>   amount: amount,
>   callback: payment,
>   containerId: "unified-session",
>   paymentOptions: ["GooglePay", "ApplePay", "STCPay", "Card"],
>   supportedNetworks: ["visa", "masterCard", "mada", "amex"],
>   language: "en"
> };
>
> var configCvvView = {
>   containerId: "cvv-component",
>   callback: proceedCvv,
> }
> /* The values of sessionId, countryCode, currencyCode and language will be taken from config 
> because myfatoorah.init(config) was called before myfatoorah.initCvv(configCvvView) */
>
> myfatoorah.init(config);
> myfatoorah.initCvv(configCvvView);
>
> ```
> ```javascript Initialize CVV then Embedded Payment
> var config = {
>   amount: amount,
>   callback: payment,
>   containerId: "unified-session",
>   paymentOptions: ["GooglePay", "ApplePay", "STCPay", "Card"],
>   supportedNetworks: ["visa", "masterCard", "mada", "amex"],
> };
>
> var configCvvView = {
>   sessionId: sessionId,
>   countryCode: countryCode,
>   currencyCode: currencyCode,
>   containerId: "cvv-component",
>   callback: proceedCvv,
>   language: "en"
> }
> /* The values of sessionId, countryCode, currencyCode and language will be taken from 
> configCvvView because myfatoorah.initCvv(configCvvView) was called before myfatoorah.init(config) */
>
> myfatoorah.initCvv(configCvvView);
> myfatoorah.init(config);
> ```

###### 4- Handle the submit button

After that, you need to handle your submit button to call **myfatoorah.submitCvv()** using the token of the card that the customer chose to pay with.

```html
<button onclick="submitCvv()">Submit CVV</button>

<script>
  function submitCvv() {
    myfatoorah.submitCvv("Token0505121807676252")
    //Pass the token of the card the customer chose to pay with.
  }
</script>
```

After you get the SessionId from the submit() function, send it to your backend to proceed with the next step.

> 📘 CVV Component Customization
>
> For more customizations of the CVV component, please check the [sample code](https://docs.myfatoorah.com/docs/unified-sample-code).

##### **4- Call ExecutePayment using the SessionId**

Upon getting the SessionId from the front end, proceed to call the ExecutePayment endpoint, utilizing the **SessionId** exclusively (not the PaymentMethodId). Subsequently, redirect the customer to the PaymentUrl provided in the response, facilitating the entry of the OTP challenge for enhanced security.

```json ExecutePayment Request
{
   "SessionId":"56d62a79-f631-443c-ab5d-838180ca867e",
   "InvoiceValue":10
}
```
```json ExecutePayment Response
{
  "IsSuccess": true,
  "Message": "Invoice Created Successfully!",
  "ValidationErrors": null,
  "Data": {
    "InvoiceId": 3033576,
    "IsDirectPayment": false,
    "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/MpgsAuthentication?paymentId=07073033576178636873&sessionId=SESSION0002237514833L20329328M1",
    "CustomerReference": "",
    "UserDefinedField": null,
    "RecurringId": ""
  }
}
```

## Tokenized Embedded Payments

*`https://docs.myfatoorah.com/docs/tokenized-embedded-payments` — updated 2026-02-16*

### **Introduction**

Explore an extra layer of functionality with our optional feature designed to securely store your customers' cards. With this feature, you receive a unique token for each card, providing an added layer of convenience for your users. For each customer, MyFatoorah effortlessly retrieves the masked card number along with the corresponding token for all saved cards. Now, you can seamlessly showcase saved cards separate from MyFatoorah's [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-integration-steps). Enhance the user experience by displaying masked card numbers while ensuring secure payment transactions through the associated card tokens.

> ❗️ Availability
>
> In order to enable this feature, you need to contact your [account manager.](https://myfatoorah.com/contact.html)

> 👍 Tokenized Embedded Payments
>
> You do not need to be PCI DSS certified to use this feature.

***

### **How it Works**

<Embed url="https://www.youtube.com/watch?v=A15wt_TzPys" title="MyFatoorah Integration | Tokenized Embedded Payment" favicon="https://www.youtube.com/favicon.ico" image="https://i.ytimg.com/vi/A15wt_TzPys/hqdefault.jpg" provider="youtube.com" href="https://www.youtube.com/watch?v=A15wt_TzPys" typeOfEmbed="youtube" html="%3Ciframe%20class%3D%22embedly-embed%22%20src%3D%22%2F%2Fcdn.embedly.com%2Fwidgets%2Fmedia.html%3Fsrc%3Dhttps%253A%252F%252Fwww.youtube.com%252Fembed%252FA15wt_TzPys%253Ffeature%253Doembed%26display_name%3DYouTube%26url%3Dhttps%253A%252F%252Fwww.youtube.com%252Fwatch%253Fv%253DA15wt_TzPys%26image%3Dhttps%253A%252F%252Fi.ytimg.com%252Fvi%252FA15wt_TzPys%252Fhqdefault.jpg%26key%3D7788cb384c9f4d5dbbdbeffd9fe4b92f%26type%3Dtext%252Fhtml%26schema%3Dyoutube%22%20width%3D%22854%22%20height%3D%22480%22%20scrolling%3D%22no%22%20title%3D%22YouTube%20embed%22%20frameborder%3D%220%22%20allow%3D%22autoplay%3B%20fullscreen%3B%20encrypted-media%3B%20picture-in-picture%3B%22%20allowfullscreen%3D%22true%22%3E%3C%2Fiframe%3E" />

#### How to save the token?

You need to call InitiateSession Endpoint to get the **SessionId** and **CountryCode** to be used in your configuration to display the Card View. You need to do this for each payment separately. **SessionId** is valid for only one payment.

In the request to InitiateSession to save the token for the card that the customer will enter, you need to pass the value of **SaveToken** to be **true** and a unique CustomerIdentifier for each of the customers. If you send the value of SaveToken as false or don't send it all, it will work as in the normal flow of the embedded payment without tokenizing the card.

The endpoint on Swagger is [InitiateSession](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_InitiateSession)

```json InitiateSession Request
{
  "CustomerIdentifier": "First-Payment",
  "SaveToken": true
}
```
```json InitiateSession Response
{
  "IsSuccess": true,
  "Message": "Initiated Successfully!",
  "ValidationErrors": null,
  "Data": {
    "SessionId": "eb4b5a89-41e7-475d-9d52-101952440cf7",
    "CountryCode": "KWT",
    "CustomerTokens": []
  }
}
```

> 📘 CustomerIdentifier
>
> It is mandatory to specify a CustomerIdentifier when the value of SaveToken is true. Each customer should have a unique CustomerIdentifier.

The next steps for the first payments will be the same as in [Card View.](https://docs.myfatoorah.com/docs/card-view-form)

***

#### How to make payments using the token?

##### **1- Call InitiateSession using the same CustomerIdentifier**

Initiate a session by calling the **InitiateSession** API with the same **CustomerIdentifier**. The response will include a unique SessionId and tokenized cards associated with that CustomerIdentifier. Each card's details, such as the **Masked Card**, **Token**, and **Card Brand**, will be provided.

```json InitiateSession Request
{
  "CustomerIdentifier": "First-Payment",
  "SaveToken": true
}
```
```json InitiateSession Response
{
    "IsSuccess": true,
    "Message": "Initiated Successfully!",
    "ValidationErrors": null,
    "Data": {
        "SessionId": "bbf6c803-f5d5-43b9-863c-a4762809455f",
        "CountryCode": "KWT",
        "CustomerTokens": [
            {
                "Token": "Token0505121801530150",
                "CardNumber": "545454xxxxxx5454",
                "CardBrand": "Master",
                "Is3DSVerified": null
            }
        ]
    }
}
```

##### **2- Display the Card View and the Tokenized Cards**

Utilize the masked card numbers from the response to present the saved cards to your customers. Enable them to choose from the list of saved cards. Once a customer selects a card, prompt them to enter the CVV for the chosen card.

##### **3- Call UpdateSession using the SessionId and the Token**

Invoke the [UpdateSession ](https://docs.myfatoorah.com/docs/update-session)endpoint by utilizing the obtained **SessionId** and **Token**. Ensure that the TokenType is set to **mftoken**. Include the CVV entered by the customer in the request body.

```json UpdateSession Request
{
  "SessionId": "5d4d46de-b23c-4717-9457-6bea5d60e476",
  "Token": "Token0505121801530150",
  "TokenType": "mftoken",
  "SecurityCode": "911"
}
```
```json UpdateSession Response
{
  "IsSuccess": true,
  "Message": null,
  "ValidationErrors": null,
  "Data": {
    "SessionId": "5d4d46de-b23c-4717-9457-6bea5d60e476",
    "CountryCode": "KWT"
  }
}
```

> 📘 SecurityCode
>
> You can bypass this field to use features like Merchant Initiated Payment or Recurring Payment. To activate this feature, please contact your [account manager.](https://myfatoorah.com/contact.html)

##### **4- Call ExecutePayment using the SessionId and the Token**

Upon obtaining the response from the UpdateSession endpoint, proceed to call the ExecutePayment endpoint, utilizing the **SessionId** exclusively (not the PaymentMethodId). Subsequently, redirect the customer to the PaymentUrl provided in the response, facilitating the entry of the OTP challenge for enhanced security.

```json ExecutePayment Request
{
   "SessionId":"eb4b5a89-41e7-475d-9d52-101952440cf7",
   "InvoiceValue":10
}
```
```json ExecutePayment Response
{
  "IsSuccess": true,
  "Message": "Invoice Created Successfully!",
  "ValidationErrors": null,
  "Data": {
    "InvoiceId": 3033576,
    "IsDirectPayment": false,
    "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/MpgsAuthentication?paymentId=07073033576178636873&sessionId=SESSION0002237514833L20329328M1",
    "CustomerReference": "",
    "UserDefinedField": null,
    "RecurringId": ""
  }
}
```

## Sample Code

*`https://docs.myfatoorah.com/docs/embedded-payment-sample-code-1` — updated 2026-02-16*

> Embedded Payment Sample Code

This sample code is a complete example of a payment page that uses the MyFatoorah embedded payment.

```html Embedded Payment
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Embedded Payment</title>
    <script src="https://demo.myfatoorah.com/sessions/v1/session.js"></script>
  </head>

  <body style="background-color: white">
    <div style="width: 400px; margin: auto">
      <div id="unified-session"></div>
    </div>

    <script>
      var config = {
        sessionId: "KWT-226c96d3-b243-4f54-aa38-727c1659ec94", //Add the "SessionId" you received from POST Session Endpoint.
        callback: payment, // MyFatoorah calls the callback function after the customer fills in the card and clicks pay or when the customer finishes the steps with GooglePay, STC Pay and Apple Pay or when the customer choose one of hosted payment methods.
        containerId: "unified-session", //Enter the div id you created in previous step.
        shouldHandlePaymentUrl: true, //default is true, if you want to handle OTP page url by yourself (in case the payment supported embedded) or need to redirect customer to hosted page when choose hosted payment method, set it to false.
				eventListener: eventHandler,
        subscribedEvents: ['VIEW_READY', "CARD_IDENTIFIED", 'PAYMENT_STARTED', 'PAYMENT_COMPLETED', 'SESSION_STARTED', 'SESSION_CANCELED', '3DS_CHALLENGE_INITIATED', 'OTP_REQUESTED'],
        settings: {
             loader: {
                 display: 'none'
             },
          // applePay: {
          //   language: "ar",
          //   style: {
          //     frameHeight: "10px",
          //     frameWidth: "100%",
          //     button: {
          //       height: "80px",
          //       type: "subscribe", //["plain", "buy", "pay", "checkout", "continue", "book", "donate", "subscribe", "reload", "add", "topup", "order", "rent", "support", "contribute", "setup", "tip"]
          //       borderRadius: "10px",
          //     },
          //   },
          //   requiredShippingContactFields: [
          //     "postalAddress",
          //     "name",
          //     "phone",
          //     "email",
          //   ],
          //   requiredBillingContactFields: ["postalAddress", "name", "phone"],
          // },
          // googlePay: {
          //   language: "ar",
          //   style: {
          //     frameHeight: "70px",
          //     frameWidth: "100%",
          //     button: {
          //       height: "40px",
          //       type: "book", //Accepted texts ["book", "buy", "checkout", "donate", "order", "pay", "plain", "subscribe"]
          //       borderRadius: "10px",
          //       color: "black", //Accepted colors ["black", "white"]
          //     },
          //   },
          // },
          // card: {
          //   language: "en",
          //   style: {
          //     showCardholderName: true,
          //     hideCardIcons: true,
          //     cardHeight: "180px",
          //     tokenHeight: "180px",
          //     input: {
          //       color: "black",
          //       fontSize: "20px",
          //       fontFamily: "Times",
          //       inputHeight: "32px",
          //       inputMargin: "-1px",
          //       borderColor: "black",
          //       //backgroundColor: "green",
          //       borderWidth: "1px",
          //       borderRadius: "30px",
          //       outerRadius: "10px",
          //       // boxShadow: "0 0 10px 5px purple, 0 0 15px 10px lightblue",
          //       placeHolder: {
          //         color: "red",
          //         holderName: "Name On Card",
          //         cardNumber: "Number",
          //         expiryDate: "MM/YY",
          //         securityCode: "CVV",
          //       },
          //     },
          //     text: {
          //       saveCard: "Save card info for future payments",
          //       addCard: "Use another Card!",
          //       deleteAlert: {
          //         title: "Delete",
          //         message: "Are you sure?",
          //         confirm: "YES",
          //         cancel: "NO",
          //       },
          //     },
          //     label: {
          //       display: false,
          //       color: "black",
          //       fontSize: "13px",
          //       fontWeight: "bold",
          //       fontFamily: "Times",
          //       text: {
          //         holderName: "Card Holder Name",
          //         cardNumber: "Card Number",
          //         expiryDate: "Expiry Date",
          //         securityCode: "Security Code",
          //       },
          //     },
          //     error: {
          //       borderColor: "red",
          //       //boxShadow: "0 0 10px 5px purple, 0 0 15px 10px lightblue",
          //       borderRadius: "8px",
          //     },
          //     button: {
          //       textContent: "Pay",
          //       fontSize: "16px",
          //       fontFamily: "Times",
          //       color: "white",
          //       backgroundColor: "#4daee0",
          //       height: "30px",
          //       borderRadius: "8px",
          //       width: "70%",
          //       margin: "0 auto",
          //       cursor: "pointer",
          //     },
          //     separator: {
          //       useCustomSeparator: false,
          //       textContent: "Enter your card",
          //       fontSize: "20px",
          //       color: "#4daee0",
          //       fontFamily: "sans-serif",
          //       textSpacing: "2px",
          //       lineStyle: "dashed",
          //       lineColor: "black",
          //       lineThickness: "3px",
          //     },
          //   },
          // },
          // stcPay: {
          //   mobileNumber: "0548220713",
          //   // language: "en",
          //   style: {
          //     frameHeight: "40px",
          //     frameWidth: "100%",
          //     input: {
          //       color: "#582490",
          //       fontSize: "14px",
          //       fontFamily: "'roboto', sans-serif",
          //       inputHeight: "40px",
          //       borderColor: "black",
          //       borderWidth: "2px",
          //       borderRadius: "9px",
          //       placeHolder: {
          //         mobileNumber: "05________",
          //         otpValue: "Enter OTP!",
          //       },
          //     },
          //     button: {
          //       borderRadius: "9px",
          //       height: "40px",
          //     },
          //   },
          // },
        },
      };

      myfatoorah.init(config);

      //Embedded Functions
      function payment(response) {
        console.log(JSON.stringify(response));
      }
      
      function eventHandler(event) {
          console.log("Event received:" + JSON.stringify(event));
      }

      //This function enables you to update the amount displayed on Apple Pay and Google Pay after the initiation of the config.
      function updateAmount(amount) {
        myfatoorah.updateAmount(amount);
      }

    </script>
  </body>
</html>

```
```html Tokenized Embedded Payment
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Embedded Payment</title>
    <script src="https://demo.myfatoorah.com/sessions/v1/session.js"></script>
  </head>

  <body style="background-color: white">
    <div style="width: 400px; margin: auto">
      <div id="unified-session"></div>
    </div>

    <!-- This is a div element for the CVV Component -->
    <div
      style="
        width: 400px;
        margin-top: 30px;
        margin-left: auto;
        margin-right: auto;
      "
      ;
      margin-bottom:
      5px;
    >
      <div id="cvv-component"></div>
    </div>

    <!-- This is a button for the submit CVV -->
    <button onclick="submitCvv()">Submit CVV</button>
    <script>
      var sessionId = "KWT-d2a00b6d-9e9b-4971-bc70-b10eade6a785"; //Add the "SessionId" you received from POST Session Endpoint.

      var configCvvView = {
        sessionId: sessionId,
        containerId: "cvv-component",
        callback: proceedCvv,
        shouldHandlePaymentUrl: true,
        // style: {
        //   frameHeight: "100px",
        //   error: {
        //     borderColor: "violet",
        //     borderRadius: "10px",
        //     //boxShadow: "10px 10px 5px #888888",
        //   },

        //   input: {
        //     color: "black",
        //     fontSize: "20px",
        //     fontFamily: "Sofia",
        //     inputHeight: "30px",
        //     inputMargin: "1px",
        //     borderColor: "blue",
        //     borderWidth: "3px",
        //     borderRadius: "25px",
        //     boxShadow: "",
        //     placeHolder: "security code(cvv)",
        //   },
        //   label: {
        //     display: true,
        //     color: "green",
        //     fontSize: "25px",
        //     fontWeight: "900",
        //     fontFamily: "Sofia",
        //     text: "cvv",
        //   },
        // },
      };

      myfatoorah.initCvv(configCvvView);

      //CVV Functions
      function proceedCvv(response) {
        if (response.isSuccess) {
          console.log("proceedCvv response >>\n" + JSON.stringify(response));
        } else {
          console.error("proceedCvv Error >> " + JSON.stringify(response));
        }
      }

      function submitCvv() {
        myfatoorah.submitCvv("TKN-0fb06aac-634d-418a-bf78-953202b67b53");
      }

    </script>
  </body>
</html>
```

<br />

## Sample Code

*`https://docs.myfatoorah.com/docs/unified-sample-code` — updated 2026-02-16*

Embedded Payment Sample Code

This sample code is a complete example of a payment page that uses the **MyFatoorah** embedded payment.

```html Embedded Payment
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Embedded Payment</title>
    <style>
    </style>
    <link rel="icon" href="Asset 1@4x.png"></link>
</head>

<body style="background-color: white;">
    <script src="https://demo.myfatoorah.com/payment/v1/session.js"></script>

    <div style="width:400px; margin: auto;">
        <div id="unified-session"></div>
    </div>

    <!-- This is a div element can be used as a container for Apple Pay -->
    <div style="width:400px; margin: auto;">
        <div id="apple-pay"></div>
        <!-- This is the custom button for Apple Pay. -->
        <!-- <button onclick="startApplePay()" style="display: block; margin: 0 auto; width: 400px; height: 30px; cursor: pointer; background-color: #000000; border: none; color: white; font-size: 16px; border-radius: 8px">Apple Pay</button> -->
    </div>

    <!-- This is a div element can be used as a container for Google Pay -->
    <div style="width: 400px; margin: 0; position: absolute; top: 0; left: 0;">
        <div id="google-pay"></div>
    </div>

    <!-- This is a div element can be used as a container for STC Pay -->
    <div style="width: 400px; margin: 0; position: absolute; top: 0; left: 0;">
        <div id="stc-pay"></div>
    </div>

    <!-- This is your custom payment button -->
    <!-- <button onclick="customSubmit()"  class="animated-button">Pay Now</button> -->


    <script>
        var sessionId = "465be64b-0197-4eac-8fb7-b8323873b3c2";
        var countryCode = "KWT";
        var currencyCode = "KWD";
        var amount = "0.25";

        var config = {
            sessionId: sessionId,
            countryCode: countryCode,
            currencyCode: currencyCode,
            amount: amount,
            callback: payment,
            containerId: "unified-session",
            paymentOptions: ["GooglePay", "ApplePay", "STCPay", "Card"], //"GooglePay", "ApplePay", "Card", "STCPay"
            supportedNetworks: ["visa", "masterCard", "mada", "amex"], //"visa", "masterCard", "mada", "amex"
            language: "en", //ar en
            settings: {
                /*applePay: {
                    //supportedNetworks: "["visa", "masterCard", "mada"]",
                    //containerId: "",
                    //callback: paymentAP,
                    //language: "ar",

                    style: {
                        frameHeight: "50px",
                        frameWidth: "100%",

                        button: {
                            height: "40px",
                            type: "pay", //["plain", "buy", "pay", "checkout", "continue", "book", "donate", "subscribe", "reload", "add", "topup", "order", "rent", "support", "contribute", "setup", "tip"]
                            borderRadius: "10px"
                        }
                    },

                    useCustomButton: false,
                    sessionStarted: sessionStarted,
                    sessionCanceled: sessionCanceled,
                    requiredShippingContactFields: ["postalAddress", "name", "phone", "email"],
                    requiredBillingContactFields: ["postalAddress", "name", "phone"]
                },*/
                /*googlePay: {
                    //supportedNetworks: ["visa", "masterCard"],
                    //containerId: "google-pay",
                    //callback: paymentGP,
					//language: "ar",

                    style: {
                        frameHeight: "70px",
                        frameWidth: "100%",

                        button: {
                            height: "40px",
                            type: "pay", //Accepted texts ["book", "buy", "checkout", "donate", "order", "pay", "plain", "subscribe"]
                            borderRadius: "10px",
                            color: "black",
                            language: "en"
                        }
                    }
                },*/
                /*card: {
                    onCardBinChanged: handleCardBinChanged,

                    style: {
                        hideNetworkIcons: false,
                        cardHeight: "180px",
                        tokenHeight: "180px",

                        input: {
                            color: "black",
                            fontSize: "20px",
                            fontFamily: "Times",
                            inputHeight: "32px",
                            inputMargin: "-1px",
                            borderColor: "black",
                            //backgroundColor: "green",
                            borderWidth: "1px",
                            borderRadius: "30px",
                            outerRadius: "10px",
                            //boxShadow: "0 0 10px 5px purple, 0 0 15px 10px lightblue"

                            placeHolder: {
                                holderName: "Name On Card",
                                cardNumber: "Number",
                                expiryDate: "MM/YY",
                                securityCode: "CVV"
                            }
                        },

                        text: {
                            saveCard: "Save card info for future payments",
                            addCard: "Use another Card!",
                            deleteAlert: {
                                title: "Delete",
                                message: "Are you sure?",
                                confirm: "YES",
                                cancel: "NO"
                            }
                        },

                        label: {
                            display: false,
                            color: "black",
                            fontSize: "13px",
                            fontWeight: "bold",
                            fontFamily: "Times",

                            text: {
                                holderName: "Card Holder Name",
                                cardNumber: "Card Number",
                                expiryDate: "Expiry Date",
                                securityCode: "Security Code"
                            }
                        },

                        error: {
                            borderColor: "red",
                            //boxShadow: "0 0 10px 5px purple, 0 0 15px 10px lightblue",
                            borderRadius: "8px"
                        },

                        button: {
                            useCustomButton: false,
                            //onButtonClicked: submit,//You will have to implement this function and call myfatoorah.submitCardPayment()
                            textContent: "Pay",
                            fontSize: "16px",
                            fontFamily: "Times",
                            color: "white",
                            backgroundColor: "#4daee0",
                            height: "30px",
                            borderRadius: "8px",
                            width: "70%",
                            margin: "0 auto",
                            cursor: "pointer"
                        },

                        separator: {
                            useCustomSeparator: false,
                            textContent: "Enter your card",
                            fontSize: "20px",
                            color: "#4daee0",
                            fontFamily: "sans-serif",
                            textSpacing: "2px",
                            lineStyle: "dashed",
                            lineColor: "black",
                            lineThickness: "3px"
                        }
                    }
                },*/
                stcPay: {
                    //containerId: "stc-pay",
                    //callback: paymentSTC,

                    mobileNumber: "0557877988",
                    useCustomButton: false,
                    sessionStarted: stcSessionStarted,
                    style: {
                        frameHeight: "40px",
                        frameWidth: "100%",

                        //language: "en",
                        //input: {

                        //color: "#582490",
                        //fontSize: "14px",
                        //fontFamily: "'roboto', sans-serif",
                        //inputHeight: "40px",

                        //borderColor: "black",
                        //borderWidth: "2px",
                        //borderRadius: "9px",

                        //placeHolder: {
                        //  mobileNumber: "05________",
                        //otpValue: "Enter OTP!"
                        //}
                        //},
                        //button: {
                        //borderRadius: "9px",
                        //height: "40px"
                        //}
                    }
                }
            }
        };


        myfatoorah.init(config);
        //myfatoorah.submitStcOtp("1234");

        //Embedded Functions
        function payment(response) {
            //Pass session id to your backend here
            if (response.isSuccess) {
                switch (response.paymentType) {
                    case "ApplePay":
                        console.log("Apple Pay Response>>\n" + JSON.stringify(response));
                        break;
                    case "GooglePay":
                        console.log("Google Pay response >>\n" + JSON.stringify(response));
                        break;
                    case "Card":
                        console.log("Card Response >>\n" + JSON.stringify(response));
                        break;
                    case "StcPay":
                        console.log("STC Pay response >>\n" + JSON.stringify(response));
                        break;
                    default:
                        console.log("Unknown payment type >>\n" + JSON.stringify(response));
                        break;
                }
            } else {
                console.log("error", response);
            }
        }

        // #region STC Pay
        function stcSessionStarted(data) {
            console.log("STC Session Started\n" + JSON.stringify(data));
        }

        function paymentSTC(response) {
            //Here you need to pass session id to you backend here
            var sessionId = response.sessionId;
            var STCMobileNumber = response.mobileNumber;

            console.log("SessionID via STC >> ", sessionId);
            console.log("Mobile Number via STC >> ", STCMobileNumber);
            console.log("response via STC >> ", response);
        }

        // #endregion

        function sessionCanceled() {
            console.log("Failed");
        }

        function sessionStarted() {
            console.log("Start");
        }

        //This function enables you to update the amount displayed on Apple Pay and Google Pay after the initiation of the config.
        function updateAmount(amount) {
            myfatoorah.updateAmount(amount);
        }

        //You need to implement here the handling of the callback for Apple Pay
        function paymentAP(response) {
            //Here you need to pass session id to you backend here
            var sessionId = response.sessionId;
            var cardBrandAP = response.card.brand;

            console.log("SessionID via AP >> ", sessionId);
            console.log("cardBrand via AP >> ", cardBrandAP);
            console.log("response via AP >> ", response);
        }

        //You need to implement here the handling of the callback for Google Pay
        function paymentGP(response) {
            //Here you need to pass session id to you backend here
            var sessionId = response.sessionId;
            var cardBrandGP = response.card.brand;

            console.log("SessionID via GP >> ", sessionId);
            console.log("cardBrand via GP >> ", cardBrandGP);
            console.log("response via GP >> ", response);
        }

        //Here you implement the function of clicking on the payment button using your own function
        function submit() {
            console.log("Submit");
            myfatoorah.submitCardPayment(); //It is mandatory to call this function
        }

        //Here you implement the function of clicking on your custom payment button
        function customSubmit() {
            console.log("Custom Submit");
            myfatoorah.submitCardPayment(); //It is mandatory to call this function
        }

        function handleCardBinChanged(response) {
            console.log(response);
        }

        //Here you specify the actions you need to do when customer clicks on your custom Apple Pay button
        function startApplePay() {
            console.log("using custom button");
            myfatoorah.initApplePayPayment(); //It is mandatory to call this function
        }
    </script>
</body>

</html>
```
```html Tokenized Embedded Payment
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Embedded Payment</title>
    <style>
    </style>
    <link rel="icon" href="Asset 1@4x.png"></link>
</head>

<body style="background-color: white;">
    <script src="https://demo.myfatoorah.com/payment/v1/session.js"></script>

    <div style="width:400px; margin: auto;">
        <div id="unified-session"></div>
    </div>

    <!-- This is a div element can be used as a container for Apple Pay -->
    <div style="width:400px; margin: auto;">
        <div id="apple-pay"></div>
        <!-- This is the custom button for Apple Pay. -->
        <!-- <button onclick="startApplePay()" style="display: block; margin: 0 auto; width: 400px; height: 30px; cursor: pointer; background-color: #000000; border: none; color: white; font-size: 16px; border-radius: 8px">Apple Pay</button> -->
    </div>

    <!-- This is a div element can be used as a container for Google Pay -->
    <div style="width: 400px; margin: 0; position: absolute; top: 0; left: 0;">
        <div id="google-pay"></div>
    </div>

    <!-- This is a div element can be used as a container for STC Pay -->
    <div style="width: 400px; margin: 0; position: absolute; top: 0; left: 0;">
        <div id="stc-pay"></div>
    </div>

    <!-- This is a div element for the CVV Component -->
    <div style="width:400px; margin-top: 30px; margin-left:auto; margin-right:auto" ; margin-bottom: 5px;>
        <div id="cvv-component"></div>
    </div>

    <!-- This is your custom payment button -->
    <!-- <button onclick="customSubmit()"  class="animated-button">Pay Now</button> -->

    <!-- This is a button for the submit CVV -->
    <button onclick="submitCvv()">Submit CVV</button>


    <script>
        var sessionId = "e04dd735-8bee-4943-9f7c-6b4837b38db8";
        var countryCode = "KWT";
        var currencyCode = "KWD";
        var amount = "0.25";

        var config = {
            sessionId: sessionId,
            countryCode: countryCode,
            currencyCode: currencyCode,
            amount: amount,
            callback: payment,
            containerId: "unified-session",
            paymentOptions: ["GooglePay", "ApplePay", "STCPay", "Card"], //"GooglePay", "ApplePay", "Card", "STCPay"
            supportedNetworks: ["visa", "masterCard", "mada", "amex"], //"visa", "masterCard", "mada", "amex"
            language: "en", //ar en
            settings: {
                /*applePay: {
                    //supportedNetworks: "["visa", "masterCard", "mada"]",
                    containerId: "",
                    //callback: paymentAP,
                    //language: "ar",

                    style: {
                        frameHeight: "50px",
                        frameWidth: "100%",

                        button: {
                            height: "40px",
                            type: "pay", //["plain", "buy", "pay", "checkout", "continue", "book", "donate", "subscribe", "reload", "add", "topup", "order", "rent", "support", "contribute", "setup", "tip"]
                            borderRadius: "10px"
                        }
                    },

                    useCustomButton: false,
                    sessionStarted: sessionStarted,
                    sessionCanceled: sessionCanceled,
                    requiredShippingContactFields: ["postalAddress", "name", "phone", "email"],
                    requiredBillingContactFields: ["postalAddress", "name", "phone"]
                },*/
                /*googlePay: {
                    //supportedNetworks: ["visa", "masterCard"],
                    //containerId: "google-pay",
                    //callback: paymentGP,
					//language: "ar",

                    style: {
                        frameHeight: "70px",
                        frameWidth: "100%",

                        button: {
                            height: "40px",
                            type: "pay", //Accepted texts ["book", "buy", "checkout", "donate", "order", "pay", "plain", "subscribe"]
                            borderRadius: "10px",
                            color: "black",
                            language: "en"
                        }
                    }
                },*/
                /*card: {
                    onCardBinChanged: handleCardBinChanged,

                    style: {
                        hideNetworkIcons: false,
                        cardHeight: "180px",
                        tokenHeight: "180px",

                        input: {
                            color: "black",
                            fontSize: "40px",
                            fontFamily: "Times",
                            inputHeight: "32px",
                            inputMargin: "-1px",
                            borderColor: "black",
                            //backgroundColor: "green",
                            borderWidth: "1px",
                            borderRadius: "30px",
                            outerRadius: "10px",
                            //boxShadow: "0 0 10px 5px purple, 0 0 15px 10px lightblue"

                            placeHolder: {
                                holderName: "Name On Card",
                                cardNumber: "Number",
                                expiryDate: "MM/YY",
                                securityCode: "CVV"
                            }
                        },

                        text: {
                            saveCard: "Save card info for future payments",
                            addCard: "Use another Card!",
                            deleteAlert: {
                                title: "Delete",
                                message: "Are you sure?",
                                confirm: "YES",
                                cancel: "NO"
                            }
                        },

                        label: {
                            display: false,
                            color: "black",
                            fontSize: "13px",
                            fontWeight: "bold",
                            fontFamily: "Times",

                            text: {
                                holderName: "Card Holder Name",
                                cardNumber: "Card Number",
                                expiryDate: "Expiry Date",
                                securityCode: "Security Code"
                            }
                        },

                        error: {
                            borderColor: "red",
                            //boxShadow: "0 0 10px 5px purple, 0 0 15px 10px lightblue",
                            borderRadius: "8px"
                        },

                        button: {
                            useCustomButton: false,
                            //onButtonClicked: submit,//You will have to implement this function and call myfatoorah.submitCardPayment()
                            textContent: "Pay",
                            fontSize: "16px",
                            fontFamily: "Times",
                            color: "white",
                            backgroundColor: "#4daee0",
                            height: "30px",
                            borderRadius: "8px",
                            width: "70%",
                            margin: "0 auto",
                            cursor: "pointer"
                        },

                        separator: {
                            useCustomSeparator: false,
                            textContent: "Enter your card",
                            fontSize: "20px",
                            color: "#4daee0",
                            fontFamily: "sans-serif",
                            textSpacing: "2px",
                            lineStyle: "dashed",
                            lineColor: "black",
                            lineThickness: "3px"
                        }
                    }
                },*/
                /*stcPay: {
                    //containerId: "stc-pay",
                    //callback: paymentSTC,

                    //mobileNumber: "0557877988",
                    useCustomButton: false,
                    sessionStarted: stcSessionStarted,
                    style: {
                        frameHeight: "40px",
                        frameWidth: "100%",

                        //language: "en",
                        //input: {

                        //color: "#582490",
                        //fontSize: "14px",
                        //fontFamily: "'roboto', sans-serif",
                        //inputHeight: "40px",

                        //borderColor: "black",
                        //borderWidth: "2px",
                        //borderRadius: "9px",

                        //placeHolder: {
                        //  mobileNumber: "05________",
                        //otpValue: "Enter OTP!"
                        //}
                        //},
                        //button: {
                        //borderRadius: "9px",
                        //height: "40px"
                        //}
                    }
                }*/
            }
        };

        var configCvvView = {
            countryCode: countryCode,
            currencyCode: "USD",
            sessionId: sessionId,
            containerId: "cvv-component",
            callback: proceedCvv,

            //language: "en",
            style: {
                frameHeight: "100px",

                error: {
                    borderColor: "violet",
                    borderRadius: "10px",
                    //boxShadow: "10px 10px 5px #888888",
                },

                input: {
                    color: "black",
                    fontSize: "20px",
                    fontFamily: "Sofia",
                    inputHeight: "30px",
                    inputMargin: "1px",
                    borderColor: "blue",
                    borderWidth: "3px",
                    borderRadius: "25px",
                    boxShadow: "",
                    placeHolder: "security code(cvv)"
                },

                label: {
                    display: true,
                    color: "green",
                    fontSize: "25px",
                    fontWeight: "900",
                    fontFamily: "Sofia",
                    text: "cvv"
                },
            },
        };

        myfatoorah.init(config);
        myfatoorah.initCvv(configCvvView);
        //myfatoorah.submitOtp("1234");

        //CVV Functions
        function proceedCvv(response) {
            if (response.isSuccess) {
                console.log("proceedCvv response >>\n" + JSON.stringify(response));
            } else {
                console.error("proceedCvv Error >> " + JSON.stringify(response));
            }
        }

        function submitCvv() {
            myfatoorah.submitCvv("Token05063784723361057")
        }

        //Embedded Functions
        function payment(response) {
            //Pass session id to your backend here
            if (response.isSuccess) {
                switch (response.paymentType) {
                    case "ApplePay":
                        console.log("Apple Pay Response>>\n" + JSON.stringify(response));
                        break;
                    case "GooglePay":
                        console.log("Google Pay response >>\n" + JSON.stringify(response));
                        break;
                    case "Card":
                        console.log("Card Response >>\n" + JSON.stringify(response));
                        break;
                    case "StcPay":
                        console.log("STC Pay response >>\n" + JSON.stringify(response));
                        break;
                    default:
                        console.log("Unknown payment type >>\n" + JSON.stringify(response));
                        break;
                }
            } else {
                console.log("error", response);
            }
        }

        function stcSessionStarted(data) {
            console.log("STC Session Started\n" + JSON.stringify(data));
        }

        function paymentSTC(response) {
            //Here you need to pass session id to you backend here
            var sessionId = response.sessionId;
            var STCMobileNumber = response.mobileNumber;

            console.log("SessionID via STC >> ", sessionId);
            console.log("Mobile Number via STC >> ", STCMobileNumber);
            console.log("response via STC >> ", response);
        }

        function sessionCanceled() {
            console.log("Failed");
        }

        function sessionStarted() {
            console.log("Start");
        }

        //This function enables you to update the amount displayed on Apple Pay and Google Pay after the initiation of the config.
        function updateAmount(amount) {
            myfatoorah.updateAmount(amount);
        }

        //You need to implement here the handling of the callback for Apple Pay
        function paymentAP(response) {
            //Here you need to pass session id to you backend here
            var sessionId = response.sessionId;
            var cardBrandAP = response.card.brand;

            console.log("SessionID via AP >> ", sessionId);
            console.log("cardBrand via AP >> ", cardBrandAP);
            console.log("response via AP >> ", response);
        }

        //You need to implement here the handling of the callback for Google Pay
        function paymentGP(response) {
            //Here you need to pass session id to you backend here
            var sessionId = response.sessionId;
            var cardBrandGP = response.card.brand;

            console.log("SessionID via GP >> ", sessionId);
            console.log("cardBrand via GP >> ", cardBrandGP);
            console.log("response via GP >> ", response);
        }

        //Here you implement the function of clicking on the payment button using your own function
        function submit() {
            console.log("Submit");
            myfatoorah.submitCardPayment(); //It is mandatory to call this function
        }

        //Here you implement the function of clicking on your custom payment button
        function customSubmit() {
            console.log("Custom Submit");
            myfatoorah.submitCardPayment(); //It is mandatory to call this function
        }

        function handleCardBinChanged(response) {
            console.log(response);
        }

        //Here you specify the actions you need to do when customer clicks on your custom Apple Pay button
        function startApplePay() {
            console.log("using custom button");
            myfatoorah.initApplePayPayment(); //It is mandatory to call this function
        }
    </script>
</body>

</html>
```
```php PHP
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
    'apiKey' => '',
    /*
     * Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'vcCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest' => true,
];

/* --------------------------- InitiateSession Endpoint --------------------- */

//------------- Post Fields -------------------------
//Check https://docs.myfatoorah.com/docs/InitiateSession#request-model
$postFields = [
        //Fill optional data
        //'CustomerIdentifier' => 'string', //optional
        //'SaveToken'          => false, //optional
];

//------------- Call the Endpoint -------------------------
try {
    $mfObj = new MyFatoorahPayment($mfConfig);
    $data  = $mfObj->InitiateSession($postFields);

    $sessionId = $data->SessionId;
    $link      = 'https://docs.myfatoorah.com/docs/embedded-payment-sample-code';

    //Display the result to your customer
    echo '<h3><u>Summary:</u></h3>';
    echo "You should draw the card view with session ID <b>$sessionId</b> as described in the link below then call the ExecutePayment Endpoint.<br>";
    echo "<a href='$link' target='_blank'>$link</a><br><br>";

    echo '<h3><u>InitiateSession Response Data:</u></h3><pre>';
    print_r($data);
    echo '</pre>';
} catch (Exception $ex) {
    echo $ex->getMessage();
    die;
}
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Unified Payment</title>
        <style>
        </style>
    </head>

    <body style="background-color: white;" >
        <script src="https://demo.myfatoorah.com/payment/v1/session.js"></script>

        <div style="width:400px; margin: auto;">
            <div id="unified-session"></div>
        </div>

        <!-- This is a div element can be used as a container for Apple Pay -->
        <div style="width:400px; margin: auto;">
            <div id="apple-pay"></div>
            <!-- This is the custom button for Apple Pay. -->
            <!-- <button onclick="startApplePay()" style="display: block; margin: 0 auto; width: 400px; height: 30px; cursor: pointer; background-color: #000000; border: none; color: white; font-size: 16px; border-radius: 8px">Apple Pay</button> -->
        </div>

        <!-- This is a div element can be used as a container for Google Pay -->
        <div style="width: 400px; margin: 0; position: absolute; top: 0; left: 0;">
            <div id="google-pay"></div>
        </div>

        <!-- This is your custom payment button -->
        <!-- <button onclick="customSubmit()" -->
        <!-- style="display: block; margin: 0 auto; width: 400px; height: 30px; cursor: pointer; background-color: #008CBA; border: none; color: white; font-size: 16px; border-radius: 8px">Pay -->
        <!-- Now</button> -->

        <script>
            var sessionId = "<?php echo $data->SessionId; ?>";
            var countryCode = "<?php echo $data->CountryCode; ?>";
            var currencyCode = "KWD";
            var amount = "99";

            var config = {
                sessionId: sessionId,
                countryCode: countryCode,
                currencyCode: currencyCode,
                amount: amount,
                callback: payment,
                containerId: "unified-session",
                paymentOptions: ["ApplePay", "GooglePay", "Card"], //"GooglePay", "ApplePay", "Card"
                supportedNetworks: ["visa", "masterCard", "mada", "amex"], //"visa", "masterCard", "mada", "amex"
                language: "en", //ar en
                settings: {
                    applePay: {
                        //supportedNetworks: "["visa", "masterCard", "mada"]",
                        //containerId: "apple-pay",
                        //callback: paymentAP,
                        style: {
                            frameHeight: "50px",
                            frameWidth: "100%",
                            button: {
                                height: "40px",
                                type: "pay", //["plain", "buy", "pay", "checkout", "continue", "book", "donate", "subscribe", "reload", "add", "topup", "order", "rent", "support", "contribute", "setup", "tip"]
                                borderRadius: "0px"
                            }
                        },
                        useCustomButton: false,
                        sessionStarted: sessionStarted,
                        sessionCanceled: sessionCanceled,
                        requiredShippingContactFields: ["postalAddress", "name", "phone", "email"],
                        requiredBillingContactFields: ["postalAddress", "name", "phone"]
                    },
                    googlePay: {
                        //supportedNetworks: ["visa", "masterCard"],
                        //containerId: "google-pay",
                        //callback: paymentGP,
                        style: {
                            frameHeight: "50px",
                            frameWidth: "100%",
                            button: {
                                height: "40px",
                                type: "pay", //Accepted texts ["book", "buy", "checkout", "donate", "order", "pay", "plain", "subscribe"]
                                borderRadius: "0px",
                                color: "black",
                                language: "en"
                            }
                        }
                    },
                    card: {
                        onCardBinChanged: handleCardBinChanged,
                        style: {
                            hideNetworkIcons: false,
                            cardHeight: "180px",
                            tokenHeight: "180px",
                            input: {
                                color: "black",
                                fontSize: "15px",
                                fontFamily: "Times",
                                inputHeight: "32px",
                                inputMargin: "-1px",
                                borderColor: "black",
                                borderWidth: "1px",
                                borderRadius: "30px",
                                outerRadius: "10px",
                                //boxShadow: "0 0 10px 5px purple, 0 0 15px 10px lightblue"
                                placeHolder: {
                                    holderName: "Name On Card",
                                    cardNumber: "Number",
                                    expiryDate: "MM/YY",
                                    securityCode: "CVV"
                                }
                            },
                            text: {
                                saveCard: "Save card info for future payments",
                                addCard: "Use another Card!",
                                deleteAlert: {
                                    title: "Delete",
                                    message: "Are you sure?",
                                    confirm: "YES",
                                    cancel: "NO"
                                }
                            },
                            label: {
                                display: false,
                                color: "black",
                                fontSize: "13px",
                                fontWeight: "bold",
                                fontFamily: "Times",
                                text: {
                                    holderName: "Card Holder Name",
                                    cardNumber: "Card Number",
                                    expiryDate: "Expiry Date",
                                    securityCode: "Security Code"
                                }
                            },
                            error: {
                                borderColor: "red",
                                //boxShadow: "0 0 10px 5px purple, 0 0 15px 10px lightblue",
                                borderRadius: "8px"
                            },
                            button: {
                                useCustomButton: false,
                                //onButtonClicked: submit,//You will have to implement this function and call myfatoorah.submitCardPayment()
                                textContent: "Pay",
                                fontSize: "16px",
                                fontFamily: "Times",
                                color: "white",
                                backgroundColor: "#4daee0",
                                height: "30px",
                                borderRadius: "8px",
                                width: "70%",
                                margin: "0 auto",
                                cursor: "pointer"
                            },
                            separator: {
                                useCustomSeparator: false,
                                textContent: "Enter your card",
                                fontSize: "20px",
                                color: "#4daee0",
                                fontFamily: "sans-serif",
                                textSpacing: "2px",
                                lineStyle: "dashed",
                                lineColor: "black",
                                lineThickness: "3px"
                            }
                        }
                    }
                }
            };

            myfatoorah.init(config);

            function payment(response) {
                //Pass session id to your backend here
                switch (response.paymentType) {
                    case "ApplePay":
                        console.log("response >> " + JSON.stringify(response));
                        break;
                    case "GooglePay":
                        console.log("response >> " + JSON.stringify(response));
                        break;
                    case "Card":
                        console.log("response >> " + JSON.stringify(response));
                        break;
                    default:
                        console.log("Unknown payment type");
                        break;
                }
                window.location = 'embedded-payment-sample-code-call-ExecutePayment.php?sessionId=' + sessionId;
            }

            function sessionCanceled() {
                console.log("Failed");
            }

            function sessionStarted() {
                console.log("Start");
            }

            //This function enables you to update the amount displayed on Apple Pay and Google Pay after the initiation of the config.
            function updateAmount(amount) {
                myfatoorah.updateAmount(amount);
            }

            //You need to implement here the handling of the callback for Apple Pay
            function paymentAP(response) {
                //Here you need to pass session id to you backend here
                var sessionId = response.sessionId;
                var cardBrandAP = response.card.brand;

                console.log("SessionID via AP >> ", sessionId);
                console.log("cardBrand via AP >> ", cardBrandAP);
                console.log("response via AP >> ", response);
            }

            //You need to implement here the handling of the callback for Google Pay
            function paymentGP(response) {
                //Here you need to pass session id to you backend here
                var sessionId = response.sessionId;
                var cardBrandGP = response.card.brand;

                console.log("SessionID via GP >> ", sessionId);
                console.log("cardBrand via GP >> ", cardBrandGP);
                console.log("response via GP >> ", response);
            }

            //Here you implement the function of clicking on the payment button using your own function
            function submit() {
                console.log("Submit");
                myfatoorah.submitCardPayment(); //It is mandatory to call this function
            }

            //Here you implement the function of clicking on your custom payment button
            function customSubmit() {
                console.log("Custom Submit");
                myfatoorah.submitCardPayment(); //It is mandatory to call this function
            }

            function handleCardBinChanged(response) {
                console.log(response);
            }

            //Here you specify the actions you need to do when customer clicks on your custom Apple Pay button
            function startApplePay() {
                console.log("using custom button");
                myfatoorah.initApplePayPayment(); //It is mandatory to call this function
            }
        </script>
    </body>
</html>

```

#### Call ExecutePayment with SessionID

```php PHP
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
    'apiKey' => '',
    /*
     * Country ISO Code (string)
     * Accepted value: KWT, SAU, ARE, QAT, BHR, OMN, JOD, or EGY. Check https://docs.myfatoorah.com/docs/iso-lookups
     */
    'vcCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest' => true,
];

/* --------------------------- ExecutePayment Endpoint --------------------- */
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
    'InvoiceValue' => $invoiceValue,
    'SessionId'    => $_GET['sessionId'],
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

    //Display the result to your customer
    //Redirect your customer to complete the payment process
    echo '<h3><u>Summary:</u></h3>';
    echo "To pay the invoice ID <b>$invoiceId</b>, click on:<br>";
    echo "<a href='$paymentLink' target='_blank'>$paymentLink</a><br><br>";

    echo '<h3><u>ExecutePayment Response Data:</u></h3><pre>';
    print_r($data);
    echo '</pre>';
} catch (Exception $ex) {
    echo $ex->getMessage();
    die;
}


```

> 📘 Sample Project
>
> You can download the sample project to test it out from [here](https://myfatoorahkw-my.sharepoint.com/:u:/g/personal/tech_myfatoorah_com/EW_L6ZR4JGBElPGHS2d9qPsBXdBhjkAufLb2ylSMA2SxEg?e=67cf9c)

> 📘 Open OTP in iframe
>
> Check the sample project to open the OTP in iframe from [here](https://myfatoorahkw-my.sharepoint.com/:u:/g/personal/tech_myfatoorah_com/EQiFCPgSXoxBo48Bj-K8tJ0BRYDYHIsDh-UxNqfzsXnh_g?e=hDehPB)

## Embedded Payment V2

*`https://docs.myfatoorah.com/docs/embedded-payment` — updated 2026-02-16*

#### **Introduction**

MyFatoorah embedded payment is a new payment integration that enhances customers' user experience. The payment steps will mostly be made from your checkout page, with limited redirection for the end user.\
The implementation is done through a few straightforward steps.

<Embed url="https://www.youtube.com/watch?v=F0ChNmj-VdI" title="My Fatoorah | Embedded Payment" favicon="https://www.youtube.com/favicon.ico" image="https://i.ytimg.com/vi/F0ChNmj-VdI/hqdefault.jpg" provider="youtube.com" href="https://www.youtube.com/watch?v=F0ChNmj-VdI" typeOfEmbed="youtube" html="%3Ciframe%20class%3D%22embedly-embed%22%20src%3D%22%2F%2Fcdn.embedly.com%2Fwidgets%2Fmedia.html%3Fsrc%3Dhttps%253A%252F%252Fwww.youtube.com%252Fembed%252FF0ChNmj-VdI%253Ffeature%253Doembed%26display_name%3DYouTube%26url%3Dhttps%253A%252F%252Fwww.youtube.com%252Fwatch%253Fv%253DF0ChNmj-VdI%26image%3Dhttps%253A%252F%252Fi.ytimg.com%252Fvi%252FF0ChNmj-VdI%252Fhqdefault.jpg%26type%3Dtext%252Fhtml%26schema%3Dyoutube%22%20width%3D%22854%22%20height%3D%22480%22%20scrolling%3D%22no%22%20title%3D%22YouTube%20embed%22%20frameborder%3D%220%22%20allow%3D%22autoplay%3B%20fullscreen%3B%20encrypted-media%3B%20picture-in-picture%3B%22%20allowfullscreen%3D%22true%22%3E%3C%2Fiframe%3E" />

***

#### **Prerequisite**

You need to call InitiateSession Endpoint to get the **SessionId** and **CountryCode** to be used in your configuration. You need to do this for each payment separately. **SessionId** is valid for only one payment.

The endpoint on Swagger is [Payment\_InitiateSession](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_InitiateSession)

```json InitiateSession Response
{
  "IsSuccess": true,
  "Message": "Initiated Successfully!",
  "ValidationErrors": null,
  "Data": {
    "SessionId": "601d963a-2f28-ec11-bae9-000d3aaca798",
    "CountryCode": "KWT"
  }
}
```

***

> 📘 Old Integration
>
> In case of integration with each method separately, you can find the documentation here:
>
> * [Card View Form](https://docs.myfatoorah.com/docs/card-view-form) for Visa/Master, Mada, and Amex
> * [Apple Pay](https://docs.myfatoorah.com/docs/apple-pay)
> * [Google Pay™](https://docs.myfatoorah.com/docs/google-pay-embedded)
> * [STC Pay](https://docs.myfatoorah.com/docs/stcpay)
> * [Sample Code](https://docs.myfatoorah.com/docs/embedded-payment-sample-code)

## Sample Code

*`https://docs.myfatoorah.com/docs/embedded-payment-sample-code` — updated 2026-02-16*

Embedded Payment

This sample code is a complete example of a payment page that uses the **MyFatoorah** embedded payment form.

```html Card View
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card View</title>
    <style></style>
</head>

<body>
    <h1>Card View</h1>

    <script src="https://demo.myfatoorah.com/cardview/v3/session.js"></script>
	<div style="width:400px">
		<div id="card-element"></div>
	</div>
    <button onclick="submit()">Pay Now</button>


    <script>
        var config = {
          countryCode: "", // Here, add your Country Code.
          sessionId: "", // Here you add the "SessionId" you receive from InitiateSession Endpoint.
          cardViewId: "card-element",
          supportedNetworks: "v,m,ae,md", //This field accepts "v,m,ae,md". This will control the cardBrand that the customers can enter.
          												 //v: Visa, m: MasterCard, ae: Amex, md: Mada
          onCardBinChanged: handleBinChanges,
          // The following style is optional.
          style: {
            hideCardIcons: false,
            direction: "ltr",
            cardHeight: 130,
            tokenHeight: 130,
            input: {
              color: "black",
              fontSize: "13px",
              fontFamily: "sans-serif",
              inputHeight: "32px",
              inputMargin: "0px",
              borderColor: "c7c7c7",
              borderWidth: "1px",
              borderRadius: "8px",
              boxShadow: "",
              placeHolder: {
                holderName: "Name On Card",
                cardNumber: "Number",
                expiryDate: "MM / YY",
                securityCode: "CVV",
              }
            },
            text: {
              saveCard: "Save card info for future payment.",
              addCard: "Use another Card!",
              deleteAlert: {
                title: "Delete",
                message: "Test",
                confirm: "yes",
                cancel: "no"
              }
            },
            label: {
              display: false,
              color: "black",
              fontSize: "13px",
              fontWeight: "normal",
              fontFamily: "sans-serif",
              text: {
                holderName: "Card Holder Name",
                cardNumber: "Card Number",
                expiryDate: "Expiry Date",
                securityCode: "Security Code",
              },
            },
            error: {
              borderColor: "red",
              borderRadius: "8px",
              boxShadow: "0px",
            },
          },
      };
      myFatoorah.init(config);

      function submit() {
            myFatoorah.submit()
            // On success
            .then(function (response) {
            // Here you need to pass session id to you backend here
            var sessionId = response.sessionId;
            var card = response.card;
			
						console.log(JSON.stringify(response));
            })
            // In case of errors
            .catch(function (error) {
                console.log(error);
            });
        }
      function handleBinChanges(bin){
				console.log(bin);
			}
    </script>

</body>

</html>
```
```html Apple Pay
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apple Pay</title>
    <style></style>
</head>

<body>
    <h1>Apple Pay</h1>

    <script src="https://demo.myfatoorah.com/applepay/v4/applepay.js"></script>	
	
   <div style="width:400px">
   <div id="card-element"></div>
   </div>
	

   <script>
        var config = {
            sessionId: "", // Here you add the "SessionId" you receive from InitiateSession Endpoint.
            countryCode: "KWT", // Here, add your Country Code.
            currencyCode: "KWD", // Here, add your Currency Code.
            amount: "10", // Add the invoice amount.
            cardViewId: "card-element",
            callback: payment,
            style:{
              frameHeight: 51,
              button: {
                height: "35px",
                type: "Pay", // Accepted texts ["Plain", "Buy", "Pay", "Check Out", "Continue", "Book", "Donate", "Subscribe", "Reload", "Add Money", "Top Up", "Order", "Rent", "Support", "Contribute", "Tip", "Set Up"]
                borderRadius: "8px"
              }
            }
        };

        myFatoorahAP.init(config);

        function payment(response) {
            // Here you need to pass session id to you backend here 
            var sessionId = response.sessionId;
            var cardInformation = response.card;
        };
     
        function updateAmount(amount) {
            myFatoorahAP.updateAmount(amount);
        };
 
   </script>
	
</body>

</html>
```
```html Google Pay
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Pay</title>
    <style></style>
</head>

<body>
    <h1>Google Pay</h1>

    <script src="https://demo.myfatoorah.com/googlepay/v1/googlepay.js"></script>	
	
   <div style="width:400px">
   <div id="card-element"></div>
   </div>
	

   <script>
      var config = {
			sessionId: "", // Here you add the "SessionId" you receive from InitiateSession Endpoint.
			countryCode: "KWT", // Here, add your Country Code.
			currencyCode: "KWD", // Here, add your Currency Code.
			amount: "10", // Add the invoice amount.
			cardViewId: "card-element",
			callback: payment,
			style: {
				frameHeight: 51,
				button: {
					height: "40px",
					text: "pay", // Accepted texts ["book", "buy", "checkout", "donate", "order", "pay", "plain", "subscribe"]
					borderRadius: "8px",
					color: "black", // Accepted colors ["black", "white", "default"]
					language: "en" 
				}
			}
		};
		
		myFatoorahGP.init(config);
     
    	function payment(response) {
   		  	// Here you need to pass session id to you backend here 
      		var sessionId = response.sessionId;
      		var cardBrand = response.cardBrand;
       		var cardIdentifier = response.cardIdentifier;
    	};
     
    	function updateAmount(amount) {
      	  myFatoorahGP.updateAmount(amount);
    	};
   </script>
	
</body>

</html>
```
```html STC Pay
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STC Pay</title>
    <style>
    </style>
    <link rel="icon" href="Asset 1@4x.png">
</head>

<body>
    <h1>STC Pay</h1>

    <script src="https://demo.myfatoorah.com/stcPay/v1/stcpay.js"></script>
    
    <div style="width:800px; margin:auto;">
        <div id="mf-stc-pay"></div>
    </div>

    <script>
        let sessionId = "4693e012-cb21-4963-a7a0-f3848c8fd432";
        let countryCode = "KWT";
        let amount = "1000";

        var configStcPay = {
            sessionId: sessionId,
            countryCode: countryCode,
            amount: amount,
            mobileNumber: "0537974429",
            containerId: "mf-stc-pay",
			sessionStarted:sessionStarted,
            callback: paymentStc,
            style: {
				frameHeight: 180,
                input: {
                    color: "#582490",
                    <!-- fontSize: "20px", -->
                    fontFamily: "'roboto', sans-serif",
                    <!-- inputHeight: "40px", -->

                    borderColor: "#582490",
                    borderWidth: "3px",
                    borderRadius: "15px",

                    placeHolder: {
                        mobileNumber: "05________",
                        otpValue: "Enter your OTP!"
                    }
                },

                button: {
                    borderRadius: "15px",
                    <!-- height: "40px" -->
                }	
            }
        };

        myFatoorahStc.init(configStcPay);
		<!-- myFatoorahStc.submitOtp("1234"); -->

        function paymentStc(response) {
            if (response.isSuccess) {
                console.log(response);
                var sessionId = response.sessionId;
                console.log("SessionID >> ", sessionId);
            } else {
                console.log(response);
            }
        }
		function sessionStarted(response) 
		{ 
		<!-- Here you need to display the OTP UI to the customer -->
			var sessionId = response.sessionId; 
			console.log("response from SessionStarted >> ", response); 
		};
    </script>
</body>
</html>
```
```php PHP
<?php

/* For simplicity check our PHP SDK library here https://myfatoorah.readme.io/php-library */

//PHP Notice:  To enable MyFatoorah auto-update, kindly give the write/read permissions to the library folder
//use zip file
include 'myfatoorah-library-2.2/MyfatoorahLoader.php';
include 'myfatoorah-library-2.2/MyfatoorahLibrary.php';

//use composer
//require 'vendor/autoload.php';
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

/* --------------------------- InitiateSession Endpoint --------------------- */

//------------- Post Fields -------------------------
//Check https://docs.myfatoorah.com/docs/InitiateSession#request-model
$postFields = [
        //Fill optional data
        //'CustomerIdentifier' => 'string', //optional
        //'SaveToken'          => false, //optional
];

//------------- Call the Endpoint -------------------------
try {
    $mfObj = new MyFatoorahPayment($mfConfig);
    $data  = $mfObj->InitiateSession($postFields);

    $sessionId = $data->SessionId;
    $link      = 'https://docs.myfatoorah.com/docs/embedded-payment-sample-code';

    //Display the result to your customer
    echo '<h3><u>Summary:</u></h3>';
    echo "You should draw the card view with session ID <b>$sessionId</b> as described in the link below then call the ExecutePayment Endpoint.<br>";
    echo "<a href='$link' target='_blank'>$link</a><br><br>";

    echo '<h3><u>InitiateSession Response Data:</u></h3><pre>';
    print_r($data);
    echo '</pre>';
} catch (Exception $ex) {
    echo $ex->getMessage();
    die;
}
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Card View Payment</title>
        <style></style>
    </head>

    <body>
        <h1>Card View Payment</h1>

        <script src="https://demo.myfatoorah.com/cardview/v2/session.js"></script>
        <div style="width:400px">
            <div id="card-element"></div>
        </div>
        <button onclick="submit()">Pay Now</button>


        <script>
            var config = {
                countryCode: "<?php echo $data->CountryCode; ?>", // Here, add your Country Code.
                sessionId: "<?php echo $data->SessionId; ?>", // Here you add the "SessionId" you receive from InitiateSession Endpoint.
                cardViewId: "card-element",
                onCardBinChanged: handleBinChanges,
                // The following style is optional.
                style: {
                    hideCardIcons: false,
                    direction: "ltr",
                    cardHeight: 130,
                    tokenHeight: 130,
                    input: {
                        color: "black",
                        fontSize: "13px",
                        fontFamily: "sans-serif",
                        inputHeight: "32px",
                        inputMargin: "0px",
                        borderColor: "c7c7c7",
                        borderWidth: "1px",
                        borderRadius: "8px",
                        boxShadow: "",
                        placeHolder: {
                            holderName: "Name On Card",
                            cardNumber: "Number",
                            expiryDate: "MM / YY",
                            securityCode: "CVV",
                        }
                    },
                    text: {
                        saveCard: "Save card info for future payment.",
                        addCard: "Use another Card!",
                        deleteAlert: {
                            title: "Delete",
                            message: "Test",
                            confirm: "yes",
                            cancel: "no"
                        }
                    },
                    label: {
                        display: false,
                        color: "black",
                        fontSize: "13px",
                        fontWeight: "normal",
                        fontFamily: "sans-serif",
                        text: {
                            holderName: "Card Holder Name",
                            cardNumber: "Card Number",
                            expiryDate: "Expiry Date",
                            securityCode: "Security Code",
                        },
                    },
                    error: {
                        borderColor: "red",
                        borderRadius: "8px",
                        boxShadow: "0px",
                    },
                },
            };
            myFatoorah.init(config);

            function submit() {
                myFatoorah.submit()
                        // On success
                        .then(function (response) {
                            // Here you need to pass session id to you backend here
                            var sessionId = response.sessionId;
                            var cardBrand = response.cardBrand;
                            alert("Call the ExecutePayment endpoint using session Id" + sessionId);
                        })
                        // In case of errors
                        .catch(function (error) {
                            console.log(error);
                        });
            }
            function handleBinChanges(bin) {
                console.log(bin);
            }
        </script>

    </body>

</html>

```

#### Call ExecutePayment with SessionID

```php PHP
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
    'vcCode' => 'KWT',
    /**
     * Test Mode (boolean)
     * Accepted value: true for the test mode or false for the live mode
     */
    'isTest'      => true,
];

/* --------------------------- ExecutePayment Endpoint --------------------- */
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
    'InvoiceValue' => $invoiceValue,
    'SessionId'    => '73ce3faf-e954-4234-b24f-524f4a8f9b23',
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

    //Display the result to your customer
    //Redirect your customer to complete the payment process
    echo '<h3><u>Summary:</u></h3>';
    echo "To pay the invoice ID <b>$invoiceId</b>, click on:<br>";
    echo "<a href='$paymentLink' target='_blank'>$paymentLink</a><br><br>";

    echo '<h3><u>ExecutePayment Response Data:</u></h3><pre>';
    print_r($data);
    echo '</pre>';
} catch (Exception $ex) {
    echo $ex->getMessage();
    die;
}

```

#### Create ExecutePayment method for the OTP to appear in an iframe

```php
//For the full implementation of the card view, review the sample code above
<button onclick="submit()">Pay Now</button>

  //Here you will call the submit() function like we normally do in the card view 
	//when the user clicks on the payment button
	function submit() {
        myFatoorah.submit()
            // On success
            .then(function (response) {
                // Here you need to pass session id to you backend here
                var sessionId = response.sessionId;
                var cardBrand = response.cardBrand;
 
              	//Call the ExecutePaymentCall method that is defined below
                executePaymentCall(sessionId);
            })
           	 // In case of errors
            .catch(function (error) {
                alert(error);
            });
    }
  

//call execute payment method after click pay button
function executePaymentCall(sessionId){
        $.ajax({
            cache: false,
            type: "POST",
            url: "executePayment.php",
            data: {
                sessionId: sessionId
            },
            success: function (response)
            {
                console.log(response);
								
                //Get the iframe that you added in the page
                var iframe = document.getElementById('iframeContainer');
                iframe.src = response;
 
                //Optional: Display the pop up where you will load the iframe
                //modal.style.display = 'block';               
            }
        });
    }
```

## Card View

*`https://docs.myfatoorah.com/docs/card-view-form` — updated 2026-02-16*

Embedded Payment

#### **Introduction**

If you would like your customer to complete the payment on your checkout page without being redirected to another page (except for the **3D Secure** page) and the **PCI DSS** compliance is a blocker, **MyFatoorah** embedded payment feature is your optimum solution.

**MyFatoorah Embedded Card View** is a Javascript library that provides a form for collecting card information. This form can be placed and styled on your checkout page. When your customer enters the correct card data and then submits the form, **MyFatoorah** will collect the card details through IFrame. Then you can smoothly complete the payment using [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment)  endpoint by following the below steps.

> 🚧 Supported Payment Methods
>
> This kind of integration only supports Visa/Master, MADA, and AMEX.

The following is the default **MyFatoorah** embedded payment form used on the checkout page. In the card number input field, gateway icons will be shown indicating the enabled payment methods in your [MyFatoorah account](https://portal.myfatoorah.com/). Note that the supported method icons only will be included.

![Embedded Payment.png](https://files.readme.io/41aba74-Embedded_Payment.png)

***

#### **How it Works**

<Embed url="https://www.youtube.com/watch?v=PH1sHhbmO5o" href="https://www.youtube.com/watch?v=PH1sHhbmO5o" typeOfEmbed="youtube" html="%3Ciframe%20class%3D%22embedly-embed%22%20src%3D%22%2F%2Fcdn.embedly.com%2Fwidgets%2Fmedia.html%3Fsrc%3Dhttps%253A%252F%252Fwww.youtube.com%252Fembed%252FPH1sHhbmO5o%253Ffeature%253Doembed%26display_name%3DYouTube%26url%3Dhttps%253A%252F%252Fwww.youtube.com%252Fwatch%253Fv%253DPH1sHhbmO5o%26image%3Dhttps%253A%252F%252Fi.ytimg.com%252Fvi%252FPH1sHhbmO5o%252Fhqdefault.jpg%26type%3Dtext%252Fhtml%26schema%3Dyoutube%22%20width%3D%22854%22%20height%3D%22480%22%20scrolling%3D%22no%22%20title%3D%22YouTube%20embed%22%20frameborder%3D%220%22%20allow%3D%22autoplay%3B%20fullscreen%3B%20encrypted-media%3B%20picture-in-picture%3B%22%20allowfullscreen%3D%22true%22%3E%3C%2Fiframe%3E" />

> 👍 Before You start
>
> Kindly refer to the prerequisite section in the [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-payment#prerequisite).

The following detailed steps will explain how to add the **MyFatoorah** embedded payment form to your checkout page.

##### 1. Include the Javascript library

Choose the test or the live library according to your working environment.

```html
// Test Environment
<script src="https://demo.myfatoorah.com/cardview/v3/session.js"></script>

// Live Environment For Kuwait, Bahrain, Jordan, and Oman.
<script src="https://portal.myfatoorah.com/cardview/v3/session.js"></script>

// Live Environment For UAE
<script src="https://ae.myfatoorah.com/cardview/v3/session.js"></script>

// Live Environment For Saudi Arabia
<script src="https://sa.myfatoorah.com/cardview/v3/session.js"></script>

// Live Environment For Qatar
<script src="https://qa.myfatoorah.com/cardview/v3/session.js"></script>

// Live Environment For Egypt
<script src="https://eg.myfatoorah.com/cardview/v3/session.js"></script>
```

##### 2. Add the form

You need to define a div element with a unique id attribute. The form will be loaded inside this div after passing the div id to the configuration variable.

```html
<div id="card-element"></div>
```

##### 3. Configure your Card View

Now you need to add the configuration variables. Place the following snippet in a new script tag after loading the library in step 1, and replace the **sessionId** parameter with the "SessionId" you receive from the InitiateSession Endpoint.

For the **countyCode** parameter check our list of [ISO Lookups](https://docs.myfatoorah.com/docs/iso-lookups).

```javascript
var config = {
    countryCode: "", // Here, add your Country Code you receive from InitiateSession Endpoint.
    sessionId: "", // Here you add the "SessionId" you receive from InitiateSession Endpoint.
    cardViewId: "card-element",
    supportedNetworks: "v,m,md,ae" 
};
myFatoorah.init(config);
```

> 📘 SupportedNetworks
>
> This field accepts "v,m,ae,md". This will control the card brands that the customers can enter in the card view.  (v: Visa | m: MasterCard | ae: Amex | md: Mada)

##### 4. Submit Action

After that, you need to handle your submit button to call MyFatoorah **submit()** action, copy the below code to your submit event handler section. Then, you need to pass the sessionId to your back end.

```javascript
      function submit() {
            myFatoorah.submit()
            // On success
            .then(function (response) {
            // Here you need to pass session id to you backend here
            var sessionId = response.sessionId;
            var card = response.card;
			
			console.log(JSON.stringify(response));
            })
            // In case of errors
            .catch(function (error) {
                console.log(error);
            });
        }
```

> 📘 Multi-Currency Embedded Payment
>
> MyFatoorah supports payment through embedded to take place in multiple currencies based on your choice if you have payment methods in these currencies enabled for your account. To select a specific currency for payment processing, pass the desired currency as an argument to the submit function. For example: myFatoorah.submit('USD')
>
> If you pass a currency that is not enabled for your account, the payment will automatically occur in the base currency associated with your account.

##### 5. Call the ExecutePayment Endpoint

Then, you need to send the **SessionId** to your server to process the actual transaction, which should be done in your backend environment using [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint.

> ❗️ Parameters conflict
>
> Do not pass the **PaymentMethodId** parameter in the [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) request and use the **SessionId** parameter instead. As **PaymentMethodId** overwrite **SessionId**.
>
> The "SessionId" you receive from InitiateSession Endpoint can not be used directly in [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) Endpoint.

You will get the PaymentURL in the [Response Model](https://docs.myfatoorah.com/docs/response-model). This payment URL is the **3D Secure** page URL as this type of payment supports only the 3D Secure Flow.  In this case, you should redirect the customer to this URL to complete the 3D Secure challenge.

```json ExecutePayment Request
{
   "SessionId":"36c1bab2-1e21-ec11-bae9-000d3aaca798",
   "InvoiceValue":10,
   "CustomerName":"fname lname",
   "DisplayCurrencyIso":"KWD",
   "MobileCountryCode":"965",
   "CustomerMobile":"12345678",
   "CustomerEmail":"mail@company.com",
   "CallBackUrl":"https://yoursite.com/success",
   "ErrorUrl":"https://yoursite.com/error",
   "Language":"ar",
   "CustomerReference":"noshipping-nosupplier",
   "CustomerAddress":{
      "Block":"string",
      "Street":"string",
      "HouseBuildingNo":"string",
      "AddressInstructions":"string"
   },
   "InvoiceItems":[
      {
         "ItemName":"item name",
         "Quantity":10,
         "UnitPrice":1,
         "Weight":2,
         "Width":3,
         "Height":4,
         "Depth":5
      }
   ]
}
```
```json ExecutePayment Response
{
   "IsSuccess":true,
   "Message":"Invoice Created Successfully!",
   "ValidationErrors":null,
   "Data":{
      "InvoiceId":1040089,
      "IsDirectPayment":true,
      "PaymentURL":"https://demo.MyFatoorah.com/En/KWT/PayInvoice/MpgsAuthentication?paymentId=0706104008982520266&sessionId=SESSION0002087297105E3203998J76",
      "CustomerReference":"noshipping-nosupplier",
      "UserDefinedField":null,
      "RecurringId":""
   }
}
```

> 👍 Payment Status
>
> To update your system automatically instead of manually following up with your customers via [your portal account](https://portal.myfatoorah.com/), you can use the [Webhook](https://docs.myfatoorah.com/docs/webhook) feature or/and set the **CallBackURL**/**ErrorUrl** parameter, that **MyFatoorah** will invoke once the payment is done.

***

#### Open the OTP page in an iframe

This feature enables you to open the OTP page in an iframe without external redirection. This will enable you to keep your customers on your website during the whole payment process.

#### **How it Works**

##### 1. Create the iframe on your website.

The design and creation of the iframe are done from your side. You will open the PaymentUrl in the iframe so that the customer can enter their OTP.

> 📘 Handling the iframe
>
> You will handle fully the iframe from your side. You need to create the iframe and open the PaymentUrl for the OTP in it. You should also handle the cancelation if the customer wants to cancel the payment after the OTP is open and so on.

##### 2. Get the redirection URL after the customer enters his OTP

After the customer completes the 3DS challenge, you will need to use an **event listener** to get the redirection URL. The event listener needs to be added to the page (In a javascript section) on which you will open the iframe.

The name of the sender of the message will be exactly **"MF-3DSecure"**

The redirection URL will be the **CallBackUrl** /**ErrorUrl** that you sent to ExecutePayment in the request appended to it the **PaymentId**.

```coffeescript Redirection URL Format
https://{{Your_CallBackURL}}/?paymentId={{PaymentId}}&Id={{ID}}
```

You will have multiple options to choose from for the next action, whether you want to open the redirection URL in the iframe or close the iFrame and show the result page directly on your website.

```php Retrieve the redirection URL
//The event listener is used to listen to the Redirection URL after the  customer completes the 3DS Challenge
window.addEventListener("message", function (event) { 
        if (!event.data) return;
        try {
            //The redirection URL is returned in the message
            var message = JSON.parse(event.data);
          
            //Proceed only with the following steps if the sender is exactly "MF-3DSecure"
            if (message.sender == "MF-3DSecure") {
              var url = message.url;
            //Here, you need to handle the next action, and here are some suggestions:
            //Redirect the full page to the received URL.
            //Load the received URL in your iframe.
            //Close the iframe and display the result on the same page. You can use AJAX requests to your server with the payment ID to confirm the transaction status (invoke GetPaymentStatus) and display the result accordingly.
            }
        } catch (error) {
            return;
        }
    }, false);	
```

## Hosted Payment Page

*`https://docs.myfatoorah.com/docs/v3-hosted-payment-page` — updated 2026-02-16*

#### **Introduction**

The Hosted Payment Page integration provides a simple and secure way to accept online payments with minimal development effort. Using this integration type, you can display a list of available payment methods configured on your MyFatoorah account, allowing your customers to select their preferred method and complete the transaction through a secure hosted page.

#### **Supported Payment Methods:**

All payment methods are supported (Cards \[Visa, Mastercard, Mada, AMEX], Apple Pay, Google Pay, etc.).

> 📘 Enable Payment Methods
>
> To enable payment methods on your Live account, please contact your [Account Manager](https://www.myfatoorah.com/en/contact-us/).\
> To enable payment methods on your Demo account, kindly send a request to <tech@myfatoorah.com> .

> 🚧 API Version
>
> Always use the **/v3/endpoint** for this integration.\
> For example: <https://apitest.myfatoorah.com/v3/payments>

#### How It Works

The Hosted Payment Page integration can be completed in one API call. The process involves three main steps:

##### Step 1: Display Available Payment Methods

First, you need to get the available payment methods that are enabled for your account. You can retrieve this information from your MyFatoorah dashboard (Commission Charges page).\
Display these payment options to your customers in your application's checkout interface.

##### **Step 2: Create a Payment Request**

You need to send a **POST** request to `/v3/payments` with the selected payment method and order details.\
This request will return a **PaymentUrl**, which you should use to redirect your customer to the hosted payment page.

**Endpoint:** `POST /v3/payments` ([Create Payment](https://docs.myfatoorah.com/reference/create-payment))

```json Request Example
{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 23
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
        "InvoiceId": "6148108",
        "PaymentId": null,
        "PaymentURL": "https://demo.MyFatoorah.com/KWT/ie/050754719614810863-ce9138bf",
        "PaymentCompleted": false,
        "TransactionDetails": null
    }
}
```

After receiving the PaymentURL, **redirect** your customer to the PaymentURL to complete the payment on the MyFatoorah-hosted page.

> 📘 Note
>
> Use uppercase with underscores for payment method naming, for example: "CARD", "APPLE\_PAY", "GOOGLE\_PAY", "KNET", "BENEFIT", "STC\_PAY", etc.

##### **Step 3: Inquire Payment Status**

After the payment is completed, **MyFatoorah will redirect the customer to your Redirection URL** (the one provided in Step 2) and append a `paymentId` as a query parameter.\
Example: <https://your-website.com/payment-callback?paymentId=100201923790872553>\
You should then call the **GET`/v3/payments/{paymentId}`** endpoint ([Get Payment Details](https://docs.myfatoorah.com/reference/get-payment-details)) to check the payment status and get the full invoice and transaction details.

```text Request Example
GET /v3/payments/07076148071303658773
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "Invoice": {
            "Id": "6389491",
            "Status": "PAID",
            "Reference": "2025060917",
            "CreationDate": "2025-12-24T14:48:17.1230000Z",
            "ExpirationDate": "2026-06-22T14:48:17.1230000Z",
            "ExternalIdentifier": null,
            "UserDefinedField": "",
            "MetaData": null
        },
        "Transaction": {
            "Id": "102585",
            "Status": "SUCCESS",
            "PaymentMethod": "VISA/MASTER",
            "PaymentId": "07076389491322460173",
            "ReferenceId": "535814102585",
            "TrackId": "24-12-2025_3224601",
            "AuthorizationId": "102585",
            "TransactionDate": "2025-12-24T14:51:06.5630000Z",
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
                "NameOnCard": "das",
                "Number": "512345xxxxxx0008",
                "Token": "",
                "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                "ExpiryMonth": "12",
                "ExpiryYear": "34",
                "Brand": "Mastercard",
                "Issuer": "Test Bank",
                "IssuerCountry": "KWT",
                "FundingMethod": "credit"
            }
        },
        "Customer": {
            "Reference": "",
            "Name": "Anonymous",
            "Mobile": "+201020304050",
            "Email": "xeraxe9309@fftube.com"
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

> 👍 Webhook
>
> We recommended enabling the [Webhook ](https://docs.myfatoorah.com/edit/webhook)feature to automatically notify your system when a transaction status changes in your application.

#### Sequence Diagram

![Hosted Payment Page integration](https://files.readme.io/f38113f1aadda9511c1f10b2099a20fad81bbcfd7b15abee41db0f978133a8d3-HOSTED_PAYMENT_PAGE.png)

> 📘 Old Integration (V2)
>
> In case you are using the integration for v2, you can find the documentation here:
>
> * [Gateway Integration](https://docs.myfatoorah.com/docs/gateway-integration)
> * [InitiatePayment](https://docs.myfatoorah.com/docs/initiate-payment)
> * [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment)
> * [Sample Code](https://docs.myfatoorah.com/docs/gateway-integration-sample-code)

## Gateway Integration

*`https://docs.myfatoorah.com/docs/gateway-integration` — updated 2026-02-16*

Integrate your applications with simple calls

#### **Introduction**

This is the most commonly known type of integration that you need to accelerate your business applications. The payment gateway integration will be done in simple few steps, minimum development efforts, and with various capabilities. Here, you display to your customer a list of the enabled and available gateways at your portal account. So, they can select which one suitable for their needs and they will be redirected directly to the gateway page to finish their payment.

***

#### **How it Works**

From a technical point of view, you have to get this integration done within your application with two simple calls to our API. The two calls to achieve this will be:

1. Call [InitiatePayment](https://docs.myfatoorah.com/docs/initiate-payment) endpoint (optional step):\
   This step will simply return all available and enabled [Payment Methods](https://myfatoorah.readme.io/v2.0/docs/payment-methods) of your portal account with the commission charge that the customer may pay on the gateway. You can call it once and store the information at your end, then use that information in the next call.

2. Call [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint:\
   This step will create a payment link at **MyFatoorah** platform by using the **PaymentMethodId** parameter. It will return a payment URL. After that, use this payment URL to redirect your customers to the payment page.

***

#### **Initiate Payment**

It is the preparation step that enables your system to collect all needed information about the available payment gateways. You should provide two parameters in your request body as follows:

* **InvoiceAmount**\
  This is the total amount of the invoice. You should give the correct invoice amount after applying coupon code, taxes, redeem, fare updates, and so on. So that, you can have the correct service charge. If you will deduct the service charge from your customers, you need display its value to your buyers before processing the payment.

* **CurrencyIso**\
  This is the currency of the given invoice amount.

The response of the [InitiatePayment](https://docs.myfatoorah.com/docs/initiate-payment) endpoint contains information about all enabled [Payment Methods](https://myfatoorah.readme.io/v2.0/docs/payment-methods) at your portal account, as described in the following parameters:

* **PaymentMethodId**\
  It will be used in the [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint as the value of its **PaymentMethodId** parameter.
* **IsDirectPayment**\
  If the value is true, you need to use the [Direct Payment](https://docs.myfatoorah.com/docs/direct-payment) flow.
* **PaymentMethodAr**, **PaymentMethodEn**, **PaymentMethodCode**, and **ImageUrl**\
  This information can be displayed to your customers to select which gateway they want to use.
* **ServiceCharge**, **CurrencyIso**\
  The **ServiceCharge** in this **CurrencyIso** can be used ONLY in displaying the service charge to the customer before payment processing. It was calculated based on the provided **InvoiceAmount** parameter and the current rate of **MyFatoorah** exchange rate.
* **TotalAmount**\
  The **TotalAmount** is the **InvoiceAmount** after applying the **ServiceCharge** to it.  Also, it is the total amount that your customers will be charged with. From your [Myfatoorah vendor account](https://portal.myfatoorah.com/), you can set the service charge or commission charge to be on the vendor, customer, or split with the customer. If it is set to be on the customer or split with the customer, you should give the correct **InvoiceAmount** after applying coupon code, taxes, redeem, fare updates, and so on, so that you can have the right **ServiceCharge** to display its value to your customers before processing the payment. If it is set to be on the vendor, **ServiceCharge** will have a value but it will not be added to the **TotalAmount**, and there is no need to pass the correct **InvoiceAmount**.

Follow the below steps to change the **Commission Charges** settings:

1. Log in to [the Myfatoorah vendor account](https://portal.myfatoorah.com/) using your **Super Master Account**.
2. Navigate to Profile → Commission Charges
3. From the **Payment Methods** list, choose the gateway that you want to determine from whom to deduct the **Commission Charges**.

![knet-commission-charges.png](https://files.readme.io/6697b9c-knet-commission-charges.png)

> 👍 Initiate Payment
>
> As a good practice, you don't have to call the [InitiatePayment](https://docs.myfatoorah.com/docs/initiate-payment) endpoint every time you need to execute payment, but you have to call it at least once to save the **PaymentMethodId** parameter that you will need in calling [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint. This practice is only valid if you don't care about the service charges calculations.

***

#### **Execute Payment**

Calling [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint is the actual payment execution step before proceeding to the payment gateway for the transaction to be done. This will redirect the buyers to the gateway to complete their payment process.

The [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint requires two mandatory parameters: (**PaymentMethodId**, **InvoiceValue**).

The "PaymentMethodId" parameter can be obtained from the [InitiatePayment](https://docs.myfatoorah.com/docs/initiate-payment) endpoint as described above.

> 👍 Payment Status
>
> In this communication, **MyFatoorah** invokes the **CallBackURL**/**ErrorUrl** parameter once the payment is done, and this will allow you to redirect the customer to your receipt page, and update the payment status accordingly.

Once a transaction is done against a certain invoice, **MyFatoorah** initiates a call to the **CallBackURL** or **ErrorUrl** and provides a **PaymentId** as a GET parameter in the URL. The call is something like:\
<http://www.domain.com/myurl?paymentId=100201923790872553>

Then you have to call [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint and pass the **PaymentId** to it, this will give you full information about the invoice and the transaction. This is a very important step and a good practice to ensure the payment response returned from the **MyFatoorah** end and is verified for more secure connectivity. You will find full details on how to implement the [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint on the [Payment Inquiry](https://docs.myfatoorah.com/docs/payment-inquiry) section.

> 📘 Webhook
>
> Also, you can use the [Webhook](https://docs.myfatoorah.com/docs/webhook) feature, to notify your system when a transaction status changed event happens in your application.

This endpoint is also used in other integration types like [Direct Payment](https://docs.myfatoorah.com/docs/direct-payment), [Tokenization](https://docs.myfatoorah.com/docs/tokenization), and [Recurring Payment](https://docs.myfatoorah.com/docs/recurring-payment).

***

## Sample Code

*`https://docs.myfatoorah.com/docs/gateway-integration-sample-code` — updated 2026-02-16*

InitiatePayment and ExecutePayment

As described earlier for the [Gateway Integration](https://docs.myfatoorah.com/docs/gateway-integration), we are going to have a sample code to consume this endpoint to make a successful integration.

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

namespace ExecutePayment
{
    class Program
    {
        // You can get test token from this page  https://myfatoorah.readme.io/docs/test-token
        static string token = "";
        static string baseURL = "https://apitest.myfatoorah.com";
        static async Task Main(string[] args)
        {
            var intiateResponse = await InitiatePayment().ConfigureAwait(false);
            Console.WriteLine("Initiate Payment Response :");
            Console.WriteLine(intiateResponse);
           
            var executeResponse = await ExecutePayment().ConfigureAwait(false);
            Console.WriteLine("Execute Payment Response :");
            Console.WriteLine(executeResponse);

            Console.ReadLine();
        }

        public static async Task<string > InitiatePayment()
        {
            var intiatePaymentRequest = new
            {
                InvoiceAmount = 100,
                CurrencyIso = "kwd"
            };

            var intitateRequestJSON = JsonConvert.SerializeObject(intiatePaymentRequest);
            return await PerformRequest(intitateRequestJSON, "InitiatePayment").ConfigureAwait(false);

        }

        public static async Task<string> ExecutePayment()
        {
            var executePaymentRequest = new
            {
                //required fields
                PaymentMethodId = "2",
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
                // to add suppliers
                Suppliers = new[] {
                        new {
                          SupplierCode = 1, InvoiceShare = 1000, ProposedShare = 500
                        }
                 }

            };
            var executeRequestJSON = JsonConvert.SerializeObject(executePaymentRequest);
            return await PerformRequest(executeRequestJSON, "ExecutePayment").ConfigureAwait(false);
        }
        public static async Task<string> PerformRequest(string requestJSON,string endPoint)
        {
            string url =  baseURL+ $"/v2/{endPoint}";
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
api_key = "MyTokenKey"  # Test token value to be placed here: https:#myfatoorah.readme.io/docs/test-token

### Live Environment
### base_url = "https:#api.myfatoorah.com"
### api_key = "mytokenvalue" #Live token value to be placed here: https:#myfatoorah.readme.io/docs/live-token


### Initaite Payment request data
initiatepay_request = {
                    "InvoiceAmount": 100,
                    "CurrencyIso": "KWD"
                    }


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
                         "InvoiceValue"    : 50,
                         "CallBackUrl"     : "https://example.com/callback.php",
                         "ErrorUrl"        : "https://example.com/callback.php",
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
```ruby
####### Initiate Payment ######
require 'net/http'
require 'uri'
require 'json'

uri = URI.parse("https://apitest.myfatoorah.com/v2/InitiatePayment")
token = 'mytokenvalue' #token value to be placed here
header = {'Authorization':token} 

body = {'InvoiceAmount': 100,'CurrencyIso':'KWD'}
### Create the HTTP objects
http = Net::HTTP.new(uri.host, uri.port)
http.use_ssl = true

request = Net::HTTP::Post.new(uri.request_uri, header)
request["Content-Type"] = 'application/json'
request.body = body.to_json

### Send the request
response = http.request(request)
puts response.read_body



####### ExecutePayment payment ######
require 'net/http'
require 'uri'
require 'json'
uri = URI.parse("https://apitest.myfatoorah.com/v2/ExecutePayment")
header = {'Authorization':token}

body = {
  'PaymentMethodId': '2',
  'CustomerName': 'Ahmed',
  'DisplayCurrencyIso': 'KWD',
  'MobileCountryCode': '+965',
  'CustomerMobile': '12345678',
  'CustomerEmail': 'xx@yy.com',
  'InvoiceValue': 100,
  'CallBackUrl': 'https://google.com',
  'ErrorUrl': 'https://yahoo.com',
  'Language': 'en',
  'CustomerReference': 'ref 1',
  'CustomerCivilId': 12345678,
  'UserDefinedField': 'Custom field',
  'ExpireDate': '',
  'CustomerAddress': {
    'Block': '',
    'Street': '',
    'HouseBuildingNo': '',
    'Address': '',
    'AddressInstructions': ''
  },
  'InvoiceItems': [
    {
      'ItemName': 'Product 01',
      'Quantity': 1,
      'UnitPrice': 100
    }
  ]
}

### Create the HTTP objects
http = Net::HTTP.new(uri.host, uri.port)
http.use_ssl = true

request = Net::HTTP::Post.new(uri.request_uri, header)
request["Content-Type"] = 'application/json'
request.body = body.to_json

### Send the request
response = http.request(request)
puts response.read_body
```
```javascript Node.js
console.log('#################### InitiatePayment ########################');
var request = require("request");
var token = 'mytokenvalue' //token value to be placed here;
var baseURL = 'https://apitest.myfatoorah.com';
var options = { method: 'POST',
  url: baseURL+'/v2/InitiatePayment',
  headers: 
   { Accept: 'application/json',
     Authorization: 'Bearer '+token,
     'Content-Type': 'application/json' },
  body: { InvoiceAmount: 100, CurrencyIso: 'KWD' },
  json: true };

request(options, function (error, response, body) {
  if (error) throw new Error(error);
  console.log(body);

});
  console.log('#################### ExecutePayment ########################');

var options = { method: 'POST',
  url: baseURL+'/v2/ExecutePayment',
  headers: 
   { Accept: 'application/json',
   Authorization: 'Bearer '+token,
   'Content-Type': 'application/json' },
  body: 
   { PaymentMethodId: '2',
     CustomerName: 'Ahmed',
     DisplayCurrencyIso: 'KWD',
     MobileCountryCode: '+965',
     CustomerMobile: '12345678',
     CustomerEmail: 'xx@yy.com',
     InvoiceValue: 100,
     CallBackUrl: 'https://google.com',
     ErrorUrl: 'https://google.com',
     Language: 'en',
     CustomerReference: 'ref 1',
     CustomerCivilId: 12345678,
     UserDefinedField: 'Custom field',
     ExpiryDate: '',
     CustomerAddress: 
      { Block: '',
        Street: '',
        HouseBuildingNo: '',
        Address: '',
        AddressInstructions: '' },
     InvoiceItems: [ { ItemName: 'Product 01', Quantity: 1, UnitPrice: 100 } ] },
  json: true };

request(options, function (error, response, body) {
  if (error) throw new Error(error);
  console.log(body);

});
```

## Invoicing

*`https://docs.myfatoorah.com/docs/v3-invoicing` — updated 2026-02-16*

#### Introduction

In this type of integration, we are providing a simple, straightforward integration. It will allow you to generate an invoice link that can be sent by any channel we support.

This will facilitate your collection if you have non-store platforms and would like to introduce a niche to your business. You can decide how are you going to send the payment link either by SMS, email, redirect the customer to the invoice link, or use all of them.

This is so helpful in many business cases. Let's explain an example of such cases that can utilize this integration. In a rent collection case, all you need is to give your customers an invoice link to pay the due rent at their convenience. This simply can be achieved with this type of integration, based on the due dates of the collection, and within your application, you can call this endpoint and send all information and the amount you need to collect, then MyFatoorah will send this payment link via the provided channel. The thing here is that you are giving time to your customers to pay once they can.

#### How It Works

##### Step 1: Create a Payment Request

To generate an invoice, send a POST request to the endpoint: `POST /v3/payments` ([Create Payment](https://docs.myfatoorah.com/reference/create-payment))

In this request, you don’t need to set the PaymentMethod value. Just specify how the customer should receive the invoice link using the NotificationOption field.

**NotificationOption Values**

| Value     | Behavior                                                                                       | Required Customer Fields                                |
| --------- | ---------------------------------------------------------------------------------------------- | ------------------------------------------------------- |
| **EMAIL** | Sends the invoice link by **email only**                                                       | `Customer.Email`                                        |
| **SMS**   | Sends the invoice link by **SMS only**                                                         | `Customer.Mobile.CountryCode`, `Customer.Mobile.Number` |
| **LINK**  | Returns only the invoice link in the API **response**                                          | None                                                    |
| **ALL**   | Sends the invoice link by **both email and SMS**, and also returns it in the **response body** | Email + Mobile fields                                   |

```json Sample Request
{
  "Order": {
    "Amount": 20
  },
  "Customer": {
    "Mobile": {
      "CountryCode": "+20",
      "Number": "1020304050"
    },
    "Email": "example@gmail.com"
  },
  "IntegrationUrls": {
    "Redirection": "https://your-website.com/payment-callback"
  },
  "NotificationOption": "ALL"
}
```
```json Response
{
  "IsSuccess": true,
  "Message": "",
  "ValidationErrors": null,
  "Data": {
    "InvoiceId": "6323179",
    "PaymentId": null,
    "PaymentURL": "https://demo.MyFatoorah.com/KWT/ie/01072632317941-057d0668",
    "PaymentCompleted": false,
    "TransactionDetails": null
  }
}
```

##### Step 2: Inquire Payment Status

After the customer completes the payment, MyFatoorah will redirect the user back to your Redirection URL, appending a paymentId query parameter.

Example: <https://your-website.com/payment-callback?paymentId=100201923790872553>

You need then call: `GET /v3/payments/{paymentId}` ([Get Payment Details](https://docs.myfatoorah.com/reference/get-payment-details)) to verify the payment and retrieve the full invoice and transaction details.

```Text Request Example
GET /v3/payments/07076323179317752773
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "Invoice": {
            "Id": "6389491",
            "Status": "PAID",
            "Reference": "2025060917",
            "CreationDate": "2025-12-24T14:48:17.1230000Z",
            "ExpirationDate": "2026-06-22T14:48:17.1230000Z",
            "ExternalIdentifier": null,
            "UserDefinedField": "",
            "MetaData": null
        },
        "Transaction": {
            "Id": "102585",
            "Status": "SUCCESS",
            "PaymentMethod": "VISA/MASTER",
            "PaymentId": "07076389491322460173",
            "ReferenceId": "535814102585",
            "TrackId": "24-12-2025_3224601",
            "AuthorizationId": "102585",
            "TransactionDate": "2025-12-24T14:51:06.5630000Z",
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
                "NameOnCard": "das",
                "Number": "512345xxxxxx0008",
                "Token": "",
                "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                "ExpiryMonth": "12",
                "ExpiryYear": "34",
                "Brand": "Mastercard",
                "Issuer": "Test Bank",
                "IssuerCountry": "KWT",
                "FundingMethod": "credit"
            }
        },
        "Customer": {
            "Reference": "",
            "Name": "Anonymous",
            "Mobile": "+201020304050",
            "Email": "xeraxe9309@fftube.com"
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

> 👍 Webhook
>
> We recommended enabling the [Webhook ](https://docs.myfatoorah.com/edit/webhook)feature to automatically notify your system when a transaction status changes in your application.

> 📘 Note
>
> The IntegrationUrls.Redirection field is optional.
> If omitted, customers will remain on the MyFatoorah result page after completing payment.

> 📘 Old Integration (V2)
>
> In case you are using the integration for v2, you can find the documentation here:
>
> * [Invoice Link](https://docs.myfatoorah.com/docs/invoice-link)
> * [SendPayment](https://docs.myfatoorah.com/docs/send-payment)
> * [Sample Code](https://docs.myfatoorah.com/docs/invoice-link-sample-code)

## Invoice Link

*`https://docs.myfatoorah.com/docs/invoice-link` — updated 2026-02-16*

Send the link, get the payment!

#### **Introduction**

In this type of integration, we are providing a simple straightforward [SendPayment](https://docs.myfatoorah.com/docs/send-payment) endpoint. It will allow you to generate an invoice link that can be sent by any channel we support.

This will facilitate your collection if you have non-store platforms and would like to introduce a niche to your business. You can decide how are you going to send the payment link either by SMS, email, redirect the customer to the invoice link, or use all of them.

This is so helpful in many business cases, let's explain an example of such cases that can utilize this integration. In a rent collection case, all you need is to give your customers an invoice link to pay the due rent at their convenience. This simply can be achieved with this type of integration, based on the due dates of the collection, and within your application, you can call this endpoint and send all information and the amount you need to collect, then **MyFatoorah** will send this payment link via the provided channel. The thing here is that you are giving time to your customers to pay once they can.

***

#### **How it Works**

The [SendPayment](https://docs.myfatoorah.com/docs/send-payment) endpoint requires three mandatory parameters: (**NotificationOption**, **CustomerName**, **InvoiceValue**).

You need to set the **NotificationOption** parameter as described below:

* **EML** send the invoice link by email only. You should provide the CustomerEmail parameter as well.
* **SMS** send the invoice link by SMS only. You should provide the CustomerMobile and MobileCountryCode as well.
* **LNK** returns only the invoice URL through the response.
* **ALL** send the invoice link by both email and SMS, also the invoice link will be in the response body. You have to provide all the needed parameters.

> 👍 Payment Status
>
> To update your system automatically instead of manually following up with your customers via [your portal account](https://portal.myfatoorah.com/), you can use the [Webhook](https://docs.myfatoorah.com/docs/webhook) feature or/and set the **CallBackURL**/**ErrorUrl** parameter, that **MyFatoorah** will invoke once the payment is done.

If you choose to set the **CallBackURL**/**ErrorUrl** parameter, **MyFatoorah** initiates a call to this URL once a transaction is done against a certain invoice. In addition, **MyFatoorah** provides a **PaymentId** as a query string parameter in the URL. The call is something like:\
<http://www.domain.com/myurl?paymentId=100201923790872553>

Then, you have to call the [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint and pass the **PaymentId** to it, this will give you full information about the invoice and the transaction. This is a very important step and a good practice to ensure the payment response is returned from **MyFatoorah**'s end and is verified for more secure connectivity. You will find full details on how to implement the [GetPaymentStatus](https://docs.myfatoorah.com/docs/get-payment-status) endpoint in the [Payment Inquiry](https://docs.myfatoorah.com/docs/payment-inquiry) section.

> 📘 Hint
>
> The **CallBackURL** /**ErrorUrl** parameters are optional. If you omit them, the customers will be redirected to **MyFatoorah** invoice page after payment.

***

## Sample Code

*`https://docs.myfatoorah.com/docs/invoice-link-sample-code` — updated 2026-02-16*

SendPayment

As described earlier for the [Invoice Link](https://docs.myfatoorah.com/docs/invoice-link), we are going to have a sample code to consume this endpoint to make a successful integration.

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
        //'InvoiceItems'       => $invoiceItems,
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
using System.Collections.Generic;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Threading.Tasks;

namespace SendPayment
{
    class Program
    {
        // You can get test token from this page  https://myfatoorah.readme.io/docs/test-token
        static string token = "";
        static string baseURL = "https://apitest.myfatoorah.com";

        static async Task Main(string[] args)
        {
            var response = await SendPayment().ConfigureAwait(false);
            Console.WriteLine("Send Payment Response :");
            Console.WriteLine(response);

            Console.ReadLine();
        }
        public static async Task<string> SendPayment()
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
               // add suppliers
                Suppliers = new[] {
                        new {
                          SupplierCode = 1, InvoiceShare = 100, ProposedShare = 70
                        }
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
### api_key = "MyTokenValue" #Live token value to be placed here: https://myfatoorah.readme.io/docs/live-token

### SendPayment Request
sendpay_data = {
                "CustomerName": "name",  # Mandatory Field ("string")
                "NotificationOption": "LNK",  # Mandatory Field ("LNK", "SMS", "EML", or "ALL")
                "InvoiceValue": 100,  # Mandatory Field (Number)
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
```ruby
###########Send Payment###########
require 'net/http'
require 'uri'
require 'json'

uri = URI.parse("https://apitest.myfatoorah.com/v2/SendPayment")
token = 'mytokenvalue' #token value to be placed here
header = {'Authorization':token} 

body = {
  'CustomerName': 'Ahmed',
  'NotificationOption': 'ALL',
  'MobileCountryCode':'+965',
  'CustomerMobile': '12345678',
  'CustomerEmail': 'xx@yy.com',
  'InvoiceValue': 100,
  'DisplayCurrencyIso': 'KWD',
  'CallBackUrl': 'https://google.com',
  'ErrorUrl': 'https://yahoo.com',
  'Language': 'en',
  'CustomerReference': 'ref 1',
  'CustomerCivilId':12345678,
  'UserDefinedField': 'Custom field',
  'ExpireDate': '',
  'CustomerAddress': {
      'Block':'',
      'Street':'',
      'HouseBuildingNo':'',
      'Address':'',
      'AddressInstructions':''
  },
  'InvoiceItems': [
    {
      'ItemName': 'Product 01',
      'Quantity': 1,
      'UnitPrice': 100
    }
  ]
}



### Create the HTTP objects
http = Net::HTTP.new(uri.host, uri.port)
http.use_ssl = true

request = Net::HTTP::Post.new(uri.request_uri, header)
request["Content-Type"] = 'application/json'
request.body = body.to_json


### Send the request
response = http.request(request)
puts response.read_body
parsed = JSON.parse(response.body)

invoiceURL = parsed['Data']['InvoiceURL']
puts invoiceURL
```
```javascript Node.js
console.log('#################### Send Payment########################');
var request = require("request");
var token = 'mytokenvalue' //token value to be placed here;
var baseURL = 'https://apitest.myfatoorah.com';

var options = { method: 'POST',
  url: baseURL+'/v2/SendPayment',
  headers: 
   { Accept: 'application/json',
     Authorization: 'Bearer '+token,
     'Content-Type': 'application/json' },
  body: 
   { NotificationOption: 'ALL',
     CustomerName: 'Ahmed',
     DisplayCurrencyIso: 'KWD',
     MobileCountryCode: '+965',
     CustomerMobile: '12345678',
     CustomerEmail: 'xx@yy.com',
     InvoiceValue: 100,
     CallBackUrl: 'https://google.com',
     ErrorUrl: 'https://google.com',
     Language: 'en',
     CustomerReference: 'ref 1',
     CustomerCivilId: 12345678,
     UserDefinedField: 'Custom field',
     ExpireDate: '',
     CustomerAddress: 
      { Block: '',
        Street: '',
        HouseBuildingNo: '',
        Address: '',
        AddressInstructions: '' },
     InvoiceItems: [ { ItemName: 'Product 01', Quantity: 1, UnitPrice: 100 } ] },
  json: true };

request(options, function (error, response, body) {
  if (error) throw new Error(error);
  console.log(body);
});
```

## Direct Payment

*`https://docs.myfatoorah.com/docs/v3-direct-payment` — updated 2026-02-16*

#### Introduction

This integration allows you to collect card details from the customer and use these details to process the payments within your page.
The Direct Payment integration provides two payment process flows: 3D Secure Flow and Non-3D Secure Flow. So what is 3D Secure? For extra fraud protection, 3D Secure requires customers to complete an additional verification step with the card issuer when paying. Typically, you direct the customer to an authentication page on their bank’s website, and they enter a password associated with the card or a code sent to their phone.

#### Prerequisite

> 🚧 Important
>
> To use this Integration, your system must be [PCI DSS certified](https://www.pcisecuritystandards.org/about_us/). This certification is required before using this integration.\
> After that, kindly contact your [account manager](https://www.myfatoorah.com/en/contact-us/) or sales representative to activate the Direct Payment feature.
> If you are not PCI DSS certified, we recommend using our [Embedded Integration](https://docs.myfatoorah.com/docs/embedded-payment-v3), which provides flexibility and tokenization without requiring PCI compliance.

> 🚧 Payment Method
>
> Please, note that not all [Payment Methods](https://docs.myfatoorah.com/docs/payment-methods) are supporting the Direct Payment feature, please refer to your [account manager](https://www.myfatoorah.com/en/contact-us/) for more details.

> 📘 Old Integration (V2)
>
> In case you are using the integration for v2, you can find the documentation here:
>
> * [Direct Payment](https://docs.myfatoorah.com/docs/direct-payment)
> * [Direct Payment Endpoint](https://docs.myfatoorah.com/docs/direct-payment-endpoint)
> * [Sample Code](https://docs.myfatoorah.com/docs/direct-payment-sample-code)

<br />

## DirectPayment

*`https://docs.myfatoorah.com/docs/direct-payment-endpoint` — updated 2026-07-23*

Endpoint

#### **Overview**

The "DirectPayment" endpoint is a POST request. It is used to facilitate the payment process for your buyers. Detailed functionality of how to use this endpoint is explained in the [Direct Payment](https://docs.myfatoorah.com/docs/direct-payment) and [Tokenization](https://docs.myfatoorah.com/docs/tokenization) sections.

The endpoint on Swagger is: [Payment\_DirectPaymentAsync](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_DirectPaymentAsync).

Now, we are going to declare the endpoint and its models along with each accepted parameter and possible value.

> 📘 **Request Header**
>
> Add **"Authorization": "Bearer ST requ"** to request header. Token of demo configuration can be found [here](https://docs.myfatoorah.com/docs/test-token).

***

#### **Request Model**

The request is a POST request with the following parameters:

| Input Parameter | Type                                    | Description                                                       |
| :-------------- | :-------------------------------------- | :---------------------------------------------------------------- |
| **PaymentType** | string                                  | It accepts 2 values as a string: "card", and "token".             |
| **Bypass3DS**   | boolean, optional                       | Specify either this transaction should be verified by 3DS or not. |
| **SaveToken**   | boolean, optional                       | "true" or "false" to save card data and returns a token.          |
| **Token**       | string, optional                        | The token value that needs to execute the transaction against     |
| **Card**        | [CardModel](#cardmodel) array, optional |                                                                   |

> 📘 **Card or Token Parameters**
>
> You have to provide at least one value of the two parameters:
>
> - You have to provide [Card Model](#cardmodel) data if the **PaymentType** parameter is "card".
> - You have to provide the **Token** parameter value if the **PaymentType** parameter is "token".

#### CardModel

| Input Parameter  | Type   | Description                                                                   |
| :--------------- | :----- | :---------------------------------------------------------------------------- |
| **Number**       | string | Represents the 16 digits of the card that will be charged for the transaction |
| **ExpiryMonth**  | string | Card expiry month                                                             |
| **ExpiryYear**   | string | Card expiry year                                                              |
| **SecurityCode** | string | Card CVV / CVC                                                                |
| **HolderName**   | string | Name on card                                                                  |

***

#### **Response Model**

After viewing the [Response Model](https://docs.myfatoorah.com/docs/response-model) that you will get as a result of your request, here, you will find full details about the **Data** Model of this API endpoint. Let's check it and its contents.

| Response Field   | Type                                  | Description                                                                    |
| :--------------- | :------------------------------------ | :----------------------------------------------------------------------------- |
| **Status**       | string                                | The transaction status in Non-3DS flow                                         |
| **ErrorMessage** | string                                | In case of error, the error message that is returned from the gateway.         |
| **PaymentId**    | string                                | The payment ID that is associated for the transaction.                         |
| **Token**        | string                                | In case of "SaveToken" is requested, this value returns the token of the card. |
| **PaymentURL**   | string                                | The OTP link. You should redirect your customer to this page.                  |
| **CardInfo**     | [CardInfoModel](#cardinfomodel) array |                                                                                |

#### CardInfoModel

| Response Field  | Type   | Description                                                                        |
| :-------------- | :----- | :--------------------------------------------------------------------------------- |
| **Number**      | string | The card number that executed the payment, displays on the first and last 4 digits |
| **ExpiryMonth** | string | Card expiry month                                                                  |
| **ExpiryYear**  | string | Card expiry year                                                                   |
| **Brand**       | string | Card brand (VISA or Master)                                                        |
| **Issuer**      | string | The card issuer bank                                                               |

***

<br />

## Sample Code | Direct Payment

*`https://docs.myfatoorah.com/docs/direct-payment-sample-code` — updated 2026-02-16*

DirectPayment

As described earlier for the [Direct Payment](https://docs.myfatoorah.com/docs/direct-payment), we are going to have a sample code to consume this endpoint to make a successful integration.

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
```csharp
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using System;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Threading.Tasks;

namespace DirectPayment
{
    class Program
    {
        // You can get test token from this page  https://myfatoorah.readme.io/docs/test-token
        static string token = "";
        static string baseURL = "https://apitest.myfatoorah.com";
        static async Task Main(string[] args)
        {
            //get url from execute payment for payment method support direct payment
            // url will be like https://apitest.myfatoorah.com/v2/DirectPayment/0106266521736/48
			var executePaymentEndPoint = "ExecutePayment";
			var excutePaymentRequest = new
			{
				InvoiceValue = 10,
				PaymentMethodId = 20, //Put here payment method id
				// any additional fields
		 
			};		 
			var excutePaymentRequestJSON = JsonConvert.SerializeObject(excutePaymentRequest);
			var executePaymentResponse = await ExecutePayment(requestJSON: excutePaymentRequestJSON, endPoint: executePaymentEndPoint).ConfigureAwait(false);
			Console.WriteLine("ExecutePayment Response :");
			Console.WriteLine(executePaymentResponse);
			
            string paymentUrl = executePaymentResponse.Data.PaymentURL;
            var directPaymentResponse = await DirectPayment(paymentUrl).ConfigureAwait(false);
            Console.WriteLine("Direct Payment Response :");
            Console.WriteLine(directPaymentResponse);
          
            Console.ReadLine();
        }
        public static async Task<string> DirectPayment(string paymentUrl)
        {
            var directPaymentRequest = new
            {
                PaymentType = "Card",
                SaveToken = false,
                Card = new
                {
                    Number = "5123450000000008",
                    ExpiryMonth = "05",
                    ExpiryYear = "21",
                    SecurityCode = "100",
                    HolderName = "holder name"
                },
                Bypass3DS = false,

            };

            var directPaymentRequestJSON = JsonConvert.SerializeObject(directPaymentRequest);
            return await PerformRequest(directPaymentRequestJSON, url:paymentUrl).ConfigureAwait(false);
        }
        public static async Task<string> PerformRequest(string requestJSON,string url="", string endPoint="")
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
                         "InvoiceValue"    : 50,
                         "CallBackUrl"     : "https://example.com/callback.php",
                         "ErrorUrl"        : "https://example.com/callback.php",
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

    # Execute payment t get Invoice Id and Invoice URL
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
```ruby
####### Direct Payment ######
require 'net/http'
require 'uri'
require 'json'

uri = URI.parse("https://apitest.myfatoorah.com/v2/ExecutePayment")
token = 'mytokenvalue' #token value to be placed here
header = {'Authorization':token} 

body = {
  'PaymentMethodId': '2',
  'CustomerName': 'Ahmed',
  'DisplayCurrencyIso': 'KWD',
  'MobileCountryCode': '+965',
  'CustomerMobile': '12345678',
  'CustomerEmail': 'xx@yy.com',
  'InvoiceValue': 100,
  'CallBackUrl': 'https://google.com',
  'ErrorUrl': 'https://yahoo.com',
  'Language': 'en',
  'CustomerReference': 'ref 1',
  'CustomerCivilId': 12345678,
  'UserDefinedField': 'Custom field',
  'ExpireDate': '',
  'CustomerAddress': {
    'Block': '',
    'Street': '',
    'HouseBuildingNo': '',
    'Address': '',
    'AddressInstructions': ''
  },
  'InvoiceItems': [
    {
      'ItemName': 'Product 01',
      'Quantity': 1,
      'UnitPrice': 100
    }
  ]
}

### Create the HTTP objects
http = Net::HTTP.new(uri.host, uri.port)
http.use_ssl = true

request = Net::HTTP::Post.new(uri.request_uri, header)
request["Content-Type"] = 'application/json'
request.body = body.to_json


### Send the request
response = http.request(request)
parsed = JSON.parse(response.body)

paymentURL = parsed['Data']['PaymentURL']

 #After getting the payment url call it as a post API and pass card info to it
 #If you have token saved before, send pass it as a token instead


uri = URI.parse(paymentURL)
token = 'mytokenvalue' #token value to be placed here
header = {'Authorization':token} 

body = {'paymentType': 'card','card': {'Number':'5123450000000008','expiryMonth':'05','expiryYear':'21','securityCode':'100'},'saveToken': false}

### Create the HTTP objects
http = Net::HTTP.new(uri.host, uri.port)
http.use_ssl = true

request = Net::HTTP::Post.new(uri.request_uri, header)
request["Content-Type"] = 'application/json'
request.body = body.to_json
### Send the request
response = http.request(request)
puts response.read_body
```
```javascript Node.js
console.log('#################### Direct Payment ########################');
var request = require("request");
var token = 'mytokenvalue' //token value to be placed here;
var baseURL = 'https://apitest.myfatoorah.com';
var options = { method: 'POST',
  url: baseURL+'/v2/ExecutePayment',
  headers: 
   { Accept: 'application/json',
     Authorization: 'bearer '+token,
     'Content-Type': 'application/json' },
  body: 
   { PaymentMethodId: '2',
     CustomerName: 'Ahmed',
     DisplayCurrencyIso: 'KWD',
     MobileCountryCode: '+965',
     CustomerMobile: '12345678',
     CustomerEmail: 'xx@yy.com',
     InvoiceValue: 100,
     CallBackUrl: 'https://google.com',
     ErrorUrl: 'https://google.com',
     Language: 'en',
     CustomerReference: 'ref 1',
     CustomerCivilId: 12345678,
     UserDefinedField: 'Custom field',
     ExpireDate: '',
     CustomerAddress: 
      { Block: '',
        Street: '',
        HouseBuildingNo: '',
        Address: '',
        AddressInstructions: '' },
     InvoiceItems: [ { ItemName: 'Product 01', Quantity: 1, UnitPrice: 100 } ] },
  json: true };

request(options, function (error, response, body) {
  if (error) throw new Error(error);
  console.log(body);
  var paymentURL = body['Data']['PaymentURL'] ;
  console.log(paymentURL);
  payInvoice(paymentURL);

});

function payInvoice(paymentURL) {
    var options = { method: 'POST',
    url: paymentURL,
    headers: 
     { Accept: 'application/json',
       Authorization: 'bearer '+token,
       'Content-Type': 'application/json' },
    body: 
    {paymentType: 'card',card: {Number:'5123450000000008',expiryMonth:'05',expiryYear:'21',securityCode:'100'},saveToken: false},
    json: true };
  
  request(options, function (error, response, body) {
    if (error) throw new Error(error);
    console.log(body);
  
  });
 }
```

## Card Direct Integration

*`https://docs.myfatoorah.com/docs/card-direct-integration` — updated 2026-02-16*

#### Overview

Card Direct Integration allows you to accept card payments directly on your website or application. You can collect the card details and send them to the payment gateway to process the payment.

#### How It Works

##### 3D Secure Flow

###### Step 1: Create the Payment

You need to send the card details along with the remaining payment data to create a payment request. Then we return an OTP (3D Secure) URL in the response.
You must redirect the customer to this URL to complete the authentication.\
After the customer completes the payment, we redirect the user to your Redirection URL, appending the paymentId.

**Endpoint: `POST /v3/payments`**([Create Payment](https://docs.myfatoorah.com/reference/create-payment))

```json Request
{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 20
    },
    "SourceOfFund": {
        "Card": {
            "Number": "5123450000000008",
            "ExpiryMonth": "01",
            "ExpiryYear": "39",
            "SecurityCode": "100",
            "HolderName": "JOHN DOE",
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
        "InvoiceId": "6501626",
        "PaymentId": "07076501626331011771",
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/MpgsAuthentication?paymentId=07076501626331011771&sessionId=SESSION0002204598275N1020871M77&mfSessionId=",
        "PaymentCompleted": false,
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
            "IsValidCard": null,
            "Is3DSVerified": false
        }
    }
}
```

Example Redirection URL after the customer completes the payment: <https://your-website.com/payment-callback?paymentId=07076389575322466574&Id=07076389575322466574>

###### Step 2: Inquire About the Payment Status

After the customer redirects back to your website, use the paymentId to inquire about the payment status.

**Endpoint: `GET /v3/payments/:paymentId`**

```curl Request
GET /v3/payments/07076389544322463873
```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "Invoice": {
            "Id": "6389544",
            "Status": "PAID",
            "Reference": "2025001607",
            "CreationDate": "2025-12-24T15:17:55.0130000Z",
            "ExpirationDate": "2026-05-23T15:17:55.0130000Z",
            "ExternalIdentifier": null,
            "UserDefinedField": "",
            "MetaData": null
        },
        "Transaction": {
            "Id": "104686",
            "Status": "SUCCESS",
            "PaymentMethod": "VISA/MASTER",
            "PaymentId": "07076389544322463873",
            "ReferenceId": "535815103616",
            "TrackId": "24-12-2025_3224638",
            "AuthorizationId": "103616",
            "TransactionDate": "2025-12-24T15:18:06.4100000Z",
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
                "Token": "",
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

##### Non-3D Secure Flow

The payment is processed immediately without redirecting the customer for authentication.
The payment result is returned directly in the same response.

**Endpoint: `POST /v3/payments`**([Create Payment](https://docs.myfatoorah.com/reference/create-payment))

```json Request
{
    "PaymentMethod": "CARD",
    "Order": {
        "Amount": 20
    },
    "SourceOfFund": {
        "Card": {
            "Number": "5123450000000008",
            "ExpiryMonth": "01",
            "ExpiryYear": "39",
            "SecurityCode": "100",
            "HolderName": "JOHN DOE",
        }
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
        "InvoiceId": "6501637",
        "PaymentId": "07076501637331012771",
        "PaymentURL": "https://your-website.com/payment-callback?paymentId=07076501637331012771&Id=07076501637331012771",
        "PaymentCompleted": true,
        "TransactionDetails": {
            "Invoice": {
                "Id": "6501637",
                "Status": "PAID",
                "Reference": "2026000643",
                "CreationDate": "2026-02-09T07:16:10.5821929Z",
                "ExpirationDate": "2026-07-09T07:16:10.5821929Z",
                "ExternalIdentifier": null,
                "UserDefinedField": "",
                "MetaData": null
            },
            "Transaction": {
                "Id": "275130",
                "Status": "SUCCESS",
                "PaymentMethod": "VISA/MASTER",
                "PaymentId": "07076501637331012771",
                "ReferenceId": "604007275130",
                "TrackId": "09-02-2026_3310127",
                "AuthorizationId": "275130",
                "TransactionDate": "2026-02-09T07:16:11.5078551Z",
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
                    "Token": "",
                    "PanHash": "b888aa5f23a817883d4d12c74044bab1ae6ee65dc8d6e11515394aba452b273b",
                    "ExpiryMonth": "01",
                    "ExpiryYear": "39",
                    "Brand": "Mastercard",
                    "Issuer": "Test Bank",
                    "IssuerCountry": "KWT",
                    "FundingMethod": "credit"
                },
                "AuthenticationData": {
                    "Eci": "",
                    "TransactionStatus": "",
                    "ProtocolVersion": "",
                    "AuthenticationValue": "",
                    "DirectoryServerTransactionId": "",
                    "Time": ""
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
        },
        "Card": null
    }
}
```

## OTP Page In an Iframe

*`https://docs.myfatoorah.com/docs/otp-page-in-an-iframe` — updated 2026-02-16*

Open the OTP page in an iframe

This feature enables you to open the OTP page in an iframe without external redirection. This will enable you to keep your customers on your website during the whole payment process.

#### How it Works

##### 1- Create the iframe on your website.

The design and creation of the iframe are done from your side. You will open the PaymentUrl in the iframe so that the customer can enter their OTP.

> 📘 Handling the iframe
>
> You will handle fully the iframe from your side. You need to create the iframe and open the PaymentUrl for the OTP in it. You should also handle the cancelation if the customer wants to cancel the payment after the OTP is open and so on.

##### 2- Get the redirection URL after the customer enters his OTP

After the customer completes the 3DS challenge, you will need to use an event listener to get the redirection URL. The event listener needs to be added to the page (In a javascript section) on which you will open the iframe.

The name of the sender of the message will be exactly "MF-3DSecure"

The redirection URL will be the Redirection value that you sent to the [Create Session](https://docs.myfatoorah.com/reference/create-session#/) endpoint, appended to it the PaymentId.

```Text Redirection URL Format
https://{{Your_CallBackURL}}/?paymentId={{PaymentId}}&Id={{ID}}
```

You will have multiple options to choose from for the next action, whether you want to open the redirection URL in the iframe or close the iFrame and show the result page directly on your website.

```javascript Retrieve the redirection URL
//The event listener is used to listen to the Redirection URL after the  customer completes the 3DS Challenge
window.addEventListener("message", function (event) { 
        if (!event.data) return;
        try {
            //The redirection URL is returned in the message
            var message = JSON.parse(event.data);
          
            //Proceed only with the following steps if the sender is exactly "MF-3DSecure"
            if (message.sender == "MF-3DSecure") {
              var url = message.url;
            //Here, you need to handle the next action, and here are some suggestions:
            //Redirect the full page to the received URL.
            //Load the received URL in your iframe.
            //Close the iframe and display the result on the same page. You can use AJAX requests to your server with the payment ID to confirm the transaction status (invoke GetPaymentStatus) and display the result accordingly.
            }
        } catch (error) {
            return;
        }
    }, false);	
```

## Native Wallet Integration

*`https://docs.myfatoorah.com/docs/v3-native-wallets` — updated 2026-02-16*

#### Introduction

Native Wallet Integration allows you to accept payments directly from digital wallet providers without redirecting customers to external payment pages. This integration method provides a seamless, secure, and native payment experience within your mobile application or website.\
By integrating directly with wallet providers (Apple Pay, Google Pay, and Samsung Pay), you can leverage the payment credentials securely stored on customers' devices, enabling faster checkouts and improved conversion rates.

> 🚧 Note
>
> You can integrate the Wallets directly with our **Embedded Integration** without needing to make the steps outlined here.
> For more details, please check this link: [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-payment-v3)

#### Supported Payment Methods

This Integration supports the following digital wallet providers:

* Apple Pay
* Google Pay
* Samsung Pay

#### **Before You Start**

##### **Prerequisites**

1. **MyFatoorah Demo Account**: Create your test account at [MyFatoorah Portal](https://registertest.myfatoorah.com/en/)
2. **API Key**: Generate your test API key from the portal (or you can also use a test token [here](https://docs.myfatoorah.com/docs/test-token))
3. **Webhook Configuration**: Set up a [webhook](https://docs.myfatoorah.com/docs/webhook-information) endpoint to receive payment status notifications

##### **Environment URLs**

| Environment                       | API Base URL                      |
| :-------------------------------- | :-------------------------------- |
| **Sandbox**                       | `https://apitest.myfatoorah.com/` |
| **Kuwait, Bahrain, Jordan, Oman** | `https://api.myfatoorah.com/`     |
| **UAE**                           | `https://api-ae.myfatoorah.com/`  |
| **Saudi Arabia**                  | `https://api-sa.myfatoorah.com/`  |
| **Qatar**                         | `https://api-qa.myfatoorah.com/`  |
| **Egypt**                         | `https://api-eg.myfatoorah.com/`  |

> 🚧 Important
>
> Always use the `/v3/endpoint` for this integration.\
> For example: <https://apitest.myfatoorah.com/v3/payments>\
> Older versions `/v2` should not be used for new integrations.

#### How It Works

##### Integration Flow

1. The customer selects their preferred wallet (Apple Pay, Google Pay, or Samsung Pay) on your platform.
2. Your application communicates with the wallet provider to generate a payment token.
3. Your server sends the wallet token to the MyFatoorah API endpoint along with payment details.
4. MyFatoorah processes the payment and returns the OTP URL or the transaction result.

##### API Endpoint

The Native Wallet Integration uses a single unified endpoint for all wallet providers:

**Endpoint**: `POST /v3/payments`

```json Apple Pay Request
{
    "PaymentMethod": "APPLE_PAY",
    "SourceOfFund": {
        "Token": "{\"PaymentData\":{\"version\":\"EC_v1\",\"data\":\"FY54AajfqpMCGOrXK0AePw8\/kxVVRMn\/hL7O0Yu1+j6nrSrJGSeUtVaDDJkvV9Nht+szczce3aWGk4CpZKWgZtFxMHWW4m8eMD2Ciq1S21ds451hQ1GhIaVJ+KZRdb0rTa39q3U5zSxb5ZyxJ6PcgAbn9UVLuy3rZvtN7WiCeh15GTMKsQA1Kky8M0Pan112xBWiOw\/7R+Lus68ADkBRMbe1UG8\/E8inocrk1Lym+nOuB9e44kQE6Z0c7ZjjK1fGG2ew+YHq1stk1bsAOOvIhjMmdAJLL1d0dsqjCqPIZv9MMDwUZRtfuUAFxn\/92lYm4WBJ22kaIBeGk\/fTx6fTFIuOxCgRA+yIdBIILF8NKXWG\/9zA5BMufojlC8WBb0T8K1OWN4eswk7y5jg\/6tQ=\",\"signature\":\"MIAGCSqGSIb3DQEHAqCAMIACAQExDTALBglghkgBZQMEAgEwgAYJKoZIhvcNAQcBAACggDCCA+QwggOLoAMCAQICCFnYobyq9OPNMAoGCCqGSM49BAMCMHoxLjAsBgNVBAMMJUFwcGxlIEFwcGxpY2F0aW9uIEludGVncmF0aW9uIENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9yaXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzAeFw0yMTA0MjAxOTM3MDBaFw0yNjA0MTkxOTM2NTlaMGIxKDAmBgNVBAMMH2VjYy1zbXAtYnJva2VyLXNpZ25fVUM0LVNBTkRCT1gxFDASBgNVBAsMC2lPUyBTeXN0ZW1zMRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABIIw\/avDnPdeICxQ2ZtFEuY34qkB3Wyz4LHNS1JnmPjPTr3oGiWowh5MM93OjiqWwvavoZMDRcToekQmzpUbEpWjggIRMIICDTAMBgNVHRMBAf8EAjAAMB8GA1UdIwQYMBaAFCPyScRPk+TvJ+bE9ihsP6K7\/S5LMEUGCCsGAQUFBwEBBDkwNzA1BggrBgEFBQcwAYYpaHR0cDovL29jc3AuYXBwbGUuY29tL29jc3AwNC1hcHBsZWFpY2EzMDIwggEdBgNVHSAEggEUMIIBEDCCAQwGCSqGSIb3Y2QFATCB\/jCBwwYIKwYBBQUHAgIwgbYMgbNSZWxpYW5jZSBvbiB0aGlzIGNlcnRpZmljYXRlIGJ5IGFueSBwYXJ0eSBhc3N1bWVzIGFjY2VwdGFuY2Ugb2YgdGhlIHRoZW4gYXBwbGljYWJsZSBzdGFuZGFyZCB0ZXJtcyBhbmQgY29uZGl0aW9ucyBvZiB1c2UsIGNlcnRpZmljYXRlIHBvbGljeSBhbmQgY2VydGlmaWNhdGlvbiBwcmFjdGljZSBzdGF0ZW1lbnRzLjA2BggrBgEFBQcCARYqaHR0cDovL3d3dy5hcHBsZS5jb20vY2VydGlmaWNhdGVhdXRob3JpdHkvMDQGA1UdHwQtMCswKaAnoCWGI2h0dHA6Ly9jcmwuYXBwbGUuY29tL2FwcGxlYWljYTMuY3JsMB0GA1UdDgQWBBQCJDALmu7tRjGXpKZaKZ5CcYIcRTAOBgNVHQ8BAf8EBAMCB4AwDwYJKoZIhvdjZAYdBAIFADAKBggqhkjOPQQDAgNHADBEAiB0obMk20JJQw3TJ0xQdMSAjZofSA46hcXBNiVmMl+8owIgaTaQU6v1C1pS+fYATcWKrWxQp9YIaDeQ4Kc60B5K2YEwggLuMIICdaADAgECAghJbS+\/OpjalzAKBggqhkjOPQQDAjBnMRswGQYDVQQDDBJBcHBsZSBSb290IENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9yaXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzAeFw0xNDA1MDYyMzQ2MzBaFw0yOTA1MDYyMzQ2MzBaMHoxLjAsBgNVBAMMJUFwcGxlIEFwcGxpY2F0aW9uIEludGVncmF0aW9uIENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9yaXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABPAXEYQZ12SF1RpeJYEHduiAou\/ee65N4I38S5PhM1bVZls1riLQl3YNIk57ugj9dhfOiMt2u2ZwvsjoKYT\/VEWjgfcwgfQwRgYIKwYBBQUHAQEEOjA4MDYGCCsGAQUFBzABhipodHRwOi8vb2NzcC5hcHBsZS5jb20vb2NzcDA0LWFwcGxlcm9vdGNhZzMwHQYDVR0OBBYEFCPyScRPk+TvJ+bE9ihsP6K7\/S5LMA8GA1UdEwEB\/wQFMAMBAf8wHwYDVR0jBBgwFoAUu7DeoVgziJqkipnevr3rr9rLJKswNwYDVR0fBDAwLjAsoCqgKIYmaHR0cDovL2NybC5hcHBsZS5jb20vYXBwbGVyb290Y2FnMy5jcmwwDgYDVR0PAQH\/BAQDAgEGMBAGCiqGSIb3Y2QGAg4EAgUAMAoGCCqGSM49BAMCA2cAMGQCMDrPcoNRFpmxhvs1w1bKYr\/0F+3ZD3VNoo6+8ZyBXkK3ifiY95tZn5jVQQ2PnenC\/gIwMi3VRCGwowV3bF3zODuQZ\/0XfCwhbZZPxnJpghJvVPh6fRuZy5sJiSFhBpkPCZIdAAAxggGIMIIBhAIBATCBhjB6MS4wLAYDVQQDDCVBcHBsZSBBcHBsaWNhdGlvbiBJbnRlZ3JhdGlvbiBDQSAtIEczMSYwJAYDVQQLDB1BcHBsZSBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTETMBEGA1UECgwKQXBwbGUgSW5jLjELMAkGA1UEBhMCVVMCCFnYobyq9OPNMAsGCWCGSAFlAwQCAaCBkzAYBgkqhkiG9w0BCQMxCwYJKoZIhvcNAQcBMBwGCSqGSIb3DQEJBTEPFw0yNTEyMDIwOTE3NDBaMCgGCSqGSIb3DQEJNDEbMBkwCwYJYIZIAWUDBAIBoQoGCCqGSM49BAMCMC8GCSqGSIb3DQEJBDEiBCBgEHd0qYFivIW\/juzkYS9ZM\/EJPtEof3BYxZF37VxYwTAKBggqhkjOPQQDAgRHMEUCIQDCTIdh626dVbaJN2iPnD4M0Zx8JM5FkEri+XNg8YzeOgIgBlqrEevxEHgwDRIWYVWgzC8+uYujiAiROWDEzt\/CEPcAAAAAAAA=\",\"header\":{\"ephemeralPublicKey\":\"MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEL7bmqfLGIjCAxd9WegJaS3kZcN4oqJFC+h+MjEKNXV6mWcR+wLfE39y3p\/oj33Oo1\/LI+tVcDi9\/mJHHCrR2YA==\",\"publicKeyHash\":\"hmOvu\/gjGyJ2irwuLSHzSB2irbqeEjsc\/IBnBTzfGnA=\",\"transactionId\":\"fb63b9dfd46b59420721e4a19b3e0397a9df2aa70b6337aec8a52bcf6582609f\"}},\"PaymentMethod\":{\"displayName\":\"MasterCard 2095\",\"network\":\"MasterCard\",\"type\":\"credit\"},\"TransactionIdentifier\":\"fb63b9dfd46b59420721e4a19b3e0397a9df2aa70b6337aec8a52bcf6582609f\"}"
    },
    "Order": {
        "Amount": 23
    },
    "IntegrationUrls": {
        "Redirection": "https://your-website.com/payment-callback"
    }
}
```
```json Google Pay
{
    "PaymentMethod": "GOOGLE_PAY", 
    "SourceOfFund": {
        "Token": "{\r\n    \"apiVersion\": 2,\r\n    \"apiVersionMinor\": 0,\r\n    \"paymentMethodData\": {\r\n        \"description\": \"Test Card: Visa\u2006\u2022\u2022\u2022\u2022\u20061111\",\r\n        \"info\": {\r\n            \"assuranceDetails\": {\r\n                \"accountVerified\": true,\r\n                \"cardHolderAuthenticated\": false\r\n            },\r\n            \"cardDetails\": \"1111\",\r\n            \"cardNetwork\": \"VISA\"\r\n        },\r\n        \"tokenizationData\": {\r\n            \"token\": \"{\\\"signature\\\":\\\"MEUCIQDP0ytSfmJ7Mo4zPFH6t78PoaFH7wYYq5xPsfuLVvujJgIgPaPMIiyp2Eort9\/pv9aDbYjQjRG5CP5NBVdXMksvKws\\\\u003d\\\",\\\"intermediateSigningKey\\\":{\\\"signedKey\\\":\\\"{\\\\\\\"keyValue\\\\\\\":\\\\\\\"MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEnHL0h9ycZUzjH9COq9FahkCqns1j6qc+NN8iuLyAnxM0WNfoFi3MeqQhxK1fioveaO4zmmoZ3ej\/d2ZN8s4owg\\\\\\\\u003d\\\\\\\\u003d\\\\\\\",\\\\\\\"keyExpiration\\\\\\\":\\\\\\\"1756167622636\\\\\\\"}\\\",\\\"signatures\\\":[\\\"MEYCIQDt6aSVdAwXg7\/diWQnAWDmd22SBK6PMgud08iz34wOKAIhALMy42fMELwTDUIiQSMFVKm\/9q9eezWclCmwSQOY4\/Qv\\\"]},\\\"protocolVersion\\\":\\\"ECv2\\\",\\\"signedMessage\\\":\\\"{\\\\\\\"encryptedMessage\\\\\\\":\\\\\\\"hG9EF0oqYpf5GZiFDJj\/OJR3wTdmXlueew1KYDambfxiqK9ffMMnouU9RJi98POJNqIKe\/HqRei1qxDdGCpOOOlOoTCkO+8s4pL8nOBZvDqqjdZxFJs97AMT87jLZTjPmaUcNd6JdrVT+pQ2SRlm7uNFcuQpeC+8flaW7ctPHOWX1zGTV3q2gJD\/qrkqjJYC\/Udznskn99QWHtSPeg8jTMAszhZXPX\/VpoG0AZ12F45wz6RpWaEUgNhYe9N11ZXCbnNtRWdKWZzu47NtubUSZuN3lb4xpyWY8Cqi+oFYLIJwbyMUY73xHTFDJ6uCaQB\/8O1N75uXd028XsyMvvkic7VlWrDbBWveVbOZkvyT2sqIFdJcjULAzXolzhIdUrtg5GXMYuL94KHtiBbYtmHyTObnkzB2RPsJqwgIgRDhw+RYHXyreH0xUD2L01Hwz5GzaWtDhI0iCBSuFJFqquUFOIeRA5TNFw+C0nUCdQSq2Xa7nfOIb6NtDk3mgWWuId9H1MEEtwzo4FWG\/WGzuzvVtwBBuPTDZMTLC52aKC1T89QY9vK2\\\\\\\",\\\\\\\"ephemeralPublicKey\\\\\\\":\\\\\\\"BIqW+pLfre\/iuruyM59WNG+JzFbbMBvQonJPuRSJqQU0FecYEoah93m89VcH4R6P0omw\/JNZ+J0\/xQbQeaiiDp4\\\\\\\\u003d\\\\\\\",\\\\\\\"tag\\\\\\\":\\\\\\\"jYVQLDGPliiSHH6xJ0Yy7yg9bcGNk75voV0rVCtsijY\\\\\\\\u003d\\\\\\\"}\\\"}\",\r\n            \"type\": \"PAYMENT_GATEWAY\"\r\n        },\r\n        \"type\": \"CARD\"\r\n    }\r\n}",
    },
    "Order": {
        "Amount": 23
    },
    "IntegrationUrls": {
        "Redirection": "https://your-website.com/payment-callback"
    }
}
```
```json Samsung Pay
{
    "PaymentMethod": "SAMSUNG_PAY",
    "Order": {
        "Amount": 25
    },
    "SourceOfFund": { 
      "Token": "{\"method\":\"3DS\",\"recurring_payment\":false,\"card_brand\":\"visa\",\"card_last4digits\":\"1738\",\"3DS\":{\"type\":\"S\",\"version\":\"100\",\"data\":\"eyJhbGciOiJSU0ExXzUiLCJraWQiOiJpbTZnTk5ZaS9YZ3BWNTVMaWZkN3BBTkQ3VU9rb3BHRHhIcy9WSmJ5NytVPSIsInR5cCI6IkpPU0UiLCJjaGFubmVsU2VjdXJpdHlDb250ZXh0IjoiUlNBX1BLSSIsImVuYyI6IkExMjhHQ00ifQ.OhMqFJKaJEiOnbWOaZM5ZW39shG0RifMl3qjOwz8oetCjhKCMqsugh9u_T5Xbq8TLbmm5PUNuk0eGe4KBaE_9cQ4J3eaEP6V2fhWZlep_gwsJCZycvuNTLSfCGMcnkbEEOKMMxYiRMzcAskqbJzEUpDwmNLE5OPnUY9lnRStI3KakcMjMn-DCaBmKik9parv4mZeMniiqJnxXz39mWRsIEo5OF1B4OSMwLBsZIByfMgH3uGcT9oXdTvCPjI62uJ5ByR64pfMOCt1edZgM3cg0LJ-KqAhGM42uM4517rdQGh6BZcaAch9WCs34H_F8RtNUdZVKJp0nUEQAlHxW-dHOA.j8gt5xNXz6C008FJ.242zDcviTAB8EHRLc7oesQJ4YoqRLt3aeOaFjmYeopCFmWfZMXElFG-qrEYUrFCEeQ8FNU9_xM1Wc4MeBzMgUpGTFY7Nz--hZzFlfV1QelUlt0nvwS_-e7QWgadUZgEaWO7iyPC2vqUxezNjBP3hgrLiQP-9G9yZTItxu0hZDlw8ow5VurJOPjsYT5xzstMXik4W6T2dDEsO7_iS9tE6rs3Tr4wWC9CqVb4OPqBDekI2REi1dFjl65E.T7L9YdU1y0jMRXNlN7F_iA\"}}"
    },
"IntegrationUrls": {
        "Redirection": "https://your-website.com/payment-/callback"
    }
}
```

#### Next Steps

Ready to integrate? Choose your wallet provider and follow the detailed integration guide:

* [Apple Pay Native Integration](https://docs.myfatoorah.com/docs/v3-apple-pay-native#/)
* [Google Pay Native Integration](https://docs.myfatoorah.com/docs/v3-google-pay-native)
* [Samsung Pay Native Integration](https://docs.myfatoorah.com/docs/v3-samsung-pay-native)

> 📘 Old Integration (V2)
>
> In case you are using the integration for v2, you can find the documentation here:
>
> * [Apple Pay Native Integration](https://docs.myfatoorah.com/docs/apple-pay-native#/)
> * [Google Pay Native Integration](https://docs.myfatoorah.com/docs/google-pay-native#/)

<br />

## Apple Pay Direct Integration

*`https://docs.myfatoorah.com/docs/v3-apple-pay-direct-integration` — updated 2026-02-16*

#### Overview

Apple Pay Direct Integration allows you to process Apple Pay payments by decrypting the Apple Pay token on your side.
After decrypting the token, you extract the card details provided by Apple Pay and send them to MyFatoorah to process the payment.

#### How It Works

The payment is processed immediately without redirecting the customer for authentication.
The payment result is returned directly in the same response.

**Endpoint: `POST /v3/payments`**([Create Payment](https://docs.myfatoorah.com/reference/create-payment))

```json Request
{
    "PaymentMethod": "APPLE_PAY",
    "Order": {
        "Amount": 20
    },
    "SourceOfFund": {
        "Card": {
            "Number": "5204240498885735",
            "ExpiryMonth": "06",
            "ExpiryYear": "26",
            "Cryptogram": "ALJJjhJSf8kZAmsy+VVBAoABFA==",
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
        "InvoiceId": "6389687",
        "PaymentId": "07076389687322474674",
        "PaymentURL": "https://your-website.com/payment-callback?paymentId=07076389687322474674&Id=07076389687322474674",
        "PaymentCompleted": true,
        "TransactionDetails": {
            "Invoice": {
                "Id": "6389687",
                "Status": "PENDING",
                "Reference": "2025001620",
                "CreationDate": "2025-12-24T16:37:19.8046448Z",
                "ExpirationDate": "2026-05-23T16:37:19.8046448Z",
                "ExternalIdentifier": null,
                "UserDefinedField": "",
                "MetaData": null
            },
            "Transaction": {
                "Id": "07076389687322474674",
                "Status": "FAILED",
                "PaymentMethod": "Apple Pay",
                "PaymentId": "07076389687322474674",
                "ReferenceId": "07076389687322474674",
                "TrackId": "24-12-2025_3224746",
                "AuthorizationId": "07076389687322474674",
                "TransactionDate": "2025-12-24T16:37:19.8671123Z",
                "ECI": "",
                "IP": {
                    "Address": "",
                    "Country": ""
                },
                "Error": {
                    "Code": "MF002",
                    "Message": "DECLINED : Restricted card"
                },
                "Card": {
                    "NameOnCard": "",
                    "Number": "520424xxxxxx5735",
                    "Token": "",
                    "PanHash": "98899f423ac13b6dbdc3ef62b275ece6e37d60f73b0c6894379b9c48d847c2ff",
                    "ExpiryMonth": "06",
                    "ExpiryYear": "26",
                    "Brand": "",
                    "Issuer": "",
                    "IssuerCountry": "",
                    "FundingMethod": ""
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
                "ServiceCharge": "0.022",
                "ServiceChargeVAT": "0.003",
                "ReceivableAmount": "19.975",
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

## Apple Pay Native Integration

*`https://docs.myfatoorah.com/docs/v3-apple-pay-native` — updated 2026-02-16*

#### **Introduction**

Apple Pay Native Integration allows you to accept payments directly from **Apple Pay** without redirecting customers to external payment pages.\
It provides a **fast, secure, and native** checkout experience within your mobile application or website by using the customer’s payment credentials stored in Apple Wallet.\
By integrating Apple Pay natively, your customers can complete payments using Face ID or Touch ID, improving both **conversion rates** and **user trust**.

![](https://files.readme.io/a1ecd347c7f608a814d4572c160131897863302523c62d926b1c619639647636-image.png)

![Apple Pay Payment Sheet Page](https://files.readme.io/d7af394bf697f1b89dbbfb8878d280554b1481c6ef53343f8a6ae065cf0d0051-image.png)

<br />

> 🚧 Note
>
> You can integrate Apple Pay directly with our **Embedded Integration** without needing to make the steps outlined here.
> For more details, please check this link: [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-payment-v3)

#### **Integration Flow Steps**

##### **1. Initial Contact & CSR File Request**

* You will need to contact the MyFatoorah tech team to request a CSR (Certificate Signing Request) file.\
  Note: The CSR file will be used for generating the necessary certificates for Apple Pay.

##### **2. Generate Payment Processing Certificate (PPC)**

* Once you have a CSR file, they need to use it to generate the Payment Processing Certificate (PPC) with Apple Pay.
* This is an essential step to ensure that you can securely process payments using Apple Pay.

> 📘 Steps for Payment Processing Certificate Generation
>
> <https://developer.apple.com/help/account/capabilities/configure-apple-pay/>

##### **3. Share PPC with MyFatoorah**

* After generating the PPC, you must send it back to the MyFatoorah tech team.
* This allows MyFatoorah to install the certificate.

##### **4. Installation and Confirmation**

* Once the PPC is received, MyFatoorah’s tech team will install the certificate on their server.
* After successful installation, MyFatoorah tech team will confirm the installation with the vendor to complete the integration.

##### **5. Start Using Apple Pay Integration**

After the confirmation from MyFatoorah, you can proceed with the next steps in the integration process:

**Endpoint:** `POST /v3/payments`

```json Request Example
{
    "PaymentMethod": "APPLE_PAY",
    "SourceOfFund": {
        "Token": "{\"PaymentData\":{\"version\":\"EC_v1\",\"data\":\"FY54AajfqpMCGOrXK0AePw8\/kxVVRMn\/hL7O0Yu1+j6nrSrJGSeUtVaDDJkvV9Nht+szczce3aWGk4CpZKWgZtFxMHWW4m8eMD2Ciq1S21ds451hQ1GhIaVJ+KZRdb0rTa39q3U5zSxb5ZyxJ6PcgAbn9UVLuy3rZvtN7WiCeh15GTMKsQA1Kky8M0Pan112xBWiOw\/7R+Lus68ADkBRMbe1UG8\/E8inocrk1Lym+nOuB9e44kQE6Z0c7ZjjK1fGG2ew+YHq1stk1bsAOOvIhjMmdAJLL1d0dsqjCqPIZv9MMDwUZRtfuUAFxn\/92lYm4WBJ22kaIBeGk\/fTx6fTFIuOxCgRA+yIdBIILF8NKXWG\/9zA5BMufojlC8WBb0T8K1OWN4eswk7y5jg\/6tQ=\",\"signature\":\"MIAGCSqGSIb3DQEHAqCAMIACAQExDTALBglghkgBZQMEAgEwgAYJKoZIhvcNAQcBAACggDCCA+QwggOLoAMCAQICCFnYobyq9OPNMAoGCCqGSM49BAMCMHoxLjAsBgNVBAMMJUFwcGxlIEFwcGxpY2F0aW9uIEludGVncmF0aW9uIENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9yaXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzAeFw0yMTA0MjAxOTM3MDBaFw0yNjA0MTkxOTM2NTlaMGIxKDAmBgNVBAMMH2VjYy1zbXAtYnJva2VyLXNpZ25fVUM0LVNBTkRCT1gxFDASBgNVBAsMC2lPUyBTeXN0ZW1zMRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABIIw\/avDnPdeICxQ2ZtFEuY34qkB3Wyz4LHNS1JnmPjPTr3oGiWowh5MM93OjiqWwvavoZMDRcToekQmzpUbEpWjggIRMIICDTAMBgNVHRMBAf8EAjAAMB8GA1UdIwQYMBaAFCPyScRPk+TvJ+bE9ihsP6K7\/S5LMEUGCCsGAQUFBwEBBDkwNzA1BggrBgEFBQcwAYYpaHR0cDovL29jc3AuYXBwbGUuY29tL29jc3AwNC1hcHBsZWFpY2EzMDIwggEdBgNVHSAEggEUMIIBEDCCAQwGCSqGSIb3Y2QFATCB\/jCBwwYIKwYBBQUHAgIwgbYMgbNSZWxpYW5jZSBvbiB0aGlzIGNlcnRpZmljYXRlIGJ5IGFueSBwYXJ0eSBhc3N1bWVzIGFjY2VwdGFuY2Ugb2YgdGhlIHRoZW4gYXBwbGljYWJsZSBzdGFuZGFyZCB0ZXJtcyBhbmQgY29uZGl0aW9ucyBvZiB1c2UsIGNlcnRpZmljYXRlIHBvbGljeSBhbmQgY2VydGlmaWNhdGlvbiBwcmFjdGljZSBzdGF0ZW1lbnRzLjA2BggrBgEFBQcCARYqaHR0cDovL3d3dy5hcHBsZS5jb20vY2VydGlmaWNhdGVhdXRob3JpdHkvMDQGA1UdHwQtMCswKaAnoCWGI2h0dHA6Ly9jcmwuYXBwbGUuY29tL2FwcGxlYWljYTMuY3JsMB0GA1UdDgQWBBQCJDALmu7tRjGXpKZaKZ5CcYIcRTAOBgNVHQ8BAf8EBAMCB4AwDwYJKoZIhvdjZAYdBAIFADAKBggqhkjOPQQDAgNHADBEAiB0obMk20JJQw3TJ0xQdMSAjZofSA46hcXBNiVmMl+8owIgaTaQU6v1C1pS+fYATcWKrWxQp9YIaDeQ4Kc60B5K2YEwggLuMIICdaADAgECAghJbS+\/OpjalzAKBggqhkjOPQQDAjBnMRswGQYDVQQDDBJBcHBsZSBSb290IENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9yaXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzAeFw0xNDA1MDYyMzQ2MzBaFw0yOTA1MDYyMzQ2MzBaMHoxLjAsBgNVBAMMJUFwcGxlIEFwcGxpY2F0aW9uIEludGVncmF0aW9uIENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9yaXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABPAXEYQZ12SF1RpeJYEHduiAou\/ee65N4I38S5PhM1bVZls1riLQl3YNIk57ugj9dhfOiMt2u2ZwvsjoKYT\/VEWjgfcwgfQwRgYIKwYBBQUHAQEEOjA4MDYGCCsGAQUFBzABhipodHRwOi8vb2NzcC5hcHBsZS5jb20vb2NzcDA0LWFwcGxlcm9vdGNhZzMwHQYDVR0OBBYEFCPyScRPk+TvJ+bE9ihsP6K7\/S5LMA8GA1UdEwEB\/wQFMAMBAf8wHwYDVR0jBBgwFoAUu7DeoVgziJqkipnevr3rr9rLJKswNwYDVR0fBDAwLjAsoCqgKIYmaHR0cDovL2NybC5hcHBsZS5jb20vYXBwbGVyb290Y2FnMy5jcmwwDgYDVR0PAQH\/BAQDAgEGMBAGCiqGSIb3Y2QGAg4EAgUAMAoGCCqGSM49BAMCA2cAMGQCMDrPcoNRFpmxhvs1w1bKYr\/0F+3ZD3VNoo6+8ZyBXkK3ifiY95tZn5jVQQ2PnenC\/gIwMi3VRCGwowV3bF3zODuQZ\/0XfCwhbZZPxnJpghJvVPh6fRuZy5sJiSFhBpkPCZIdAAAxggGIMIIBhAIBATCBhjB6MS4wLAYDVQQDDCVBcHBsZSBBcHBsaWNhdGlvbiBJbnRlZ3JhdGlvbiBDQSAtIEczMSYwJAYDVQQLDB1BcHBsZSBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTETMBEGA1UECgwKQXBwbGUgSW5jLjELMAkGA1UEBhMCVVMCCFnYobyq9OPNMAsGCWCGSAFlAwQCAaCBkzAYBgkqhkiG9w0BCQMxCwYJKoZIhvcNAQcBMBwGCSqGSIb3DQEJBTEPFw0yNTEyMDIwOTE3NDBaMCgGCSqGSIb3DQEJNDEbMBkwCwYJYIZIAWUDBAIBoQoGCCqGSM49BAMCMC8GCSqGSIb3DQEJBDEiBCBgEHd0qYFivIW\/juzkYS9ZM\/EJPtEof3BYxZF37VxYwTAKBggqhkjOPQQDAgRHMEUCIQDCTIdh626dVbaJN2iPnD4M0Zx8JM5FkEri+XNg8YzeOgIgBlqrEevxEHgwDRIWYVWgzC8+uYujiAiROWDEzt\/CEPcAAAAAAAA=\",\"header\":{\"ephemeralPublicKey\":\"MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEL7bmqfLGIjCAxd9WegJaS3kZcN4oqJFC+h+MjEKNXV6mWcR+wLfE39y3p\/oj33Oo1\/LI+tVcDi9\/mJHHCrR2YA==\",\"publicKeyHash\":\"hmOvu\/gjGyJ2irwuLSHzSB2irbqeEjsc\/IBnBTzfGnA=\",\"transactionId\":\"fb63b9dfd46b59420721e4a19b3e0397a9df2aa70b6337aec8a52bcf6582609f\"}},\"PaymentMethod\":{\"displayName\":\"MasterCard 2095\",\"network\":\"MasterCard\",\"type\":\"credit\"},\"TransactionIdentifier\":\"fb63b9dfd46b59420721e4a19b3e0397a9df2aa70b6337aec8a52bcf6582609f\"}"
    },
    "Order": {
        "Amount": 23
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
        "InvoiceId": "6341679",
        "PaymentId": "07076341679319334173",
        "PaymentURL": "https://your-website.com/payment-callback?paymentId=07076341679319334173&Id=07076341679319334173",
        "PaymentCompleted": true,
        "TransactionDetails": {
            "Invoice": {
                "Id": "6341679",
                "Status": "PENDING",
                "Reference": "2025001273",
                "CreationDate": "2025-12-04T06:10:11.0896736Z",
                "ExpirationDate": "2026-05-03T06:10:11.0896736Z",
                "ExternalIdentifier": null,
                "UserDefinedField": "",
                "MetaData": null
            },
            "Transaction": {
                "Id": "07076341679319334173",
                "Status": "FAILED",
                "PaymentMethod": "Apple Pay",
                "PaymentId": "07076341679319334173",
                "ReferenceId": "07076341679319334173",
                "TrackId": "04-12-2025_3193341",
                "AuthorizationId": "07076341679319334173",
                "TransactionDate": "2025-12-04T06:10:11.1521705Z",
                "ECI": "",
                "IP": {
                    "Address": "",
                    "Country": ""
                },
                "Error": {
                    "Code": "MF002",
                    "Message": "DECLINED : Do not honour"
                },
                "Card": {
                    "NameOnCard": "",
                    "Number": "520424xxxxxx1813",
                    "PanHash": "62624d11f4553f7957aa138d25481080ab4646a2e545a62c2652e09ad0d48091",
                    "ExpiryMonth": "07",
                    "ExpiryYear": "28",
                    "Brand": "Mastercard",
                    "Issuer": "",
                    "IssuerCountry": "",
                    "FundingMethod": ""
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
                "ServiceCharge": "0.023",
                "ServiceChargeVAT": "0.003",
                "ReceivableAmount": "22.974",
                "DisplayCurrency": "KWD",
                "ValueInDisplayCurrency": "23",
                "PayCurrency": "KWD",
                "ValueInPayCurrency": "23"
            },
            "Suppliers": []
        }
    }
}
```

## Apple Pay

*`https://docs.myfatoorah.com/docs/apple-pay` — updated 2026-02-16*

Embedded Payment

#### **Introduction**

To provide a better user experience to your Apple Pay users, **MyFatoorah** is providing the Apple Pay embedded payment.

**MyFatoorah Apple Pay Embedded Payment** is a Javascript library that provides the Apple Pay button to your website. This button can be placed on your checkout page. When your customers click the button,  **MyFatoorah** will direct the customers to the Apple Pay payment sheet page to authorize the payment. Then you can smoothly complete the payment using [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment)  endpoint by following the below steps.

![Apple Pay Button](https://files.readme.io/6be4af1-Apple_Pay.png)

![Apple Pay Payment Sheet Page](https://files.readme.io/db15645-Apple_Pay_Payment_Sheet.png)

***

#### **How it Works**

<Embed url="https://www.youtube.com/watch?v=Zi2WxveaQg8" href="https://www.youtube.com/watch?v=Zi2WxveaQg8" typeOfEmbed="youtube" html="%3Ciframe%20class%3D%22embedly-embed%22%20src%3D%22%2F%2Fcdn.embedly.com%2Fwidgets%2Fmedia.html%3Fsrc%3Dhttps%253A%252F%252Fwww.youtube.com%252Fembed%252FZi2WxveaQg8%253Ffeature%253Doembed%26display_name%3DYouTube%26url%3Dhttps%253A%252F%252Fwww.youtube.com%252Fwatch%253Fv%253DZi2WxveaQg8%26image%3Dhttps%253A%252F%252Fi.ytimg.com%252Fvi%252FZi2WxveaQg8%252Fhqdefault.jpg%26type%3Dtext%252Fhtml%26schema%3Dyoutube%22%20width%3D%22854%22%20height%3D%22480%22%20scrolling%3D%22no%22%20title%3D%22YouTube%20embed%22%20frameborder%3D%220%22%20allow%3D%22autoplay%3B%20fullscreen%3B%20encrypted-media%3B%20picture-in-picture%3B%22%20allowfullscreen%3D%22true%22%3E%3C%2Fiframe%3E" />

> 👍 Before You start
>
> Kindly refer to the prerequisite section in the [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-payment#prerequisite).

> 🚧 Apple Pay Domain Verification
>
> To enable Apple Pay, you must verify your domain. For detailed instructions, please refer to our [Apple Pay Domain Verification Guide](https://docs.myfatoorah.com/docs/apple-pay-domain-verification).

The following detailed steps will explain how to add the **MyFatoorah Apple Pay Embedded Payment** to your checkout page.

##### 1. Include the Javascript library

```html
// Test Environment
<script src="https://demo.myfatoorah.com/applepay/v4/applepay.js"></script>

// Live Environment For Kuwait, Bahrain, Jordan, and Oman
<script src="https://portal.myfatoorah.com/applepay/v4/applepay.js"></script>

// Live Environment for UAE
<script src="https://ae.myfatoorah.com/applepay/v4/applepay.js"></script>

// Live Environment for Saudi Arabia
<script src="https://sa.myfatoorah.com/applepay/v4/applepay.js"></script>

// Live Environment for Qatar
<script src="https://qa.myfatoorah.com/applepay/v4/applepay.js"></script>

// Live Environment for Egypt
<script src="https://eg.myfatoorah.com/applepay/v4/applepay.js"></script>
```

##### 2. Add the form

You need to define a div element with a unique **id** attribute. The button will be loaded inside this div after passing the div id to the configuration variable.

```html
<div id="card-element"></div>
```

##### 3. Apple Pay Configuration

Now you need to add the configuration variables. Place the following snippet in a new script tag after loading the library in step 1, and replace the **sessionId** parameter with the "SessionId" you receive from the InitiateSession Endpoint. Moreover, you need to add the country code, currency code, and invoice amount.

For the **currencyCode** parameter check our list of [ISO Lookups](https://docs.myfatoorah.com/docs/iso-lookups).

```javascript
var config = {
    sessionId: "", // Here you add the "SessionId" you receive from InitiateSession Endpoint.
    countryCode: "KWT", // Here, add your Country Code. 
    currencyCode: "KWD", // Here, add your Currency Code.
    amount: "10", // Add the invoice amount.
    cardViewId: "card-element",
    callback: payment,
    sessionStarted: sessionStarted,
    sessionCanceled: sessionCanceled   
};

myFatoorahAP.init(config);
```

This will load the Apple Pay button on your page. When customers click the button, they will be redirected to the Apple Pay payment sheet to authorize the payment.

###### Update Display Amount (Optional)

You can update the amount to be displayed in the payment sheet after initializing the button by calling the following function:

```javascript
myFatoorahAP.updateAmount(amount);
```

##### 4. Call the payment function to load the response

In response to the payment function, you will receive the **sessionId**, and information about the card used for the payment. You need to return the SessionId to the backend to continue the payment steps.

```javascript
function payment(response) {
    // Here you need to pass session id to you backend here 
    var sessionId = response.sessionId;
    var cardInformation = response.card;
}
```

***

###### Personalized Apple Pay Button:

Achieve a personalized look for the Apple Pay button by following these steps:

* Omit the div element specified in step #2.
* Exclude the cardViewId field from the config object outlined in step #3.
* Create an Apple Pay button that follows the [Apple Pay Button Guidelines](https://developer.apple.com/design/human-interface-guidelines/apple-pay#Button-styles).
* Once the customer clicks on the Apple Pay button on your checkout page, initiate the Apple Pay functionality by calling myFatoorahAP.initPayment().

##### 5. Call the ExecutePayment Endpoint

Then, you need to send the **SessionId** to your server to process the actual transaction, which should be done in your backend environment using [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint.

> ❗️ Parameters conflict
>
> Do not pass the **PaymentMethodId** parameter in the [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) request and use the **SessionId** parameter instead. As **PaymentMethodId** overwrite **SessionId**.
>
> The "SessionId" you receive from InitiateSession Endpoint can not be used directly in [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) Endpoint.

***

## Apple Pay Native Integration

*`https://docs.myfatoorah.com/docs/apple-pay-native` — updated 2026-02-16*

#### **Introduction**

To provide a better user experience for your Apple Pay users, **MyFatoorah** provides Apple Pay native integration.

**MyFatoorah Apple Pay Native Integration** provides the Apple Pay button to your website. This button can be placed on your checkout page. When your customers click the button, **MyFatoorah** will direct the customers to the Apple Pay payment sheet to complete the payment. Then you can smoothly complete the payment using [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment)  endpoint by following the below steps.

![Apple Pay Button](https://files.readme.io/6be4af1-Apple_Pay.png)

![Apple Pay Payment Sheet.png](https://files.readme.io/db15645-Apple_Pay_Payment_Sheet.png)

##### 1. Initial Contact & CSR File Request

* You will need to contact the MyFatoorah tech team to request a CSR (Certificate Signing Request) file.\
  Note: The CSR file will be used for generating the necessary certificates for Apple Pay.

##### 2. Generate Payment Processing Certificate (PPC)

* Once you have a CSR file, they need to use it to generate the Payment Processing Certificate (PPC) with Apple Pay.
* This is an essential step to ensure that you can securely process payments using Apple Pay.

> 📘 Steps for Payment Processing Certificate Generation
>
> <https://developer.apple.com/help/account/capabilities/configure-apple-pay/>

##### 3. Share PPC with MyFatoorah

* After generating the PPC, you must send it back to the MyFatoorah tech team.
* This allows MyFatoorah to install the certificate.

##### 4. Installation and Confirmation

* Once the PPC is received, MyFatoorah’s tech team will install the certificate on their server.
* After successful installation, MyFatoorah tech team will confirm the installation with the vendor to complete the integration.

##### 5. Start Using Apple Pay Integration

After the confirmation from MyFatoorah, you can proceed with the next steps in the integration process:

###### a. Call the InitiateSession Endpoint

After you call the InitiateSession endpoint, you will get a SessionId and the CountryCode.

```json InitiateSession Response
{
    "IsSuccess": true,
    "Message": "Initiated Successfully!",
    "ValidationErrors": null,
    "Data": {
        "SessionId": "dfc6a3c3-df09-44cb-9c6a-0a6375752da6",
        "CountryCode": "KWT",
        "CustomerTokens": []
    }
}
```

###### b. Update Session with Apple Pay Token

You then need to update the session with the Apple Pay Token. This token authenticates and authorizes the payment transaction.

```json UpdateSession Request
 {
    "SessionId": "{{SessionId}}",
    "Token": "{\"paymentData\":{\"data\":\"LtkwRCNbGEWItmzLUqhI3QRafxrHgfZqqBC96Sw0kceza3tk1exVyDSlGD6OBYnLRArECd3CIa0ifke040JC9/UwcO3WulNR0bn9GZFGS6zL8QCkEI0t1CPfnhta2YD+FGnV7yXdnk81y/aWwYQG2044n2L/wog+rifQsssDmXc2bqfiR8REXzKR9F+jtAWZ46UYBWlNq6sKn5n3pykwwmMVHCZGTKKczU4GsXLXAuMeBLxPRdGz8LC3AmB/BcAGMH2Ra0eMOoOONAcuRgAAaQEVQN8WLJYqwiPGaWzYwS7dX29q2CqzWy8eEbAzprabvDnXkDzNQ2bc0LHL0F9Oro6zEfZtbKD8ubW2SzUe0cxxM0jux/zFmZhtcoj+3FTySkCTMA1bVYiIMO/JAA==\",\"signature\":\"MIAGCSqGSIb3DQEHAqCAMIACAQExDTALBglghkgBZQMEAgEwgAYJKoZIhvcNAQcBAACggDCCA+MwggOIoAMCAQICCEwwQUlRnVQ2MAoGCCqGSM49BAMCMHoxLjAsBgNVBAMMJUFwcGxlIEFwcGxpY2F0aW9uIEludGVncmF0aW9uIENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9yaXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzAeFw0xOTA1MTgwMTMyNTdaFw0yNDA1MTYwMTMyNTdaMF8xJTAjBgNVBAMMHGVjYy1zbXAtYnJva2VyLXNpZ25fVUM0LVBST0QxFDASBgNVBAsMC2lPUyBTeXN0ZW1zMRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABMIVd+3r1seyIY9o3XCQoSGNx7C9bywoPYRgldlK9KVBG4NCDtgR80B+gzMfHFTD9+syINa61dTv9JKJiT58DxOjggIRMIICDTAMBgNVHRMBAf8EAjAAMB8GA1UdIwQYMBaAFCPyScRPk+TvJ+bE9ihsP6K7/S5LMEUGCCsGAQUFBwEBBDkwNzA1BggrBgEFBQcwAYYpaHR0cDovL29jc3AuYXBwbGUuY29tL29jc3AwNC1hcHBsZWFpY2EzMDIwggEdBgNVHSAEggEUMIIBEDCCAQwGCSqGSIb3Y2QFATCB/jCBwwYIKwYBBQUHAgIwgbYMgbNSZWxpYW5jZSBvbiB0aGlzIGNlcnRpZmljYXRlIGJ5IGFueSBwYXJ0eSBhc3N1bWVzIGFjY2VwdGFuY2Ugb2YgdGhlIHRoZW4gYXBwbGljYWJsZSBzdGFuZGFyZCB0ZXJtcyBhbmQgY29uZGl0aW9ucyBvZiB1c2UsIGNlcnRpZmljYXRlIHBvbGljeSBhbmQgY2VydGlmaWNhdGlvbiBwcmFjdGljZSBzdGF0ZW1lbnRzLjA2BggrBgEFBQcCARYqaHR0cDovL3d3dy5hcHBsZS5jb20vY2VydGlmaWNhdGVhdXRob3JpdHkvMDQGA1UdHwQtMCswKaAnoCWGI2h0dHA6Ly9jcmwuYXBwbGUuY29tL2FwcGxlYWljYTMuY3JsMB0GA1UdDgQWBBSUV9tv1XSBhomJdi9+V4UH55tYJDAOBgNVHQ8BAf8EBAMCB4AwDwYJKoZIhvdjZAYdBAIFADAKBggqhkjOPQQDAgNJADBGAiEAvglXH+ceHnNbVeWvrLTHL+tEXzAYUiLHJRACth69b1UCIQDRizUKXdbdbrF0YDWxHrLOh8+j5q9svYOAiQ3ILN2qYzCCAu4wggJ1oAMCAQICCEltL786mNqXMAoGCCqGSM49BAMCMGcxGzAZBgNVBAMMEkFwcGxlIFJvb3QgQ0EgLSBHMzEmMCQGA1UECwwdQXBwbGUgQ2VydGlmaWNhdGlvbiBBdXRob3JpdHkxEzARBgNVBAoMCkFwcGxlIEluYy4xCzAJBgNVBAYTAlVTMB4XDTE0MDUwNjIzNDYzMFoXDTI5MDUwNjIzNDYzMFowejEuMCwGA1UEAwwlQXBwbGUgQXBwbGljYXRpb24gSW50ZWdyYXRpb24gQ0EgLSBHMzEmMCQGA1UECwwdQXBwbGUgQ2VydGlmaWNhdGlvbiBBdXRob3JpdHkxEzARBgNVBAoMCkFwcGxlIEluYy4xCzAJBgNVBAYTAlVTMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE8BcRhBnXZIXVGl4lgQd26ICi7957rk3gjfxLk+EzVtVmWzWuItCXdg0iTnu6CP12F86Iy3a7ZnC+yOgphP9URaOB9zCB9DBGBggrBgEFBQcBAQQ6MDgwNgYIKwYBBQUHMAGGKmh0dHA6Ly9vY3NwLmFwcGxlLmNvbS9vY3NwMDQtYXBwbGVyb290Y2FnMzAdBgNVHQ4EFgQUI/JJxE+T5O8n5sT2KGw/orv9LkswDwYDVR0TAQH/BAUwAwEB/zAfBgNVHSMEGDAWgBS7sN6hWDOImqSKmd6+veuv2sskqzA3BgNVHR8EMDAuMCygKqAohiZodHRwOi8vY3JsLmFwcGxlLmNvbS9hcHBsZXJvb3RjYWczLmNybDAOBgNVHQ8BAf8EBAMCAQYwEAYKKoZIhvdjZAYCDgQCBQAwCgYIKoZIzj0EAwIDZwAwZAIwOs9yg1EWmbGG+zXDVspiv/QX7dkPdU2ijr7xnIFeQreJ+Jj3m1mfmNVBDY+d6cL+AjAyLdVEIbCjBXdsXfM4O5Bn/Rd8LCFtlk/GcmmCEm9U+Hp9G5nLmwmJIWEGmQ8Jkh0AADGCAYgwggGEAgEBMIGGMHoxLjAsBgNVBAMMJUFwcGxlIEFwcGxpY2F0aW9uIEludGVncmF0aW9uIENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9yaXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUwIITDBBSVGdVDYwCwYJYIZIAWUDBAIBoIGTMBgGCSqGSIb3DQEJAzELBgkqhkiG9w0BBwEwHAYJKoZIhvcNAQkFMQ8XDTIzMTIwNjA5MzgwMVowKAYJKoZIhvcNAQk0MRswGTALBglghkgBZQMEAgGhCgYIKoZIzj0EAwIwLwYJKoZIhvcNAQkEMSIEIFshZ3SIlhSMd1j3Jr38QzjklP1lwjGlGpTWBtIDk4/rMAoGCCqGSM49BAMCBEcwRQIhALupCHcbRaNB3R+CJI5EYRyBWny7RVypXpkQ10qbnOx4AiBOJkTQmfm1Sr/7zzmKELLa5OXFwh2Lu1/FBx1e4W18wQAAAAAAAA==\",\"header\":{\"publicKeyHash\":\"SysfkV8a2ep5wmCAL4iS+gOexTs38Kz3EnlsguSBAiE=\",\"ephemeralPublicKey\":\"MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE8xVra1KL01656womGIPVdfxv8AMGaSXOIbgH+cTi30D86uBPvG6Cy1LEIwXvr9g3EBYEXpskuKI+dv7DU3o9hw==\",\"transactionId\":\"9ff74188b13962f31b01dbd015cf0c91a6ff63d1f10492fe1b56f2deccdb8884\"},\"version\":\"EC_v1\"},\"paymentMethod\":{\"displayName\":\"MasterCard 8095\",\"network\":\"MasterCard\",\"type\":\"credit\"},\"transactionIdentifier\":\"9ff74188b13962f31b01dbd015cf0c91a6ff63d1f10492fe1b56f2deccdb8884\"}",
    "TokenType": "applepay"
}
```
```json UpdateSession Response
{
    "IsSuccess": true,
    "Message": null,
    "ValidationErrors": null,
    "Data": {
        "SessionId": "2e1d470b-1c8b-4495-bb79-a32ef8f222c5",
        "CountryCode": "KWT"
    }
}
```

###### c. Call the Execute Payment Endpoint

Then, you need to send the **SessionId** to your server to process the actual transaction, which should be done in your backend environment using [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint.

> ❗️ Parameters conflict
>
> Do not pass the **PaymentMethodId** parameter in the [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) request and use the **SessionId** parameter instead. As **PaymentMethodId** overwrite **SessionId**.
>
> The "SessionId" you receive from InitiateSession Endpoint can not be used directly in [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) Endpoint.

## Apple Pay Domain Verification

*`https://docs.myfatoorah.com/docs/apple-pay-domain-verification` — updated 2026-02-16*

#### **Verify your domain with Apple Pay**

> 🚧 Note
>
> This step is not required in the case of integrating using [MyFatoorah IOS SDK](https://myfatoorah.readme.io/docs/sdk-guide).

To use Apple Pay Button, you need to register with Apple on all of your web domains that will show an Apple Pay button. This includes both top-level domains and subdomains. You need to do this for domains you use in both demo and live servers.

1. Host the domain verification file.
2. Register your domain with Myfatoorah.

#### Step1: Host the domain verification file

You will be using a domain verification file provided by MyFatoorah. Contact the **technical support** team at <tech@myfatoorah.com> to get the domain verification file that you will host on your domain.

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
> Do not remove the file from the server. Apple might need to revalidate the domain on a yearly basis and this file must be available for successful validation.

> ❗️ SSL
>
> Domain should be TLS (HTTPS) enabled.

#### Step2: Register your domain with Myfatoorah

To register your domain with **Myfatoorah** and Apple Pay, you need to call [v2/RegisterApplePayDomain](https://api.myfatoorah.com/swagger/ui/index#!/Payment/Payment_RegisterApplePayDomain) endpoint. The request is a POST request with the following parameters.

```json Request
{ 
 "DomainName": "example.com" 
}
```
```json Success Response
{
  "IsSuccess": true,
  "Message": "OK",
  "ValidationErrors": null,
  "Data": null
}
```
```json Failed Response
{
  "IsSuccess": false,
  "Message": "Please make sure that domain verification file in the following path 'https://<YourDomainName>/.well-known/apple-developer-merchantid-domain-association' is valid and accessible",
  "ValidationErrors": null,
  "Data": null
}
```

End Point:

```text Test Url
https://apitest.myfatoorah.com/v2/RegisterApplePayDomain
```

```text Live Url
https://api.myfatoorah.com/v2/RegisterApplePayDomain
```
```text SAU
https://api-sa.myfatoorah.com/v2/RegisterApplePayDomain
```
```Text Qatar
https://api-qa.myfatoorah.com/v2/RegisterApplePayDomain
```

> 📘 Notes
>
> * Domain name in the request JSON should be domain name without a scheme (HTTPS)
> * Header should have a valid token to access it.
> * You may receive failed response with error detail in case of an invalid request.

***

## Google Pay Native Integration

*`https://docs.myfatoorah.com/docs/v3-google-pay-native` — updated 2026-02-16*

#### **Introduction**

Google Pay enables fast and simple checkout experiences on your website or mobile application, giving you access to hundreds of millions of cards saved to Google Accounts worldwide. It enhances user experience by allowing customers to complete payments quickly and securely using their saved payment credentials.\
MyFatoorah provides Google Pay Native integration that allows you to handle your front-end interactions completely, this gives you the desired flexibility. However, this also means that you need to handle a few interactions with Google to support Google Pay.

![](https://files.readme.io/2d5ef600ac30949c7e90b0ec7ff13bd3f733eda70e2f84c56bf508636d7f162a-image.png)

![Google Pay Payment Sheet Page](https://files.readme.io/06c7f8e2c153aa9ba9190c083f45961678bcb99c8913a35cd9979aee9d82e96e-image.png)

> 🚧 Note
>
> You can integrate Google Pay directly with our **Embedded Integration** without needing to make the steps outlined here.
> For more details, please check this link: [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-payment-v3)

> 📘 Google Criteria: Must be Applied
>
> * [Web integration developer documentation](https://developers.google.com/pay/api/web/overview).
> * [Brand Guidelines.](https://developers.google.com/pay/api/web/guides/brand-guidelines)
> * [Integration checklist.](https://developers.google.com/pay/api/web/guides/test-and-deploy/integration-checklist)
> * Your website must include a link to the Google Pay [Google Pay™ terms of services.](https://payments.developers.google.com/terms/sellertos) and communicate that these terms apply whenever the Google Pay service is offered.
> * You will need To [contact](https://pay.google.com/business/console/home) Google via the Business Console so that they can add your app to their system **for production use** and to get the value of **merchantId**.

#### Integration Flow Steps

The following Events and functions are needed to be handled in integration with the Google Pay API:

* **isReadyToPay**: This function is to determine a user's ability to return a form of payment from the Google Pay API.
* **onPaymentAuthorized Event**: This method is called when a payment is authorized in the payment sheet.

##### **1. Add Google Pay button container**

You will need to add an HTML div element like below.

```html
<div id="gp-container"></div>
```

##### **2. Load Google Javascript file**

You will need to load the Google Pay Javascript file first, as mentioned [here](https://developers.google.com/pay/api/web/overview).

##### **3. Identify your payment processor**

You need to identify the payment processor in the **TokenizationSpecification** message to google. Google will use these identifiers to encrypt the Payment token using the Public key of the Payment processor.

```javascript
"tokenizationSpecification": 
 {
    "type": "PAYMENT_GATEWAY",
    "parameters": 
    {
        "gateway": "myfatoorah",
        "gatewayMerchantId": "YOUR_GATEWAY_MERCHANT_ID" // add your gateway merchant id
    }
 }
```

##### **4. Define the supported payment card networks**

```javascript
const allowedCardNetworks = ["AMEX", "MASTERCARD", "VISA"]; 
const allowedCardAuthMethods = ["PAN_ONLY", "CRYPTOGRAM_3DS"];
```

> 📘 Supported Card Networks & Authentication Methods Configuration
>
> 1- Currently only allowed card networks value are: AMEX, MASTERCARD, VISA.
>
> 2- MyFatoorah supports both PAN\_ONLY and CRYPTOGRAM\_3DS authentication methods:
>
> * PAN\_ONLY - the card is stored on file within your customer's Google account and not bound to an Android device.
> * CRYPTOGRAM\_3DS - the payment credentials is bound to an Android device.
>
> **The Difference between PAN only and Cryptogram lies in the way authentication/3D Secure is handled:**
>
> * PAN Only: 3DS/Authentication is handled through MyFatoorah in the usual way.
> * Cryptogram: Authentication is handled by the device, e.g. by using the devices fingerprint sensor. **This is limited to Android devices, using the Google Chrome browser! All other devices and browsers will always chose PAN Only!**.

##### 5. Set the Google Pay environment.

```javascript
const paymentsClient = new google.payments.api.PaymentsClient({environment: 'PRODUCTION'}); //value TEST for test environment
```

##### 6. Add Callback intents

```javascript
function getGooglePaymentDataRequest() {
        paymentDataRequest.callbackIntents = ["PAYMENT_AUTHORIZATION"];
        return paymentDataRequest;
    }
```

#### **7. Handle Google Pay Button Click.**

Register for the user clicks and load the the payment sheet by calling loadPaymentData().

```javascript
function onGooglePaymentButtonClicked() {
    // ...
    paymentsClient.loadPaymentData(paymentDataRequest);
    // ...
}
```

After the client click Google Pay button, the payment sheet will be shown.

#### **8. Handle Pay Button Click (payment sheet)**

When the client clicks pay button **onPaymentAuthorized** will be called with the paymentData. The paymentData will be sent to MyFatoorah as a token parameter.

**Endpoint:** `POST /v3/payments`

```json Request Example

{
    "PaymentMethod": "GOOGLE_PAY",
    "SourceOfFund": {
        "Token": "{\r\n  \"apiVersion\": 2,\r\n  \"apiVersionMinor\": 0,\r\n  \"paymentMethodData\": {\r\n    \"description\": \"Test Card: Visa\u2006\u2022\u2022\u2022\u2022\u20061111\",\r\n    \"info\": {\r\n      \"assuranceDetails\": {\r\n        \"accountVerified\": true,\r\n        \"cardHolderAuthenticated\": false\r\n      },\r\n      \"cardDetails\": \"1111\",\r\n      \"cardFundingSource\": \"CREDIT\",\r\n      \"cardNetwork\": \"VISA\"\r\n    },\r\n    \"tokenizationData\": {\r\n      \"token\": \"{\\\"signature\\\":\\\"MEUCIGpmtS\/vFhaVppmCDEFnnqpW6Z\/Ap8FObackeHeg\/AsCAiEAyK60CXAMFtfktc+UlL9F6IjhKE17UBLbgDDjFqIDKpg\\\\u003d\\\",\\\"intermediateSigningKey\\\":{\\\"signedKey\\\":\\\"{\\\\\\\"keyValue\\\\\\\":\\\\\\\"MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEogjhVC1el9s05o7nKyKMBor9TKpVzDYj+9kxZ44MS4zlG2iNl3U23xOXSAP7b8EN2HyPvdmy26cvJCHy1ji+kw\\\\\\\\u003d\\\\\\\\u003d\\\\\\\",\\\\\\\"keyExpiration\\\\\\\":\\\\\\\"1761675618059\\\\\\\"}\\\",\\\"signatures\\\":[\\\"MEQCIF5pYwYHq+Zd8an7G4fO24Fna\/VP3fCFZ0BxTlY+R610AiA8TJAY28fMLsfOuNZsgRTUPzMxnJ+uMaWfzn\/JA701vg\\\\u003d\\\\u003d\\\"]},\\\"protocolVersion\\\":\\\"ECv2\\\",\\\"signedMessage\\\":\\\"{\\\\\\\"encryptedMessage\\\\\\\":\\\\\\\"R6bWvTChN4dn\/vrByJinbyBYZlkskHO7KzfIPc49lZzKs\/+5rFFkWGGgFQ2r9T\/+w1idYXPxruImLsBpO8mW3hdf6TnuKQD6\/zsk0lKJDtzCcwebS2mxQhheS0diWy5ZoJ00hc7Q+SQ+spjCAkRCVkDlyj679XaF56FR7sVzCBPeTrgoURP7NctFcCfkkfZsG76TtzT\/1pC+bRuLGa7KHKwTpGgMCGrrlj8aAEP8D9pzNwYCD9ofcUu84FGJYfIJ77zSddEC2NUKu7c6U+nIj5uqvaQdYLOOCwpogdIF+8HoUaDGcCwdjm7\/j90CjgUbehjziH75spy72XnM6cGf3Og494Cwmrz\/tM6A4ffsmNaQQRLGWDjqNzZp1pWxEflnySA+MDrvWsitN0V5roVObAhzIpYIY\/kPXy\/z07vTdhdNB3iFc9HSuJFop6jAS+32HdB7DNv5f5M3j9e1u1hvNmb9OrXTkpqA96gLwnLLRvCVL\/oFDtMueFdCIIIfOUOK6sOXgp7g\/uqW7RFKfMqDQ2anONmeLfPGXky4tgnPpPL5hUl8WghIgOpcNY5ZwD6jXjPzQ5ClcCs01KAr4bxlvKM\\\\\\\\u003d\\\\\\\",\\\\\\\"ephemeralPublicKey\\\\\\\":\\\\\\\"BDFUuu3yEANn9yCaA67+7KocHDCayJGSV7bofHphwvDHo+YUulTY1k6oguUZ3ZqCrAr2fOyjBT73aRq3c1Ao90M\\\\\\\\u003d\\\\\\\",\\\\\\\"tag\\\\\\\":\\\\\\\"tq5F16Vi464Xd\/OtbaQd9Ts7I8mbuzdqyEQVwgQcBRI\\\\\\\\u003d\\\\\\\"}\\\"}\",\r\n      \"type\": \"PAYMENT_GATEWAY\"\r\n    },\r\n    \"type\": \"CARD\"\r\n  }\r\n}"
    },
    "Order": {
        "Amount": 23
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
        "InvoiceId": "6220414",
        "PaymentId": "07076220414309685873",
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/MpgsAuthentication?paymentId=07076220414309685873&sessionId=SESSION0002746342720E03853514F4",
        "PaymentCompleted": false,
        "TransactionDetails": null
    }
}
```

## Google Pay™

*`https://docs.myfatoorah.com/docs/google-pay-embedded` — updated 2026-02-16*

Embedded Payment

#### **Introduction**

The Google Pay™ enables fast, simple checkout on your website, and gives you convenient access to hundreds of millions of cards saved to Google Accounts worldwide. To provide a better user experience to your Google Pay™ users, MyFatoorah is providing the Google Pay™ embedded payment.

**MyFatoorah Google Pay™ embedded payment** is a Javascript library that provides the Google Pay™ button to your website. This button can be placed on your checkout page. When your customers click the button, MyFatoorah will direct the customers to the Google Pay™ payment sheet page to authorize the payment. Then you can smoothly complete the payment using ExecutePayment endpoint by following the below steps.

![Google Pay Button](https://files.readme.io/0f3cf2d-Google_Pay.png)

![Google Pay Payment Sheet Page](https://files.readme.io/53d6350-Google_Pay.png)

> 📘 Terms of Service
>
> Your website must include a link to the Google Pay™ [Google Pay™ terms of services.](https://payments.developers.google.com/terms/sellertos) and communicate that these terms apply whenever the Google Pay™ service is offered.

***

#### **How it Works**

<Embed url="https://www.youtube.com/watch?v=Q4BSBMHuWoM" href="https://www.youtube.com/watch?v=Q4BSBMHuWoM" typeOfEmbed="youtube" html="%3Ciframe%20class%3D%22embedly-embed%22%20src%3D%22%2F%2Fcdn.embedly.com%2Fwidgets%2Fmedia.html%3Fsrc%3Dhttps%253A%252F%252Fwww.youtube.com%252Fembed%252FQ4BSBMHuWoM%253Ffeature%253Doembed%26display_name%3DYouTube%26url%3Dhttps%253A%252F%252Fwww.youtube.com%252Fwatch%253Fv%253DQ4BSBMHuWoM%26image%3Dhttps%253A%252F%252Fi.ytimg.com%252Fvi%252FQ4BSBMHuWoM%252Fhqdefault.jpg%26type%3Dtext%252Fhtml%26schema%3Dyoutube%22%20width%3D%22854%22%20height%3D%22480%22%20scrolling%3D%22no%22%20title%3D%22YouTube%20embed%22%20frameborder%3D%220%22%20allow%3D%22autoplay%3B%20fullscreen%3B%20encrypted-media%3B%20picture-in-picture%3B%22%20allowfullscreen%3D%22true%22%3E%3C%2Fiframe%3E" />

> 👍 Before You start
>
> * Kindly refer to the prerequisite section in the [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-payment#prerequisite).

The following detailed steps will explain how to add **MyFatoorah Google Pay™ Embedded Payment** form to your checkout page.

##### 1. Include the Javascript library

```html
// Test Environment
<script src="https://demo.myfatoorah.com/googlepay/v1/googlepay.js"></script>

// Live Environment For Kuwait, Bahrain, Jordan, and Oman
<script src="https://portal.myfatoorah.com/googlepay/v1/googlepay.js"></script>

// Live Environment for UAE
<script src="https://ae.myfatoorah.com/googlepay/v1/googlepay.js"></script>

// Live Environment for Saudi Arabia
<script src="https://sa.myfatoorah.com/googlepay/v1/googlepay.js"></script>

// Live Environment for Qatar
<script src="https://qa.myfatoorah.com/googlepay/v1/googlepay.js"></script>

// Live Environment for Egypt
<script src="https://eg.myfatoorah.com/googlepay/v1/googlepay.js"></script>
```

##### 2. Add the form

You need to define a div element with a unique **id** attribute. The button will be loaded inside this div after passing the div id to the configuration variable.

```html
<div id="gp-card-element"></div>
```

##### 3. Google Pay™ Configuration

Now you need to add the configuration variables. Place the following snippet in a new script tag after loading the library in step 1, and replace the sessionId parameter with the "SessionId" you receive from InitiateSession Endpoint. Moreover, you need to add the country code, currency code, invoice amount, and other details.

For the **currencyCode** parameter check our list of [ISO Lookups](https://docs.myfatoorah.com/docs/iso-lookups).

```javascript
var config = {
    sessionId: "", // Here you add the "SessionId" you receive from the InitiateSession Endpoint.
    amount: "10", // Add the invoice amount.
    currencyCode: "KWD", // Here, add your currency code.
    countryCode: "KWT", // Here, add your country code.
    cardViewId: "gp-card-element",
    callback: payment,
    style: { 
        frameHeight: 51,
        button: {
            height: "40px",
            text: "pay", // Accepted texts: ["book", "buy", "checkout", "donate", "order", "pay", "plain", "subscribe"]
            borderRadius: "8px",
            color: "black", // Accepted colors: ["black", "white", "default"]
            language: "en"
        }
    }
};

myFatoorahGP.init(config);
```

This will load the Google Pay™ button on your page. When customers click the button, they will be redirected to the Google Pay™ payment sheet to authorize the payment.

###### Update Display Amount (Optional)

You can update the amount to be displayed in the payment sheet after initializing the button by calling the following function:

```javascript
myFatoorahGP.updateAmount(amount);
```

##### 4. Call the payment function to load the response

In the response of the payment function, you will receive the **SessionId** and **CardBrand**.

```javascript
function payment(response) {
    // Here you need to pass session id to you backend here 
    var sessionId = response.sessionId;
    var cardBrand = response.cardBrand; 
  	var cardIdentifier = response.cardIdentifier;
}
```

##### 5. Call the ExecutePayment Endpoint

Then, you need to send the **SessionId** to your server to process the actual transaction, which should be done in your backend environment using [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint.

> ❗️ Parameters conflict
>
> Do not pass the **PaymentMethodId** parameter in the [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) request and use the **SessionId** parameter instead. As **PaymentMethodId** overwrite **SessionId**.
>
> The "SessionId" you receive from InitiateSession Endpoint can not be used directly in [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) Endpoint.

***

## Google Pay™ Direct Integration

*`https://docs.myfatoorah.com/docs/google-pay-native` — updated 2026-02-16*

#### **Introduction**

The Google Pay™ enables fast, simple checkout on your website, and gives you convenient access to hundreds of millions of cards saved to Google Accounts worldwide. To provide a better user experience to your Google Pay™ users.

MyFatoorah provides support to the direct integration that allows you to handle your front-end interactions completely, this gives you the desired flexibility. However, this also means that you need to handle a few interactions with Google in order to support Google Pay™.

![google_pay.PNG](https://files.readme.io/2871693-google_pay.PNG)

![Google Pay.png](https://files.readme.io/acafacb-Google_Pay.png)

> 📘 MyFatoorah Prerequists
>
> * Google Pay™ to be enabled to your account (Contact your Account Manager for Activation).

> 📘 Google Criteria: Must be Applied
>
> * [Web integration developer documentation](https://developers.google.com/pay/api/web/overview).
> * [Brand Guidelines.](https://developers.google.com/pay/api/web/guides/brand-guidelines)
> * [Integration checklist.](https://developers.google.com/pay/api/web/guides/test-and-deploy/integration-checklist)
> * Your website must include a link to the Google Pay™ [Google Pay™ terms of services.](https://payments.developers.google.com/terms/sellertos) and communicate that these terms apply whenever the Google Pay™ service is offered.
> * You will need To [contact](https://pay.google.com/business/console/home) Google via the Business Console so that they can add your app to their system **for production use** and to get the value of **merchantId**.

***

#### Direct Integration: How it works

The following Events and functions are needed to be handled in integration with the Google Pay™ API:

* **isReadyToPay**: This function is to determine a user's ability to return a form of payment from the Google Pay™ API.
* **onPaymentAuthorized Event**: This method is called when a payment is authorized in the payment sheet.

You need to call InitiateSession Endpoint when loading your checkout page to get the **SessionId** and **CountryCode** to be used in your configuration. You need to do this for each payment separately. **SessionId** is valid for only one payment.

The endpoint on Swagger is [Payment\_InitiateSession](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_InitiateSession)

```json
{
  "IsSuccess": true,
  "Message": "Initiated Successfully!",
  "ValidationErrors": null,
  "Data": {
    "SessionId": "601d963a-2f28-ec11-bae9-000d3aaca798",
    "CountryCode": "KWT"
  }
}
```

#### 1.  Add Google Pay™ button container

You will need to add an HTML div element like below.

```html
<div id="gp-container"></div>
```

#### 2. Load Google Javascript file

You will need to load the Google Pay™ Javascript file first, as mentioned [here](https://developers.google.com/pay/api/web/overview).

#### 3. Identify your payment processor

You need to identify the payment processor in the **TokenizationSpecification** message to google. Google will use these identifiers to encrypt the Payment token using the Public key of the Payment processor.

```javascript
"tokenizationSpecification": 
 {
    "type": "PAYMENT_GATEWAY",
    "parameters": 
    {
        "gateway": "myfatoorah",
        "gatewayMerchantId": "YOUR_GATEWAY_MERCHANT_ID" // add your gateway merchant id
    }
 }
```

#### 4. Define the supported payment card networks

```javascript
const allowedCardNetworks = ["AMEX", "MASTERCARD", "VISA"]; 
const allowedCardAuthMethods = ["PAN_ONLY", "CRYPTOGRAM_3DS"];
```

> 📘 Supported Card Networks &  Authentication Methods Configuration
>
> 1- Currently only allowed card networks value are: AMEX, MASTERCARD, VISA.\
> 2- MyFatoorah supports both PAN\_ONLY and CRYPTOGRAM\_3DS authentication methods:
>
> * PAN\_ONLY - the card is stored on file within your customer's Google account and not bound to an Android device.
> * CRYPTOGRAM\_3DS - the payment credentials is bound to an Android device.
>
> **The Difference between PAN only and Cryptogram lies in the way authentication/3D Secure is handled:**
>
> * PAN Only: 3DS/Authentication is handled through MyFatoorah in the usual way.
> * Cryptogram: Authentication is handled by the device, e.g. by using the devices fingerprint sensor. **This is limited to Android devices, using the Google Chrome browser! All other devices and browsers will always chose PAN Only!**.

#### 5. Set the Google Pay™ environment.

```javascript
const paymentsClient = new google.payments.api.PaymentsClient({environment: 'PRODUCTION'}); //value TEST for test environment
```

#### 6.  Add Callback intents

```javascript
function getGooglePaymentDataRequest() {
        paymentDataRequest.callbackIntents = ["PAYMENT_AUTHORIZATION"];
        return paymentDataRequest;
    }
```

#### 7. Handle Google Pay™ Button Click.

Register for the user clicks and load the the payment sheet by calling loadPaymentData().

```javascript
function onGooglePaymentButtonClicked() {
    // ...
    paymentsClient.loadPaymentData(paymentDataRequest);
    // ...
}
```

After the client click Google Pay button, the payment sheet will be shown.

#### 8.  Handle Pay Button Click (payment sheet)

When the client clicks pay button **onPaymentAuthorized** will be called with the paymentData. The paymentData will be sent to MyFatoorah in **UpdateSession** endpoint as token parameter.

Actions to be done to complete the payment:\
1- Send the  paymentData and sessionId to your server to call **UpdateSession** endpoint.\
Please check [Payment\_UpdateSession](https://apitest.myfatoorah.com/swagger/ui/index#!/Payment/Payment_UpdateSession).\
2- When receiving success response from **UpdateSession**, call [ExecutePayment](https://myfatoorah.readme.io/docs/execute-payment) endpoint with the **SessionId** to process the actual transaction.

```javascript
function onPaymentAuthorized(paymentData) {
	//Send the paymentData and sessionId to your server and perform the following actions:
	//1: Call UpdateSession endpoint
	//2: If success then call ExecutePayment endpoint
}
```

> ❗️ Parameters conflict
>
> Do not pass the **PaymentMethodId** parameter in the [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) request and use the **SessionId** parameter instead. As **PaymentMethodId** overwrite **SessionId**.
>
> The "SessionId" you receive from InitiateSession Endpoint can not be used directly in [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) Endpoint.

## Samsung Pay Direct Integration

*`https://docs.myfatoorah.com/docs/v3-samsung-pay-direct-integration` — updated 2026-02-16*

#### Overview

Samsung Pay Direct Integration allows you to process Samsung Pay payments by decrypting the Samsung Pay token on your side.
After decrypting the token, you extract the card details provided by Samsung Pay and send them to MyFatoorah to process the payment.

#### How It Works

The payment is processed immediately without redirecting the customer for authentication.
The payment result is returned directly in the same response.

**Endpoint: `POST /v3/payments`**([Create Payment](https://docs.myfatoorah.com/reference/create-payment))

```json Request
{
    "PaymentMethod": "SAMSUNG_PAY",
    "Order": {
        "Amount": 20
    },
    "SourceOfFund": {
        "Card": {
            "Number": "5204240498885735",
            "ExpiryMonth": "06",
            "ExpiryYear": "26",
            "Cryptogram": "ALJJjhJSf8kZAmsy+VVBAoABFA==",
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
        "InvoiceId": "6389685",
        "PaymentId": "07076389685322474474",
        "PaymentURL": "https://your-website.com/payment-callback?paymentId=07076389685322474474&Id=07076389685322474474",
        "PaymentCompleted": true,
        "TransactionDetails": {
            "Invoice": {
                "Id": "6389685",
                "Status": "PENDING",
                "Reference": "2025001618",
                "CreationDate": "2025-12-24T16:35:59.7738109Z",
                "ExpirationDate": "2026-05-23T16:35:59.7738109Z",
                "ExternalIdentifier": null,
                "UserDefinedField": "",
                "MetaData": null
            },
            "Transaction": {
                "Id": "07076389685322474474",
                "Status": "FAILED",
                "PaymentMethod": "Samsung Pay",
                "PaymentId": "07076389685322474474",
                "ReferenceId": "07076389685322474474",
                "TrackId": "24-12-2025_3224744",
                "AuthorizationId": "07076389685322474474",
                "TransactionDate": "2025-12-24T16:35:59.8202131Z",
                "ECI": "",
                "IP": {
                    "Address": "",
                    "Country": ""
                },
                "Error": {
                    "Code": "MF002",
                    "Message": "DECLINED : Invalid card number"
                },
                "Card": {
                    "NameOnCard": "",
                    "Number": "520424xxxxxx5735",
                    "Token": "",
                    "PanHash": "98899f423ac13b6dbdc3ef62b275ece6e37d60f73b0c6894379b9c48d847c2ff",
                    "ExpiryMonth": "06",
                    "ExpiryYear": "26",
                    "Brand": "",
                    "Issuer": "",
                    "IssuerCountry": "",
                    "FundingMethod": ""
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
                "ServiceCharge": "0.22",
                "ServiceChargeVAT": "0.033",
                "ReceivableAmount": "19.747",
                "DisplayCurrency": "KWD",
                "ValueInDisplayCurrency": "20",
                "PayCurrency": "SAR",
                "ValueInPayCurrency": "247.02"
            },
            "Suppliers": []
        }
    }
}
```

## Samsung Pay Native Integration

*`https://docs.myfatoorah.com/docs/v3-samsung-pay-native` — updated 2026-02-16*

#### Introduction

Samsung Pay Native Integration allows you to accept payments directly from **Samsung Pay** without redirecting customers to external payment pages.\
It provides a **fast, secure, and native checkout experience** within your mobile application or website by using the customer’s payment credentials stored in **Samsung Wallet**.\
By integrating Samsung Pay natively, your customers can complete payments effortlessly using their **Samsung device’s authentication methods,** resulting in a smoother and more trusted checkout flow.

> 🚧 Note
>
> You can integrate Samsung Pay directly with our **Embedded Integration** without needing to make the steps outlined here.
> For more details, please check this link: [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-payment-v3)

#### Integration Flow Steps

##### **1. Initial Contact & CSR File Request**

* You will need to contact the MyFatoorah tech team to request a CSR (Certificate Signing Request) file.\
  Note: The CSR file will be used for generating the necessary certificates for Samsung Pay.

##### **2. Generate Payment Processing Certificate (PPC)**

* Once you have a CSR file, they need to use it to generate the Payment Processing Certificate (PPC) with Samsung Pay.
* This is an essential step to ensure that you can securely process payments using Samsung Pay.

##### **3. Share PPC with MyFatoorah**

* After generating the PPC, you must send it back to the MyFatoorah tech team.
* This allows MyFatoorah to install the certificate.

##### **4. Installation and Confirmation**

* Once the PPC is received, MyFatoorah’s tech team will install the certificate on their server.
* After successful installation, MyFatoorah tech team will confirm the installation with the vendor to complete the integration.

##### **5. Start Using Samsung Pay Integration**

After the confirmation from MyFatoorah, you can proceed with the next steps in the integration process:

**Endpoint:** `POST /v3/payments`

```json Request Example
{
    "PaymentMethod": "SAMSUNG_PAY",
    "Order": {
        "Amount": 25
    },
    "SourceOfFund": { 
      "Token": "{\"method\":\"3DS\",\"recurring_payment\":false,\"card_brand\":\"visa\",\"card_last4digits\":\"1738\",\"3DS\":{\"type\":\"S\",\"version\":\"100\",\"data\":\"eyJhbGciOiJSU0ExXzUiLCJraWQiOiJpbTZnTk5ZaS9YZ3BWNTVMaWZkN3BBTkQ3VU9rb3BHRHhIcy9WSmJ5NytVPSIsInR5cCI6IkpPU0UiLCJjaGFubmVsU2VjdXJpdHlDb250ZXh0IjoiUlNBX1BLSSIsImVuYyI6IkExMjhHQ00ifQ.OhMqFJKaJEiOnbWOaZM5ZW39shG0RifMl3qjOwz8oetCjhKCMqsugh9u_T5Xbq8TLbmm5PUNuk0eGe4KBaE_9cQ4J3eaEP6V2fhWZlep_gwsJCZycvuNTLSfCGMcnkbEEOKMMxYiRMzcAskqbJzEUpDwmNLE5OPnUY9lnRStI3KakcMjMn-DCaBmKik9parv4mZeMniiqJnxXz39mWRsIEo5OF1B4OSMwLBsZIByfMgH3uGcT9oXdTvCPjI62uJ5ByR64pfMOCt1edZgM3cg0LJ-KqAhGM42uM4517rdQGh6BZcaAch9WCs34H_F8RtNUdZVKJp0nUEQAlHxW-dHOA.j8gt5xNXz6C008FJ.242zDcviTAB8EHRLc7oesQJ4YoqRLt3aeOaFjmYeopCFmWfZMXElFG-qrEYUrFCEeQ8FNU9_xM1Wc4MeBzMgUpGTFY7Nz--hZzFlfV1QelUlt0nvwS_-e7QWgadUZgEaWO7iyPC2vqUxezNjBP3hgrLiQP-9G9yZTItxu0hZDlw8ow5VurJOPjsYT5xzstMXik4W6T2dDEsO7_iS9tE6rs3Tr4wWC9CqVb4OPqBDekI2REi1dFjl65E.T7L9YdU1y0jMRXNlN7F_iA\"}}"
	}
}

```
```json Response
{
    "IsSuccess": true,
    "Message": "",
    "ValidationErrors": null,
    "Data": {
        "InvoiceId": "6316950",
        "PaymentId": "07076316950317297473",
        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/Result?paymentId=07076316950317297473",
        "PaymentCompleted": true,
        "TransactionDetails": {
            "Invoice": {
                "Id": "6316950",
                "Status": "PENDING",
                "Reference": "2025060692",
                "CreationDate": "2025-11-26T08:33:41.5559932Z",
                "ExpirationDate": "2026-05-25T08:33:41.5559932Z",
                "ExternalIdentifier": null,
                "UserDefinedField": "",
                "MetaData": null
            },
            "Transaction": {
                "Id": "07076316950317297473",
                "Status": "FAILED",
                "PaymentMethod": "Samsung Pay",
                "PaymentId": "07076316950317297473",
                "ReferenceId": "07076316950317297473",
                "TrackId": "26-11-2025_3172974",
                "AuthorizationId": "07076316950317297473",
                "TransactionDate": "2025-11-26T08:33:41.7557429Z",
                "ECI": "",
                "IP": {
                    "Address": "",
                    "Country": ""
                },
                "Error": {
                    "Code": "MF002",
                    "Message": "DECLINED : Invalid card number"
                },
                "Card": {
                    "NameOnCard": "",
                    "Number": "427106xxxxxx3952",
                    "PanHash": "fa6e018a4f6e01a4b05de343e4aefc3890117c63a68eaffaecd71c11ece52d13",
                    "ExpiryMonth": "12",
                    "ExpiryYear": "29",
                    "Brand": "Visa",
                    "Issuer": "",
                    "IssuerCountry": "",
                    "FundingMethod": ""
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
                "ValueInBaseCurrency": "25",
                "ServiceCharge": "0.1",
                "ServiceChargeVAT": "0.015",
                "ReceivableAmount": "24.885",
                "DisplayCurrency": "KWD",
                "ValueInDisplayCurrency": "25",
                "PayCurrency": "KWD",
                "ValueInPayCurrency": "25"
            },
            "Suppliers": []
        }
    }
}
```

## STC Pay

*`https://docs.myfatoorah.com/docs/stcpay` — updated 2026-02-16*

Embedded Payment

#### **Introduction**

**STC Pay Embedded** enables you to give your customers a better user experience when paying using STC Pay. It enables your customers to enter their card information directly on your checkout page instead of being redirected.

**MyFatoorah STC Pay** embedded payment is a Javascript library that provides a form for collecting the mobile number and OTP from the customer. This form can be placed and styled on your checkout page. When your customer enters the mobile number and his OTP, **MyFatoorah** will enable you to complete the payment using the [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint by following the below steps.

#### **How it Works**

> 👍 Before You start
>
> Kindly refer to the prerequisite section in the [Embedded Payment](https://docs.myfatoorah.com/docs/embedded-payment#prerequisite).

The following detailed steps will explain how to add the **MyFatoorah Embedded STC Pay** to your checkout page.

##### 1. Call InitiateSession endpoint

After you call the InitiateSession endpoint, you will get a SessionId and the CountryCode.

```json JSON
{
    "IsSuccess": true,
    "Message": "Initiated Successfully!",
    "ValidationErrors": null,
    "Data": {
        "SessionId": "dfc6a3c3-df09-44cb-9c6a-0a6375752da6",
        "CountryCode": "KWT",
        "CustomerTokens": []
    }
}
```

##### 2. Include the Javascript library

Choose the test or the live library according to your working environment.

```html
// Test Environment
<script src="https://demo.myfatoorah.com/stcPay/v1/stcpay.js"></script> 

// Live Environment For Saudi Arabia
<script src="https://sa.myfatoorah.com/stcPay/v1/stcpay.js"></script> 
```

##### 3. Add the form

You need to define a div element with a unique id attribute. The form will be loaded inside this div after passing the div id to the configuration variable.

```html
<div id="mf-stc-pay"></div>
```

##### 4. STC Pay Configuration

Now you need to add the configuration variables. Place the following snippet in a new script tag after loading the library in step 1, and replace the **sessionId** parameter with the "SessionId" you receive from the InitiateSession Endpoint. You should call **myFatoorahStc.init** using the configuration variable that you created.

For the **countyCode** parameter check our list of [ISO Lookups](https://docs.myfatoorah.com/docs/iso-lookups). You should add the **CountryCode** that you receive in the response of **InitiateSession**.

```javascript
		var configStcPay = {
			sessionId: session, //Here you add the "SessionId" you receive from InitiateSession Endpoint.
			countryCode: country, //Here, add your "CountryCode" you receive from InitiateSession Endpoint.
			amount: amount,
			mobileNumber: "0557877988",
			containerId: "mf-stc-pay",
			callback: paymentSt
    }
myFatoorahStc.init(configStcPay);

```

This will load the STC Pay view on your checkout page.

###### Using your display of STC Pay:

MyFatoorah allows you to integrate STC Pay functionalities seamlessly into your application, enabling payments via STC Pay using your own custom user interface, without displaying MyFatoorah's view.

###### Steps:

1. You must add a value for the mobileNumber and don't add a value for the containerId in the configuration variable. Then you call **myFatoorahStc.init(configStcPay);**. MyFatoorah then will trigger the **sessionStarted** function returning to you the SessionId and the expiryDuration for the OTP to display it to your customer, and sends the OTP to the customer.

```javascript JavaScript
		var configStcPay = {
			sessionId: session, //Here you add the "SessionId" you receive from InitiateSession Endpoint.
			countryCode: country, //Here, add your "CountryCode" you receive from InitiateSession Endpoint.
			amount: amount,
			mobileNumber: "0557877988",
      sessionStarted: sessionStarted,
			callback: paymentStc
    }
		function sessionStarted(response) 
		{ 
		{/* Here you need to display the OTP UI to the customer */}
			var sessionId = response.sessionId; 
			console.log("response from SessionStarted >> ", response); 
		};
myFatoorahStc.init(configStcPay);

```
```json callback object
{
    "isSuccess": true,
    "sessionId": "f0b0e33f-3e3e-403c-855c-ef6a0c778ec5",
    "expiryDuration": 120
}
```

2. You will collect the OTP on your user interface and send it to MyFatoorah in the function **myFatoorahStc.submitOtp(value);**. When you call the function, MyFatoorah again triggers the callback function giving you the SessionId. You will then use the SessionId to call **ExecutePayment**.

```text Submitting the OTP
myFatoorahStc.submitOtp("1234");
```
```json callback object
{
    "isSuccess": true,
    "sessionId": "f0b0e33f-3e3e-403c-855c-ef6a0c778ec5"
}
```

##### 5. Handle the Callback function

After the customer completes the payment details entry, MyFatoorah triggers the CallBack function and returns the SessionId to you. You should send the SessionId to your backend to call ExecutePayment to complete the payment.

```javascript
function paymentStc(response) {
  if (response.isSuccess) {
    console.log(response);
    var sessionId = response.sessionId;
    console.log("SessionID >> ", sessionId);
  } else {
    console.log(response);
  }
}
```

##### 6. Call the ExecutePayment Endpoint

Then, you need to send the **SessionId** to your server to process the actual transaction, which should be done in your backend environment using [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) endpoint.

> ❗️ Parameters conflict
>
> Do not pass the **PaymentMethodId** parameter in the [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) request and use the **SessionId** parameter instead. As **PaymentMethodId** overwrite **SessionId**.
>
> The "SessionId" you receive from InitiateSession Endpoint can not be used directly in [ExecutePayment](https://docs.myfatoorah.com/docs/execute-payment) Endpoint.

You will get the PaymentURL in the [Response Model](https://docs.myfatoorah.com/docs/response-model). This payment URL is the **3D Secure** page URL as this type of payment supports only the 3D Secure Flow.  In this case, you should redirect the customer to this URL to complete the 3D Secure challenge.

```json ExecutePayment Request
{
   "SessionId":"8b521de0-4f92-4a17-b277-6f6f87435118",
   "InvoiceValue":10,
}
```
```json ExecutePayment Response
{
   "IsSuccess":true,
   "Message":"Invoice Created Successfully!",
   "ValidationErrors":null,
   "Data":{
      "InvoiceId":123456,
      "IsDirectPayment":false,
      "PaymentURL":"https://demo.MyFatoorah.com/En/KWT/PayInvoice/MpgsAuthentication?paymentId=0706104008982520266&sessionId=SESSION0002087297105E3203998J76",
      "CustomerReference":"",
      "UserDefinedField":null,
      "RecurringId":""
   }
}
```

> 👍 Payment Status
>
> To update your system automatically instead of manually following up with your customers via [your portal account](https://portal.myfatoorah.com/), you can use the [Webhook](https://docs.myfatoorah.com/docs/webhook) feature or/and set the **CallBackURL**/**ErrorUrl** parameter, that **MyFatoorah** will invoke once the payment is done.

***
