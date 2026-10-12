# PayCrossBorder API notes

Vendored from docs.paycrossborder.com (V 1.2.0), 2026-10-12. Host is
unspecified in the docs ("http://host"), so `base_url` is per connection.

## Common

- Headers on every call: `channel: WEB`, `source: AGENT`, `Accept-Language: en`.
- Success: HTTP 2xx and `resultCode: "0"`. The docs list no error codes.
- Dates: `requestDate` is `dd-mm-yyyy`; identity dates are `yyyy-mm-dd`.
- Countries are ISO-3. Currencies are PCB codes such as `USD-USA`, `USDT`.

## Login — `POST /ewallet/oauth/token` (form)

`grant_type=password&scope=read write&username=…&password=…`. The example
password is an uppercase hex digest (unconfirmed whether issued or derived →
`password_hash`). Response: `access_token`, `expires_in` (seconds),
`clientCode`.

## Quotation — `POST /ewallet/api/v1/fxratequotation/api`

`payinAmount` or `payoutAmount`, `sendCurrencyCode`, `receiveCurrencyCode`,
`settlementCurrencyCode`, `destinationCountryCode`, `sourceCountry`,
`serviceTypeCode` (B2C…), `paymentMode` (Cash | Bank), `senderCode`,
`customerCode`, `chargeTypeCode` (BEN | SHA | OUR). Response
`fxRateQuotation`: `code` (→ `compliance.forexQuoteId`), `fxRateValue`,
`senderMargin`, `payinAmount`, `payoutAmount`, `fee`, `duration`/`timeLeft`
(minutes; quotes expire in ~5).

## Payout — `POST /ewallet/api/v1/payoutProcess/api`

Body `{"payout": {transactionInfo, sender, receiver, compliance}}`.
`sendClientTrxReference` 10–30 alphanumerics; `descriptionText` ≤ 25.
Registered sender: `{business: {isAutoRegistered: false, declaration: false,
docReferenceNumber, customerCode}}`. Receiver per rail:

- Crypto: identity + address + `receiverDOB` (mandatory) + `walletAddress`
  (26–50) + `cryptoNetwork`.
- FIAT: identity + address + `receiverAccountNumber`,
  `receiverAccountHolderName`, `receiverBankName`, `receiverSwiftCode`,
  `receiverBankCode`, `receiverAccountType` (BRA, PHL, DOM, URY),
  `taxIdNumber` (ARG, THA, BRA, ECU, GTM, HND, KAZ, MEX, PER, CHL, PHL, JPN,
  URY, COL, CRI, RUS, BLR, PAK); phone on SWIFT to BRA, PHL, CHN, UGA, URY,
  COL, MEX, HKG, MYS. Per-country Local RAIL tables in COUNTRYVALIDATIONS.md
  are not encoded in the driver.
- WPT: identity + address + `receiverMsisdn`, `dialCode`,
  `receiverServiceProviderName`, `receiverServiceProviderCode`,
  `receiverServiceProviderMobile`.

Response `payoutResponseBean.transReference` is PCB's id (→ `payout_reference`),
`status` starts as `In Process`.

## Enquiry — `GET /ewallet/api/v1/transactionInfo/api?types=all&status=all&transId=…`

`transactionInfoResponse[]` rows: `transId`, `senderUniqueId` (assumed = our
`sendClientTrxReference`), `statusName`, `transactionStatus` (`IP` seen),
`vendorResultDescription`.

## Statuses (responseCodes.md)

In Process, Payout Processing → Sent · Payout Pass → Paid · Payout Fail,
Reverse → Failed · Technical Failure → Unknown.

## Balance — `GET /ewallet/api/v1/wallet/walletOwner/{walletOwnerCode}`

`walletList[]`: `walletTypeName` (Main Wallet | Comission Wallet),
`currencyName`, `value` (the balance), `minTransValue`, `maxTransValue`.
