# MyFatoorah — API reference — V3

## Create Payment

*`https://docs.myfatoorah.com/reference/create-payment` — updated 2026-08-18*

> 📘 API Base URL
>
> To identify the correct API Base URL for your environment, please refer to this [table](https://docs.myfatoorah.com/docs/api-key#api--portal-urls).

<br />

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V3"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v3/payments": {
      "post": {
        "summary": "Create Payment",
        "description": "",
        "operationId": "create-payment",
        "tags": [
          "Payments"
        ],
        "responses": {
          "201": {
            "description": "Payment created successfully",
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "IsSuccess": {
                      "type": "string",
                      "description": "\"true\" or \"false\" indicating the status of your request."
                    },
                    "Message": {
                      "type": "string",
                      "description": "The message response associated with the request done"
                    },
                    "ValidationErrors": {
                      "type": "array",
                      "items": {
                        "properties": {},
                        "type": "object"
                      },
                      "description": "A model that contains two keys \"Name\" and \"Error\". This is used to indicate the validation result for all parameters you have sent in your request. This can have one or more items based on the invalid parameter count."
                    },
                    "Data": {
                      "type": "object",
                      "properties": {
                        "InvoiceId": {
                          "type": "string",
                          "description": "A unique invoice number."
                        },
                        "PaymentId": {
                          "type": "string"
                        },
                        "PaymentURL": {
                          "type": "string",
                          "description": "URL where the customer can complete the payment."
                        },
                        "PaymentCompleted": {
                          "type": "boolean"
                        },
                        "TransactionDetails": {
                          "type": "object",
                          "properties": {},
                          "description": "Contains transaction information if available; null if payment not completed."
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "Payment Response": {
                    "summary": "Payment Response",
                    "value": {
                      "IsSuccess": true,
                      "Message": "",
                      "ValidationErrors": null,
                      "Data": {
                        "InvoiceId": "6309730",
                        "PaymentId": null,
                        "PaymentURL": "https://demo.MyFatoorah.com/KWT/ie/01072630973041-4f6031ae",
                        "PaymentCompleted": false,
                        "TransactionDetails": null
                      }
                    }
                  },
                  "Payment Response with Result of Payment (Non-3DS payments)": {
                    "summary": "Payment Response with Result of Payment (Non-3DS payments)",
                    "value": {
                      "IsSuccess": true,
                      "Message": "",
                      "ValidationErrors": null,
                      "Data": {
                        "InvoiceId": "6322611",
                        "PaymentId": "07076322611317711671",
                        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/Result?paymentId=07076322611317711671",
                        "PaymentCompleted": true,
                        "TransactionDetails": {
                          "Invoice": {
                            "Id": "6322611",
                            "Status": "PAID",
                            "Reference": "2025001236",
                            "CreationDate": "2025-11-29T12:16:31.1091431Z",
                            "ExpirationDate": "2025-11-29T14:15:55.0000000Z",
                            "ExternalIdentifier": null,
                            "UserDefinedField": "",
                            "MetaData": null
                          },
                          "Transaction": {
                            "Id": "283269",
                            "Status": "SUCCESS",
                            "PaymentMethod": "VISA/MASTER",
                            "PaymentId": "07076322611317711671",
                            "ReferenceId": "533312283269",
                            "TrackId": "29-11-2025_3177116",
                            "AuthorizationId": "283269",
                            "TransactionDate": "2025-11-29T12:16:33.3219471Z",
                            "ECI": "",
                            "IP": {
                              "Address": "197.32.109.5",
                              "Country": "Egypt"
                            },
                            "Error": {
                              "Code": "",
                              "Message": ""
                            },
                            "Card": {
                              "NameOnCard": "test",
                              "Number": "512345xxxxxx0008",
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
                            "ValueInBaseCurrency": "10",
                            "ServiceCharge": "0.001",
                            "ServiceChargeVAT": "0",
                            "ReceivableAmount": "9.999",
                            "DisplayCurrency": "KWD",
                            "ValueInDisplayCurrency": "10",
                            "PayCurrency": "KWD",
                            "ValueInPayCurrency": "10"
                          },
                          "Suppliers": []
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        },
        "parameters": [],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "PaymentMethod": {
                    "type": "string",
                    "default": "CARD",
                    "enum": [
                      "CARD",
                      "APPLE_PAY",
                      "GOOGLE_PAY",
                      "KNET"
                    ],
                    "description": "Specify the payment method you want to use, or omit this parameter to create a payment page that displays all enabled methods. This is required for redirection cases."
                  },
                  "Order": {
                    "type": "object",
                    "properties": {
                      "Amount": {
                        "type": "number",
                        "description": "The payment amount must be greater than 0.",
                        "default": "10"
                      },
                      "Currency": {
                        "type": "string",
                        "description": "The currency ISO code you want to display to the customer, by default is the same as the base currency of the country API.",
                        "default": "",
                        "enum": [
                          "SAR",
                          "BHD",
                          "AED",
                          "QAR",
                          "OMR",
                          "KWD",
                          "JOD",
                          "EGP"
                        ]
                      },
                      "ExternalIdentifier": {
                        "type": "string",
                        "description": "You may use as additional information to be stored with the transaction."
                      }
                    },
                    "description": "Order information related to the payment..",
                    "required": [
                      "Amount"
                    ]
                  },
                  "SourceOfFund": {
                    "type": "object",
                    "properties": {
                      "SessionId": {
                        "type": "string",
                        "description": "Session ID returned from initiating the session, used for Embedded Integration with COLLECT_DETAILS mode"
                      },
                      "Token": {
                        "type": "string",
                        "description": "Token for saved cards when processing payments without entering card data again, or for Wallet tokens."
                      },
                      "Type": {
                        "type": "string"
                      },
                      "Card": {
                        "type": "object",
                        "properties": {
                          "Number": {
                            "type": "string",
                            "description": "Represents the 16 digits of the card that will be charged for the transaction"
                          },
                          "ExpiryMonth": {
                            "type": "string",
                            "description": "Card expiry month"
                          },
                          "ExpiryYear": {
                            "type": "string",
                            "description": "Card expiry year"
                          },
                          "SecurityCode": {
                            "type": "string",
                            "description": "Card CVV / CVC"
                          },
                          "HolderName": {
                            "type": "string",
                            "description": "Name on the card."
                          },
                          "Cryptogram": {
                            "type": "string",
                            "description": "Cryptogram value used for 3DSecure payments and wallet payments."
                          },
                          "EciIndicator": {
                            "type": "string",
                            "description": "3DSecure ECI value from the card issuer."
                          }
                        },
                        "description": "Used only if you are PCI-certified and processing card details through direct integration."
                      }
                    },
                    "description": "Contains the payment source details (Tokenized card, direct card details, or Session Id)"
                  },
                  "PaymentExpiry": {
                    "type": "string",
                    "format": "date-time",
                    "description": "The payment expiration date. Must be in UTC timezone."
                  },
                  "SaveCardOptions": {
                    "type": "object",
                    "properties": {
                      "SaveToken": {
                        "type": "boolean",
                        "description": "If true, card tokenization will be enabled for future transactions."
                      }
                    },
                    "description": "Used to tokenize card details when using direct integration."
                  },
                  "ThreeDS": {
                    "type": "object",
                    "properties": {
                      "Enabled": {
                        "type": "boolean",
                        "description": "If true, the user will be required to complete the OTP step.",
                        "default": ""
                      },
                      "AuthenticationValue": {
                        "type": "string",
                        "description": "Cryptogram"
                      },
                      "DirectoryServerTransactionId": {
                        "type": "string"
                      },
                      "Eci": {
                        "type": "string"
                      },
                      "ProtocolVersion": {
                        "type": "string"
                      },
                      "TransactionStatus": {
                        "type": "string"
                      }
                    },
                    "description": "3D Secure authentication details for the direct card transaction."
                  },
                  "NotificationOption": {
                    "type": "string",
                    "default": "",
                    "enum": [
                      "EMAIL",
                      "SMS",
                      "ALL",
                      "LINK"
                    ],
                    "description": "Defines how the customer receives invoice notifications.\nNote: Email or mobile becomes mandatory based on the selected option."
                  },
                  "OperationType": {
                    "type": "string",
                    "default": "",
                    "enum": [
                      "AUTHORIZE",
                      "PAY"
                    ],
                    "description": "Defines the type of payment operation."
                  },
                  "Suppliers": {
                    "type": "array",
                    "description": "Required only if Multi-Vendor feature is enabled.",
                    "items": {
                      "properties": {
                        "SupplierCode": {
                          "type": "integer",
                          "description": "The supplier code you need to associate the invoice with."
                        },
                        "ProposedDepositShare": {
                          "type": "number",
                          "description": "The amount that the supplier will get after paying the invoice."
                        },
                        "InvoiceShare": {
                          "type": "number",
                          "description": "Amount specified for this supplier from the total invoice value."
                        }
                      },
                      "type": "object"
                    }
                  },
                  "Customer": {
                    "type": "object",
                    "properties": {
                      "Name": {
                        "type": "string",
                        "description": "Customer's name."
                      },
                      "Mobile": {
                        "type": "object",
                        "properties": {
                          "CountryCode": {
                            "type": "string",
                            "description": "The maximum country code length is 4 and match the following regular expression: ^(?:(\\+)|(00)|(\\*)|())\\d{1,4}$"
                          },
                          "Number": {
                            "type": "string",
                            "description": "The maximum mobile number length is 11 and matches the following regular expression: ^(?:(\\+)|(00)|(\\*)|())\\d{6,14}(#?)$"
                          }
                        }
                      },
                      "Email": {
                        "type": "string",
                        "description": "Required when NotificationOption = ALL or EMAIL. "
                      },
                      "Reference": {
                        "type": "string",
                        "description": "Refers to the order or transaction ID in your own system that you can link with the invoice generated for reporting purposes."
                      },
                      "CivilId": {
                        "type": "string",
                        "description": "Extra customer identification information (optional)."
                      }
                    },
                    "description": "Customer information that will be returned back to you in webhook events."
                  },
                  "IntegrationUrls": {
                    "type": "object",
                    "properties": {
                      "Redirection": {
                        "type": "string",
                        "description": "URL where the user is redirected after making the payment. Use this URL to check the payment status."
                      },
                      "Webhook": {
                        "type": "string",
                        "description": "You will get the webhook events for the created invoice on the specified Webhook URL. This includes transactions webhook, refunds webhook, capture/release webhook. The secret key for this URL will be the same as your webhook URL used in the dashboard.  If you don't add this parameter, MyFatoorah sends the webhook event to the one configured in the dashboard."
                      }
                    },
                    "description": "URLs for redirecting and receiving payment status notifications."
                  },
                  "Language": {
                    "type": "string",
                    "description": "Invoice language.",
                    "enum": [
                      "EN",
                      "AR"
                    ]
                  },
                  "IpAddress": {
                    "type": "string",
                    "description": "The customer’s IP address"
                  },
                  "MetaData": {
                    "type": "object",
                    "properties": {
                      "UDF1": {
                        "type": "string"
                      },
                      "UDF2": {
                        "type": "string"
                      },
                      "UDF3": {
                        "type": "string"
                      },
                      "UDF4": {
                        "type": "string"
                      },
                      "UDF5": {
                        "type": "string"
                      }
                    },
                    "description": "Custom fields stored with the transaction and returned in the webhook."
                  },
                  "DisplayPaymentMethods": {
                    "type": "array",
                    "items": {
                      "type": "string"
                    },
                    "description": "Array of payment methods to be displayed on the invoice page.\nsuch as: [\"card\", \"knet\", \"googlepay\", \"applepay\"]\nIf this parameter is not sent, and the PaymentMethod field is also not provided, all payment methods enabled on your account will be displayed automatically."
                  },
                  "NetworkTransactionId": {
                    "type": "string",
                    "description": "Can be used when paying with network tokens."
                  }
                },
                "required": [
                  "Order"
                ]
              },
              "examples": {
                "Invoice Example": {
                  "summary": "Invoice Example",
                  "value": {
                    "PaymentMethod": "INVOICE",
                    "Order": {
                      "Amount": 10
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  },
  "components": {
    "securitySchemes": {
      "bearerToken": {
        "type": "http",
        "scheme": "bearer",
        "x-default": "SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx"
      }
    }
  },
  "x-readme": {
    "explorer-enabled": true,
    "proxy-enabled": true
  },
  "security": [
    {
      "bearerToken": []
    }
  ]
}
```

## Get Payment Details

*`https://docs.myfatoorah.com/reference/get-payment-details` — updated 2025-12-06*

> Get the details of a payment by its paymentId.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V3"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v3/payments/{paymentId}": {
      "get": {
        "summary": "Get payment details",
        "description": "Get the details of a payment by its paymentId.",
        "operationId": "get-payment-details",
        "tags": [
          "Payments"
        ],
        "parameters": [
          {
            "name": "paymentId",
            "in": "path",
            "required": true,
            "description": "Unique identifier of the payment you want to retrieve.",
            "schema": {
              "type": "string"
            }
          }
        ],
        "responses": {
          "200": {
            "description": "Successful response with payment details",
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "IsSuccess": {
                      "type": "boolean",
                      "description": "Indicates if the request was successful."
                    },
                    "Message": {
                      "type": "string",
                      "description": "Response message associated with the request."
                    },
                    "ValidationErrors": {
                      "type": "array",
                      "items": {
                        "properties": {},
                        "type": "object"
                      },
                      "description": "A model that contains two keys \"Name\" and \"Error\". This is used to indicate the validation result for all parameters you have sent in your request. This can have one or more items based on the invalid parameter count."
                    },
                    "Data": {
                      "type": "object",
                      "description": "Payment details",
                      "properties": {
                        "Invoice": {
                          "type": "object",
                          "description": "Invoice information",
                          "properties": {
                            "Id": {
                              "type": "string",
                              "description": "Unique identifier of the invoice."
                            },
                            "Status": {
                              "type": "string",
                              "description": "Current invoice status (PAID, PENDING, CANCELED)."
                            },
                            "Reference": {
                              "type": "string",
                              "description": "Invoice reference that is generated by MyFatoorah."
                            },
                            "CreationDate": {
                              "type": "string",
                              "format": "date-time",
                              "description": "UTC timestamp when the invoice was created."
                            },
                            "ExpirationDate": {
                              "type": "string",
                              "format": "date-time",
                              "description": "UTC timestamp when the invoice will expire."
                            },
                            "ExternalIdentifier": {
                              "type": "string",
                              "nullable": true,
                              "description": "Optional external reference stored with the invoice."
                            },
                            "UserDefinedField": {
                              "type": "string",
                              "description": "Custom field for additional data."
                            },
                            "MetaData": {
                              "type": "object",
                              "nullable": true,
                              "description": "Optional metadata associated with the invoice."
                            }
                          }
                        },
                        "Transaction": {
                          "type": "object",
                          "description": "Payment transaction details",
                          "properties": {
                            "Id": {
                              "type": "string",
                              "description": "Unique transaction identifier."
                            },
                            "Status": {
                              "type": "string",
                              "description": "Payment transaction status (SUCCESS, FAILED, INPROGRESS, CANCELED, AUTHORIZE)."
                            },
                            "PaymentMethod": {
                              "type": "string",
                              "description": "Payment method used (VISA/MASTER, KNET, APPLEPAY)."
                            },
                            "PaymentId": {
                              "type": "string",
                              "description": "The payment ID that is assigned to this transaction."
                            },
                            "ReferenceId": {
                              "type": "string",
                              "description": "The reference that is generated by the payment gateway."
                            },
                            "TrackId": {
                              "type": "string",
                              "description": "The track number that is used to track the transaction with the gateway."
                            },
                            "AuthorizationId": {
                              "type": "string",
                              "description": "Authorization ID from the payment gateway."
                            },
                            "TransactionDate": {
                              "type": "string",
                              "format": "date-time",
                              "description": "UTC timestamp when the transaction occurred."
                            },
                            "ECI": {
                              "type": "string",
                              "description": "The ECI record of the transaction."
                            },
                            "IP": {
                              "type": "object",
                              "description": "IP address details of the payer",
                              "properties": {
                                "Address": {
                                  "type": "string",
                                  "description": "IP address of the payer."
                                },
                                "Country": {
                                  "type": "string",
                                  "description": "Country of the payer based on IP."
                                }
                              }
                            },
                            "Error": {
                              "type": "object",
                              "description": "Error information if transaction failed",
                              "properties": {
                                "Code": {
                                  "type": "string",
                                  "description": "The MyFatoorah error code."
                                },
                                "Message": {
                                  "type": "string",
                                  "description": "Error message returned by the acquirer bank/platform."
                                }
                              }
                            },
                            "Card": {
                              "type": "object",
                              "description": "Card details used for the transaction",
                              "properties": {
                                "NameOnCard": {
                                  "type": "string",
                                  "description": "Cardholder name."
                                },
                                "Number": {
                                  "type": "string",
                                  "description": "Masked card number (PAN)."
                                },
                                "PanHash": {
                                  "type": "string",
                                  "description": "Hash of the card PAN."
                                },
                                "ExpiryMonth": {
                                  "type": "string",
                                  "description": "Card expiry month."
                                },
                                "ExpiryYear": {
                                  "type": "string",
                                  "description": "Card expiry year."
                                },
                                "Brand": {
                                  "type": "string",
                                  "description": "Card brand (VISA, Mastercard, etc.)."
                                },
                                "Issuer": {
                                  "type": "string",
                                  "description": "Card issuer name."
                                },
                                "IssuerCountry": {
                                  "type": "string",
                                  "description": "Issuer country code."
                                },
                                "FundingMethod": {
                                  "type": "string",
                                  "description": "Card funding method (credit/debit)."
                                },
                                "Token": {
                                  "type": "string",
                                  "description": "Token for the card saved"
                                }
                              }
                            }
                          }
                        },
                        "Customer": {
                          "type": "object",
                          "description": "Customer information",
                          "properties": {
                            "Reference": {
                              "type": "string",
                              "description": "The customer reference associated with the invoice."
                            },
                            "Name": {
                              "type": "string",
                              "description": "Customer name."
                            },
                            "Mobile": {
                              "type": "string",
                              "description": "Customer mobile number."
                            },
                            "Email": {
                              "type": "string",
                              "description": "Customer email."
                            }
                          }
                        },
                        "Amount": {
                          "type": "object",
                          "description": "Amount details of the payment",
                          "properties": {
                            "BaseCurrency": {
                              "type": "string",
                              "description": "Base currency of the invoice."
                            },
                            "ValueInBaseCurrency": {
                              "type": "string",
                              "description": "Amount in base currency."
                            },
                            "ServiceCharge": {
                              "type": "string",
                              "description": "Service charge applied."
                            },
                            "ServiceChargeVAT": {
                              "type": "string",
                              "description": "VAT on the service charge."
                            },
                            "ReceivableAmount": {
                              "type": "string",
                              "description": "Amount receivable after deductions."
                            },
                            "DisplayCurrency": {
                              "type": "string",
                              "description": "Currency used for display."
                            },
                            "ValueInDisplayCurrency": {
                              "type": "string",
                              "description": "Amount in display currency."
                            },
                            "PayCurrency": {
                              "type": "string",
                              "description": "Currency used for payment."
                            },
                            "ValueInPayCurrency": {
                              "type": "string",
                              "description": "Amount in pay currency."
                            }
                          }
                        },
                        "Suppliers": {
                          "type": "array",
                          "description": "List of suppliers in case of multi-vendor transactions",
                          "items": {
                            "properties": {},
                            "type": "object"
                          }
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "OK": {
                    "summary": "OK",
                    "value": {
                      "IsSuccess": true,
                      "Message": "",
                      "ValidationErrors": null,
                      "Data": {
                        "Invoice": {
                          "Id": "6284890",
                          "Status": "PAID",
                          "Reference": "2025001147",
                          "CreationDate": "2025-11-13T10:37:13.5730000Z",
                          "ExpirationDate": "2026-04-12T10:37:13.5730000Z",
                          "ExternalIdentifier": null,
                          "UserDefinedField": "",
                          "MetaData": null
                        },
                        "Transaction": {
                          "Id": "193704",
                          "Status": "SUCCESS",
                          "PaymentMethod": "VISA/MASTER",
                          "PaymentId": "07076284890314778073",
                          "ReferenceId": "531710192684",
                          "TrackId": "13-11-2025_3147780",
                          "AuthorizationId": "192684",
                          "TransactionDate": "2025-11-13T10:37:28.8800000Z",
                          "ECI": "02",
                          "IP": {
                            "Address": "197.32.119.125",
                            "Country": "Egypt"
                          },
                          "Error": {
                            "Code": "",
                            "Message": ""
                          },
                          "Card": {
                            "NameOnCard": "string",
                            "Number": "512345xxxxxx0008",
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
                          "ValueInBaseCurrency": "10",
                          "ServiceCharge": "0.001",
                          "ServiceChargeVAT": "0",
                          "ReceivableAmount": "9.999",
                          "DisplayCurrency": "KWD",
                          "ValueInDisplayCurrency": "10",
                          "PayCurrency": "KWD",
                          "ValueInPayCurrency": "10"
                        },
                        "Suppliers": []
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  },
  "components": {
    "securitySchemes": {
      "bearerToken": {
        "type": "http",
        "scheme": "bearer",
        "x-default": "SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx"
      }
    }
  },
  "x-readme": {
    "explorer-enabled": true,
    "proxy-enabled": true
  },
  "security": [
    {
      "bearerToken": []
    }
  ]
}
```

## Update Payment (Capture or Release)

*`https://docs.myfatoorah.com/reference/update-payment` — updated 2025-11-29*

> Used in the Auth & Capture flow to capture full/partial amount or release the authorized amount back to the customer. Only one Capture or Release operation is allowed for each invoice.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V3"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v3/payments/{paymentId}": {
      "put": {
        "summary": "Update Payment (Capture or Release)",
        "description": "Used in the Auth & Capture flow to capture full/partial amount or release the authorized amount back to the customer. Only one Capture or Release operation is allowed for each invoice.",
        "operationId": "update-payment",
        "tags": [
          "Payments"
        ],
        "responses": {
          "200": {
            "description": "",
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "IsSuccess": {
                      "type": "boolean",
                      "description": "Indicates if the request was successful."
                    },
                    "Message": {
                      "type": "string",
                      "description": "Response message associated with the request."
                    },
                    "ValidationErrors": {
                      "type": "array",
                      "items": {
                        "properties": {},
                        "type": "object"
                      },
                      "description": "A model that contains two keys \"Name\" and \"Error\". This is used to indicate the validation result for all parameters you have sent in your request. This can have one or more items based on the invalid parameter count."
                    },
                    "Data": {
                      "type": "object",
                      "description": "Payment details",
                      "properties": {
                        "Invoice": {
                          "type": "object",
                          "description": "Invoice information",
                          "properties": {
                            "Id": {
                              "type": "string",
                              "description": "Unique identifier of the invoice."
                            },
                            "Status": {
                              "type": "string",
                              "description": "Current invoice status (PAID, PENDING, CANCELED)."
                            },
                            "Reference": {
                              "type": "string",
                              "description": "Invoice reference that is generated by MyFatoorah."
                            },
                            "CreationDate": {
                              "type": "string",
                              "format": "date-time",
                              "description": "UTC timestamp when the invoice was created."
                            },
                            "ExpirationDate": {
                              "type": "string",
                              "format": "date-time",
                              "description": "UTC timestamp when the invoice will expire."
                            },
                            "ExternalIdentifier": {
                              "type": "string",
                              "nullable": true,
                              "description": "Optional external reference stored with the invoice."
                            },
                            "UserDefinedField": {
                              "type": "string",
                              "description": "Custom field for additional data."
                            },
                            "MetaData": {
                              "type": "object",
                              "nullable": true,
                              "description": "Optional metadata associated with the invoice."
                            }
                          }
                        },
                        "Transaction": {
                          "type": "object",
                          "description": "Payment transaction details",
                          "properties": {
                            "Id": {
                              "type": "string",
                              "description": "Unique transaction identifier."
                            },
                            "Status": {
                              "type": "string",
                              "description": "Payment transaction status (SUCCESS, FAILED, INPROGRESS, CANCELED, AUTHORIZE)."
                            },
                            "PaymentMethod": {
                              "type": "string",
                              "description": "Payment method used (VISA/MASTER, KNET, APPLEPAY)."
                            },
                            "PaymentId": {
                              "type": "string",
                              "description": "The payment ID that is assigned to this transaction."
                            },
                            "ReferenceId": {
                              "type": "string",
                              "description": "The reference that is generated by the payment gateway."
                            },
                            "TrackId": {
                              "type": "string",
                              "description": "The track number that is used to track the transaction with the gateway."
                            },
                            "AuthorizationId": {
                              "type": "string",
                              "description": "Authorization ID from the payment gateway."
                            },
                            "TransactionDate": {
                              "type": "string",
                              "format": "date-time",
                              "description": "UTC timestamp when the transaction occurred."
                            },
                            "ECI": {
                              "type": "string",
                              "description": "The ECI record of the transaction."
                            },
                            "IP": {
                              "type": "object",
                              "description": "IP address details of the payer",
                              "properties": {
                                "Address": {
                                  "type": "string",
                                  "description": "IP address of the payer."
                                },
                                "Country": {
                                  "type": "string",
                                  "description": "Country of the payer based on IP."
                                }
                              }
                            },
                            "Error": {
                              "type": "object",
                              "description": "Error information if transaction failed",
                              "properties": {
                                "Code": {
                                  "type": "string",
                                  "description": "The MyFatoorah error code."
                                },
                                "Message": {
                                  "type": "string",
                                  "description": "Error message returned by the acquirer bank/platform."
                                }
                              }
                            },
                            "Card": {
                              "type": "object",
                              "description": "Card details used for the transaction",
                              "properties": {
                                "NameOnCard": {
                                  "type": "string",
                                  "description": "Cardholder name."
                                },
                                "Number": {
                                  "type": "string",
                                  "description": "Masked card number (PAN)."
                                },
                                "PanHash": {
                                  "type": "string",
                                  "description": "Hash of the card PAN."
                                },
                                "ExpiryMonth": {
                                  "type": "string",
                                  "description": "Card expiry month."
                                },
                                "ExpiryYear": {
                                  "type": "string",
                                  "description": "Card expiry year."
                                },
                                "Brand": {
                                  "type": "string",
                                  "description": "Card brand (VISA, Mastercard, etc.)."
                                },
                                "Issuer": {
                                  "type": "string",
                                  "description": "Card issuer name."
                                },
                                "IssuerCountry": {
                                  "type": "string",
                                  "description": "Issuer country code."
                                },
                                "FundingMethod": {
                                  "type": "string",
                                  "description": "Card funding method (credit/debit)."
                                }
                              }
                            }
                          }
                        },
                        "Customer": {
                          "type": "object",
                          "description": "Customer information",
                          "properties": {
                            "Reference": {
                              "type": "string",
                              "description": "The customer reference associated with the invoice."
                            },
                            "Name": {
                              "type": "string",
                              "description": "Customer name."
                            },
                            "Mobile": {
                              "type": "string",
                              "description": "Customer mobile number."
                            },
                            "Email": {
                              "type": "string",
                              "description": "Customer email."
                            }
                          }
                        },
                        "Amount": {
                          "type": "object",
                          "description": "Amount details of the payment",
                          "properties": {
                            "BaseCurrency": {
                              "type": "string",
                              "description": "Base currency of the invoice."
                            },
                            "ValueInBaseCurrency": {
                              "type": "string",
                              "description": "Amount in base currency."
                            },
                            "ServiceCharge": {
                              "type": "string",
                              "description": "Service charge applied."
                            },
                            "ServiceChargeVAT": {
                              "type": "string",
                              "description": "VAT on the service charge."
                            },
                            "ReceivableAmount": {
                              "type": "string",
                              "description": "Amount receivable after deductions."
                            },
                            "DisplayCurrency": {
                              "type": "string",
                              "description": "Currency used for display."
                            },
                            "ValueInDisplayCurrency": {
                              "type": "string",
                              "description": "Amount in display currency."
                            },
                            "PayCurrency": {
                              "type": "string",
                              "description": "Currency used for payment."
                            },
                            "ValueInPayCurrency": {
                              "type": "string",
                              "description": "Amount in pay currency."
                            }
                          }
                        },
                        "Suppliers": {
                          "type": "array",
                          "description": "List of suppliers in case of multi-vendor transactions",
                          "items": {
                            "properties": {},
                            "type": "object"
                          }
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "RELEASE Response": {
                    "summary": "RELEASE Response",
                    "value": {
                      "IsSuccess": true,
                      "Message": "",
                      "ValidationErrors": null,
                      "Data": {
                        "Invoice": {
                          "Id": "6309667",
                          "Status": "PENDING",
                          "Reference": "2025001220",
                          "CreationDate": "2025-11-22T20:49:34.3170000Z",
                          "ExpirationDate": "2026-04-21T20:49:34.3170000Z",
                          "ExternalIdentifier": null,
                          "UserDefinedField": "",
                          "MetaData": null
                        },
                        "Transaction": {
                          "Id": "07076309667316789374",
                          "Status": "CANCELED",
                          "PaymentMethod": "VISA/MASTER",
                          "PaymentId": "07076309667316789374",
                          "ReferenceId": "07076309667316789374",
                          "TrackId": "22-11-2025_3167893",
                          "AuthorizationId": "07076309667316789374",
                          "TransactionDate": "2025-11-22T20:50:36.1911594Z",
                          "ECI": "",
                          "IP": {
                            "Address": "",
                            "Country": ""
                          },
                          "Error": {
                            "Code": "",
                            "Message": "Transaction Released"
                          },
                          "Card": {
                            "NameOnCard": "test test",
                            "Number": "512345xxxxxx0008",
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
                          "ValueInBaseCurrency": "10",
                          "ServiceCharge": "0.001",
                          "ServiceChargeVAT": "0",
                          "ReceivableAmount": "9.999",
                          "DisplayCurrency": "KWD",
                          "ValueInDisplayCurrency": "10",
                          "PayCurrency": "KWD",
                          "ValueInPayCurrency": "10"
                        },
                        "Suppliers": []
                      }
                    }
                  },
                  "CAPTURE Response": {
                    "summary": "CAPTURE Response",
                    "value": {
                      "IsSuccess": true,
                      "Message": "",
                      "ValidationErrors": null,
                      "Data": {
                        "Invoice": {
                          "Id": "6309664",
                          "Status": "PAID",
                          "Reference": "2025001219",
                          "CreationDate": "2025-11-22T20:47:21.1670000Z",
                          "ExpirationDate": "2026-04-21T20:47:21.1670000Z",
                          "ExternalIdentifier": null,
                          "UserDefinedField": "",
                          "MetaData": null
                        },
                        "Transaction": {
                          "Id": "243686",
                          "Status": "SUCCESS",
                          "PaymentMethod": "VISA/MASTER",
                          "PaymentId": "07076309664316788974",
                          "ReferenceId": "532620242606",
                          "TrackId": "22-11-2025_3167889",
                          "AuthorizationId": "242606",
                          "TransactionDate": "2025-11-22T20:48:26.3470244Z",
                          "ECI": "02",
                          "IP": {
                            "Address": "",
                            "Country": ""
                          },
                          "Error": {
                            "Code": "",
                            "Message": ""
                          },
                          "Card": {
                            "NameOnCard": "test test",
                            "Number": "512345xxxxxx0008",
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
                          "ValueInBaseCurrency": "10",
                          "ServiceCharge": "0.001",
                          "ServiceChargeVAT": "0",
                          "ReceivableAmount": "9.999",
                          "DisplayCurrency": "KWD",
                          "ValueInDisplayCurrency": "10",
                          "PayCurrency": "KWD",
                          "ValueInPayCurrency": "10"
                        },
                        "Suppliers": []
                      }
                    }
                  }
                }
              }
            }
          }
        },
        "parameters": [
          {
            "in": "path",
            "name": "paymentId",
            "schema": {
              "type": "string"
            },
            "required": true
          }
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "OperationType": {
                    "type": "string",
                    "description": "Operation type to execute. Use CAPTURE to capture the authorized amount (fully or partially) or RELEASE to return the full amount to the customer.",
                    "enum": [
                      "CAPTURE",
                      "RELEASE"
                    ]
                  },
                  "Amount": {
                    "type": "number",
                    "description": "Amount to be captured. Required only when OperationType is CAPTURE."
                  }
                },
                "required": [
                  "OperationType"
                ]
              },
              "example": {
                "OperationType": "CAPTURE",
                "Amount": 10
              }
            }
          }
        }
      }
    }
  },
  "components": {
    "securitySchemes": {
      "bearerToken": {
        "type": "http",
        "scheme": "bearer",
        "x-default": "SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx"
      }
    }
  },
  "x-readme": {
    "explorer-enabled": true,
    "proxy-enabled": true
  },
  "security": [
    {
      "bearerToken": []
    }
  ]
}
```

## Create Session

*`https://docs.myfatoorah.com/reference/create-session` — updated 2025-11-29*

> Creates a session for embedded payment flows (COLLECT_DETAILS or COMPLETE_PAYMENT).

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V3"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v3/sessions": {
      "post": {
        "summary": "Create Session",
        "description": "Creates a session for embedded payment flows (COLLECT_DETAILS or COMPLETE_PAYMENT).",
        "operationId": "create-session",
        "tags": [
          "Sessions"
        ],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "PaymentMode": {
                    "type": "string",
                    "description": "Defines session mode. COLLECT_DETAILS or COMPLETE_PAYMENT",
                    "enum": [
                      "COMPLETE_PAYMENT",
                      "COLLECT_DETAILS"
                    ],
                    "default": "COMPLETE_PAYMENT"
                  },
                  "Order": {
                    "type": "object",
                    "properties": {
                      "Amount": {
                        "type": "number",
                        "description": "The payment amount must be greater than 0.",
                        "default": "10"
                      },
                      "Currency": {
                        "type": "string",
                        "description": "The currency ISO code you want to display to the customer, by default is the same as the base currency of the country API.",
                        "default": "",
                        "enum": [
                          "SAR",
                          "BHD",
                          "AED",
                          "QAR",
                          "OMR",
                          "KWD",
                          "JOD",
                          "EGP"
                        ]
                      },
                      "ExternalIdentifier": {
                        "type": "string",
                        "description": "You may use as additional information to be stored with the transaction."
                      }
                    },
                    "description": "Order information related to the payment..",
                    "required": [
                      "Amount"
                    ]
                  },
                  "SupportedNetworks": {
                    "type": "array",
                    "items": {
                      "type": "string"
                    },
                    "description": "Specifies the allowed card networks for the session such as: [\"visa\", \"masterCard\", \"mada\", \"amex\"]."
                  },
                  "SaveCardOptions": {
                    "type": "object",
                    "description": "Controls card tokenization behavior.",
                    "properties": {
                      "SaveToken": {
                        "type": "boolean",
                        "description": "If true, saves the card as a token after successful payment."
                      },
                      "ShowSavedCardsInCardView": {
                        "type": "boolean",
                        "description": "If true, saved cards will appear in the embedded card view."
                      },
                      "RetrieveSavedTokens": {
                        "type": "boolean",
                        "description": "If true, retrieves the customer’s previously saved tokens."
                      }
                    }
                  },
                  "SupportedPaymentMethods": {
                    "type": "array",
                    "items": {
                      "type": "string"
                    },
                    "description": "Payment methods to show in the embedded view such as: [\"card\", \"knet\", \"googlepay\", \"applepay\"]\nIf not sent, all payment methods enabled on your account will appear automatically."
                  },
                  "SessionExpiry": {
                    "type": "string",
                    "format": "date-time",
                    "description": "The session expiration date. Must be in UTC timezone."
                  },
                  "ThreeDS": {
                    "type": "object",
                    "properties": {
                      "Enabled": {
                        "type": "boolean",
                        "description": "If true, the user will be required to complete the OTP step.",
                        "default": ""
                      }
                    },
                    "description": "Enables or disables 3D Secure authentication."
                  },
                  "OperationType": {
                    "type": "string",
                    "default": "",
                    "enum": [
                      "AUTHORIZE",
                      "PAY",
                      "VERIFY"
                    ],
                    "description": "Defines the type of payment operation."
                  },
                  "Suppliers": {
                    "type": "array",
                    "description": "Required only if Multi-Vendor feature is enabled.",
                    "items": {
                      "properties": {
                        "SupplierCode": {
                          "type": "integer",
                          "description": "The supplier code you need to associate the invoice with."
                        },
                        "ProposedDepositShare": {
                          "type": "number",
                          "description": "The amount that the supplier will get after paying the invoice."
                        },
                        "InvoiceShare": {
                          "type": "number",
                          "description": "Amount specified for this supplier from the total invoice value."
                        }
                      },
                      "type": "object"
                    }
                  },
                  "Customer": {
                    "type": "object",
                    "properties": {
                      "Name": {
                        "type": "string",
                        "description": "Customer's name."
                      },
                      "Mobile": {
                        "type": "object",
                        "properties": {
                          "CountryCode": {
                            "type": "string",
                            "description": "The maximum country code length is 4 and match the following regular expression: ^(?:(\\+)|(00)|(\\*)|())\\d{1,4}$"
                          },
                          "Number": {
                            "type": "string",
                            "description": "The maximum mobile number length is 11 and matches the following regular expression: ^(?:(\\+)|(00)|(\\*)|())\\d{6,14}(#?)$"
                          }
                        }
                      },
                      "Email": {
                        "type": "string",
                        "description": "Customer's email address."
                      },
                      "Reference": {
                        "type": "string",
                        "description": "Refers to the order or transaction ID in your own system that you can link with the invoice generated for reporting purposes."
                      },
                      "CivilId": {
                        "type": "string",
                        "description": "Extra customer identification information (optional)."
                      }
                    },
                    "description": "Customer information that will be returned back to you in webhook events."
                  },
                  "IntegrationUrls": {
                    "type": "object",
                    "properties": {
                      "Redirection": {
                        "type": "string",
                        "description": "URL where the user is redirected after making the payment. Use this URL to check the payment status."
                      },
                      "Webhook": {
                        "type": "string",
                        "description": "You will get the webhook events for the created invoice on the specified Webhook URL. This includes transactions webhook, refunds webhook, capture/release webhook. The secret key for this URL will be the same as your webhook URL used in the dashboard.  If you don't add this parameter, MyFatoorah sends the webhook event to the one configured in the dashboard."
                      }
                    },
                    "description": "URLs for redirecting and receiving payment status notifications."
                  },
                  "Language": {
                    "type": "string",
                    "description": "Invoice language.",
                    "enum": [
                      "EN",
                      "AR"
                    ]
                  },
                  "MetaData": {
                    "type": "object",
                    "properties": {
                      "UDF1": {
                        "type": "string"
                      },
                      "UDF2": {
                        "type": "string"
                      },
                      "UDF3": {
                        "type": "string"
                      },
                      "UDF4": {
                        "type": "string"
                      },
                      "UDF5": {
                        "type": "string"
                      }
                    },
                    "description": "Custom fields stored with the transaction and returned in the webhook."
                  }
                },
                "required": [
                  "PaymentMode",
                  "Order"
                ]
              },
              "examples": {
                "Create Session Example": {
                  "summary": "Create Session Example",
                  "value": {
                    "PaymentMode": "COMPLETE_PAYMENT",
                    "Order": {
                      "Amount": 10
                    }
                  }
                }
              }
            }
          }
        },
        "responses": {
          "201": {
            "description": "Session created successfully",
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "IsSuccess": {
                      "type": "boolean",
                      "description": "Indicates if the request was successful."
                    },
                    "Message": {
                      "type": "string",
                      "description": "Response message associated with the request."
                    },
                    "ValidationErrors": {
                      "type": "array",
                      "items": {
                        "properties": {},
                        "type": "object"
                      },
                      "description": "A model that contains two keys \"Name\" and \"Error\". This is used to indicate the validation result for all parameters you have sent in your request. This can have one or more items based on the invalid parameter count."
                    },
                    "Data": {
                      "type": "object",
                      "description": "Session details.",
                      "properties": {
                        "SessionId": {
                          "type": "string",
                          "description": "Unique identifier of the session used in embedded integration."
                        },
                        "SessionExpiry": {
                          "type": "string",
                          "description": "Timestamp indicating when the session will expire."
                        },
                        "EncryptionKey": {
                          "type": "string",
                          "description": "Key used to decrypt the payment result returned in the callback when using Embedded integration (COMPLETE_PAYMENT Mode)."
                        },
                        "OperationType": {
                          "type": "string",
                          "description": "The operation associated with the created session."
                        },
                        "Order": {
                          "type": "object",
                          "description": "Order details associated with the session.",
                          "properties": {
                            "Amount": {
                              "type": "number",
                              "description": "Order amount submitted in the session."
                            },
                            "Currency": {
                              "type": "string",
                              "description": "Currency code submitted in the session."
                            },
                            "ExternalIdentifier": {
                              "type": "string",
                              "description": "External identifier submitted in the session."
                            }
                          }
                        },
                        "Customer": {
                          "type": "object",
                          "description": "Customer information returned with the session.",
                          "properties": {
                            "Reference": {
                              "type": "string",
                              "description": "Customer reference that was passed during session creation."
                            },
                            "Cards": {
                              "type": "array",
                              "description": "List of token cards associated with the customer.",
                              "items": {
                                "type": "object",
                                "properties": {
                                  "Is3DSVerified": {
                                    "type": "boolean",
                                    "description": "Indicates if the card has been verified with 3D Secure."
                                  },
                                  "Token": {
                                    "type": "string",
                                    "description": "Token representing the saved card."
                                  },
                                  "Number": {
                                    "type": "string",
                                    "description": "Masked card number (PAN)."
                                  },
                                  "Brand": {
                                    "type": "string",
                                    "description": "Card brand (VISA, MasterCard, etc.)."
                                  }
                                }
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  },
  "components": {
    "securitySchemes": {
      "bearerToken": {
        "type": "http",
        "scheme": "bearer",
        "x-default": "SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx"
      }
    }
  },
  "x-readme": {
    "explorer-enabled": true,
    "proxy-enabled": true
  },
  "security": [
    {
      "bearerToken": []
    }
  ]
}
```

## Get Session Details

*`https://docs.myfatoorah.com/reference/get-session-details` — updated 2025-11-29*

> Retrieves detailed information about a specific session, including its status, transaction results, and card details.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V3"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v3/sessions/{sessionId}": {
      "get": {
        "summary": "Get Session Details",
        "description": "Retrieves detailed information about a specific session, including its status, transaction results, and card details.",
        "operationId": "get-session-details",
        "tags": [
          "Sessions"
        ],
        "parameters": [
          {
            "name": "sessionId",
            "in": "path",
            "required": true,
            "description": "The unique identifier of the session to retrieve.",
            "schema": {
              "type": "string"
            }
          }
        ],
        "responses": {
          "200": {
            "description": "Session details retrieved successfully",
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "IsSuccess": {
                      "type": "boolean",
                      "description": "Indicates if the request was successful."
                    },
                    "Message": {
                      "type": "string",
                      "description": "Response message associated with the request."
                    },
                    "ValidationErrors": {
                      "type": "array",
                      "items": {
                        "type": "object",
                        "properties": {
                          "Name": {
                            "type": "string",
                            "description": "The name of the field with validation error."
                          },
                          "Error": {
                            "type": "string",
                            "description": "The validation error message."
                          }
                        }
                      },
                      "description": "List of validation errors, if any."
                    },
                    "Data": {
                      "type": "object",
                      "description": "Session details and transaction information.",
                      "properties": {
                        "SessionExpiry": {
                          "type": "string",
                          "description": "The expiration timestamp of the session."
                        },
                        "IsUsed": {
                          "type": "boolean",
                          "description": "Indicates whether the session has been used for a transaction."
                        },
                        "OperationType": {
                          "type": "string",
                          "description": "The type of operation (PAY, AUTHORIZE, VERIFY)."
                        },
                        "Order": {
                          "type": "object",
                          "description": "Order details associated with the session.",
                          "properties": {
                            "Amount": {
                              "type": "number",
                              "description": "The order amount."
                            },
                            "Currency": {
                              "type": "string",
                              "description": "The currency code."
                            },
                            "ExternalIdentifier": {
                              "type": "string",
                              "description": "External identifier for the order."
                            }
                          }
                        },
                        "Customer": {
                          "type": "object",
                          "description": "Customer information associated with the session.",
                          "properties": {
                            "Reference": {
                              "type": "string",
                              "description": "Customer reference identifier."
                            }
                          }
                        },
                        "Card": {
                          "type": "object",
                          "description": "Card details used in the session.",
                          "properties": {
                            "Number": {
                              "type": "string",
                              "description": "Masked card number."
                            },
                            "ExpiryMonth": {
                              "type": "string",
                              "description": "Card expiration month."
                            },
                            "ExpiryYear": {
                              "type": "string",
                              "description": "Card expiration year."
                            },
                            "Brand": {
                              "type": "string",
                              "description": "Card brand (VISA, MasterCard, etc.)."
                            },
                            "PanType": {
                              "type": "string",
                              "description": "Type of card PAN."
                            },
                            "Issuer": {
                              "type": "string",
                              "description": "Card issuer name."
                            },
                            "PanHash": {
                              "type": "string",
                              "description": "Hashed PAN value."
                            },
                            "Token": {
                              "type": "string",
                              "description": "Tokenized card identifier."
                            },
                            "NameOnCard": {
                              "type": "string",
                              "description": "Name as it appears on the card."
                            },
                            "IssuerCountry": {
                              "type": "string",
                              "description": "Country of the card issuer."
                            },
                            "FundingMethod": {
                              "type": "string",
                              "description": "Funding method (Credit/Debit)."
                            },
                            "ProductName": {
                              "type": "string",
                              "description": "Card product name."
                            },
                            "First8digit": {
                              "type": "string",
                              "description": "First 8 digits of the card number (BIN)."
                            },
                            "Is3DSVerified": {
                              "type": "boolean",
                              "description": "Indicates if 3DS verification was performed."
                            },
                            "IsLocalCard": {
                              "type": "boolean",
                              "description": "Indicates if the card is a local card."
                            }
                          }
                        },
                        "TransactionResult": {
                          "type": "object",
                          "description": "Transaction result details if the session was used.",
                          "properties": {
                            "Invoice": {
                              "type": "object",
                              "description": "Invoice information.",
                              "properties": {
                                "Id": {
                                  "type": "string",
                                  "description": "Invoice unique identifier."
                                },
                                "Status": {
                                  "type": "string",
                                  "description": "Invoice status."
                                },
                                "Reference": {
                                  "type": "string",
                                  "description": "Invoice reference number."
                                },
                                "CreationDate": {
                                  "type": "string",
                                  "description": "Invoice creation date."
                                },
                                "ExpirationDate": {
                                  "type": "string",
                                  "description": "Invoice expiration date."
                                },
                                "ExternalIdentifier": {
                                  "type": "string",
                                  "description": "External identifier for the invoice."
                                },
                                "UserDefinedField": {
                                  "type": "string",
                                  "description": "User defined field value."
                                },
                                "MetaData": {
                                  "type": "object",
                                  "description": "Custom metadata associated with the invoice.",
                                  "properties": {
                                    "UDF1": {
                                      "type": "string"
                                    },
                                    "UDF2": {
                                      "type": "string"
                                    },
                                    "UDF3": {
                                      "type": "string"
                                    },
                                    "UDF4": {
                                      "type": "string"
                                    },
                                    "UDF5": {
                                      "type": "string"
                                    }
                                  }
                                }
                              }
                            },
                            "Transaction": {
                              "type": "object",
                              "description": "Transaction details.",
                              "properties": {
                                "Id": {
                                  "type": "string",
                                  "description": "Transaction unique identifier."
                                },
                                "Status": {
                                  "type": "string",
                                  "description": "Transaction status."
                                },
                                "PaymentMethod": {
                                  "type": "string",
                                  "description": "Payment method used."
                                },
                                "PaymentId": {
                                  "type": "string",
                                  "description": "Payment identifier."
                                },
                                "ReferenceId": {
                                  "type": "string",
                                  "description": "Reference identifier for the transaction."
                                },
                                "TrackId": {
                                  "type": "string",
                                  "description": "Tracking identifier."
                                },
                                "AuthorizationId": {
                                  "type": "string",
                                  "description": "Authorization identifier from payment gateway."
                                },
                                "TransactionDate": {
                                  "type": "string",
                                  "description": "Transaction date and time."
                                },
                                "ECI": {
                                  "type": "string",
                                  "description": "Electronic Commerce Indicator."
                                },
                                "IP": {
                                  "type": "object",
                                  "description": "IP address information.",
                                  "properties": {
                                    "Address": {
                                      "type": "string",
                                      "description": "IP address of the customer."
                                    },
                                    "Country": {
                                      "type": "string",
                                      "description": "Country associated with the IP address."
                                    }
                                  }
                                },
                                "Error": {
                                  "type": "object",
                                  "description": "Error information if transaction failed.",
                                  "properties": {
                                    "Code": {
                                      "type": "string",
                                      "description": "Error code."
                                    },
                                    "Message": {
                                      "type": "string",
                                      "description": "Error message."
                                    }
                                  }
                                },
                                "Card": {
                                  "type": "object",
                                  "description": "Card information used in the transaction."
                                }
                              }
                            },
                            "Customer": {
                              "type": "object",
                              "description": "Customer information.",
                              "properties": {
                                "Reference": {
                                  "type": "string",
                                  "description": "Customer reference identifier."
                                },
                                "Name": {
                                  "type": "string",
                                  "description": "Customer name."
                                },
                                "Mobile": {
                                  "type": "string",
                                  "description": "Customer mobile number."
                                },
                                "Email": {
                                  "type": "string",
                                  "description": "Customer email address."
                                }
                              }
                            },
                            "Amount": {
                              "type": "object",
                              "description": "Amount details for the transaction.",
                              "properties": {
                                "BaseCurrency": {
                                  "type": "string",
                                  "description": "Base currency code."
                                },
                                "ValueInBaseCurrency": {
                                  "type": "string",
                                  "description": "Transaction value in base currency."
                                },
                                "ServiceCharge": {
                                  "type": "string",
                                  "description": "Service charge amount."
                                },
                                "ServiceChargeVAT": {
                                  "type": "string",
                                  "description": "VAT on service charge."
                                },
                                "ReceivableAmount": {
                                  "type": "string",
                                  "description": "Total receivable amount."
                                },
                                "DisplayCurrency": {
                                  "type": "string",
                                  "description": "Display currency code."
                                },
                                "ValueInDisplayCurrency": {
                                  "type": "string",
                                  "description": "Transaction value in display currency."
                                },
                                "PayCurrency": {
                                  "type": "string",
                                  "description": "Payment currency code."
                                },
                                "ValueInPayCurrency": {
                                  "type": "string",
                                  "description": "Transaction value in payment currency."
                                }
                              }
                            },
                            "Suppliers": {
                              "type": "array",
                              "description": "Supplier information for multi-vendor transactions.",
                              "items": {
                                "type": "object",
                                "properties": {
                                  "Code": {
                                    "type": "integer",
                                    "description": "Supplier code."
                                  },
                                  "Name": {
                                    "type": "string",
                                    "description": "Supplier name."
                                  },
                                  "InvoiceShare": {
                                    "type": "string",
                                    "description": "Supplier's share of the invoice."
                                  },
                                  "ProposedShare": {
                                    "type": "string",
                                    "description": "Proposed share for the supplier."
                                  },
                                  "DepositShare": {
                                    "type": "string",
                                    "description": "Deposit share for the supplier."
                                  }
                                }
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "Used Session Response": {
                    "summary": "Used Session Response",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Created Successfully!",
                      "ValidationErrors": null,
                      "Data": {
                        "SessionExpiry": "2025-11-23T00:52:16.8334906+00:00",
                        "IsUsed": true,
                        "OperationType": "PAY",
                        "Order": {
                          "Amount": 20,
                          "Currency": "KWD",
                          "ExternalIdentifier": null
                        },
                        "Customer": {
                          "Reference": null
                        },
                        "Card": null,
                        "TransactionResult": {
                          "Invoice": {
                            "Id": "6309731",
                            "Status": "PAID",
                            "Reference": "2025001221",
                            "CreationDate": "2025-11-22T22:52:52.7430000Z",
                            "ExpirationDate": "2025-11-23T00:52:16.0000000Z",
                            "ExternalIdentifier": null,
                            "UserDefinedField": "",
                            "MetaData": null
                          },
                          "Transaction": {
                            "Id": "240517",
                            "Status": "SUCCESS",
                            "PaymentMethod": "VISA/MASTER",
                            "PaymentId": "07076309731316792373",
                            "ReferenceId": "532622239437",
                            "TrackId": "23-11-2025_3167923",
                            "AuthorizationId": "239437",
                            "TransactionDate": "2025-11-22T22:53:11.6993214Z",
                            "ECI": "02",
                            "IP": {
                              "Address": "197.32.67.183",
                              "Country": "Egypt"
                            },
                            "Error": {
                              "Code": "",
                              "Message": ""
                            },
                            "Card": {
                              "NameOnCard": "test test",
                              "Number": "512345xxxxxx0008",
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
                    }
                  },
                  "Unused Session Response": {
                    "summary": "Unused Session Response",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Created Successfully!",
                      "ValidationErrors": null,
                      "Data": {
                        "SessionExpiry": "2025-11-23T00:27:01.1874521+00:00",
                        "IsUsed": false,
                        "OperationType": "PAY",
                        "Order": {
                          "Amount": 20,
                          "Currency": "KWD",
                          "ExternalIdentifier": null
                        },
                        "Customer": {
                          "Reference": null
                        },
                        "Card": null,
                        "TransactionResult": null
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  },
  "components": {
    "securitySchemes": {
      "bearerToken": {
        "type": "http",
        "scheme": "bearer",
        "x-default": "SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx"
      }
    }
  },
  "x-readme": {
    "explorer-enabled": true,
    "proxy-enabled": true
  },
  "security": [
    {
      "bearerToken": []
    }
  ]
}
```

## Get Invoice by InvoiceId

*`https://docs.myfatoorah.com/reference/get-invoice-by-invoiceid` — updated 2026-03-03*

> 🚧 Note
>
> If the invoice doesn't exist OR the invoice exists but has no transactions, the API will return the "Message": "No invoices match this InvoiceId".

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V3"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v3/invoices/{InvoiceId}": {
      "get": {
        "description": "",
        "responses": {
          "200": {
            "description": "",
            "content": {
              "application/json": {
                "examples": {
                  "OK": {
                    "summary": "OK",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Invoice Retrieved Successfully",
                      "ValidationErrors": null,
                      "Data": {
                        "Invoice": {
                          "Id": "6551972",
                          "Status": "PAID",
                          "Reference": "2026042650",
                          "CreationDate": "2026-03-01T08:03:35.1670000Z",
                          "ExpirationDate": "2026-03-04T08:03:35.1670000Z",
                          "ExternalIdentifier": "externalID",
                          "UserDefinedField": "",
                          "MetaData": null
                        },
                        "Transactions": [
                          {
                            "Id": "7723522335956390803813",
                            "Status": "SUCCESS",
                            "PaymentMethod": "VISA/MASTER",
                            "PaymentId": "07076551972335088674",
                            "ReferenceId": "7723522335956390803813",
                            "TrackId": "01-03-2026_3350886",
                            "AuthorizationId": "831000",
                            "TransactionDate": "2026-03-01T08:03:54.7700000Z",
                            "ECI": "02",
                            "IP": {
                              "Address": "197.193.7.57",
                              "Country": "Egypt"
                            },
                            "Error": {
                              "Code": "",
                              "Message": ""
                            },
                            "Card": {
                              "NameOnCard": "test test",
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
                          }
                        ],
                        "Customer": {
                          "Name": "Anonymous",
                          "Mobile": "+965",
                          "Email": ""
                        },
                        "Amount": {
                          "BaseCurrency": "KWD",
                          "ValueInBaseCurrency": "10",
                          "ServiceCharge": "0.11",
                          "ServiceChargeVAT": "0.016",
                          "ReceivableAmount": "9.874",
                          "DisplayCurrency": "KWD",
                          "ValueInDisplayCurrency": "10",
                          "PayCurrency": "KWD",
                          "ValueInPayCurrency": "10"
                        },
                        "Suppliers": []
                      }
                    }
                  }
                }
              }
            }
          }
        },
        "parameters": [
          {
            "in": "path",
            "name": "InvoiceId",
            "schema": {
              "type": "string",
              "default": "6551972"
            },
            "required": true
          }
        ],
        "operationId": "get_v3-invoices-invoiceid"
      }
    }
  },
  "components": {
    "securitySchemes": {
      "bearerToken": {
        "type": "http",
        "scheme": "bearer",
        "x-default": "SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx"
      }
    }
  },
  "x-readme": {
    "explorer-enabled": true,
    "proxy-enabled": true
  },
  "security": [
    {
      "bearerToken": []
    }
  ]
}
```

## Get Invoice by ExternalIdentifier

*`https://docs.myfatoorah.com/reference/get-invoice-by-externalidentifier` — updated 2026-03-03*

> 🚧 Note
>
> * If multiple invoices exist with the same ExternalIdentifier, the API will return data for the recent invoice only.
> * If the latest invoice doesn't contain any transactions, the API response will include  "Message": "No invoices match this External Identifier".

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V3"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v3/invoices/externalIdentifier/{externalIdentifier}": {
      "get": {
        "description": "",
        "responses": {
          "200": {
            "description": "",
            "content": {
              "application/json": {
                "examples": {
                  "OK": {
                    "summary": "OK",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Invoice Retrieved Successfully",
                      "ValidationErrors": null,
                      "Data": {
                        "Invoice": {
                          "Id": "6551997",
                          "Status": "PAID",
                          "Reference": "2026042663",
                          "CreationDate": "2026-03-01T08:20:29.2630000Z",
                          "ExpirationDate": "2026-03-04T08:20:29.2630000Z",
                          "ExternalIdentifier": "testExternal",
                          "UserDefinedField": "",
                          "MetaData": null
                        },
                        "Transactions": [
                          {
                            "Id": "7723532468256428203814",
                            "Status": "SUCCESS",
                            "PaymentMethod": "VISA/MASTER",
                            "PaymentId": "07076551997335090173",
                            "ReferenceId": "7723532468256428203814",
                            "TrackId": "01-03-2026_3350901",
                            "AuthorizationId": "831000",
                            "TransactionDate": "2026-03-01T08:20:47.8900000Z",
                            "ECI": "02",
                            "IP": {
                              "Address": "197.193.7.57",
                              "Country": "Egypt"
                            },
                            "Error": {
                              "Code": "",
                              "Message": ""
                            },
                            "Card": {
                              "NameOnCard": "test test",
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
                          }
                        ],
                        "Customer": {
                          "Name": "Anonymous",
                          "Mobile": "+965",
                          "Email": ""
                        },
                        "Amount": {
                          "BaseCurrency": "KWD",
                          "ValueInBaseCurrency": "10",
                          "ServiceCharge": "0.11",
                          "ServiceChargeVAT": "0.016",
                          "ReceivableAmount": "9.874",
                          "DisplayCurrency": "KWD",
                          "ValueInDisplayCurrency": "10",
                          "PayCurrency": "KWD",
                          "ValueInPayCurrency": "10"
                        },
                        "Suppliers": []
                      }
                    }
                  }
                }
              }
            }
          }
        },
        "parameters": [
          {
            "in": "path",
            "name": "externalIdentifier",
            "schema": {
              "type": "string",
              "default": "testExternal"
            },
            "required": true
          }
        ],
        "operationId": "get_v3-invoices-externalidentifier-externalidentifier"
      }
    }
  },
  "components": {
    "securitySchemes": {
      "bearerToken": {
        "type": "http",
        "scheme": "bearer",
        "x-default": "SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx"
      }
    }
  },
  "x-readme": {
    "explorer-enabled": true,
    "proxy-enabled": true
  },
  "security": [
    {
      "bearerToken": []
    }
  ]
}
```

## Get Customer Details

*`https://docs.myfatoorah.com/reference/get-customer-details` — updated 2025-12-24*

> Retrieves the details of a customer including the cards saved with his reference.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V3"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v3/customers/{Reference}": {
      "get": {
        "summary": "Copy of Get Session Details",
        "description": "Retrieves detailed information about a specific session, including its status, transaction results, and card details.",
        "operationId": "get_v3sessions{sessionId}-1",
        "tags": [
          "Sessions"
        ],
        "parameters": [
          {
            "in": "path",
            "name": "Reference",
            "schema": {
              "type": "string"
            },
            "required": true
          }
        ],
        "responses": {
          "200": {
            "description": "Session details retrieved successfully",
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "IsSuccess": {
                      "type": "boolean",
                      "description": "Indicates if the request was successful."
                    },
                    "Message": {
                      "type": "string",
                      "description": "Response message associated with the request."
                    },
                    "ValidationErrors": {
                      "type": "array",
                      "items": {
                        "type": "object",
                        "properties": {
                          "Name": {
                            "type": "string",
                            "description": "The name of the field with validation error."
                          },
                          "Error": {
                            "type": "string",
                            "description": "The validation error message."
                          }
                        }
                      },
                      "description": "List of validation errors, if any."
                    },
                    "Data": {
                      "type": "object",
                      "description": "Session details and transaction information.",
                      "properties": {
                        "Reference": {
                          "type": "string",
                          "description": "The Customer Reference"
                        },
                        "Cards": {
                          "type": "array",
                          "description": "Array of the card object",
                          "items": {
                            "properties": {
                              "Is3DSVerified": {
                                "type": "boolean",
                                "description": "Whether or not the card was 3DS verified before"
                              },
                              "Token": {
                                "type": "string",
                                "description": "The token value to be used"
                              },
                              "Number": {
                                "type": "string",
                                "description": "Masked card number"
                              },
                              "Brand": {
                                "type": "string",
                                "description": "The brand of the tokenized card"
                              },
                              "TokenType": {
                                "type": "string",
                                "description": "The type of the token to be for CARD or APPLE_PAY"
                              }
                            },
                            "type": "object"
                          }
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "OK": {
                    "summary": "OK",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Initiated Successfully!",
                      "ValidationErrors": null,
                      "Data": {
                        "Reference": "MYREFERENC IS COOL",
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
                }
              }
            }
          }
        }
      }
    }
  },
  "components": {
    "securitySchemes": {
      "bearerToken": {
        "type": "http",
        "scheme": "bearer",
        "x-default": "SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx"
      }
    }
  },
  "x-readme": {
    "explorer-enabled": true,
    "proxy-enabled": true
  },
  "security": [
    {
      "bearerToken": []
    }
  ]
}
```
