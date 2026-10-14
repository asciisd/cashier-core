# Callbacks / Notifications

Xoala calls two things "notification": the Standard Checkout redirect-back
(customer's browser POSTs to `merchantRedirectUrl`) and the server-to-server
`notificationUrl` webhook. Both carry the same field shape and the same
checksum rule (`standard-checkout.md` rule 3):

```
md5(paymentId|merchantTransactionId|amount|<short status>|secureKey)
```

`<short status>` is the Y/N/P/3D/C code. On a real payload it arrives under
the key `transactionStatus`, alongside a `status` field that carries the
*long* form (`payoutsuccessful`, `capturesuccess`, ...). Sign
`transactionStatus`, never `status` — see `pitfalls.md`.

## Card notification — success

Sample (`sampleResponse.NOTI_CARD`):

```json
{
  "paymentId": "18608029",
  "status": "payoutsuccessful",
  "transactionStatus": "Y",
  "paymentBrand": "VISA",
  "paymentMode": "CC",
  "firstName": "John",
  "lastName": "Do",
  "amount": "100.00",
  "currency": "JPY",
  "descriptor": "",
  "merchantTransactionId": "Payout-JPY-1",
  "remark": "Payout Successful",
  "tmpl_amount": "50.00",
  "tmpl_currency": "JPY",
  "checksum": "01143de763e7b8a9917d365ebecdcd19",
  "result": { "code": "00001", "description": "Transaction succeeded" },
  "customer": { "email": "john@gmail.com", "id": "" },
  "card": {
    "bin": "444433",
    "lastFourDigits": "1111",
    "last4Digits": "1111",
    "expiryMonth": "12",
    "expiryYear": "2030"
  },
  "timestamp": "2023-02-09 19:42:23",
  "eci": "",
  "bankReferenceId": "756209",
  "terminalId": "19671"
}
```

(This particular sample is drawn from a Payout flow — `status: payoutsuccessful`
— but the field shape, including the split `card` and `customer` sub-objects,
is the same one used for a card deposit notification.)

## Card notification — failure

Sample (`sampleResponse.FAIL_NOTI_CARD`) — same shape, `status: payoutfailed`,
`transactionStatus: N`, `result.code: 10001` ("Transaction failed"). The
`checksum` field is present and computed the same way regardless of outcome.

## Bank-transfer notification

Sample (`sampleResponse.NOTI_BANKTRANS`) — same envelope, with
`paymentBrand: "Bank Transfer"`, `paymentMode: "NB"`, and an empty `card`
sub-object (all fields blank strings rather than absent):

```json
{
  "paymentId": "18000392",
  "status": "payoutsuccessful",
  "transactionStatus": "Y",
  "paymentBrand": "Bank Transfer",
  "paymentMode": "NB",
  "amount": "100000.65",
  "currency": "VND",
  "merchantTransactionId": "Payout-BT-3",
  "checksum": "bb2fb84fc04a7c996a32be6cb114ee5a",
  "result": { "code": "00001", "description": "Transaction succeeded" },
  "customer": { "email": "test@email.com", "id": "" },
  "card": { "bin": "", "lastFourDigits": "", "last4Digits": "", "expiryMonth": "", "expiryYear": "" },
  "timestamp": "2023-02-09 18:26:49",
  "bankReferenceId": "88828868",
  "terminalId": "19576"
}
```

Parse defensively: `card` is always present as an object, even when every
field inside it is `""` for a non-card rail.

## Standard Checkout's own sample response

`sampleResponse.StandardCheckout` (workflow `stdkit`) is the one sample that
does **not** match the parameter table it illustrates — see `pitfalls.md` for
why that matters:

```json
{
  "trackingid": "34464",
  "paymentId": "34464",
  "token": "kGuadVKOymeTYkuAYoxon2vKUzu88Po1",
  "merchantTransactionId": "Test Transaction",
  "amount": "50.00",
  "currency": "USD",
  "status": "authsuccessful",
  "checksum": "7OsiGNIhrJVPHPhzk49E6WcjE6tFJYOE",
  "resultCode": "00001",
  "resultDescription": "Transaction processed successfully",
  "cardBin": "411111",
  "cardLast4Digits": "1111",
  "eci": "5",
  "timestamp": "2018-08-17 15:05:25"
}
```
