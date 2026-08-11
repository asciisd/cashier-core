# Heropayments — API error codes

## V2

| URL method | Message | Error codes | Comment |
|---|---|---|---|
| v2/payments,v2/payments-address, v2/withdrawal, v2/invoices | Field externalOrderId for this user is not unique | 400 | Each transaction must have a unique identifier externalOrderId - enter unique externalOrderId |
| v2/payments, v2/withdrawal, v2/estimate, v2/rate, v2/min-amount | {CURRENCY_NAME} is not supported yet.  Get list of supported currencies from v2/currencies | 400 | Currency you entered is not valid - check this supported currencies list OR you can use v2/currencies method Documentation: https://documenter.getpostman.com/view/17469357/UVyvwv7a#699875a1-914c-4a63-ae69-204f7ab5626d |
| v2/payments, v2/withdrawal, v2/estimate, v2/rate, v2/min-amount | {CURRENCY_NAME} is disabled | 400 |  |
| v2/payments, v2/withdrawal, v2/payments-address | priceAmount should be positive | 400 | Currency you entered is not valid - check this supported currencies list OR you can use v2/min-amount method Documentation: https://documenter.getpostman.com/view/17469357/UVyvwv7a#74245569-2457-46f7-bbce-55df4d9d4229 |
| v2/payments, v2/withdrawal, v2/payments-address | priceAmount is less than minimal usd 4.99 | 400 |  |
| v2/payments, v2/payments-address, v2/withdrawal | X USD (X USDT20) is less than minimal 30 USD (30 USDT20) | 400 |  |
| v2/payments, v2/payments-address | {CURRENCY_NAME}  is withdrawal-only. | 400 | Currency you entered is disabled for withdrawals. Please reach out to support for assistance. |
| v2/withdrawal | {CURRENCY_NAME}  is deposit-only. | 400 | Currency you entered is disabled for deposits. Please reach out to support for assistance. |
| v2/payments, v2/withdrawal, v2/estimate, v2/rate | Rate is outdated | 400 | The currency is currently undergoing technical maintenance. Please reach out to support for assistance. |
| v2/payments, v2/withdrawal, v2/estimate, v2/rate | Amount is bigger than maximum: X USDT20 | 500 |  |
| v2/payments, v2/withdrawal, v2/estimate, v2/rate | Amount is not in range | 500 |  |
| v2/withdrawal | Error, merchant withdrawal limit per day has been exceeded. Please, text to support to update the limit. | 400 | Merchant / Customer limit is exceeded - please, text to support to update the limit. |
| v2/withdrawal | Error, customer withdrawal limit per day has been exceeded. Please, text to support to update the limit. | 400 |  |
| v2/withdrawal | Payout address not valid | 400 | The address you entered is not valid, or you need to provide payoutExtraId [v2] (In case of XRP is a memo) that is either missing or incorrect. |
| v2/payments, v2/withdrawal | Fiat currency CURRENCY_NAME is not found. Get list of supported fiat currencies from v2/currencies?fiat=true https://documenter.getpostman.com/view/17469357/UVyvwv7a#699875a1-914c-4a63-ae69-204f7ab5626d | 400 | Currency you entered is not valid - check supported currencies list (fiat currencies tab) |
| v2/withdrawal | Error, insufficient funds for withdrawal, balance: X USDT20, amount: X | 400 | Balance is not sufficient for withdrawal. Top up your balance in the Back Office (BO) |
| v2/withdrawal | Invalid <from> or <to> currency | 500 | Merchant account is not fully configured. Please contact support with this error |
| v2/payments, 2/payments-address, v2/invoices, v2/balance | timeout of 15000ms exceeded | 500 | Infrastructure error. Try again |
| v2/withdrawal | timeout of 15000ms exceeded | 500 | Please check if the withdrawal was created using the externalOrderID in the Back Office (BO):  OR by using the "GET payment by id" method: https://app.heropayments.io/ If you don’t see the withdrawal, please contact support  GET payment by id method in documentation: https://documenter.getpostman.com/view/17469357/UVyvwv7a#f850d90d-7926-46f6-bcbf-4b90369cbbf8 |
| v2/payments, 2/payments-address, v2/invoices, v2/balance | internal server error | 500 | Infrastructure error. Try again |
| v2/withdrawal | internal server error | 500 | Please check if the withdrawal was created using the externalOrderID in the Back Office (BO):  OR by using the "GET payment by id" method: https://app.heropayments.io/ If you don’t see the withdrawal, please contact support  GET payment by id method in documentation: https://documenter.getpostman.com/view/17469357/UVyvwv7a#f850d90d-7926-46f6-bcbf-4b90369cbbf8 |
| All methods | Unknown x-api-key | 401 | x-api-key is wrong. Please check it in the Back Office (BO) |
| All methods | Invalid signature | 401 | Please check the script you're using for calculating x-api-sign |
| All methods | Bad Gateway | 502 | Infrastructure error. Try again later. |

## Custody

| URL method | Message | Error codes | Comment |
|---|---|---|---|
| custody/deposit, custody/withdrawal | Field externalOrderId for this user is not unique | 400 | Each transaction must have a unique identifier externalOrderId - enter unique externalOrderId |
| custody/deposit, custody/withdrawal, custody/min-amount | {CURRENCY_NAME} is not supported yet. | 400 | Currency you entered is not valid - check this supported currencies list |
| custody/deposit, custody/withdrawal, custody/min-amount | {CURRENCY_NAME} is disabled | 400 |  |
| custody/deposit, custody/withdrawal | priceAmount should be positive | 400 | Amount you entered is less than minimal amount - check this supported currencies list OR use custody/min-amount method Documentation: https://documenter.getpostman.com/view/17469357/UVyvwv7a#d8dae306-0798-4c8b-880b-209a9a258345 |
| custody/deposit, custody/withdrawal | priceAmount is less than minimal usd 4.99 | 400 |  |
| custody/deposit, custody/withdrawal | X USD (X USDT20) is less than minimal 30 USD (30 USDT20) | 400 |  |
| custody/deposit | {CURRENCY_NAME}  is withdrawal-only. | 400 | Currency you entered is disabled for withdrawals. Please reach out to support for assistance. |
| custody/withdrawal | {CURRENCY_NAME}  is deposit-only. | 400 | Currency you entered is disabled for deposits. Please reach out to support for assistance. |
| custody/deposit, custody/withdrawal | Rate is outdated | 400 | The currency is currently undergoing technical maintenance. Please reach out to support for assistance. |
| custody/deposit, custody/withdrawal | Amount is bigger than maximum: X USDT20 | 500 |  |
| custody/deposit, custody/withdrawal | Amount is not in range | 500 |  |
| custody/withdrawal | Error, merchant withdrawal limit per day has been exceeded. Please, text to support to update the limit. | 400 | Merchant / Customer limit is exceeded - please, text to support to update the limit. |
| custody/withdrawal | Error, customer withdrawal limit per day has been exceeded. Please, text to support to update the limit. | 400 |  |
| custody/withdrawal | Payout address not valid | 400 | The address you entered is not valid, or you need to provide AddressExtra [custody] / payoutExtraId [v2] (In case of XRP is a memo) that is either missing or incorrect. |
| custody/withdrawal | Error, insufficient funds for withdrawal, balance: X USDT20, amount: X | 400 | Balance is not sufficient for withdrawal. Top up your balance in the Back Office (BO) |
| custody/withdrawal | Invalid <from> or <to> currency | 500 | Merchant account is not fully configured. Please contact support with this error |
| custody/deposit, custody/balances, custody/min-amount | timeout of 15000ms exceeded | 500 | Infrastructure error. Try again |
| custody/withdrawal | timeout of 15000ms exceeded | 500 | Please check if the withdrawal was created using the externalOrderID in the Back Office (BO) or by using the "GET payment by id" method. If you don’t see the withdrawal, please contact support  GET payment by id method in documentation: https://documenter.getpostman.com/view/17469357/UVyvwv7a#f850d90d-7926-46f6-bcbf-4b90369cbbf8 |
| custody/deposit, custody/balances, custody/min-amount | internal server error | 500 | Infrastructure error. Try again |
| custody/withdrawal | internal server error | 500 | Please check if the withdrawal was created using the externalOrderID in the Back Office (BO) or by using the "GET payment by id" method. If you don’t see the withdrawal, please contact support  GET payment by id method in documentation: https://documenter.getpostman.com/view/17469357/UVyvwv7a#f850d90d-7926-46f6-bcbf-4b90369cbbf8 |
| All methods | Unknown x-api-key | 401 | x-api-key is wrong. Please check it in the Back Office (BO) |
| All methods | Invalid signature | 401 | Please check the script you're using for calculating x-api-sign |
| All methods | Bad Gateway | 502 | Infrastructure error. Try again later. |
