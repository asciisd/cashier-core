# MyFatoorah — Mobile SDKs

## Overview

*`https://docs.myfatoorah.com/docs/sdk-overview` — updated 2025-11-11*

> Mobile app integration

In order to facilitate the integration of your application with MyFatoorah payment platforms, we have developed a cutting-edge SDK that works smoothly with your application and provides you with a clear way to embed our payment functions within your application.

The SDK will save your efforts and time instead of integrating with our API using normal API calls and will allow you to have the setup ready in a quick, modern, and secured way. Currently, we are supporting the major mobile devices platforms OS. Let's figure out the main SDK integration guide as following:

* IOS
* Android
* Flutter
* React Native
* Cordova

***

The SDK has multiple integration option.

* Apple Pay
* Google Pay
* Cards
* Gateway Redirection
* Direct Payment

***

In case you are calling all the payment APIs from the backend, you can also use the SDK to simplify your mobile side code.

## iOS SDK

*`https://docs.myfatoorah.com/docs/sdk-guide` — updated 2025-11-14*

> SDK Guide for iOS

#### **Demo project**

* Use [iOS source files](https://dev.azure.com/myfatoorahsc/_git/MF-SDK-iOS-Demo?version=GBmfsdk_version2_demo) for the demo project.

#### **SDK iOS Installation / Usage**

##### Cocoapod

You can download MFSDK by adding this line to your `Podfile`

```shell Swift
pod 'MyFatoorah'
pod repo update
pod install
```
```objectivec
pod 'MyFatoorah'
pod repo update
pod install
```

##### Swift Package Manager

You can download MFSDK by adding the `https://dev.azure.com/myfatoorahsc/_git/MF-SDK-iOS-Demo` repository as a Swift Package

Import framework in AppDelegate:

```swift
import MFSDK
```
```objectivec
import MFSDK
```

Add below code in the **didFinishLaunchingWithOptions** method:

```swift
func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?) -> Bool {
// Override point for customization after application launch.
// set up your My Fatoorah Merchant details
MFSettings.shared.configure(token: <#Put your token here#>, country: <# Country of your account #>, environment: <# Test or Live #>)


// you can change color and title of nvgigation bar
let them = MFTheme(navigationTintColor: .white, navigationBarTintColor: .lightGray, navigationTitle: "Payment", cancelButtonTitle: "Cancel")
MFSettings.shared.setTheme(theme: them)
return true
}
```
```objectivec
- (BOOL)application:(UIApplication *)application didFinishLaunchingWithOptions:(NSDictionary *)launchOptions {
// set up your My Fatoorah Merchant details
    [[MFSettings shared] configureWithToken:@<#Put your token here#> country: <#Country of your account#>  environment:<#Live or Test#>];


// you can change color and title of nvgigation bar
MFTheme* theme = [[MFTheme alloc] initWithNavigationTintColor:[UIColor whiteColor] navigationBarTintColor:[UIColor lightGrayColor] navigationTitle:@"Payment" cancelButtonTitle:@"Cancel"];
[[MFSettings shared] setThemeWithTheme:theme];

return YES;
}
```

> 📘 Updating Your System:
>
> Starting from release '2.0.132', MyFatoorah will not call the callbackurl or errorurl. To update your system, share the invoiceId with the backend side,  and call GetPaymentStatus to receive the updated status.
>
> Moreover, It is highly recommended to implement the webhook as well to receive events for all transaction status changes directly from MyFatoorah side.

***

#### **Initiate/Execute Payment**

As described earlier for the [Gateway Integration](https://docs.myfatoorah.com/docs/gateway-integration), we are going to have the SDK integrated with the same steps to make a successful integration with the SDK.

> 🚧 Initiate Payment
>
> As a good practice, you don't have to call the [Initiate Payment](https://docs.myfatoorah.com/docs/initiate-payment) function every time you need to execute payment, but you have to call it at least once to save the PaymentMethodId that you will need to call [Execute Payment](https://docs.myfatoorah.com/docs/execute-payment)

##### Initiate Payment

```swift Swift
// initiatePayment 
        let invoiceValue = 5.0
        var selectedPaymentMethod = 1
        let initiatePayment = MFInitiatePaymentRequest(invoiceAmount: invoiceValue, currencyIso: .kuwait_KWD)
        MFPaymentRequest.shared.initiatePayment(request: initiatePayment, apiLanguage: .english) { [weak self] (response) in
            switch response {
            case .success(let initiatePaymentResponse):
                var paymentMethods = initiatePaymentResponse.paymentMethods
                if let paymentMethods = initiatePaymentResponse.paymentMethods, !paymentMethods.isEmpty {
                    selectedPaymentMethod = paymentMethods[0].paymentMethodId
                }
            case .failure(let failError):
                print(failError)
            }
        }
```
```objectivec ObjectiveC
			double invoiceValue = 5.0;
        MFInitiatePaymentRequest* initiatePayment = [[MFInitiatePaymentRequest alloc] initWithInvoiceAmount:invoiceValue currencyIso:MFCurrencyISOKuwait_KWD];
        [[MFPaymentRequest shared] initiatePaymentWithRequest:initiatePayment apiLanguage:MFAPILanguageEnglish completion:^(MFInitiatePaymentResponse * initiatePaymentResponse, MFFailResponse * failResponse) {
            if (initiatePaymentResponse != NULL) {
                if ([initiatePaymentResponse.paymentMethods count] > 0) {
                    NSLog(@"%@",initiatePaymentResponse);
                }
            } else {
                    NSLog(@"%@",failResponse);
                    
                }
            }
        }];
```

##### Execute Payment

```swift Swift
        let request = MFExecutePaymentRequest(invoiceValue: invoiceValue, paymentMethod: paymentMethod.paymentMethodId)
        
        // Uncomment this to add ptoducts for your invoice
        // var productList = [MFProduct]()
        // let product = MFProduct(name: "ABC", unitPrice: 1, quantity: 2)
        // productList.append(product)
        // request.invoiceItems = productList
        
        MFPaymentRequest.shared.executePayment(request: request, apiLanguage: .english) { [weak self] (response,invoiceId) in
            switch response {
            case .success(let executePaymentResponse):
                print("\(executePaymentResponse.invoiceStatus ?? "")")
            case .failure(let failError):
                print(failError)
            }
        }


 
```
```objectivec ObjectiveC
        MFExecutePaymentRequest* request = [[MFExecutePaymentRequest alloc] initWithInvoiceValue:invoiceValue paymentMethod:_paymentMethodId];
        //         Uncomment this to add ptoducts for your invoice
        // NSMutableArray* productList = [[NSMutableArray alloc] init];
        // MFProduct* product = [[MFProduct alloc] initWithName:@"ABC" unitPrice:1 quantity:2];
        // [productList addObject:product];
        // request.invoiceItems = productList;
        [[MFPaymentRequest shared] executePaymentWithRequest:request apiLanguage:MFAPILanguageEnglish completion:^(MFPaymentStatusResponse * paymentStatusResponse, MFFailResponse * failResponse, NSString* invoiceId) {
            if (paymentStatusResponse != NULL) {
                NSLog(@"%@",paymentStatusResponse);
            } else {
                NSLog(@"%@",failResponse);
            }
        }];

```

##### MFPaymentDelegate

````swift Swift
// ```MFPaymentDelegate```
// You can conform this protocol to listen for invoice status, now this protocol has `didInvoiceCreated`
// method to get the invoice id immediately after creating the invoice. 
class ViewController: UIViewController {
    override func viewDidLoad() {
        // Set delegate for your view controller
        MFSettings.shared.delegate = self
}


// conforms ```MFPaymentDelegate```
extension ViewController: MFPaymentDelegate {
    func didInvoiceCreated(invoiceId: String) {
        print("#\(invoiceId)")
    }
}   
````

````objectivec
// ```MFPaymentDelegate```
// You can conform this protocol to listen for invoice status, now this protocol has `didInvoiceCreated`
// method to get the invoice id immediately after creating the invoice. 

// ViewController.h
@interface ViewController : UIViewController<MFPaymentDelegate>
.
.
@end


// ViewController.m
@implementation ViewController

- (void)viewDidLoad {
    [super viewDidLoad];
    
    [MFSettings shared].delegate = self;
}

// conforms `MFPaymentDelegate`
- (void)didInvoiceCreatedWithInvoiceId:(NSString * _Nonnull)invoiceId {
    NSLog(@"invoice id: %@", invoiceId);
}
````

***

#### **In-App Apple Pay iOS**

1-Create a variable from `MFApplePayButton`, you can create it from code or storyboard as you like.

```swift Add Apple Pay button
let applePayButton = MFApplePayButton()
view.addSubview(applePayButton)
applePayButton.translatesAutoresizingMaskIntoConstraints = false
applePayButton.leadingAnchor.constraint(equalTo: view.leadingAnchor).isActive = true
applePayButton.trailingAnchor.constraint(equalTo: view.trailingAnchor).isActive = true
applePayButton.widthAnchor.constraint(equalTo: view.widthAnchor).isActive = true
applePayButton.centerYAnchor.constraint(equalTo: view.centerYAnchor).isActive = true
applePayButton.heightAnchor.constraint(equalToConstant: 80).isActive = true
```

2- Initiate session and get sessionId to handle Apple Pay

```swift
let invoiceValue = Decimal(string: amountTextField.text ?? "0") ?? 0
let request = MFExecutePaymentRequest(invoiceValue: invoiceValue, displayCurrencyIso: .kuwait_KWD)
MFPaymentRequest.shared.initiateSession(apiLanguage: .english) { [weak self] response in
     switch response {
      case .success(let session):
        self?.applePayButton.load(session, request, .english, startLoading: {
            self?.activityIndicator.startAnimating()
    }, completion: { response, invoiceId in
            self?.activityIndicator.stopAnimating()
            switch response {
             case .success(let executePaymentResponse):
              if let invoiceStatus = executePaymentResponse.invoiceStatus {
                self?.showSuccess(invoiceStatus)
              }
             case .failure(let error):
                    self?.showFailError(error)
                    }
                })
      case .failure(let error):
                print("#initiate session", error.localizedDescription)
        }
}
```

3- (Optional) Configure Apple Pay Button

```swift
let applePayConfigure = MFInApplePayConfigureBuilder.default
        applePayConfigure.setHeight(50) // set height to Apple Pay Button
        applePayConfigure.setBorderRadius(20) // set border radius to Apple Pay Button
        applePayConfigure.setButtonText("Buy with") // set the text before Apple icon
        applePayConfigure.hideLoadingIndicator(true) // hide loading indicator, default is false
```

> 📘
>
> For the button text which is before the Apple icon you should choose one from those texts \["", "Buy with", "Pay with", "Check Out with", "Continue with", "Book with", "Donate with", "Subscribe with", "Reload with", "Add Money with", "Top Up with", "Order with", "Rent with", "Support with", "Contribute with", "Tip with", "Set Up"],

> 👍 Apple Pay Test
>
> Use the [Apple document](https://developer.apple.com/apple-pay/sandbox-testing/) that explains how to test Apple pays on your device.

#### **Embedded Payment for iOS**

**Usage**\
1- Create a variable from `MFCardPaymentView`, you can add it:\
**In storyboard**

* Drag view to view
* Select the dragged view.
* In the right navigation select 'Identity Inspector'.
* Set Class `MFPaymentCardView` and Module `MFSDK`

![](https://files.readme.io/9ccfc21-Screen_Shot_2021-10-18_at_4.06.52_PM.png "Screen Shot 2021-10-18 at 4.06.52 PM.png")

**Or by code**

```swift
let cardPaymentView = MFCardPaymentView()
view.addSubview(cardPaymentView)
cardPaymentView.translatesAutoresizingMaskIntoConstraints = false
cardPaymentView.leadingAnchor.constraint(equalTo: view.leadingAnchor).isActive = true
cardPaymentView.trailingAnchor.constraint(equalTo: view.trailingAnchor).isActive = true
cardPaymentView.widthAnchor.constraint(equalTo: view.widthAnchor).isActive = true
cardPaymentView.centerYAnchor.constraint(equalTo: view.centerYAnchor).isActive = true
cardPaymentView.heightAnchor.constraint(equalToConstant: 220).isActive = true
```
```objectivec
MFPaymentCardView * paymentCardView = [[MFPaymentCardView alloc] init];
[self.view addSubview:paymentCardView];
[[paymentCardView.leadingAnchor constraintEqualToAnchor:self.view.leadingAnchor] setActive:YES];
[[paymentCardView.trailingAnchor constraintEqualToAnchor:self.view.trailingAnchor] setActive:YES];
[[paymentCardView.widthAnchor constraintEqualToAnchor:self.view.widthAnchor] setActive:YES];
[[paymentCardView.heightAnchor constraintEqualToConstant:220] setActive:YES];
```

2- You can configure the card with labels, placeholders, colors, height, and border-radius also by code or storyboard

```swift
// get default configure
    var configure = MFCardConfigureBuilder.default
    // but you can create yours
    // set placeholders
    configure.setPlaceholder(MFCardPlaceholder(cardHolderNamePlaceholder: "Name", cardNumberPlaceholder: "Number", expiryDatePlaceholder: "MM / YY", cvvPlaceholder: "CVV"))
    // set labels
    configure.setLabel(MFCardLabel(cardHolderNameLabel: "Card holder name", cardNumberLabel: "Card number", expiryDateLabel: "MM / YY", cvvLabel: "CVV", showLabels: true, fontWeight: .normal))
    // set theme
    let theme = MFCardTheme(inputColor: .black, labelColor: .black, errorColor: .red, borderColor: .black)
    theme.language = .english // select .arabic for rtl vie
    configure.setTheme(theme)
    // set height and margin of card inputs
    configure.setCardInput(MFCardInput(inputHeight: 32, inputMargin: 15))

    // set labels and texts font size
    configure.setFontSize(15)
    // set border width
    configure.setBorderWidth(1)
    // set border radius for input fields
    configure.setBorderRadius(8)
    // set the display of card icons
    configure.setHideCardIcon(false)
    // set Font Family
    configure.setFontFamily(.timesNewRoman)
  	// set box shadow
    configure.setBoxShadow(MFBoxShadow(hOffset: 0, vOffset: 0, blur: 0, spread: 0, color: .gray))


    // create the new configure and assigned to payment card view
    paymentCardView.configure = configure.build()
```
```objectivec
// get default configure
    MFCardConfigureBuilder * configure = [MFCardConfigureBuilder default];
    // but you can create yours
    // set placeholders
    MFCardPlaceholder * placeholder = [[MFCardPlaceholder alloc] initWithCardHolderNamePlaceholder:@"Name on card" cardNumberPlaceholder:@"Card number" expiryDatePlaceholder:@"MM / YY" cvvPlaceholder:@"CVV"];
    [configure setPlaceholder:placeholder];

    // set labels
    MFCardLabel * label = [[MFCardLabel alloc] initWithCardHolderNameLabel:@"Card holder name" cardNumberLabel:@"Card Number" expiryDateLabel:@"Expiry Date" cvvLabel:@"CVV" showLabels:YES];
    [configure setLabel:label];

    // set theme
    MFCardTheme * theme = [[MFCardTheme alloc] initWithInputColor:UIColor.blackColor labelColor:UIColor.blackColor errorColor:UIColor.redColor];
    [configure setTheme:theme];


    // set labels and texts font size
    [configure setFontSize:14];

    // set border width
    [configure setBorderWidth:1];

    // set border radius
    [configure setBorderRadius:8];

    // create the new configure and assigned to payment card view
    paymentCardView.configure = [configure build];
```

3- Initiate session and get session id to setup `MFCardPaymentView`

```swift
MFPaymentRequest.shared.initiateSession(apiLanguage: .english) { response in
		switch response {
    case .success(let session):
      	self.paymentCardView.load(initiateSession: session)
    case .failure(let error):
    		self.showFailError(error)
    }
}
```
```objectivec
[[MFPaymentRequest shared] initiateSessionWithApiLanguage:MFAPILanguageEnglish completion:^(MFInitiateSessionResponse * response, MFFailResponse * error) {
            if (error == NULL) {
                [paymentCardView loadWithInitiateSession:response];
            } else {
                NSLog(error);
            }
    }];
```

(Alternative Option) You can call the function including onCardBinChanged closure to receive the card bin

```swift Swift
MFPaymentRequest.shared.initiateSession(apiLanguage: .english) { [weak self] response in
		switch response {
    case .success(let session):
      	self?.paymentCardView.load(initiateSession: session) { bin in
        		print("bin", bin)
        }
    case .failure(let error):
    		self?.showFailError(error)
    }
}
```

4- Now your `MFPaymentCardView` is set up, so once the customer entered the card details you need to call `pay`

```swift
// create request, make sure you don't set paymentMethodId, because it override sessionId and this is wrong.
let request = MFExecutePaymentRequest(invoiceValue: 5)

// call pay method to make your order
cardPaymentView.pay(request, .english) { response, invoiceId in
    switch response {
    case .success(let paymentStatus):
        print(paymentStatus)
    case .failure(let error):
        print(error)
    }
}
```
```objectivec
// create request, make sure you don't set paymentMethodId, because it override sessionId and this is wrong.
    NSDecimalNumber * decimalNumber = [NSDecimalNumber decimalNumberWithString:self.amountTextField.text];
    NSDecimal invoiceValue = [decimalNumber decimalValue];
    MFExecutePaymentRequest * request = [[MFExecutePaymentRequest alloc] initWithInvoiceValue:invoiceValue];
    [_paymentCardView pay:request :MFAPILanguageEnglish completion:^(MFPaymentStatusResponse * response, MFFailResponse * error, NSString * invoiceId) {
        if (response != NULL) {
            if (response.invoiceStatus != NULL) {
                [self showSuccess:response.invoiceStatus];
            } else {
                [self showFailError:error];
            }
        }else {
            [self showFailError:error];
        }
    }];
```

(Alternative Option) You can validate the session first and complete the payment if the session is valid

```swift Swift
// create request, make sure you don't set paymentMethodId, because it override sessionId and this is wrong.
let request = MFExecutePaymentRequest(invoiceValue: 5)

// call calidate method
paymentCardView.validate{ [weak self] result in
    switch result {
    case .success(let cardBrand):
        
        // call pay method to make your order
        cardPaymentView.pay(request, .english) { response, invoiceId in
            switch response {
            case .success(let paymentStatus):
                print(paymentStatus)
            case .failure(let error):
                print(error)
            }
        }
    case .failure(let failError):
    }
}
```

***

#### **Payment Inquiry**

We have explained the main usage for the [Payment Inquiry](https://docs.myfatoorah.com/docs/payment-inquiry) function, which will enable your application to get the full details about a certain invoice/payment. You can use this function within your application on different platforms as well. Here we are explaining some samples of its usage through the SDK.

```swift
let paymentStatusRequest = MFPaymentStatusRequest(Key: "id", KeyType: .invoiceId)
MFPaymentRequest.shared.getPaymentStatus(paymentStatus: paymentStatusRequest, apiLanguage: .english) { [weak self] (response) in
    self?.stopRequestLoading()
        switch response {
            case .success(let paymentStatusResponse):
                print("\(paymentStatusResponse.invoiceStatus)")
            case .failure(let failError):
                print("\(failError)")
        }
}
```
```objectivec
MFPaymentStatusRequest* request = [[MFPaymentStatusRequest alloc]initWithKey:@"1234" KeyType:MFKeyTypeInvoiceId];
    
    [[MFPaymentRequest shared] getPaymentStatusWithPaymentStatus:request apiLanguage:MFAPILanguageEnglish completion:^(MFPaymentStatusResponse * paymentStatusResponse, MFFailResponse * failResponse) {
        if(paymentStatusResponse != NULL) {
            NSLog(@"%@",paymentStatusResponse.invoiceStatus);
        } else {
            NSLog(@"%@",failResponse);
        }
    }];
```

***

#### **Send Payment**

We have explained in the [Send Payment](https://docs.myfatoorah.com/docs/send-payment) section earlier, the different usage cases for it and how it works, here we are going to embed some sample code for calling it through the SDK on the different platforms.

```swift
let invoiceValue = 5.0
        var notificationOption : MFNotificationOption =  .all
        /*
         notificationOption = .sms
         notificationOption = .email
         notificationOption = .link
         */
        
        let invoice = MFSendPaymentRequest(invoiceValue: invoiceValue, notificationOption: notificationOption, customerName: "customerName")
        
        invoice.customerEmail = "a@b.com"// must be email Required if you choose notificationOption .all or  .email
        invoice.customerMobile = "mobile no"//Required if you choose notificationOption .all or .sms
        invoice.mobileCountryIsoCode = MFMobileCountryCodeISO.kuwait.rawValue
        
        MFPaymentRequest.shared.sendPayment(request: invoice, apiLanguage: .english) { [weak self] (result) in
            switch result {
            case .success(let sendPaymentResponse):
                if let invoiceURL = sendPaymentResponse.invoiceURL {
                    print("result: RedirectUrl is \(invoiceURL)")
                }
            case .failure(let failError):
                print("Error: \(failError)")
            }
        }
```
```objectivec
double invoiceValue = 5.0;
    MFSendPaymentRequest* request = [[MFSendPaymentRequest alloc] initWithInvoiceValue:invoiceValue notificationOption:MFNotificationOptionAll customerName:@"Test"];
    request.mobileCountryIsoCode = [MFEnumRawValue rawValueWithEnumValue:MFMobileCountryCodeISOKuwait];
    [[MFPaymentRequest shared] sendPaymentWithRequest:request apiLanguage:MFAPILanguageEnglish completion:^(MFSendPaymentResponse * sendPaymentResponse, MFFailResponse * failResponse) {
        
        if(sendPaymentResponse != NULL) {
            NSLog(@"%@",sendPaymentResponse.invoiceURL);
            
        } else {
            NSLog(@"%@",failResponse.errorDescription);
        }
        
    }];
```

***

#### **Direct Payment/Tokenization**

As we have explained earlier in the \[Direct Payment] integration and how it works, it also has the same scenario for the SDK implementation, you have to know the following steps to understand how it works:

* Get the payment method that allows Direct Payment by calling initiatePayment to get paymentMethodId
* Collect card info from user ***MFCardInfo(cardNumber: "51234500000000081", cardExpiryMonth: "05", cardExpiryYear: "21", cardSecurityCode: "100", saveToken: false)***
* If you want to save your credit card info and get a token for your next payment you have to set ***saveToken: true*** and you will get the token in the response read more in [Tokenization](https://myfatoorah.readme.io/v2.0/docs/tokenization)
* If you want to execute payment through a saved token you have to use ***MFCardInfo(cardToken: "put your token here")***
* Now you are ready to execute the payment, please check the following sample code

```swift
let card = MFCardInfo(cardNumber: "51234500000000081", cardExpiryMonth: "05", cardExpiryYear: "21", HolderName: "John", cardSecurityCode: "100", saveToken: true) //MFCardInfo(cardToken: "token")
         // card.bypass = false // default is true
        let invoiceValue = 5.0
        
        let paymentMethod = 2 // if you don't Know this you have to call initiatePayment
        let request = MFExecutePaymentRequest(invoiceValue: invoiceValue, paymentMethod: 2)
        MFPaymentRequest.shared.executeDirectPayment(request: request, cardInfo: card, apiLanguage: .english) { [weak self] response, invoiceId in
            switch response {
            case .success(let directPaymentResponse):
                if let cardInfoResponse = directPaymentResponse.cardInfoResponse, let card = cardInfoResponse.cardInfo {
                    print("Status: with card number \(card.number)")
                }
                if let invoiceId = invoiceId {
                    print("Success with invoiceId \(invoiceId)")
                }
            case .failure(let failError):
                print("Error: \(failError.errorDescription)")
                if let invoiceId = invoiceId {
                    print("Fail: \(failError.statusCode) with invoiceId \(invoiceId)")
                }
            }
        }
```
```objectivec
MFExecutePaymentRequest* request = [self getExecutePaymentRequest:paymentMethodId];

    MFCardInfo* card = [self getCardInfo];

    [self startLoading];

    [[MFPaymentRequest shared] executeDirectPaymentWithRequest:request cardInfo:card apiLanguage:MFAPILanguageEnglish completion:^(MFDirectPaymentResponse * response, MFFailResponse * error, NSString * invoiceId) {


        if (response !=NULL) {

            if (response.cardInfoResponse != NULL) {

                NSLog(@"%@",[NSString stringWithFormat:@"Status: with card number: %@", response.cardInfoResponse.cardInfo.number]);

            }

            if (invoiceId != NULL) {
                NSLog(@"%@", [NSString stringWithFormat:@"Success with invoice id %@", invoiceId ]);

            }

        }

    }];
```

## Android SDK

*`https://docs.myfatoorah.com/docs/android-sdk` — updated 2025-11-14*

> SDK Guide for Android

#### **Demo project**

* Use [Android source files](https://dev.azure.com/myfatoorahsc/_git/MF-SDK-Android-Demo) for the demo project.

***

#### **SDK Android Installation / Usage**

1- Add `mavenCentral()` under ` allprojects/repositories` and `buildscript/repositories` in the project Gradle file.

2-Add the following dependency in the app Gradle file:

```kotlin Usage
implementation 'com.myfatoorah:myfatoorah:3.0.4'
```

2-Add ProGuard Rules (Mandatory for ShrinkResources):\
If you’re enabling shrinkResources or using code obfuscation, add the following to your ProGuard or R8 file (proguard-rules.pro):

```kotlin Usage
### Keep rules for MyFatoorah SDK
-keep class com.myfatoorah.sdk.** { *; }
```

Add bellow line in the `onCreate()` method of your `Application` class:

```kotlin
// set up your My Fatoorah Merchant details
MFSDK.init("Put you Token API Key here", MFCountry.KUWAIT, MFEnvironment.TEST)
```
```java
// set up your My Fatoorah Merchant details
MFSDK.INSTANCE.init("Put you Token API Key here", MFCountry.KUWAIT, MFEnvironment.TEST);
```

Add bellow code in the `onCreate()` method of your `Activity`:

```kotlin
// You can custom your action bar, but this is optional not required to set this line
MFSDK.setUpActionBar("MyFatoorah Payment", R.color.toolbar_title_color, R.color.toolbar_background_color, true)
// To hide action bar
// MFSDK.setUpActionBar(isShowToolBar = false)
```
```java
// You can custom your action bar, but this is optional not required to set this line
 MFSDK.INSTANCE.setUpActionBar("MyFatoorah Payment", R.color.toolbar_title_color, R.color.toolbar_background_color, true);
```

> 📘 After Testing
>
> Once your testing is finished, simply replace environment from TEST to LIVE and the API URL with the live, click [here ](https://docs.myfatoorah.com/docs/live-token) for more information.

***

#### **Initiate/Execute Payment**

As described earlier for the [Gateway Integration](https://docs.myfatoorah.com/docs/gateway-integration), we are going to have the SDK integrated with the same steps to make a successful integration with the SDK.

> 🚧 Initiate Payment
>
> As a good practice, you don't have to call the [Initiate Payment](https://docs.myfatoorah.com/docs/initiate-payment) function every time you need to execute payment, but you have to call it at least once to save the PaymentMethodId that you will need to call [Execute Payment](https://docs.myfatoorah.com/docs/execute-payment)

```kotlin
// initiatePayment 

val request = MFInitiatePaymentRequest(0.100, MFCurrencyISO.KUWAIT_KWD)
MFSDK.initiatePayment(
    request,
    MFAPILanguage.EN
) { result: MFResult<MFInitiatePaymentResponse> ->
    when (result) {
        is Success ->
            Log.d(TAG, "Response: " + Gson().toJson(result.response))
        is Fail ->
            Log.d(TAG, "Fail: " + Gson().toJson(result.error))
    }
}
    

// executePayment 

val request = MFExecutePaymentRequest(1, 0.100)
MFSDK.executePayment(
    this,
    request,
    MFAPILanguage.EN,
    onInvoiceCreated = {
        Log.d(TAG, "invoiceId: $it")
    }
) { invoiceId: String, result: MFResult<MFGetPaymentStatusResponse> ->
    when (result) {
        is Success -> 
            Log.d(TAG, "Response: " + Gson().toJson(result.response))
        is Fail -> 
            Log.d(TAG, "Fail: " + Gson().toJson(result.error))
    }
}
```
```java
// initiatePayment 

MFInitiatePaymentRequest request = new MFInitiatePaymentRequest(0.100, MFCurrencyISO.KUWAIT_KWD);
MFSDK.INSTANCE.initiatePayment(
    request,
    MFAPILanguage.EN,
    (MFResult<MFInitiatePaymentResponse> result) -> {
        if (result instanceof Success) {
            Log.d(TAG, "Response: " + new Gson().toJson(
                    ((Success<MFInitiatePaymentResponse>) result).getResponse()));
        } else if (result instanceof Fail) {
            Log.d(TAG, "Error: " + new Gson().toJson(((Fail) result).getError()));
        }

        return Unit.INSTANCE;
    });


// executePayment 

MFExecutePaymentRequest request = new MFExecutePaymentRequest(1, 0.100);
MFSDK.INSTANCE.executePayment(
        this,
        request,
        MFAPILanguage.EN,
        (String invoiceId) -> {
            Log.d(TAG, "invoiceId: " + invoiceId);
            return Unit.INSTANCE;
        },
        (String invoiceId, MFResult<MFGetPaymentStatusResponse> result) -> {
            if (result instanceof Success)
                Log.d(TAG, "Response: " + new Gson().toJson(((Success<MFGetPaymentStatusResponse>) result).getResponse()));
            else if (result instanceof Fail)
                Log.d(TAG, "Error: " + new Gson().toJson(((Fail) result).getError()));

            return Unit.INSTANCE;
        });
```

***

#### **Direct Payment/Tokenization**

As we have explained earlier in the \[Direct Payment] integration and how it works, it also has the same scenario for the SDK implementation, you have to know the following steps to understand how it works:

* Get the payment method that allows Direct Payment by calling initiatePayment to get paymentMethodId
* Collect card info from user ***MFCardInfo(cardNumber: "51234500000000081", cardExpiryMonth: "05", cardExpiryYear: "21", cardSecurityCode: "100", saveToken: false)***
* If you want to save your credit card info and get a token for next payment you have to set ***saveToken: true*** and you will get the token in the response read more in [Tokenization](https://myfatoorah.readme.io/v2.0/docs/tokenization)
* If you want to execute a payment through a saved token you have use ***MFCardInfo(cardToken: "put your token here")***
* Now you are ready to execute the payment, please check the following sample code

```kotlin
val request = MFExecutePaymentRequest(2, 0.100)

// val mfCardInfo = MFCardInfo("Your token here")
val mfCardInfo = MFCardInfo("5123450000000008", "09", "21", "100", true)

MFSDK.executeDirectPayment(
    this,
    request,
    mfCardInfo,
    MFAPILanguage.EN,
    onInvoiceCreated = {
        Log.d(TAG, "invoiceId: $it")
    }
) { invoiceId: String, result: MFResult<MFDirectPaymentResponse> ->
    when (result) {
        is Success ->
            Log.d(TAG, "Response: " + Gson().toJson(result.response))
        is Fail ->
            Log.d(TAG, "Fail: " + Gson().toJson(result.error))
    }
}
```
```java
MFExecutePaymentRequest request = new MFExecutePaymentRequest(2, 0.100);

// MFCardInfo mfCardInfo = new MFCardInfo("Your token here");
MFCardInfo mfCardInfo = new MFCardInfo("5123450000000008", "09", "21", "100", false);

MFSDK.INSTANCE.executeDirectPayment(
        this,
        request,
        mfCardInfo,
        MFAPILanguage.EN,
        (String invoiceId) -> {
            Log.d(TAG, "invoiceId: " + invoiceId);
            return Unit.INSTANCE;
        },
        (String invoiceId, MFResult<MFDirectPaymentResponse> result) -> {
            if (result instanceof Success) 
                Log.d(TAG, "Response: " + new Gson().toJson(((Success<MFDirectPaymentResponse>) result).getResponse()));
            else if (result instanceof Fail) 
                Log.d(TAG, "Error: " + new Gson().toJson(((Fail) result).getError()));

            return Unit.INSTANCE;
        });
```

***

#### **Send Payment**

We have explained in the [Send Payment](https://docs.myfatoorah.com/docs/send-payment) section earlier, the different usage cases for it and how it works, here we are going to embed some sample code for calling it through the SDK on the different platforms

```kotlin
val request = MFSendPaymentRequest(0.100, "Customer name", MFNotificationOption.LINK)
MFSDK.sendPayment(request, MFAPILanguage.EN) { result: MFResult<MFSendPaymentResponse> ->
    when(result){
        is MFResult.Success ->
            Log.d(TAG, "Response: " + Gson().toJson(result.response))
        is MFResult.Fail ->
            Log.d(TAG, "Fail: " + Gson().toJson(result.error))
    }
}
```
```java
MFSendPaymentRequest request = new MFSendPaymentRequest(0.100, "Customer name", MFNotificationOption.LINK);
MFSDK.INSTANCE.sendPayment(request, MFAPILanguage.EN, (MFResult<MFSendPaymentResponse> result) -> {
        if (result instanceof MFResult.Success)
            Log.d(TAG, "Response: " + new Gson().toJson(((MFResult.Success<MFSendPaymentResponse>) result).getResponse()));
        else if (result instanceof MFResult.Fail)
            Log.d(TAG, "Error: " + new Gson().toJson(((MFResult.Fail) result).getError()));

        return Unit.INSTANCE;
    });
```

***

#### **Payment Inquiry**

We have explain the main usage for the [Payment Inquiry](https://docs.myfatoorah.com/docs/payment-inquiry) function, that will enable your application to get the full details about a certain invoice / payment. You can use this function within your application on the different platforms as well. Here we are explaining some samples of its usage through the SDK.

```kotlin
val request = MFGetPaymentStatusRequest("12345", MFKeyType.INVOICE_ID)
MFSDK.getPaymentStatus(request, "en", object: MFCallback<MFGetPaymentStatusResponse>{
    override fun onResponse(isSuccess: Boolean, response: MFGetPaymentStatusResponse?, error: MFError?) {
        if(isSuccess)
            Log.d(TAG, "Response: " + Gson().toJson(response))
        else
            Log.d(TAG, "Error: " + Gson().toJson(error))
    }
})
```
```java
MFGetPaymentStatusRequest request = new MFGetPaymentStatusRequest("12345", MFKeyType.INVOICE_ID);
MFSDK.INSTANCE.getPaymentStatus(request, "en", new MFCallback<MFGetPaymentStatusResponse>() {
    @Override
    public void onResponse(boolean isSuccess, @Nullable MFGetPaymentStatusResponse response, @Nullable MFError error) {
        if (isSuccess)
            Log.d(TAG, "Response: " + new Gson().toJson(response));
        else
            Log.d(TAG, "Error: " + new Gson().toJson(error));
    }
});
```

***

#### **Android Embedded Payment Usage**

***Step 1:***

Add `MFPaymentCardView` in your `xml ` layout like the following:

```xml
<com.myfatoorah.sdk.views.embeddedpayment.MFPaymentCardView
   android:id="@+id/mfPaymentView"
   android:layout_width="wrap_content"
   android:layout_height="wrap_content"/>
```

`Note:` you could custom a lot of properties of the payment card view like the following:

```xml
<com.myfatoorah.sdk.views.embeddedpayment.MFPaymentCardView
   android:id="@+id/mfPaymentView"
   android:layout_width="wrap_content"
   android:layout_height="wrap_content"
   app:inputColor="@color/paymentCardViewTextColor"
   app:labelColor="@color/paymentCardViewLabelColor"
   app:errorColor="@color/paymentCardViewValidateTextColor"
   app:cardHolderNameHint="cardHolderNameHint"
   app:cardNumberHint="cardNumberHint"
   app:expiryDateHint="expiryDateHint"
   app:cvvHint="cvvHint"
   app:showLabels="false"
   app:cardHolderNameLabel="cardHolderNameLabel"
   app:cardNumberLabel="cardNumberLabel"
   app:expiryDateLabel="expiryDateLabel"
   app:cvvLabel="cvvLabel"/>
```

(Alternative Option) You could custom a lot of properties of the payment card view in code behind like the following:

```kotlin
mfPaymentView.cardStyle = MFCardViewStyle(
    hideCardIcons = false,
    direction = "ltr",
    cardHeight = 230,
    input = MFCardViewInput(
        color = getColorFromRes(R.color.cardview_input_text_color),
        fontSize = 13f,
        fontFamily = MFFontFamily.Tahoma,
        inputHeight = 32f,
        inputMargin = 0f,
        borderColor = getColorFromRes(R.color.cardview_input_border_color),
        borderWidth = 2f,
        borderRadius = 8f,
        boxShadow = MFBoxShadow(10, 10, 5, 0, getColorFromRes(R.color.cardview_input_boxshadow_color)),
        placeHolder = MFCardViewPlaceHolder(
            holderName = "Name On Card test",
            cardNumber = "Number test",
            expiryDate = "MM / YY",
            securityCode = "CVV test"
        )
    ),
    label = MFCardViewLabel(
        display = true,
        color = getColorFromRes(R.color.cardview_label_text_color),
        fontSize = 13f,
        fontFamily = MFFontFamily.CourierNew,
        fontWeight = MFFontWeight.Bold,
        text = MFCardViewText(
            holderName = "Card Holder Name test",
            cardNumber = "Card Number test",
            expiryDate = "Expiry Date test",
            securityCode = "Security Code test"
        )
    ),
    error = MFCardViewError(
        borderColor = getColorFromRes(R.color.cardview_error_border_color),
        borderRadius = 8f,
        boxShadow = MFBoxShadow(10, 10, 5, 0, getColorFromRes(R.color.cardview_error_boxshadow_color))
    )
)
```
```java
MFCardViewInput cardViewInput = new MFCardViewInput(
        getColorFromRes(R.color.cardview_input_text_color),
        13f,
        MFFontFamily.Tahoma,
        32f,
        0f,
        getColorFromRes(R.color.cardview_input_border_color),
        2f,
        8f,
        new MFBoxShadow(10, 10, 5, 0, getColorFromRes(R.color.cardview_input_boxshadow_color)),
        new MFCardViewPlaceHolder("Name On Card test", "Number test", "MM / YY", "CVV test")
);
MFCardViewLabel cardViewLabel = new MFCardViewLabel(
        true,
        getColorFromRes(R.color.cardview_label_text_color),
        13f,
        MFFontFamily.CourierNew,
        MFFontWeight.Bold,
        new MFCardViewText("Card Holder Name test", "Card Number test", "Expiry Date test", "Security Code test")
);
MFCardViewError cardViewError = new MFCardViewError(
        getColorFromRes(R.color.cardview_error_border_color),
        8f,
        new MFBoxShadow(10, 10, 5, 0, getColorFromRes(R.color.cardview_error_boxshadow_color))
);
MFCardViewStyle cardViewStyle =
        new MFCardViewStyle(false, "ltr", 230, cardViewInput, cardViewLabel, cardViewError);
mfPaymentView.setCardStyle(cardViewStyle);
```

***Step 2:***

You need to call `initiateSession()` function to create session. You need to do this for each payment separately. Session is valid for only one payment. and inside it's success state, call `load()` function and pass it the session response, to load the payment card view on the screen, like the following:

`Note:` If you want to use saved card option with embedded payment, send the parameter `customerIdentifier` in the `MFInitiateSessionRequest` with a unique value for each customer. This value cannot be used for more than one Customer. Check commented lines in the following code.

```kotlin
// val request = MFInitiateSessionRequest(customerIdentifier = "12345")
// MFSDK.initiateSession(request)

MFSDK.initiateSession {
    when (it) {
        is MFResult.Success -> {
            mfPaymentView.load(
                it.response,
                onCardBinChanged = { bin ->
                    Log.d(TAG, "bin: $bin")
                }
            )
        }
        is MFResult.Fail -> {
            Log.d(TAG, "Fail: " + Gson().toJson(it.error))
        }
    }
}
```
```java
// MFInitiateSessionRequest request = new MFInitiateSessionRequest("12332212");
// MFSDK.INSTANCE.initiateSession(request, (MFResult<MFInitiateSessionResponse> result) -> {

MFSDK.INSTANCE.initiateSession(null, (MFResult<MFInitiateSessionResponse> result) -> {
    if (result instanceof MFResult.Success) {
        mfPaymentView.load(
                ((Success<MFInitiateSessionResponse>) result).getResponse(),
                (String bin) -> {
                    Log.d(TAG, "bin: " + bin);
                    return Unit.INSTANCE;
                });
    }
    if (result instanceof MFResult.Fail) {
        Log.d(TAG, "Fail: " + new Gson().toJson(((Fail) result).getError()));
    }

    return Unit.INSTANCE;
});
```

`Note:` The `initiateSession()` function should called after `MFSDK.init()` function (that we mentioned above).

***Step 3:***

Finally, you need to handle your `Pay` button to call the `pay()` function, copy the below code to your pay event handler section:

```kotlin
val request = MFExecutePaymentRequest(0.100)

mfPaymentView.pay(
    this,
    request,
    MFAPILanguage.EN,
    onInvoiceCreated = {
        Log.d(TAG, "invoiceId: $it")
    }
) { invoiceId: String, result: MFResult<MFGetPaymentStatusResponse> ->
    when (result) {
        is MFResult.Success -> {
            Log.d(TAG, "Response: " + Gson().toJson(result.response))
        }
        is MFResult.Fail -> {
            Log.d(TAG, "Fail: " + Gson().toJson(result.error))
        }
    }
    Log.d(TAG, "invoiceId: $invoiceId")
}
```
```java
MFExecutePaymentRequest request = new MFExecutePaymentRequest(0.100);

        mfPaymentView.pay(
                this,
                request,
                MFAPILanguage.EN,
                (String invoiceId) -> {
                    Log.d(TAG, "invoiceId: " + invoiceId);
                    return Unit.INSTANCE;
                },
                (String invoiceId, MFResult<MFGetPaymentStatusResponse> result) -> {
                    if (result instanceof MFResult.Success) {
                        Log.d(TAG, "Response: " + new Gson().toJson(((Success<MFGetPaymentStatusResponse>) result).getResponse()));
                    }
                    else if (result instanceof MFResult.Fail) {
                        String error = new Gson().toJson(((Fail) result).getError());
                        Log.d(TAG, "Fail: " + error);
                    }

                    Log.d(TAG, "invoiceId:" + invoiceId);

                    return Unit.INSTANCE;
                });
```

(Alternative Option) You can validate the session first and complete the payment if the session is valid

```kotlin
val request = MFExecutePaymentRequest(0.100)
mfPaymentView.validate { result: MFResult<String> ->
    when (result) {
        is MFResult.Success -> {
            Log.d(TAG, "Response: " + Gson().toJson(result.response))
            mfPaymentView.pay(
                this,
                request,
                MFAPILanguage.EN,
                onInvoiceCreated = { Log.d(TAG, "invoiceId: $it") })
            { invoiceId: String, result: MFResult<MFGetPaymentStatusResponse> ->
                when (result) {
                    is MFResult.Success -> {
                        Log.d(TAG, "Response: " + Gson().toJson(result.response))
                    }
                    is MFResult.Fail -> {
                        Log.d(TAG, "Fail: " + Gson().toJson(result.error))
                    }
                }
            }
        }
        is MFResult.Fail -> Log.d(TAG, "Fail: " + Gson().toJson(result.error))
    }
}
```
```java
MFExecutePaymentRequest request = new MFExecutePaymentRequest(0.100);
mfPaymentView.validate((MFResult<String> resultValidate) -> {
    if (resultValidate instanceof MFResult.Success) {
        Log.d(TAG, "Response: " + new Gson().toJson(((Success<String>) resultValidate).getResponse()));
        mfPaymentView.pay(
                this,
                request,
                MFAPILanguage.EN,
                (String invoiceId) -> {
                    Log.d(TAG, "invoiceId: " + invoiceId);
                    return Unit.INSTANCE;
                },
                (String invoiceId, MFResult<MFGetPaymentStatusResponse> resultPay) -> {
                    if (resultPay instanceof MFResult.Success) {
                        Log.d(TAG, "Response: " + new Gson().toJson(((Success<MFGetPaymentStatusResponse>) resultPay).getResponse()));
                    } else if (resultPay instanceof MFResult.Fail) {
                        Log.d(TAG, "Fail: " + new Gson().toJson(((Fail) resultPay).getError()));
                    }
                    return Unit.INSTANCE;
                });
    } else if (resultValidate instanceof MFResult.Fail) {
        Log.d(TAG, "Fail: " + new Gson().toJson(((Fail) resultValidate).getError()));
    }
    return Unit.INSTANCE;
});
```

##### Read Card with NFC (Optional)

Add the following code if you want to add the feature of a reading card with NFC.

```kotlin
binding.mfPaymentView.load(
    initiateSessionResponse,
    onCardBinChanged = { bin ->
        Log.d(TAG, "bin: $bin")
    },
    onCardHeightChanged = { height ->
        Log.d(TAG, "height: $height")
    },
    showNFCReadCardIcon = true
)
mfPaymentView.enableCardNFC(this)


override fun onResume() {
    super.onResume()
    mfPaymentView.enableCardNFC(this)
}

public override fun onPause() {
    super.onPause()
    mfPaymentView.disableCardNFC(this)
}
```
```java
mfPaymentCardView.load(
  			initiateSessionResponse,
        (String bin) -> {
            Log.d("TAG", bin);
	          return Unit.INSTANCE;
        }, (Float height) -> {
            Log.d("TAG", height.toString());
  	        return Unit.INSTANCE;
        }, true);
mfPaymentCardView.enableCardNFC(this);


@Override
protected void onResume() {
    super.onResume();
    mfPaymentView.enableCardNFC(this);
}

@Override
protected void onPause() {
    super.onPause();
    mfPaymentView.disableCardNFC(this);
}
```

### Google Pay

This section guides you through the integration of Google Pay using the MyFatoorah SDK for Android applications. The provided example demonstrates how to set up and handle payments with Google Pay within your app.

#### Step 1: Initiating Session for Google Pay:

Before making any Google Pay transactions, initiate session for transactions.

```kotlin Kotlin
MFSDK.initiateSession(MFInitiateSessionRequest(customerIdentifier = "your_customer_identifier")) {
    when (it) {
        is MFResult.Success -> setupGooglePayHelper(it.response.sessionId)
        is MFResult.Fail -> Log.d(TAG, "Session initiation failed: " + Gson().toJson(it.error))
        else -> {}
    }
}
```

#### Step 2: Setting Up GooglePayRequest:

Configure Google Pay with necessary details such as the merchant ID, merchant name, country code, and currency.

```kotlin Kotlin
val googlePayRequest = GooglePayRequest(
    totalPrice = "1",  // Total price for the transaction
    merchantId = "your_merchant_id",
    merchantName = "your_merchant_name",
    countryCode = MFCountry.KUWAIT.code,
    currencyIso = MFCurrencyISO.UAE_AED
)
```

#### Step 3: Setting Up MFGooglePayHelper:

Set the Google Pay button in your layout to initiate payments. Use the MFGooglePayHelper to handle the transaction process and results.

```kotlin Kotlin
mfGooglePayHelper = MFGooglePayHelper(
    activity = this,
    googlePayRequestCODE = googlePayRequestCODE,
    sessionId = sessionId,
    googlePayRequest = googlePayRequest,
    onInvoiceCreated = { invoiceId -> Log.d(TAG, "Invoice Created: $invoiceId") },
    callback = { invoiceId, mfResult ->
        when (mfResult) {
            is MFResult.Success -> Log.d(TAG, "Payment Success: " + Gson().toJson(mfResult.response))
            is MFResult.Fail -> Log.d(TAG, "Payment Failed: " + Gson().toJson(mfResult.error))
            else -> {}
        }
    }
)
mfGooglePayHelper.setGooglePayButton(binding.mfGooglePayButton)
```

#### Step 4: Handling Activity Results:

Ensure to handle the result from the Google Pay activity in your onActivityResult.

```kotlin Kotlin
private val googlePayRequestCODE: Int = 991 // Any unique requestCode specified for GooglePay
override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
    super.onActivityResult(requestCode, resultCode, data)
    if (requestCode == googlePayRequestCODE) {
        mfGooglePayHelper.onActivityResult(requestCode, resultCode, data)
    }
}
```

#### Step 5: Add MFGooglePayButton:

##### Use MFGooglePayButton

Add MFGooglePayButton in your layout.xml

```xml Xml
<com.myfatoorah.sdk.views.embeddedpayment.googlepay.MFGooglePayButton
    android:id="@+id/mfGooglePayButton"
    android:layout_width="match_parent"
    android:layout_height="wrap_content" />
```

##### Another option

1. Add a Google Pay button to your layout:

```xml
<com.google.android.gms.wallet.button.PayButton
    android:id="@+id/googlePayButton"
    android:layout_width="match_parent"
    android:layout_height="wrap_content" />
```

2. Ensure you have the necessary dependencies in your build.gradle file:

```groovy Gradle
// Google Wallet
implementation 'com.google.android.gms:play-services-wallet:19.4.0'
```

3. Update your AndroidManifest.xml to include the necessary permissions and metadata:

```xml
<uses-permission android:name="android.permission.INTERNET"/>

<application ...>
    <meta-data
        android:name="com.google.android.gms.wallet.api.enabled"
        android:value="true"/>
</application>
```

#### Step 6: Going Live with google pay:

* Complete your google Business Profile and get your Merchant ID.
* [Request production access from Google Pay & Wallet Console.](https://developers.google.com/pay/api/android/guides/test-and-deploy/request-prod-access)

## Flutter

*`https://docs.myfatoorah.com/docs/flutter` — updated 2025-11-11*

> Guide for Flutter

> 📘 Flutter SDK New Version
>
> Starting from version '3.0.0', MyFatoorah has uploaded a new plugin that depends directly on the native implementation. The following link is the migration guideline: [Migration to the new Flutter SDK](https://docs.myfatoorah.com/docs/flutter-migration)

*Demo project*

**Flutter Plugin**: <https://pub.dev/packages/myfatoorah_flutter>\
**Flutter Plugin Demo**: <https://dev.azure.com/myfatoorahsc/_git/MF-SDK-Cross-Platforms-Demos>

#### Installation /Usage

1. Add the MyFatoorah plugin to your `pubspec.yaml` file.

```
dependencies:
  myfatoorah_flutter: ^3.2.1
```

2. Install the plugin by running the following command.

   **$ flutter pub get**

3. Import the plugin like this:

```d
import 'package:myfatoorah_flutter/myfatoorah_flutter.dart';
```

4. Initiate the MyFatoorah Plugin with the following line:

```d
MFSDK.init("Add Your API Key", MFCountry.KUWAIT, MFEnvironment.TEST);
```

5. (Optional)

```d
// Use the following lines if you want to set up the properties of AppBar.
  setUpActionBar() {
    MFSDK.setUpActionBar(
        toolBarTitle: 'Company Payment',
        toolBarTitleColor: '#FFEB3B',
        toolBarBackgroundColor: '#CA0404',
        isShowToolBar: true);
  }
```

> 📘 After testing
>
> Once your testing is finished, simply replace environment from TEST to LIVE and the API URL with the live, click [here ](https://docs.myfatoorah.com/docs/live-account)for more information.

#### Initiate / Execute Payment

As described earlier for the [Gateway Integration](https://docs.myfatoorah.com/docs/gateway-integration), we are going to have the SDK integrated with the same steps to make a successful integration with the SDK

> 🚧 Initiate Payment
>
> As a good practice, you don't have to call the [Initiate Payment](https://docs.myfatoorah.com/docs/initiate-payment) function every time you need to execute payment, but you have to call it at least once to save the PaymentMethodId that you will need to call \[Execute Payment]\(doc:execute-payment

```d Dart
// Initiate Payment
  initiatePayment() async {
    MFInitiatePaymentRequest request = MFInitiatePaymentRequest(
        invoiceAmount: 10, currencyIso: MFCurrencyISO.SAUDIARABIA_SAR);
    await MFSDK
        .initiatePayment(request, MFLanguage.ENGLISH)
        .then((value) => debugPrint(value.toString()))
        .catchError((error) => {debugPrint(error.message)});
  }

  
  // executePayment 
  
	// The value "1" is the paymentMethodId of KNET payment method.
  // You should call the "initiatePayment" API to can get this id and the ids of all other payment methods
  executePayment() async {
    MFExecutePaymentRequest request = MFExecutePaymentRequest(invoiceValue: 10);
    request.paymentMethodId = 1;

    await MFSDK
        .executePayment(request, MFLanguage.ENGLISH, (invoiceId) {
          debugPrint(invoiceId);
        })
        .then((value) => debugPrint(value.toString()))
        .catchError((error) => {debugPrint(error.message)});
  }
```

#### Send Payment

We have explained in the [Send Payment](https://docs.myfatoorah.com/docs/send-payment) section earlier, the different usage cases for it and how it works, here we are going to embed some sample code for calling it through the SDK on the different platforms

```d Dart
  sendPayment() async {
    MFSendPaymentRequest request = MFSendPaymentRequest();
    request.customerName = "TEESST";
    request.invoiceValue = 10;
    request.notificationOption = MFNotificationOption.EMAIL;

    await MFSDK
        .sendPayment(request, MFLanguage.ENGLISH)
        .then((value) => debugPrint(value.toString()))
        .catchError((error) => {debugPrint(error.message)});
  }
```

#### Payment Enquiry

We have explained the main usage for the [Payment Inquiry](https://docs.myfatoorah.com/docs/payment-inquiry) function, which will enable your application to get the full details about a certain invoice/payment. You can use this function within your application on the different platforms as well. Here we are explaining some samples of its usage through the SDK.

```d Dart
  getPaymentStatus() async {
    MFGetPaymentStatusRequest request = MFGetPaymentStatusRequest(
        key: '2593740', keyType: MFKeyType.INVOICEID.name);
    await mfSDK
        .getPaymentStatus(request, MFLanguage.ENGLISH.name)
        .then((value) => debugPrint(value.toString()))
        .catchError((error) => {debugPrint(error.message)});
  }
```

#### Embedded Payment Usage

***Step 1:***

Create an instance of `MFPaymentCardView` and add it to your `build()` function like the following:

```d Dart
class _MyAppState extends State<MyApp> {
  ...
  late MFCardPaymentView mfCardView;
  ...
}
```

`Note:` you could custom a lot of properties of the payment card view like the following:

```d Dart
  MFCardViewStyle cardViewStyle() {
    MFCardViewStyle cardViewStyle = MFCardViewStyle();
    cardViewStyle.cardHeight = 200;
    cardViewStyle.hideCardIcons = false;
    cardViewStyle.input?.inputMargin = 5;
    cardViewStyle.label?.display = true;
    cardViewStyle.input?.fontFamily = MFFontFamily.Monaco;
    cardViewStyle.label?.fontWeight = MFFontWeight.Heavy;
    return cardViewStyle;
  }

@override
Widget build(BuildContext context) {
  mfCardView = MFCardPaymentView(cardViewStyle: cardViewStyle());
 	...
}

Widget embeddedCardView() {
  return Column(
    children: [
      SizedBox(
        height: 200,
        child: mfCardView,
      ),
    ],
  );
}
```

***Step 2:***

You need to call `initiateSession()` function to create a session. You need to do this for each payment separately. The session is valid for only one payment. and inside its success state, call `load()` function and pass it the session response, to load the payment card view on the screen, like the following:

`Note:` If you want to use the saved card option with embedded payment, send the parameter customerIdentifier in the `MFInitiateSessionRequest` with a unique value for each customer. This value cannot be used for more than one Customer. Check the commented lines in the following code.

```d Dart
 initiateSession() async {
    MFInitiateSessionRequest initiateSessionRequest =
        MFInitiateSessionRequest();
    await MFSDK
        .initiateSession(initiateSessionRequest, (bin) {
          debugPrint(bin);
        })
        .then((value) => {
              debugPrint(value.toString()),
            })
        .catchError((error) => {debugPrint(error.message)});
  }
```

`Note:` The `initiateSession()` function should called after `MFSDK.init()` function (that we mentioned above).

***Step 3:***

Finally, you need to handle your `Pay` button to call the `pay()` function, copy the below code to your pay event handler section:

```d Dart
  pay() async {
    var executePaymentRequest = MFExecutePaymentRequest(invoiceValue: 10);
    executePaymentRequest.sessionId = sessionId;

    await mfCardView
        .pay(executePaymentRequest, MFLanguage.ENGLISH, (invoiceId) {
          debugPrint(invoiceId);
        })
        .then((value) => log(value.toString()))
        .catchError((error) => {log(error.message)});
  }
```

#### Apple Pay Embedded Payment (new for iOS only)

To provide a better user experience to your Apple Pay users, MyFatoorah is providing the Apple Pay embedded payment. Follow these steps:

***Step 1:***

Create an instance of `MFApplePayButton` and add it to your `build()` function like the following:

```d Dart
class _MyAppState extends State<MyApp> {
  ...
  late MFApplePayButton mfApplePayButton;
  ...
}

@override
Widget build(BuildContext context) {
  mfApplePayButton = MFApplePayButton(applePayStyle: MFApplePayStyle());
 	...
}

Widget applePayView() {
  return Column(
    children: [
      SizedBox(
        height: 50,
        child: mfApplePayButton,
      )
    ],
  );
}
```

***Step 2:***

You need to call `applePayPayment()` function to create a session. You need to do this for each payment separately. `Session` is valid for only one payment. and inside its success state.

```d Dart
  applePayPayment() async {
    MFExecutePaymentRequest executePaymentRequest =
        MFExecutePaymentRequest(invoiceValue: 10);
    executePaymentRequest.displayCurrencyIso = MFCurrencyISO.KUWAIT_KWD;
    await mfApplePayButton
        .applePayPayment(executePaymentRequest, MFLanguage.ENGLISH,
            (invoiceId) {
          debugPrint(invoiceId);
        })
        .then((value) => log(value.toString()))
        .catchError((error) => {log(error.message)});
  }
```

`Note:` The `applePayPayment()` function should called after `MFSDK.init()` function (that we mentioned above).

#### Direct Payment / Tokenization

As we have explained earlier in the [Direct Payment](https://docs.myfatoorah.com/docs/direct-payment) integration and how it works, it also has the same scenario for the SDK implementation, you have to know the following steps to understand how it works:

* Get the payment method that allows Direct Payment by calling initiatePayment to get paymentMethodId
* Collect card info from user ***MFCard(cardHolderName: 'myFatoorah', number: '5454545454545454',  expiryMonth: '10', expiryYear: '23', securityCode: '000')***
* If you want to save your credit card info and get a token for your next payment you have to set ***saveToken: true*** and you will get the token in the response read more in [Tokenization](https://docs.myfatoorah.com/docs/tokenization)
* Now you are ready to execute the payment, please check the following sample code

```d Dart
  executeDirectPayment() async {
    var executePaymentRequest = MFExecutePaymentRequest(invoiceValue: 10);
    executePaymentRequest.paymentMethodId = 20;

    var mfCardRequest = MFCard(
            cardHolderName: 'myFatoorah',
            number: '5454545454545454',
            expiryMonth: '10',
            expiryYear: '23',
            securityCode: '000',
          );

    var directPaymentRequest = MFDirectPaymentRequest(
        executePaymentRequest: executePaymentRequest,
        token: null,
        card: mfCardRequest);

    await MFSDK
        .executeDirectPayment(directPaymentRequest, MFLanguage.ENGLISH,
            (invoiceId) {
          debugPrint(invoiceId);
        })
        .then((value) => debugPrint(value.toString()))
        .catchError((error) => {debugPrint(error.message)});
  }
```

#### GooglePay

***Step 1: Define Google Pay Button***

Before using Google Pay, you need to define the MFGooglePayButton widget in your class:

```d Dart
late MFGooglePayButton mfGooglePayButton;
```

***Step 2: Initiate a Session for Google Pay***

Use the MFInitiateSessionRequest class to create a session.

```d Dart
MFInitiateSessionRequest initiateSessionRequest = MFInitiateSessionRequest();

await MFSDK
    .initSession(initiateSessionRequest, MFLanguage.ENGLISH)
    .then((value) => {setupGooglePayHelper(value.sessionId)})
    .catchError((error) => {debugPrint(error.message)});

```

***Step 3: Setup Google Pay Helper***

Once the session is initiated, use the session ID to configure Google Pay.

Configure Google Pay with necessary details such as the merchant ID, merchant name, country code, and currency.

The following method sets up the Google Pay request:

```d Dart
setupGooglePayHelper(String sessionId) async {
  MFGooglePayRequest googlePayRequest = MFGooglePayRequest(
      totalPrice: "1",
      merchantId: "your_google_merchant_id",
      merchantName: "Test Vendor",
      countryCode: MFCountry.KUWAIT,
      currencyIso: MFCurrencyISO.UAE_AED);

  await mfGooglePayButton
      .setupGooglePayHelper(sessionId, googlePayRequest, (invoiceId) {
        debugPrint("-----------Invoice Id: $invoiceId------------");
      })
      .then((value) => debugPrint(value))
      .catchError((error) => {debugPrint(error.message)});
}

```

***Step 4: Integrating Google Pay Button in UI***

```d Dart
@override
Widget build(BuildContext context) {
  mfGooglePayButton = MFGooglePayButton();

  return MaterialApp(
    home: Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            SizedBox(
              height: 70,
              child: mfGooglePayButton,
            ),
          ],
        ),
      ),
    ),
  );
}
```

***Step 5: Going Live with google pay:***

* Complete your google Business Profile and get your Merchant ID.
* [Request production access from Google Pay & Wallet Console.](https://developers.google.com/pay/api/android/guides/test-and-deploy/request-prod-access)

## Migration to the new Flutter SDK

*`https://docs.myfatoorah.com/docs/flutter-migration` — updated 2025-11-11*

Guide for Flutter Migration to the new SDK

#### Installation /Usage

1. Initiate the MyFatoorah Plugin with the following line:

```Text New
MFSDK.init("Add Your API Key", MFCountry.KUWAIT, MFEnvironment.TEST);
```
```d Old
MFSDK.init("Put API Key here", MFCountry.KUWAIT, MFEnvironment.TEST);
```

2. (Optional)

```Text New
// Use the following lines if you want to set up the properties of AppBar.
  setUpActionBar() {
     MFSDK.setUpActionBar(
        toolBarTitle: 'Company Payment',
        toolBarTitleColor: '#FFEB3B',
        toolBarBackgroundColor: '#CA0404',
        isShowToolBar: true);
  }
```
```d Old
// Use the following lines if you want to set up the properties of AppBar.

  setUpActionBar() async {
    await mfSDK
        .setUpActionBar('Company Payment', '', '', true)
        .then((value) => log(value))
        .catchError((error) => {log(error.message)});
  }
```

#### Initiate / Execute Payment

```Text New
// Initiate Payment
  initiatePayment() async {
    MFInitiatePaymentRequest request = MFInitiatePaymentRequest(
        invoiceAmount: 10, currencyIso: MFCurrencyISO.SAUDIARABIA_SAR);
    await MFSDK
        .initiatePayment(request, MFLanguage.ENGLISH)
        .then((value) => debugPrint(value.toString()))
        .catchError((error) => {debugPrint(error.message)});
  }

  
  // executePayment 
  
	// The value "1" is the paymentMethodId of KNET payment method.
  // You should call the "initiatePayment" API to can get this id and the ids of all other payment methods
  executePayment() async {
    MFExecutePaymentRequest request = MFExecutePaymentRequest(invoiceValue: 10);
    request.paymentMethodId = 1;

    await MFSDK
        .executePayment(request, MFLanguage.ENGLISH, (invoiceId) {
          debugPrint(invoiceId);
        })
        .then((value) => debugPrint(value.toString()))
        .catchError((error) => {debugPrint(error.message)});
  }
```
```d Old
// Initiate Payment
  
    var request = new MFInitiatePaymentRequest(5.5, MFCurrencyISO.KUWAIT_KWD);

    MFSDK.initiatePayment(request, MFAPILanguage.EN,
            (MFResult<MFInitiatePaymentResponse> result) => {

          if(result.isSuccess()) {
            print(result.response.toJson().toString())
          }
          else {
            print(result.error.message)
          }
        });

  
  // executePayment 
  
	// The value "1" is the paymentMethodId of KNET payment method.
    // You should call the "initiatePayment" API to can get this id and the ids of all other payment methods
    String paymentMethod = 1;

    var request = new MFExecutePaymentRequest(paymentMethod, 0.100);

    MFSDK.executePayment(context, request, MFAPILanguage.EN,
        onInvoiceCreated: (String invoiceId) =>
        {
          print("invoiceId: " + invoiceId)
        },
        onPaymentResponse: (String invoiceId,
            MFResult<MFPaymentStatusResponse> result) =>
        {
          if(result.isSuccess()) {
            print(result.response.toJson().toString())
          }
          else {
            print(result.error.message)
          }
        });
```

#### Send Payment

```d New
  sendPayment() async {
    MFSendPaymentRequest request = MFSendPaymentRequest();
    request.customerName = "TEESST";
    request.invoiceValue = 10;
    request.notificationOption = MFNotificationOption.EMAIL;

    await MFSDK
        .sendPayment(request, MFLanguage.ENGLISH)
        .then((value) => debugPrint(value.toString()))
        .catchError((error) => {debugPrint(error.message)});
  }
```
```Text Old
var request = MFSendPaymentRequest(invoiceValue: 0.100, customerName: "Customer name",
		notificationOption: MFNotificationOption.LINK);

    MFSDK.sendPayment(MFAPILanguage.EN, request, 
            (MFResult<MFSendPaymentResponse> result) => {
      
      if(result.isSuccess()) {
        print(result.response.toJson().toString())
      }
      else {
        print(result.error.message)
      }
    });
```

#### Payment Enquiry

```d New
  getPaymentStatus() async {
    MFGetPaymentStatusRequest request = MFGetPaymentStatusRequest(
        key: '2593740', keyType: MFKeyType.INVOICEID.name);
    await mfSDK
        .getPaymentStatus(request, MFLanguage.ENGLISH.name)
        .then((value) => debugPrint(value.toString()))
        .catchError((error) => {debugPrint(error.message)});
  }
```
```Text Old
var request = MFPaymentStatusRequest(invoiceId: "12345");

    MFSDK.getPaymentStatus(MFAPILanguage.EN, request,
            (MFResult<MFPaymentStatusResponse> result) => {

          if(result.isSuccess()) {
            print(result.response.toJson().toString())
          }
          else {
            print(result.error.message)
          }
        });
```

#### Embedded Payment Usage

***Step 1:***

```d New
class _MyAppState extends State<MyApp> {
  ...
  late MFCardPaymentView mfCardView;
  ...
}
```
```Text Old
@override
  Widget build(BuildContext context) {
    return createPaymentCardView();
  }

  createPaymentCardView() {
    mfPaymentCardView = MFPaymentCardView();
    return mfPaymentCardView;
  }
```

View Customization

```d New
MFCardViewStyle cardViewStyle() {
    MFCardViewStyle cardViewStyle = MFCardViewStyle();
    cardViewStyle.cardHeight = 200;
    cardViewStyle.hideCardIcons = false;
    cardViewStyle.input?.inputMargin = 5;
    cardViewStyle.label?.display = true;
    cardViewStyle.input?.fontFamily = MFFontFamily.Monaco;
    cardViewStyle.label?.fontWeight = MFFontWeight.Heavy;
    return cardViewStyle;
  }

@override
Widget build(BuildContext context) {
  mfCardView = MFCardPaymentView(cardViewStyle: cardViewStyle());
 	...
}

Widget embeddedCardView() {
  return Column(
    children: [
      SizedBox(
        height: 200,
        child: mfCardView,
      ),
    ],
  );
}
```
```Text Old
mfPaymentCardView = MFPaymentCardView(
      inputColor: Colors.red,
      labelColor: Colors.yellow,
      errorColor: Colors.blue,
      borderColor: Colors.green,
      fontSize: 14,
      borderWidth: 1,
      borderRadius: 10,
      cardHeight: 220,
      cardHolderNameHint: "card holder name hint",
      cardNumberHint: "card number hint",
      expiryDateHint: "expiry date hint",
      cvvHint: "cvv hint",
      showLabels: true,
      cardHolderNameLabel: "card holder name label",
      cardNumberLabel: "card number label",
      expiryDateLabel: "expiry date label",
      cvvLabel: "securtity code label",
    );
```

***Step 2:***

Call `initiateSession()`

```d New
 initiateSession() async {
    MFInitiateSessionRequest initiateSessionRequest =
        MFInitiateSessionRequest();
    await MFSDK
        .initiateSession(initiateSessionRequest, (bin) {
          debugPrint(bin);
        })
        .then((value) => {
              debugPrint(value.toString()),
            })
        .catchError((error) => {debugPrint(error.message)});
  }
```
```Text Old
void initiateSession() {
    // var request = MFInitiateSessionRequest("12332212");
    // MFSDK.initiateSession(request, (MFResult<MFInitiateSessionResponse> result) => {

    MFSDK.initiateSession(null, (MFResult<MFInitiateSessionResponse> result) => {
      if(result.isSuccess()) 
        mfPaymentCardView.load(result.response!,
            onCardBinChanged: (String bin) => {print("Bin: " + bin)})
     else
        print("Response: " + result.error!.toJson().toString().toString());
    });
  }
```

***Step 3:***

Handle your `Pay` button

```Text New
  pay() async {
    var executePaymentRequest = MFExecutePaymentRequest(invoiceValue: 10);
    executePaymentRequest.sessionId = sessionId;

    await mfCardView
        .pay(executePaymentRequest, MFLanguage.ENGLISH, (invoiceId) {
          debugPrint(invoiceId);
        })
        .then((value) => log(value.toString()))
        .catchError((error) => {log(error.message)});
  }
```
```d Old
var request = MFExecutePaymentRequest.constructor(0.100);

mfPaymentCardView.pay(
    request,
    MFAPILanguage.EN,
    (String invoiceId, MFResult<MFPaymentStatusResponse> result) =>
    {
      if (result.isSuccess())
        {
          setState(() {
            print("Response: " + result.response!.toJson().toString());
          })
        }
      else
        {
          setState(() {
            print("Error: " + result.error!.toJson().toString());
          })
        }
    });
```

#### Apple Pay Embedded Payment (new for iOS only)

***Step 1:***

Create an instance of `MFApplePayButton`

```d New
class _MyAppState extends State<MyApp> {
  ...
  late MFApplePayButton mfApplePayButton;
  ...
}

@override
Widget build(BuildContext context) {
  mfApplePayButton = MFApplePayButton(applePayStyle: MFApplePayStyle());
 	...
}

Widget applePayView() {
  return Column(
    children: [
      SizedBox(
        height: 50,
        child: mfApplePayButton,
      )
    ],
  );
}
```
```Text Old
@override
  Widget build(BuildContext context) {
    return createApplePayButton();
  }

  createApplePayButton() {
    mfApplePayButton = MFApplePayButton();
    return mfApplePayButton;
  }
```

***Step 2:***

Call `initiateSession()`

```d New
 applePayPayment() async {
    MFExecutePaymentRequest executePaymentRequest =
        MFExecutePaymentRequest(invoiceValue: 10);
    executePaymentRequest.displayCurrencyIso = MFCurrencyISO.KUWAIT_KWD;
    await mfApplePayButton
        .applePayPayment(executePaymentRequest, MFLanguage.ENGLISH,
            (invoiceId) {
          debugPrint(invoiceId);
        })
        .then((value) => log(value.toString()))
        .catchError((error) => {log(error.message)});
  }
```
```Text Old
void initiateSession() {
    MFSDK.initiateSession((MFResult<MFInitiateSessionResponse> result) => {
      if(result.isSuccess()) 
        loadApplePay(result.response)
      else
        print("Response: " + result.error!.toJson().toString().toString());
    });
  }

void loadApplePay(MFInitiateSessionResponse mfInitiateSessionResponse) {
    var request = MFExecutePaymentRequest.constructorForApplyPay(
    0.100, MFCurrencyISO.KUWAIT_KWD);

    mfApplePayButton.load(
    mfInitiateSessionResponse,
    request,
    MFAPILanguage.EN,
        (String invoiceId, MFResult<MFPaymentStatusResponse> result) =>
    {
        if (result.isSuccess())
        {
            setState(() {
                print("invoiceId: " + invoiceId);
                print("Response: " + result.response.toJson().toString());
                _response = result.response.toJson().toString();
            })
        }
        else
        {
            setState(() {
                print("invoiceId: " + invoiceId);
                print("Error: " + result.error.toJson().toString());
                    _response = result.error.message;
                })
            }
        });
}
```

#### Direct Payment / Tokenization

```Text New
  executeDirectPayment() async {
    var executePaymentRequest = MFExecutePaymentRequest(invoiceValue: 10);
    executePaymentRequest.paymentMethodId = 20;

    var mfCardRequest = MFCard(
            cardHolderName: 'myFatoorah',
            number: '5454545454545454',
            expiryMonth: '10',
            expiryYear: '23',
            securityCode: '000',
          );

    var directPaymentRequest = MFDirectPaymentRequest(
        executePaymentRequest: executePaymentRequest,
        token: null,
        card: mfCardRequest);

    await MFSDK
        .executeDirectPayment(directPaymentRequest, MFLanguage.ENGLISH,
            (invoiceId) {
          debugPrint(invoiceId);
        })
        .then((value) => debugPrint(value.toString()))
        .catchError((error) => {debugPrint(error.message)});
  }
```
```Text Old
// The value "20" is the paymentMethodId of Visa/Master payment method (for the direct payment).
    // You should call the "initiatePayment" API to can get this id and the ids of all other payment methods
    String paymentMethod = 20;

    var request = new MFExecutePaymentRequest(paymentMethod, 0.100);
	
    var mfCardInfo = new MFCardInfo("2223000000000007", "05", "21", "100",
        bypass3DS: true, saveToken: true);

    MFSDK.executeDirectPayment(context, request, mfCardInfo, MFAPILanguage.EN,
            (String invoiceId, MFResult<MFDirectPaymentResponse> result) => {

          if(result.isSuccess()) {
            print(result.response.toJson().toString())
          }
          else {
            print(result.error.message)
          }
        });
```

## React Native

*`https://docs.myfatoorah.com/docs/react-native` — updated 2025-11-11*

> Guide for React Native

> 📘 ReactNative SDK New Version
>
> Starting from version '1.0.0', MyFatoorah has uploaded a new plugin that depends directly on the native implementation.

### Introduction

MyFatoorah SDK v2 is an enhanced and improved SDK version that will simplify the integration with the MyFatoorah payment platform through simple straightforward steps.

##### Prerequisites

In order to use MyFatoorah SDK in the live environment, you have to consider some points to be made before you proceed with your live integration. Here you are the list that should be done and completed before going live with your account:

* You have to Create [ Live Account ](https://myfatoorah.readme.io/v2.0/docs/create-live-account) and get the account approved
* You have to get the API feature activated, you have to communicate with your account manager to enable it
* Get the API key that will be used within the integration
* If you are in need to have a [Direct Payment](https://myfatoorah.readme.io/v2.0/docs/direct-payment) integration working within your app, please communicate with your account manager to enable this feature for you as well

[![NPM](https://img.shields.io/npm/v/myfatoorah-reactnative.svg)](https://www.npmjs.com/package/myfatoorah-reactnative)

#### Demo project

React Native Plugin: [myfatoorah-reactnative](https://www.npmjs.com/package/myfatoorah-reactnative)\
Plugin Demo: [react\_native\_demo](https://dev.azure.com/myfatoorahsc/MF-SDK-Cross-Platforms-Demos/_git/MF-SDK-Cross-Platforms-Demos?version=GBreact_native_demo\&path=%2FREADME.md&_a=preview)

### Demo Information

Demo account information

```
baseURL: https://apitest.myfatoorah.com

APIKey(Token): 7Fs7eBv21F5xAocdPvvJ-sCqEyNHq4cygJrQUFvFiWEexBUPs4AkeLQxH4pzsUrY3Rays7GVA6SojFCz2DMLXSJVqk8NG-plK-cZJetwWjgwLPub_9tQQohWLgJ0q2invJ5C5Imt2ket_-JAlBYLLcnqp_WmOfZkBEWuURsBVirpNQecvpedgeCx4VaFae4qWDI_uKRV1829KCBEH84u6LYUxh8W_BYqkzXJYt99OlHTXHegd91PLT-tawBwuIly46nwbAs5Nt7HFOozxkyPp8BW9URlQW1fE4R_40BXzEuVkzK3WAOdpR92IkV94K_rDZCPltGSvWXtqJbnCpUB6iUIn1V-Ki15FAwh_nsfSmt_NQZ3rQuvyQ9B3yLCQ1ZO_MGSYDYVO26dyXbElspKxQwuNRot9hi3FIbXylV3iN40-nCPH4YQzKjo5p_fuaKhvRh7H8oFjRXtPtLQQUIDxk-jMbOp7gXIsdz02DrCfQIihT4evZuWA6YShl6g8fnAqCy8qRBf_eLDnA9w-nBh4Bq53b1kdhnExz0CMyUjQ43UO3uhMkBomJTXbmfAAHP8dZZao6W8a34OktNQmPTbOHXrtxf6DS-oKOu3l79uX_ihbL8ELT40VjIW3MJeZ_-auCPOjpE3Ax4dzUkSDLCljitmzMagH2X8jN8-AYLl46KcfkBV

API_Direct_Payment_Key(Token): TXLrkmSj-VlRTOOC2GCkpLbg2fWXIgcucpP6p0T94ZXcd3uqdg-YI7IUjCbaU1DsdsAGjIW3gnczqjv2CLFKfsiZ3GcD0H6zo5BxFCiAwK45lFGBDdmIw91QRPOtudpxuPJvdkjV_GVVyg5tfndVMc46CuSoNBqfLuzUWiSE51sy-EgboaIZHpFU8xl4fGRFzAwPprwFinftAq3cWTHDEb5dKcxrqIlVxpJM9gqdFo5S3-BsapiEBaVc69QEg2WXVSSf00giFXGiiCiXdD6LZQKn1iE3wQaJttbdDdNjPuLtH0KxNdqC24ONZEh6UKPDKWmOItbyDp-eA5lPJEsAo6BaLUQ5bcFQZXV7k0fk1Dnq4Wj0Rv9SmM7uyC58YFv6b2vxkcgbV1tu8D1bXPSgq7DlvpMn4mh-H1gBisp4xPjYzpfP91n3gvHuizUp4vd70VIuuGY1-cvOGeUs59RfrP4wk_X4UI_qjwNkVF0fS1Of02cIi4AFWNwGkT-ZZhz7Bg-9lyhrOQYrNiO1mIGgxv-OiG5Cc3y5arR7ZpSYl4K8A2TwQNCXZChoIdXwSDMYvHZTZHdmnNlTM2u7lXro9YDluR0vyE5rNacAI9ubEh-iCH7WeJF2xr32Pp_APn22BVyd-4gNpS5XUOIEK21xBxg2NAkuO2ukYC6CoyAAGeGRDBWOQjvm1gdzSjQ-AKrWNJiKwQ
```

[# Test Cards ](https://myfatoorah.readme.io/v2.0/docs/test-cards)

#### Installation

```text cmd
npm install --save myfatoorah-reactnative
```

* If you are using Cocoapod you should do that:

```text cmd
cd ios && pod install && ..
```

##### Setup your screen

```typescript
import { MFSDK } from 'myfatoorah-reactnative';

//Add the code in the App()

  const configure = async () => {
    await MFSDK.init(
      'rLtt6JWvbUHDDhsZnfpAhpYk4dxYDQkbcPTyGaKp2TYqQgG7FGZ5Th_WD53Oq8Ebz6A53njUoo1w3pjU1D4vs_ZMqFiz_j0urb_BH9Oq9VZoKFoJEDAbRZepGcQanImyYrry7Kt6MnMdgfG5jn4HngWoRdKduNNyP4kzcp3mRv7x00ahkm9LAK7ZRieg7k1PDAnBIOG3EyVSJ5kK4WLMvYr7sCwHbHcu4A5WwelxYK0GMJy37bNAarSJDFQsJ2ZvJjvMDmfWwDVFEVe_5tOomfVNt6bOg9mexbGjMrnHBnKnZR1vQbBtQieDlQepzTZMuQrSuKn-t5XZM7V6fCW7oP-uXGX-sMOajeX65JOf6XVpk29DP6ro8WTAflCDANC193yof8-f5_EYY-3hXhJj7RBXmizDpneEQDSaSz5sFk0sV5qPcARJ9zGG73vuGFyenjPPmtDtXtpx35A-BVcOSBYVIWe9kndG3nclfefjKEuZ3m4jL9Gg1h2JBvmXSMYiZtp9MR5I6pvbvylU_PP5xJFSjVTIz7IQSjcVGO41npnwIxRXNRxFOdIUHn0tjQ-7LwvEcTXyPsHXcMD8WtgBh-wxR8aKX7WPSsT1O8d8reb2aR7K3rkV3K82K_0OgawImEpwSvp9MNKynEAJQS6ZHe_J_l77652xwPNxMRTMASk1ZsJL',
      MFCountry.KUWAIT,
      MFEnvironment.TEST
    );
  };

  const setUpActionBar = async () => {
    await MFSDK.setUpActionBar('Company Payment', processColor('#FFFFFF'), processColor('#000000'), true);
  };
```

> 📘 After testing
>
> Once your testing is finished, in. the "init" function, change the url. to the live url, select your live country, and set the environment. to live. Click [here ](https://docs.myfatoorah.com/docs/live-account)for more information.

#### Initiate / Execute Payment

As described earlier for the [Gateway Integration](https://docs.myfatoorah.com/docs/gateway-integration), we are going to have the SDK integrated with the same steps to make a successful integration with the SDK

> 🚧 Initiate Payment
>
> As a good practice, you don't have to call the [Initiate Payment](https://docs.myfatoorah.com/docs/initiate-payment) function every time you need to execute payment, but you have to call it at least once to save the PaymentMethodId that you will need to call [Execute Payment](https://docs.myfatoorah.com/docs/execute-payment).

```typescript
  const initiatePayment = async () => {
    var initiatePaymentRequest: MFInitiatePaymentRequest = new MFInitiatePaymentRequest(10, MFCurrencyISO.KUWAIT_KWD);

    await MFSDK
      .initiatePayment(initiatePaymentRequest, MFLanguage.ARABIC)
      .then((success) => console.log(success))
      .catch((error) => console.log(error));
  };

  const executePayment = async () => {
    var executePaymentRequest = new MFExecutePaymentRequest(10);
    executePaymentRequest.paymentMethodId = 2;
    executePaymentRequest.customerEmail = 'Test@test.com';
    executePaymentRequest.customerMobile = '123456789';
    executePaymentRequest.customerReference = 'Test12345';
    executePaymentRequest.displayCurrencyIso = MFCurrencyISO.QATAR_QAR;
    executePaymentRequest.expiryDate = '2024-06-08T17:36:23.173';

    await MFSDK
      .executePayment(executePaymentRequest, MFLanguage.ARABIC, (invoiceId: string) => console.log('invoiceId: ' + invoiceId))
      .then((success) => console.log(success))
      .catch((error) => console.log(error));
  };
```

#### Send Payment

We have explained in the [Send Payment](https://docs.myfatoorah.com/docs/send-payment) section earlier, the different usage cases for it and how it works, here we are going to embed some sample code for calling it through the SDK on the different platforms

```typescript

  const sendPayment = async () => {
    var sendPaymentRequest = new MFSendPaymentRequest(10, MFNotificationOption.LINK, 'customerName');
    sendPaymentRequest.customerEmail = 'Test@test.com';
    sendPaymentRequest.customerMobile = '123456789';
    sendPaymentRequest.customerReference = 'Test12345';
    sendPaymentRequest.displayCurrencyIso = MFCurrencyISO.UNITEDSTATES_USD;
    sendPaymentRequest.expiryDate = '2023-06-08T17:36:23.132Z';

    await MFSDK
      .sendPayment(sendPaymentRequest, MFLanguage.ARABIC)
      .then((success) => console.log(success))
      .catch((error) => console.log(error));
  };
```

#### Payment Enquiry

We have explained, the main usage for the [Payment Inquiry](https://docs.myfatoorah.com/docs/payment-inquiry) function, which will enable your application to get the full details about a certain invoice/payment. You can use this function within your application on different platforms as well. Here we are explaining some samples of its usage through the SDK.

```typescript
  const getPaymentStatus = async () => {
    var getPaymentStatusRequest = new MFGetPaymentStatusRequest('1515410', MFKeyType.INVOICEID);

    await MFSDK
      .getPaymentStatus(getPaymentStatusRequest, MFLanguage.ARABIC)
      .then((success) => console.log(success))
      .catch((error) => console.log(error));
  };
```

> 📘 Apple Pay for iPhone devices.
>
> Apple Pay is available from iOS 13.0. Apple is like other payment getways but when creating execute payment request you should send payment id for Apply Pay.

### Card View Payment

You can display MyFatoorah. \[card view]\(doc: card-view-form) directly in your application. to. give the customers. a better user experience

##### 1- First setup `MFCardPaymentView` and get a reference for it:

```typescript
  var cardPaymentView: MFCardPaymentView | null;

  const paymentCardStyle = () => {
    var cardViewInput = new MFCardViewInput(
      processColor('gray'),
      13,
      MFFontFamily.SansSerif,
      32,
      0,
      processColor('#c7c7c7'),
      2,
      8,
      new MFBoxShadow(10, 10, 5, 0, processColor('gray')),
      new MFCardViewPlaceHolder('Name On Card test', 'Number test', 'MM / YY', 'CVV test')
    );
    var cardViewLabel = new MFCardViewLabel(
      true,
      processColor('black'),
      13,
      MFFontFamily.CourierNew,
      MFFontWeight.Bold,
      new MFCardViewText('Card Holder Name test', 'Card Number test', 'Expiry Date test', 'Security Code test')
    );
    var cardViewError = new MFCardViewError(processColor('green'), 8, new MFBoxShadow(10, 10, 5, 0, processColor('yellow')));
    var cardViewStyle = new MFCardViewStyle(false, 'initial', 230, cardViewInput, cardViewLabel, cardViewError);

    return cardViewStyle;
   };

// Add card view and the pay button to your view
    <MFCardPaymentView ref={(ref) => (this.cardPaymentView = ref)} paymentStyle={this.paymentCardStyle()} />

    <View>
      <TouchableOpacity onPress={this.pay}>
        <Text>Pay</Text>
      </TouchableOpacity>
    </View>
```

##### 2-Display the CardView and set the pay function

```typescript

  initiateSession = async () => {
    var initiateSessionRequest = new MFInitiateSessionRequest('testCustomer');

    await MFSDK.initiateSession(initiateSessionRequest)
      .then((success: MFInitiateSessionResponse) => {
        console.log(success);
        this.loadCardView(success);
      })
      .catch((error) => console.log('error : ' + error));
  };

  loadCardView = async (initiateSessionResponse:MFInitiateSessionResponse) => {
    await this.cardPaymentView
      ?.load(initiateSessionResponse, (bin: string) => console.log('bin: ' + bin))
      .then((success) => {
        console.log(success);
      })
      .catch((error) => console.log('error : ' + error));
  };

  const pay = async () => {
    var executePaymentRequest = new MFExecutePaymentRequest(10);
    executePaymentRequest.sessionId = sessionId ?? '';

    await cardPaymentView
      ?.pay(executePaymentRequest, MFLanguage.ARABIC, (invoiceId: string) => onEventReturn('invoiceId: ' + invoiceId))
      .then((success) => onSuccess(success))
      .catch((error) => onError(error));
  };
```

### In Apple Pay

You can use In Apple Pay and don't have to open in web view by using 'MFApplePayButtonView'

##### 1- First setup `MFApplePayButtonView` and get a reference for it:

```typescript
  var applePayView: MFApplePayButtonView | null;

	const applePayStyle = () => {
    var applePayButton = new MFApplePayStyle(30, 30, 'Buy with', false);
    return applePayButton;
  };

// Add Apple Pay button to your view
  {Platform.OS === 'ios' && <MFApplePayButtonView ref={(ref) => (applePayView = ref)} style={styles.cardView} applePayButtonStyle={applePayStyle()} />}


```

##### 2- Display Apple Pay Button

```typescript
  const applePay = async () => {
    var executePaymentRequest = new MFExecutePaymentRequest(10);
    executePaymentRequest.displayCurrencyIso = MFCurrencyISO.KUWAIT_KWD;
    executePaymentRequest.sessionId = sessionId ?? '';

    await applePayView
      ?.applePayPayment(executePaymentRequest, MFLanguage.ARABIC, (invoiceId: string) => console.log('invoiceId: ' + invoiceId))
      .then((success) => console.log(success))
      .catch((error) => console.log(error));
  };
```

#### Direct Payment

As we have explained earlier in the [Direct Payment](https://docs.myfatoorah.com/docs/direct-payment) integration and how it works, it also has the same scenario for the SDK implementation, you have to know the following steps to understand how it works:

* Get the payment method that allows Direct Payment by calling initiatePayment to get paymentMethodId
* Collect card info from user ***MFCardInfo(cardNumber: "51234500000000081", cardExpiryMonth: "05", cardExpiryYear: "21", cardSecurityCode: "100", saveToken: false)***
* If you want to save your credit card info and get a token for your next payment you have to set ***saveToken: true*** and you will get the token in the response read more in [Tokenization](https://docs.myfatoorah.com/docs/tokenization)
* If you want to execute a payment through a saved token you have use ***MFCardInfo(cardToken: "put your token here")***
* Now you are ready to execute the payment, please check the following sample code

```javascript JavaScript
  const executeDirectPayment = async () => {
    var executePaymentRequest = new MFExecutePaymentRequest(10);
    executePaymentRequest.paymentMethodId = 20; //9
    var mfCardRequest = new MFCardRequest('5454545454545454', '05', '23', '000', 'myFatoorah');

    var directPaymentRequest = new MFDirectPaymentRequest(executePaymentRequest, null, mfCardRequest);

    await MFSDK
      .executeDirectPayment(directPaymentRequest, MFLanguage.ARABIC, (invoiceId: string) => console.log('invoiceId: ' + invoiceId))
      .then((success) => console.log(success))
      .catch((error) => console.log(error));
  };
```

#### GooglePay

***Step 1: Define Google Pay Button***

Before using Google Pay, you need to define the MFGooglePayButton in your class:

```javascript TypeScript
googlePayButton: MFGooglePayButton | null = null;
```

***Step 2: Initiate a Session for Google Pay***

Use the MFInitiateSessionRequest class to create a session.

```javascript TypeScript
var initiateSessionRequest = new MFInitiateSessionRequest();

await MFSDK.initiateSession(initiateSessionRequest)
  .then((response: MFInitiateSessionResponse) => {
    console.log('result : ' + result);
    var sessionId = response.SessionId ?? '';
    this.setupGooglePayHelper(sessionId);
  })
  .catch((error) => console.log('error : ' + error));
```

***Step 3: Setup Google Pay Helper***

Once the session is initiated, use the session ID to configure Google Pay.

Configure Google Pay with necessary details such as the merchant ID, merchant name, country code, and currency.

The following method sets up the Google Pay request:

```javascript TypeScript
setupGooglePayHelper = async (sessionId: String) => {
  var request = new MFGooglePayRequest(
    '1', 
    'your_google_merchant_id',
    'Test Vendor', 
    MFCountry.KUWAIT, 
    MFCurrencyISO.UAE_AED
  );

  await this.googlePayButton
    ?.setupGooglePayHelper(sessionId, request, (invoiceId: string) => console.log('Invoice Id: ' + invoiceId))
    .then((success) => console.log('Google Pay Success: ' + success.InvoiceStatus))
    .catch((error) => console.log('Google Pay Error: ' + error));
};
```

***Step 4: Integrating Google Pay Button in UI***

```javascript TypeScript
render() {
  return (
    <View style={styles.container}>
      <MFGooglePayButton
        ref={(ref) => (this.googlePayButton = ref)}
        style={styles.googlePay}
        theme={GooglePayButtonConstants.Themes.Dark}
        type={GooglePayButtonConstants.Types.Checkout}
        radius={10}
      />
    </View>
  );
}
```

***Step 5: Going Live with google pay:***

* Complete your google Business Profile and get your Merchant ID.
* [Request production access from Google Pay & Wallet Console.](https://developers.google.com/pay/api/android/guides/test-and-deploy/request-prod-access)

## Cordova

*`https://docs.myfatoorah.com/docs/cordova` — updated 2025-11-11*

> Guide for Cordova

#### Demo project

Cordova Plugin: [cordova-plugin-myfatoorah](https://www.npmjs.com/package/cordova-plugin-myfatoorah)\
Cordova Plugin Demo: [cordova-demo](https://dev.azure.com/myfatoorahsc/MF-SDK-Cross-Platforms-Demos/_git/MF-SDK-Cross-Platforms-Demos?version=GBcordova_demo)

#### Installation

* Run the following commands on your Terminal on the parent directory of your project.

```text
npm i cordova-plugin-myfatoorah
cordova plugin add cordova-plugin-myfatoorah

// for Android
cordova platforms add android 
cordova run android

// for iOS
cordova platforms add ios
cordova run ios
```

#### Usage

1. Initiate MyFatoorah Plugin with the following line:

```javascript
function init() {
  cordova.plugins.MyFatoorahCordovaPlugin.initiate(
    "Put your API Key here",
    cordova.plugins.MyFatoorahCordovaPlugin.MFCountry.KUWAIT,
    cordova.plugins.MyFatoorahCordovaPlugin.MFEnvironment.TEST
  );
}
```

2. (Optional)

```javascript
// Use the following lines if you want to set up the title of the page.

function setUpTitle() {
  cordova.plugins.MyFatoorahCordovaPlugin.setUpTitle("MyFatoorah Payment", true);
}
```

> 📘 After testing
>
> Once your testing is finished, simply replace the environment from **TEST** to **LIVE** and the **API Key** with the live one, click [here ](https://docs.myfatoorah.com/docs/live-account)for more information.

#### Initiate / Execute Payment

As described earlier for the [Gateway Integration](https://docs.myfatoorah.com/docs/gateway-integration), we are going to have the SDK integrated with the same steps to make a successful integration with the SDK

> 🚧 Initiate Payment
>
> As a good practice, you don't have to call the [Initiate Payment](https://docs.myfatoorah.com/docs/initiate-payment) function every time you need to execute payment, but you have to call it at least once to save the PaymentMethodId that you will need to call [Execute Payment](https://docs.myfatoorah.com/docs/execute-payment)

```javascript
// Initiate Payment

function initiatePayment() {
  var initiatePaymentRequest = new cordova.plugins.MyFatoorahCordovaPlugin.MFInitiatePaymentRequest();
  initiatePaymentRequest.invoiceAmount = 0.100;
  initiatePaymentRequest.currencyIso = cordova.plugins.MyFatoorahCordovaPlugin.MFCurrencyISO.KUWAIT_KWD;

  cordova.plugins.MyFatoorahCordovaPlugin.initiatePayment(
  initiatePaymentRequest,
  cordova.plugins.MyFatoorahCordovaPlugin.MFLanguage.ARABIC,
  function (result) {
    if (result["status"] == "success") {
      alert("data:" + JSON.stringify(result["data"]))
    }
    else if (result["status"] == "error") {
      alert("error:" + result["message"])
    }
  });
}

// Eexcute Payment

function executePayment(paymentMethodId) {
  var executePaymentRequest = new cordova.plugins.MyFatoorahCordovaPlugin.MFExecutePaymentRequest();
  executePaymentRequest.invoiceValue = 0.100
  executePaymentRequest.paymentMethod = paymentMethodId

  cordova.plugins.MyFatoorahCordovaPlugin.executePayment(
    executePaymentRequest,
    cordova.plugins.MyFatoorahCordovaPlugin.MFLanguage.ENGLISH,
    function (result) {
      if (result["status"] == "success") {
        alert("data:" + JSON.stringify(result["data"]))
      } else if (result["status"] == "error") {
        alert("error:" + JSON.stringify(result))
      }
    }
  );
}
```

#### Send Payment

We have explained in the [Send Payment](https://docs.myfatoorah.com/docs/send-payment) section earlier, the different usage cases for it and how it works, here we are going to embed some sample code for calling it through the SDK on the different platforms

```javascript
function sendPayment() {
  var sendPaymentRequest = new cordova.plugins.MyFatoorahCordovaPlugin.MFSendPaymentRequest();
  sendPaymentRequest.invoiceValue = 0.100
  sendPaymentRequest.customerName = "customerName"
  sendPaymentRequest.notificationOption = cordova.plugins.MyFatoorahCordovaPlugin.MFNotificationOption.LINK

  cordova.plugins.MyFatoorahCordovaPlugin.sendPayment(
    sendPaymentRequest,
    cordova.plugins.MyFatoorahCordovaPlugin.MFLanguage.ENGLISH,
    function (result) {
      if (result["status"] == "success") {
        alert("data:" + JSON.stringify(result["data"]))
      } else if (result["status"] == "error") {
        alert("error:" + JSON.stringify(result))
      }
    }
  )
}
```

#### Payment Enquiry

We have explain the main usage for the [Payment Inquiry](https://docs.myfatoorah.com/docs/payment-inquiry) function, that will enable your application to get the full details about a certain invoice / payment. You can use this function within your application on the different platforms as well. Here we are explaining some samples of its usage through the SDK.

```javascript
function getPaymentStatus() {
  var getPaymentStatusRequest = new cordova.plugins.MyFatoorahCordovaPlugin.MFPaymentStatusRequest();
  getPaymentStatusRequest.key = "1515410";
  getPaymentStatusRequest.keyType = cordova.plugins.MyFatoorahCordovaPlugin.MFKeyType.INVOICEID;
  
  cordova.plugins.MyFatoorahCordovaPlugin.getPaymentStatus(
    getPaymentStatusRequest,
    cordova.plugins.MyFatoorahCordovaPlugin.MFLanguage.ENGLISH,
    function (result) {
      parseResult(result);
    }
  )
}
```
