# MyFatoorah — API reference — V2

## SendPayment

*`https://docs.myfatoorah.com/reference/send-payment` — updated 2026-04-21*

> Creates a MyFatoorah invoice. This endpoint generates an invoice link that can be sent to customers via email, SMS, or returned directly in the response.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/SendPayment": {
      "post": {
        "summary": "SendPayment",
        "description": "Creates a MyFatoorah invoice. This endpoint generates an invoice link that can be sent to customers via email, SMS, or returned directly in the response.",
        "operationId": "send-payment",
        "tags": [
          "Payments (V2)"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "CustomerName",
                  "NotificationOption",
                  "InvoiceValue"
                ],
                "properties": {
                  "CustomerName": {
                    "type": "string",
                    "description": "Your customer name that will be displayed during the checkout.",
                    "default": "John Doe"
                  },
                  "InvoiceValue": {
                    "type": "number",
                    "description": "The amount you are seeking to charge the customer and accepts decimal values. Must be greater than 0.",
                    "default": "10"
                  },
                  "NotificationOption": {
                    "type": "string",
                    "enum": [
                      "EML",
                      "SMS",
                      "LNK",
                      "ALL"
                    ],
                    "description": "EML: sends the invoice link by email only (requires CustomerEmail). SMS: sends the invoice link by SMS (requires CustomerMobile). LNK: returns the invoice link through the response body only. ALL: sends the invoice link by Email and SMS, also returns the invoice link through the response body.",
                    "default": "LNK"
                  },
                  "DisplayCurrencyIso": {
                    "type": "string",
                    "description": "The currency ISO code you want to display to the customer, by default it is the same as the base currency of the country API. Possible values: KWD, SAR, BHD, AED, QAR, OMR, JOD, EGP.",
                    "enum": [
                      "KWD",
                      "SAR",
                      "BHD",
                      "AED",
                      "QAR",
                      "OMR",
                      "JOD",
                      "EGP"
                    ]
                  },
                  "MobileCountryCode": {
                    "type": "string",
                    "description": "Customer mobile number country code (e.g., +965 for Kuwait)."
                  },
                  "CustomerMobile": {
                    "type": "string",
                    "description": "Customer mobile number. Mandatory if NotificationOption is SMS or ALL. String uses English letters ONLY and does not accept Arabic characters. Its length is between 0 and 11. Pattern: ^(?:(+)|(00)|(*)|())[0-9]{3,14}((#)|())$"
                  },
                  "CustomerEmail": {
                    "type": "string",
                    "description": "Customer email that will get the invoice URL. Mandatory if NotificationOption is EML or ALL."
                  },
                  "Language": {
                    "type": "string",
                    "enum": [
                      "EN",
                      "AR"
                    ],
                    "description": "EN: to display the checkout page in English. AR: to display the checkout page in Arabic."
                  },
                  "CustomerReference": {
                    "type": "string",
                    "description": "Refers to the order or transaction ID in your own system that you can link with the invoice generated for reporting purposes."
                  },
                  "CustomerCivilId": {
                    "type": "string",
                    "description": "Your customer civil ID that you can associate with the transaction if needed."
                  },
                  "UserDefinedField": {
                    "type": "string",
                    "description": "A custom field that you may use as additional information to be stored with the transaction. If using save card characters 'CK-', max length is 50 chars. Otherwise, maximum length is 500."
                  },
                  "CallBackUrl": {
                    "type": "string",
                    "description": "The return URL you like to have for successful payment. The localhost is not allowed to be set as a domain. Maximum 254 characters."
                  },
                  "ErrorUrl": {
                    "type": "string",
                    "description": "The return URL in case of a failed payment or any exception raised during the payment. The localhost is not allowed. Maximum 254 characters."
                  },
                  "CustomerAddress": {
                    "type": "object",
                    "properties": {
                      "Block": {
                        "type": "string",
                        "description": "Block number or area name that contains the delivery address."
                      },
                      "Street": {
                        "type": "string",
                        "description": "Delivery address street name."
                      },
                      "HouseBuildingNo": {
                        "type": "string",
                        "description": "House / Building number."
                      },
                      "AddressInstructions": {
                        "type": "string",
                        "description": "Additional instructions for the delivery address, landmark or directions."
                      }
                    }
                  },
                  "ExpiryDate": {
                    "type": "string",
                    "format": "date-time",
                    "description": "The date you want the invoice link to expire. If not passed, the default is considered from the account profile in the portal."
                  },
                  "InvoiceItems": {
                    "type": "array",
                    "description": "Array of invoice items. Note: The InvoiceValue should equal the total sum of (UnitPrice × Quantity) for all items.",
                    "items": {
                      "type": "object",
                      "required": [
                        "ItemName",
                        "Quantity",
                        "UnitPrice"
                      ],
                      "properties": {
                        "ItemName": {
                          "type": "string",
                          "description": "Invoice item name that will be displayed in the invoice."
                        },
                        "Quantity": {
                          "type": "integer",
                          "description": "Item quantity."
                        },
                        "UnitPrice": {
                          "type": "number",
                          "description": "Item unit price."
                        },
                        "Weight": {
                          "type": "number",
                          "description": "Weight in kg. Must be between 0 and 100."
                        },
                        "Width": {
                          "type": "number",
                          "description": "Width in cm. Must be between 0 and 200."
                        },
                        "Height": {
                          "type": "number",
                          "description": "Height in cm. Must be between 0 and 160."
                        },
                        "Depth": {
                          "type": "number",
                          "description": "Depth in cm. Must be between 0 and 200."
                        }
                      }
                    }
                  },
                  "ShippingMethod": {
                    "type": "integer",
                    "enum": [
                      1,
                      2
                    ],
                    "description": "1: for DHL, 2: for ARAMEX."
                  },
                  "ShippingConsignee": {
                    "type": "object",
                    "description": "Mandatory if you are creating a Shipping invoice.",
                    "required": [
                      "PersonName",
                      "Mobile",
                      "LineAddress",
                      "CityName",
                      "CountryCode"
                    ],
                    "properties": {
                      "PersonName": {
                        "type": "string",
                        "description": "Consignee person name."
                      },
                      "Mobile": {
                        "type": "string",
                        "description": "Consignee mobile number."
                      },
                      "EmailAddress": {
                        "type": "string",
                        "format": "email",
                        "description": "Consignee email address."
                      },
                      "LineAddress": {
                        "type": "string",
                        "description": "Consignee line address."
                      },
                      "CityName": {
                        "type": "string",
                        "description": "Consignee city name."
                      },
                      "PostalCode": {
                        "type": "string",
                        "description": "Consignee postal code."
                      },
                      "CountryCode": {
                        "type": "string",
                        "description": "Consignee country code."
                      }
                    }
                  },
                  "Suppliers": {
                    "type": "array",
                    "description": "Mandatory only if you are using the Multi-Vendors feature.",
                    "items": {
                      "type": "object",
                      "required": [
                        "SupplierCode",
                        "InvoiceShare"
                      ],
                      "properties": {
                        "SupplierCode": {
                          "type": "integer",
                          "description": "The supplier code you need to associate the invoice with."
                        },
                        "ProposedShare": {
                          "type": "number",
                          "description": "The amount that the supplier will get after paying the invoice."
                        },
                        "InvoiceShare": {
                          "type": "number",
                          "description": "Amount specified for this supplier from the total invoice value."
                        }
                      }
                    }
                  },
                  "ProcessingDetails": {
                    "type": "object",
                    "description": "Used for payment methods that have the authorization and capture feature enabled.",
                    "properties": {
                      "AutoCapture": {
                        "type": "boolean",
                        "description": "true: To make the capture process directly without authorization. false: To make the authorization step first, then call UpdatePaymentStatus to capture/release the amount."
                      }
                    }
                  },
                  "InvoicePaymentMethods": {
                    "type": "array",
                    "description": "Enter the PaymentMethodId for the payment methods you want to display on the invoice. You can find the PaymentMethodId from InitiatePayment endpoint.",
                    "items": {
                      "type": "integer"
                    }
                  },
                  "WebhookUrl": {
                    "type": "string",
                    "description": "You will get the webhook events for the created invoice on the specified Webhook URL. This includes transactions webhook, refunds webhook, capture/release webhook. The secret key for this URL will be the same as your webhook URL used in the dashboard. If you don't add this parameter, MyFatoorah sends the webhook event to the one configured in the dashboard."
                  }
                }
              },
              "examples": {
                "Basic Invoice": {
                  "summary": "Basic Invoice Example",
                  "value": {
                    "CustomerName": "John Doe",
                    "NotificationOption": "LNK",
                    "InvoiceValue": 100
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Invoice created successfully",
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
                      "properties": {
                        "InvoiceId": {
                          "type": "integer",
                          "description": "The invoice number that you can use to inquire the invoice payment status later."
                        },
                        "InvoiceURL": {
                          "type": "string",
                          "format": "uri",
                          "description": "The URL that should be sent to the customer to proceed with the payment."
                        },
                        "CustomerReference": {
                          "type": "string",
                          "description": "Refers to the order or transaction ID in your system that you have sent in the request."
                        },
                        "UserDefinedField": {
                          "type": "string",
                          "description": "The custom field that you have passed in the request."
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "Success Response": {
                    "summary": "Successful Invoice Creation",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Invoice Created Successfully!",
                      "ValidationErrors": null,
                      "Data": {
                        "InvoiceId": 6345386,
                        "InvoiceURL": "https://demo.MyFatoorah.com/KWT/ie/01072634538642-1732339e",
                        "CustomerReference": null,
                        "UserDefinedField": null
                      }
                    }
                  }
                }
              }
            }
          },
          "400": {
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {}
                },
                "examples": {
                  "Validation Error Response": {
                    "summary": "Validation Error Response",
                    "value": {
                      "IsSuccess": false,
                      "Message": "Invalid data",
                      "ValidationErrors": [
                        {
                          "Name": "InvoiceValue",
                          "Error": "Invoice value must be more than 0"
                        }
                      ],
                      "Data": null
                    }
                  }
                }
              }
            },
            "description": "Bad Request"
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

## InitiatePayment

*`https://docs.myfatoorah.com/reference/initiate-payment` — updated 2026-04-21*

> Retrieves all available and enabled payment methods of your portal account with the commission charge that the customer may pay on the gateway. This endpoint does not execute or store any payment information, it just gets the proper information based on your MyFatoorah profile and calculates the service charges.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/InitiatePayment": {
      "post": {
        "summary": "InitiatePayment",
        "description": "Retrieves all available and enabled payment methods of your portal account with the commission charge that the customer may pay on the gateway. This endpoint does not execute or store any payment information, it just gets the proper information based on your MyFatoorah profile and calculates the service charges.",
        "operationId": "initiate-payment",
        "tags": [
          "Payments (V2)"
        ],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "InvoiceAmount": {
                    "type": "number",
                    "description": "The transaction amount you need to charge your customer after applying coupon codes, taxes, fare updates, and so on.",
                    "default": 100
                  },
                  "CurrencyIso": {
                    "type": "string",
                    "description": "The currency code that you need to charge your customer through. MyFatoorah will calculate the total amount of the invoice for this currency after applying the exchange rate.",
                    "enum": [
                      "KWD",
                      "SAR",
                      "BHD",
                      "AED",
                      "QAR",
                      "OMR",
                      "JOD",
                      "EGP"
                    ],
                    "default": "KWD"
                  }
                }
              },
              "examples": {
                "Basic Request": {
                  "summary": "InitiatePayment Example",
                  "value": {
                    "InvoiceAmount": 100,
                    "CurrencyIso": "KWD"
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Payment methods retrieved successfully",
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
                      "properties": {
                        "PaymentMethods": {
                          "type": "array",
                          "description": "List of available payment methods with their details and service charges.",
                          "items": {
                            "type": "object",
                            "properties": {
                              "PaymentMethodId": {
                                "type": "integer",
                                "description": "The payment method ID that is used in MyFatoorah system to identify the method of payment."
                              },
                              "PaymentMethodAr": {
                                "type": "string",
                                "description": "Payment method Arabic name."
                              },
                              "PaymentMethodEn": {
                                "type": "string",
                                "description": "Payment method English name."
                              },
                              "PaymentMethodCode": {
                                "type": "string",
                                "description": "Code that refers to the payment method."
                              },
                              "IsDirectPayment": {
                                "type": "boolean",
                                "description": "Indicates if this payment method supports Direct Payment."
                              },
                              "ServiceCharge": {
                                "type": "number",
                                "description": "The transaction service charges based on the settings in the portal profile."
                              },
                              "TotalAmount": {
                                "type": "number",
                                "description": "The total amount that you should send before calling Execute Payment including the service charges after calculating it."
                              },
                              "CurrencyIso": {
                                "type": "string",
                                "description": "The currency code that you have sent in CurrencyIso."
                              },
                              "ImageUrl": {
                                "type": "string",
                                "description": "Payment icon URL that you can use to display it in your page for the customers."
                              },
                              "IsEmbeddedSupported": {
                                "type": "boolean",
                                "description": "Indicates if this payment method supports Embedded Payment."
                              },
                              "PaymentCurrencyIso": {
                                "type": "string",
                                "description": "The currency of the gateway in which the payment will be made."
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "Success Response": {
                    "summary": "Successful Response with Payment Methods",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Initiated Successfully!",
                      "ValidationErrors": null,
                      "Data": {
                        "PaymentMethods": [
                          {
                            "PaymentMethodId": 11,
                            "PaymentMethodAr": "أبل الدفع",
                            "PaymentMethodEn": "Apple Pay",
                            "PaymentMethodCode": "ap",
                            "IsDirectPayment": false,
                            "ServiceCharge": 0.03,
                            "TotalAmount": 100,
                            "CurrencyIso": "KWD",
                            "ImageUrl": "https://demo.myfatoorah.com/imgs/payment-methods/ap.png",
                            "IsEmbeddedSupported": true,
                            "PaymentCurrencyIso": "KWD"
                          },
                          {
                            "PaymentMethodId": 1,
                            "PaymentMethodAr": "كي نت",
                            "PaymentMethodEn": "KNET",
                            "PaymentMethodCode": "kn",
                            "IsDirectPayment": false,
                            "ServiceCharge": 0.02,
                            "TotalAmount": 100,
                            "CurrencyIso": "KWD",
                            "ImageUrl": "https://demo.myfatoorah.com/imgs/payment-methods/kn.png",
                            "IsEmbeddedSupported": false,
                            "PaymentCurrencyIso": "KWD"
                          },
                          {
                            "PaymentMethodId": 2,
                            "PaymentMethodAr": "فيزا / ماستر",
                            "PaymentMethodEn": "VISA/MASTER",
                            "PaymentMethodCode": "vm",
                            "IsDirectPayment": false,
                            "ServiceCharge": 0.01,
                            "TotalAmount": 100,
                            "CurrencyIso": "KWD",
                            "ImageUrl": "https://demo.myfatoorah.com/imgs/payment-methods/vm.png",
                            "IsEmbeddedSupported": true,
                            "PaymentCurrencyIso": "KWD"
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

## InitiateSession

*`https://docs.myfatoorah.com/reference/initiate-session` — updated 2026-04-21*

> Initiates a session for Embedded Integration

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/InitiateSession": {
      "post": {
        "summary": "InitiateSession",
        "description": "Initiates a session for Embedded Integration",
        "operationId": "initiate-session",
        "tags": [
          "Payments (V2)"
        ],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "CustomerIdentifier": {
                    "type": "string",
                    "description": "Must be unique for each of your customers. This identifier is used to retrieve saved tokens."
                  },
                  "SaveToken": {
                    "type": "boolean",
                    "description": "If you need to tokenize card and use it for future charges. Note: Tokenization feature must be enabled for your account",
                    "default": false
                  },
                  "IsRecurring": {
                    "type": "boolean",
                    "description": "If you need to create payment with standard recurring.",
                    "default": false
                  }
                }
              },
              "examples": {
                "Basic Session": {
                  "summary": "Basic Session Example",
                  "value": {}
                },
                "Session with Tokenization": {
                  "summary": "Session with Save Token",
                  "value": {
                    "CustomerIdentifier": "customer_12345",
                    "SaveToken": true
                  }
                },
                "Recurring Session": {
                  "summary": "Recurring Payment Session",
                  "value": {
                    "IsRecurring": true
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Session initiated successfully",
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
                      "properties": {
                        "SessionId": {
                          "type": "string",
                          "description": "The unique session identifier"
                        },
                        "CountryCode": {
                          "type": "string",
                          "description": "The country code associated with your account."
                        },
                        "CustomerTokens": {
                          "type": "array",
                          "description": "List of saved payment tokens for the customer.",
                          "items": {
                            "type": "object",
                            "properties": {
                              "Token": {
                                "type": "string",
                                "description": "The tokenized card for future payments."
                              },
                              "CardNumber": {
                                "type": "string",
                                "description": "Masked card number (512345xxxxxx0008)."
                              },
                              "CardBrand": {
                                "type": "string",
                                "description": "The card brand (Visa, Master, Amex)."
                              },
                              "Is3DSVerified": {
                                "type": "boolean",
                                "description": "Indicates if the card has been verified with 3D Secure."
                              },
                              "TokenType": {
                                "type": "string",
                                "description": "The type of token."
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "Success Response": {
                    "summary": "Success Response",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Session created successfully",
                      "ValidationErrors": null,
                      "Data": {
                        "SessionId": "81234567890abcdef1234567",
                        "CountryCode": "KWT",
                        "CustomerTokens": []
                      }
                    }
                  },
                  "Customer With Saved Tokens": {
                    "summary": "Customer With Saved Tokens",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Initiated Successfully!",
                      "ValidationErrors": null,
                      "Data": {
                        "SessionId": "6d1794bb-551c-4598-a4a9-9a0c15b1f5af",
                        "CountryCode": "KWT",
                        "CustomerTokens": [
                          {
                            "Token": "TKN-7c257e39-ac48-426a-8c0c-a63c32e76a03",
                            "CardNumber": "512345xxxxxx0008",
                            "CardBrand": "Master",
                            "Is3DSVerified": true,
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

## ExecutePayment

*`https://docs.myfatoorah.com/reference/execute-payment` — updated 2026-04-21*

> Creates a MyFatoorah invoice against a certain gateway. This endpoint processes the payment with a specific payment method and returns the payment URL where the customer should be redirected to complete the payment.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/ExecutePayment": {
      "post": {
        "summary": "ExecutePayment",
        "description": "Creates a MyFatoorah invoice against a certain gateway. This endpoint processes the payment with a specific payment method and returns the payment URL where the customer should be redirected to complete the payment.",
        "operationId": "execute-payment",
        "tags": [
          "Payments (V2)"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "InvoiceValue"
                ],
                "properties": {
                  "InvoiceValue": {
                    "type": "number",
                    "description": "The amount you are seeking to charge the customer and accepts decimal values (e.g., 2.500).",
                    "default": 10
                  },
                  "PaymentMethodId": {
                    "type": "integer",
                    "description": "The payment method ID (e.g., 2 for VISA/MASTER). Required if SessionId is not provided. You can get this from InitiatePayment endpoint."
                  },
                  "SessionId": {
                    "type": "string",
                    "description": "Session ID used for Embedded Payment integration. Required if PaymentMethodId is not provided. Note: Only one of PaymentMethodId and SessionId is required."
                  },
                  "CustomerName": {
                    "type": "string",
                    "description": "Your customer name that should be displayed during the checkout."
                  },
                  "CallBackUrl": {
                    "type": "string",
                    "description": "The return URL in case of a successful payment. The localhost is not allowed to be set as a domain. Maximum 254 characters."
                  },
                  "ErrorUrl": {
                    "type": "string",
                    "description": "The return URL in case of a failed payment or any exception raised during the payment. The localhost is not allowed to be set as a domain. Maximum 254 characters."
                  },
                  "DisplayCurrencyIso": {
                    "type": "string",
                    "description": "The currency ISO code you want to display to the customer, by default is the same as the base currency of the country API.",
                    "enum": [
                      "KWD",
                      "SAR",
                      "BHD",
                      "AED",
                      "QAR",
                      "OMR",
                      "JOD",
                      "EGP"
                    ]
                  },
                  "MobileCountryCode": {
                    "type": "string",
                    "description": "Customer mobile number country code (e.g., +965 for Kuwait)."
                  },
                  "CustomerMobile": {
                    "type": "string",
                    "description": "Customer mobile number. String uses English letters ONLY and does not accept Arabic characters. Its length is between 0 and 11. Pattern: ^(?:(+)|(00)|(*)|())[0-9]{3,14}((#)|())$"
                  },
                  "CustomerEmail": {
                    "type": "string",
                    "description": "Customer email address."
                  },
                  "Language": {
                    "type": "string",
                    "enum": [
                      "EN",
                      "AR"
                    ],
                    "description": "EN: to display the checkout page in English. AR: to display the checkout page in Arabic."
                  },
                  "CustomerReference": {
                    "type": "string",
                    "description": "Refers to the order or transaction ID in your own system and you can use it for payment inquiry as well."
                  },
                  "CustomerCivilId": {
                    "type": "string",
                    "description": "Your customer civil ID that you can associate with the transaction if needed."
                  },
                  "UserDefinedField": {
                    "type": "string",
                    "description": "A custom field that you may use as additional information to be stored with the transaction. Maximum length is 500."
                  },
                  "CustomerAddress": {
                    "type": "object",
                    "properties": {
                      "Block": {
                        "type": "string",
                        "description": "Block number or area name that contains the delivery address."
                      },
                      "Street": {
                        "type": "string",
                        "description": "Delivery address street name."
                      },
                      "HouseBuildingNo": {
                        "type": "string",
                        "description": "House / Building number."
                      },
                      "Address": {
                        "type": "string",
                        "description": "Full address details."
                      },
                      "AddressInstructions": {
                        "type": "string",
                        "description": "Additional instructions for the delivery address, landmark or directions."
                      }
                    }
                  },
                  "ExpiryDate": {
                    "type": "string",
                    "format": "date-time",
                    "description": "The date you want the payment to expire. If not passed, the default is considered from the account profile in the portal."
                  },
                  "InvoiceItems": {
                    "type": "array",
                    "description": "Array of invoice items. Note: The InvoiceValue should equal the total sum of (UnitPrice * Quantity) for all items.",
                    "items": {
                      "type": "object",
                      "required": [
                        "ItemName",
                        "Quantity",
                        "UnitPrice"
                      ],
                      "properties": {
                        "ItemName": {
                          "type": "string",
                          "description": "Invoice item name that will be displayed in the invoice."
                        },
                        "Quantity": {
                          "type": "integer",
                          "description": "Item quantity."
                        },
                        "UnitPrice": {
                          "type": "number",
                          "description": "Item unit price."
                        },
                        "Weight": {
                          "type": "number",
                          "description": "Weight in kg. Must be between 0 and 100."
                        },
                        "Width": {
                          "type": "number",
                          "description": "Width in cm. Must be between 0 and 200."
                        },
                        "Height": {
                          "type": "number",
                          "description": "Height in cm. Must be between 0 and 160."
                        },
                        "Depth": {
                          "type": "number",
                          "description": "Depth in cm. Must be between 0 and 200."
                        }
                      }
                    }
                  },
                  "ShippingMethod": {
                    "type": "integer",
                    "enum": [
                      1,
                      2
                    ],
                    "description": "1: for DHL, 2: for ARAMEX."
                  },
                  "ShippingConsignee": {
                    "type": "object",
                    "description": "Mandatory if you are creating a Shipping invoice.",
                    "required": [
                      "PersonName",
                      "Mobile",
                      "LineAddress",
                      "CityName",
                      "CountryCode"
                    ],
                    "properties": {
                      "PersonName": {
                        "type": "string",
                        "description": "Consignee person name."
                      },
                      "Mobile": {
                        "type": "string",
                        "description": "Consignee mobile number."
                      },
                      "EmailAddress": {
                        "type": "string",
                        "description": "Consignee email address."
                      },
                      "LineAddress": {
                        "type": "string",
                        "description": "Consignee line address."
                      },
                      "CityName": {
                        "type": "string",
                        "description": "Consignee city name."
                      },
                      "PostalCode": {
                        "type": "string",
                        "description": "Consignee postal code."
                      },
                      "CountryCode": {
                        "type": "string",
                        "description": "Consignee country code."
                      }
                    }
                  },
                  "Suppliers": {
                    "type": "array",
                    "description": "Mandatory only if you are using the Multi-Vendors feature.",
                    "items": {
                      "type": "object",
                      "required": [
                        "SupplierCode",
                        "InvoiceShare"
                      ],
                      "properties": {
                        "SupplierCode": {
                          "type": "integer",
                          "description": "The supplier code you need to associate the invoice with."
                        },
                        "ProposedShare": {
                          "type": "number",
                          "description": "The amount that the supplier will get after paying the invoice.( net value you need to be deposited to the supplier without any fees or commissions, this will override the InvoiceShare if both are sent )"
                        },
                        "InvoiceShare": {
                          "type": "number",
                          "description": "Amount specified for this supplier from the total invoice value."
                        }
                      }
                    }
                  },
                  "RecurringModel": {
                    "type": "object",
                    "description": "Configuration for recurring payments.",
                    "properties": {
                      "RecurringType": {
                        "type": "string",
                        "enum": [
                          "Custom",
                          "Daily",
                          "Weekly",
                          "Monthly"
                        ],
                        "description": "Defines the interval time of charging the customer again with the same amount. Possible values: Custom, Daily, Weekly, Monthly."
                      },
                      "IntervalDays": {
                        "type": "integer",
                        "description": "Valid for 'Custom' RecurringType. Must be between 1 and 180 days."
                      },
                      "Iteration": {
                        "type": "integer",
                        "description": "Determines how many times you will charge the customer for your services."
                      },
                      "RetryCount": {
                        "type": "integer",
                        "description": "Optional parameter. Accepts integer values between 1 and 5. Used in case of any failure recurring, to retry paying the same failed invoice till it paid or the count reset to zero."
                      }
                    }
                  },
                  "ProcessingDetails": {
                    "type": "object",
                    "description": "Used for Non3DS and AutoCapture features.",
                    "properties": {
                      "AutoCapture": {
                        "type": "boolean",
                        "description": "true: To make the capture process directly without authorization. false: To make the authorization step first, then call UpdatePaymentStatus to capture/release the amount. Note: The Authorization & Capture feature must be enabled on your account to use this."
                      },
                      "Bypass3DS": {
                        "type": "boolean",
                        "description": "true: To bypass 3DS challenge. false: To redirect the customer to the 3DS challenge. Note: The Bypass3DS feature must be enabled on your account to use this."
                      }
                    }
                  },
                  "WebhookUrl": {
                    "type": "string",
                    "description": "You will get the webhook events for the created invoice on the specified Webhook URL. This includes transactions webhook, refunds webhook, capture/release webhook. The secret key for this URL will be the same as your webhook URL used in the dashboard. If you don't add this parameter, MyFatoorah sends the webhook event to the one configured in the dashboard."
                  }
                }
              },
              "examples": {
                "Basic Payment": {
                  "summary": "Basic Payment with PaymentMethodId",
                  "value": {
                    "PaymentMethodId": 2,
                    "CustomerName": "John Doe",
                    "InvoiceValue": 10,
                    "CallBackUrl": "https://yoursite.com/success",
                    "ErrorUrl": "https://yoursite.com/error"
                  }
                },
                "Complete Payment": {
                  "summary": "Complete Payment Example",
                  "value": {
                    "PaymentMethodId": 1,
                    "CustomerName": "fname lname",
                    "DisplayCurrencyIso": "KWD",
                    "MobileCountryCode": "+965",
                    "CustomerMobile": "12345678",
                    "CustomerEmail": "mail@company.com",
                    "InvoiceValue": 10,
                    "CallBackUrl": "https://yoursite.com/success",
                    "ErrorUrl": "https://yoursite.com/error",
                    "Language": "AR",
                    "CustomerReference": "noshipping-nosupplier",
                    "CustomerAddress": {
                      "Block": "string",
                      "Street": "string",
                      "HouseBuildingNo": "string",
                      "AddressInstructions": "string"
                    },
                    "InvoiceItems": [
                      {
                        "ItemName": "item name",
                        "Quantity": 10,
                        "UnitPrice": 1,
                        "Weight": 2,
                        "Width": 3,
                        "Height": 4,
                        "Depth": 5
                      }
                    ]
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Payment executed successfully",
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
                      "properties": {
                        "InvoiceId": {
                          "type": "integer",
                          "description": "The invoice number that you can use to inquire about the invoice payment status later."
                        },
                        "IsDirectPayment": {
                          "type": "boolean",
                          "description": "Indicates if this URL is for Direct Payment method."
                        },
                        "PaymentURL": {
                          "type": "string",
                          "description": "The URL that you should redirect the customer to OR submit the card details to process the payment."
                        },
                        "CustomerReference": {
                          "type": "string",
                          "description": "Refers to the order or transaction ID in your system that you have sent in the request."
                        },
                        "UserDefinedField": {
                          "type": "string",
                          "description": "The custom field that you have passed in the request."
                        },
                        "RecurringId": {
                          "type": "string",
                          "description": "If you set the RecurringModel, the system will return the RecurringId value."
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "Success Response": {
                    "summary": "Successful Payment Execution",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Invoice Created Successfully!",
                      "ValidationErrors": null,
                      "Data": {
                        "InvoiceId": 6424703,
                        "IsDirectPayment": false,
                        "PaymentURL": "https://demo.MyFatoorah.com/En/KWT/PayInvoice/Checkout?invoiceKey=01072642470341-8defa47a&paymentGatewayId=2168",
                        "CustomerReference": "noshipping-nosupplier",
                        "UserDefinedField": null,
                        "RecurringId": ""
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

## GetPaymentStatus

*`https://docs.myfatoorah.com/reference/get-payment-status` — updated 2026-04-21*

> Checks the status of an invoice to verify whether the payment has been completed successfully. This endpoint allows you to inquire about payment status using InvoiceId, PaymentId, or CustomerReference.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/GetPaymentStatus": {
      "post": {
        "summary": "GetPaymentStatus",
        "description": "Checks the status of an invoice to verify whether the payment has been completed successfully. This endpoint allows you to inquire about payment status using InvoiceId, PaymentId, or CustomerReference.",
        "operationId": "get-payment-status",
        "tags": [
          "Payments (V2)"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "Key",
                  "KeyType"
                ],
                "properties": {
                  "Key": {
                    "type": "string",
                    "description": "Refers to the invoice number or paymentId based on the key type."
                  },
                  "KeyType": {
                    "type": "string",
                    "enum": [
                      "InvoiceId",
                      "PaymentId",
                      "CustomerReference"
                    ],
                    "description": "InvoiceId: Refers to the invoice number generated by MyFatoorah. PaymentId: The value returned upon having any update on the invoice payment (recommended). CustomerReference: The reference used to link your orders in the store."
                  }
                }
              },
              "examples": {
                "InvoiceId Request": {
                  "summary": "Query by Invoice ID",
                  "value": {
                    "Key": "6424733",
                    "KeyType": "InvoiceId"
                  }
                },
                "PaymentId Request": {
                  "summary": "Query by Payment ID (Recommended)",
                  "value": {
                    "Key": "07076424733325055272",
                    "KeyType": "PaymentId"
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Payment status retrieved successfully",
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
                            "type": "string"
                          },
                          "Error": {
                            "type": "string"
                          }
                        }
                      },
                      "description": "List of validation errors, if any."
                    },
                    "Data": {
                      "type": "object",
                      "properties": {
                        "InvoiceId": {
                          "type": "number",
                          "description": "The invoice ID that was used in the inquiry call."
                        },
                        "InvoiceStatus": {
                          "type": "string",
                          "enum": [
                            "Pending",
                            "Paid",
                            "Canceled"
                          ],
                          "description": "Status of the invoice."
                        },
                        "InvoiceReference": {
                          "type": "string",
                          "description": "Invoice reference generated by MyFatoorah."
                        },
                        "CustomerReference": {
                          "type": "string",
                          "description": "The customer reference data associated with the invoice."
                        },
                        "CreatedDate": {
                          "type": "string",
                          "format": "date-time",
                          "description": "The creation date of the invoice."
                        },
                        "ExpiryDate": {
                          "type": "string",
                          "format": "date-time",
                          "description": "The expiry date for the invoice."
                        },
                        "InvoiceValue": {
                          "type": "number",
                          "description": "Invoice total value with the actual base account currency."
                        },
                        "Comments": {
                          "type": "string",
                          "description": "Comments associated with the invoice."
                        },
                        "CustomerName": {
                          "type": "string",
                          "description": "The customer name saved along with the invoice."
                        },
                        "CustomerMobile": {
                          "type": "string",
                          "description": "Customer mobile number."
                        },
                        "CustomerEmail": {
                          "type": "string",
                          "description": "Customer email address."
                        },
                        "UserDefinedField": {
                          "type": "string",
                          "description": "The user defined field stored during invoice creation."
                        },
                        "InvoiceDisplayValue": {
                          "type": "string",
                          "description": "Invoice value displayed in case of different currency from the base one."
                        },
                        "DueDeposit": {
                          "type": "number",
                          "description": "The amount that will be deposited to the vendor."
                        },
                        "DepositStatus": {
                          "type": "string",
                          "description": "The deposit status of the invoice (Deposited or Not Deposited)."
                        },
                        "InvoiceItems": {
                          "type": "array",
                          "items": {
                            "type": "object",
                            "properties": {
                              "ItemName": {
                                "type": "string",
                                "description": "Invoice item name stored with the invoice."
                              },
                              "Quantity": {
                                "type": "integer",
                                "description": "Item quantity."
                              },
                              "UnitPrice": {
                                "type": "number",
                                "description": "Item unit price."
                              },
                              "Weight": {
                                "type": "number",
                                "description": "Weight in kg (100 >= Weight > 0)."
                              },
                              "Width": {
                                "type": "number",
                                "description": "Width in cm (200 >= Width > 0)."
                              },
                              "Height": {
                                "type": "number",
                                "description": "Height in cm (160 >= Height > 0)."
                              },
                              "Depth": {
                                "type": "number",
                                "description": "Depth in cm (200 >= Depth > 0)."
                              }
                            }
                          },
                          "description": "Array of invoice items."
                        },
                        "InvoiceTransactions": {
                          "type": "array",
                          "items": {
                            "type": "object",
                            "properties": {
                              "TransactionDate": {
                                "type": "string",
                                "format": "date-time",
                                "description": "The date of the transaction related to the invoice."
                              },
                              "PaymentGateway": {
                                "type": "string",
                                "description": "The gateway the transaction was processed through."
                              },
                              "ReferenceId": {
                                "type": "string",
                                "description": "The reference generated by the payment gateway."
                              },
                              "TrackId": {
                                "type": "string",
                                "description": "The track number used to track the transaction with the gateway."
                              },
                              "TransactionId": {
                                "type": "string",
                                "description": "The transaction ID."
                              },
                              "PaymentId": {
                                "type": "string",
                                "description": "The payment ID assigned to this transaction."
                              },
                              "AuthorizationId": {
                                "type": "string",
                                "description": "The authorization ID assigned to this transaction."
                              },
                              "TransactionStatus": {
                                "type": "string",
                                "description": "The status of the transaction (InProgress, Succss, Failed, Canceled, Authorize)."
                              },
                              "TransationValue": {
                                "type": "string",
                                "description": "The value of the transaction."
                              },
                              "CustomerServiceCharge": {
                                "type": "string",
                                "description": "The service charges considered on the customer during the transaction."
                              },
                              "TotalServiceCharge": {
                                "type": "string",
                                "description": "Total service charge deducted from MyFatoorah side."
                              },
                              "DueValue": {
                                "type": "string",
                                "description": "The value of this transaction."
                              },
                              "PaidCurrency": {
                                "type": "string",
                                "description": "The currency used to pay for the transaction."
                              },
                              "PaidCurrencyValue": {
                                "type": "string",
                                "description": "The currency value used to pay for the transaction."
                              },
                              "VatAmount": {
                                "type": "number",
                                "description": "The value of the VAT amount for the transaction."
                              },
                              "Currency": {
                                "type": "string",
                                "description": "Transaction currency."
                              },
                              "Error": {
                                "type": "string",
                                "description": "The error message associated with the transaction from the acquirer bank/platform."
                              },
                              "CardNumber": {
                                "type": "string",
                                "description": "The masked card number used for the transaction."
                              },
                              "ErrorCode": {
                                "type": "string",
                                "description": "The MyFatoorah error code. Refer to Error Codes table."
                              },
                              "ECI": {
                                "type": "string",
                                "description": "The ECI record of the transaction."
                              },
                              "Card": {
                                "type": "object",
                                "properties": {
                                  "NameOnCard": {
                                    "type": "string",
                                    "description": "The name of the cardholder entered by the payer."
                                  },
                                  "Number": {
                                    "type": "string",
                                    "description": "The masked card number."
                                  },
                                  "PanHash": {
                                    "type": "string",
                                    "description": "A unique identifier for the PAN."
                                  },
                                  "ExpiryMonth": {
                                    "type": "string",
                                    "description": "Expiry month entered by the customer."
                                  },
                                  "ExpiryYear": {
                                    "type": "string",
                                    "description": "Expiry year entered by the customer."
                                  },
                                  "Brand": {
                                    "type": "string",
                                    "description": "Visa/Mastercard/Mada."
                                  },
                                  "Issuer": {
                                    "type": "string",
                                    "description": "Name of the issuer bank."
                                  },
                                  "IssuerCountry": {
                                    "type": "string",
                                    "description": "The issuer country of the card."
                                  },
                                  "FundingMethod": {
                                    "type": "string",
                                    "description": "The funding method of the card (e.g., debit, credit, prepaid)."
                                  }
                                },
                                "description": "Contains details about the card used for payment."
                              }
                            }
                          },
                          "description": "Array of invoice transactions."
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "Payment Enquiry Response": {
                    "summary": "Payment Enquiry Response",
                    "value": {
                      "IsSuccess": true,
                      "Message": "",
                      "ValidationErrors": null,
                      "Data": {
                        "InvoiceId": 6424733,
                        "InvoiceStatus": "Paid",
                        "InvoiceReference": "2026007518",
                        "CustomerReference": "noshipping-nosupplier",
                        "CreatedDate": "2026-01-10T00:15:07.293",
                        "ExpiryDate": "January 13, 2026",
                        "ExpiryTime": "00:15:07.293",
                        "InvoiceValue": 1,
                        "Comments": null,
                        "CustomerName": "Anonymous",
                        "CustomerMobile": "+965",
                        "CustomerEmail": null,
                        "UserDefinedField": null,
                        "InvoiceDisplayValue": "1.000 KD",
                        "DueDeposit": 0.884,
                        "DepositStatus": "Not Deposited",
                        "InvoiceItems": [],
                        "InvoiceTransactions": [
                          {
                            "TransactionDate": "2026-01-10T00:15:33.3533333",
                            "PaymentGateway": "VISA/MASTER",
                            "ReferenceId": "7679933321406829504805",
                            "TrackId": "10-01-2026_3250552",
                            "TransactionId": "7679933321406829504805",
                            "PaymentId": "07076424733325055272",
                            "AuthorizationId": "831000",
                            "TransactionStatus": "Succss",
                            "TransationValue": "1.000",
                            "CustomerServiceCharge": "0.000",
                            "TotalServiceCharge": "0.101",
                            "DueValue": "1.000",
                            "PaidCurrency": "KD",
                            "PaidCurrencyValue": "1.000",
                            "VatAmount": "0.015",
                            "IpAddress": "197.32.85.48",
                            "Country": "Egypt",
                            "Currency": "KD",
                            "Error": null,
                            "CardNumber": "512345xxxxxx0008",
                            "ErrorCode": "",
                            "ECI": "02",
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
                        "Suppliers": []
                      }
                    }
                  },
                  "Multi-suppliers Enquiry Response": {
                    "summary": "Payment Enquiry Response",
                    "value": {
                      "IsSuccess": true,
                      "Message": "",
                      "ValidationErrors": null,
                      "Data": {
                        "InvoiceId": 6424734,
                        "InvoiceStatus": "Paid",
                        "InvoiceReference": "2026007519",
                        "CustomerReference": "noshipping-nosupplier",
                        "CreatedDate": "2026-01-10T00:18:03.93",
                        "ExpiryDate": "January 13, 2026",
                        "ExpiryTime": "00:18:03.930",
                        "InvoiceValue": 100,
                        "Comments": null,
                        "CustomerName": "Anonymous",
                        "CustomerMobile": "+965",
                        "CustomerEmail": null,
                        "UserDefinedField": null,
                        "InvoiceDisplayValue": "100.000 KD",
                        "DueDeposit": 4,
                        "DepositStatus": "Not Deposited",
                        "InvoiceItems": [],
                        "InvoiceTransactions": [
                          {
                            "TransactionDate": "2026-01-10T00:18:27.0533333",
                            "PaymentGateway": "VISA/MASTER",
                            "ReferenceId": "7679935062786932204805",
                            "TrackId": "10-01-2026_3250553",
                            "TransactionId": "7679935062786932204805",
                            "PaymentId": "07076424734325055372",
                            "AuthorizationId": "831000",
                            "TransactionStatus": "Succss",
                            "TransationValue": "100.000",
                            "CustomerServiceCharge": "0.000",
                            "TotalServiceCharge": "0.200",
                            "DueValue": "100.000",
                            "PaidCurrency": "KD",
                            "PaidCurrencyValue": "100.000",
                            "VatAmount": "0.030",
                            "IpAddress": "197.32.85.48",
                            "Country": "Egypt",
                            "Currency": "KD",
                            "Error": null,
                            "CardNumber": "512345xxxxxx0008",
                            "ErrorCode": "",
                            "ECI": "02",
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
                        "Suppliers": [
                          {
                            "SupplierCode": 1,
                            "SupplierName": "test",
                            "InvoiceShare": 99.77,
                            "ProposedShare": null,
                            "DepositShare": 95.77
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

## UpdatePaymentStatus

*`https://docs.myfatoorah.com/reference/update-payment-status` — updated 2026-04-21*

> Updates the payment status by either capturing fully/partially the invoice amount or releasing the amount back into the customer's account. This endpoint is used for authorization and capture operations. Note: You can make only one Capture/Release operation on each invoice.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/UpdatePaymentStatus": {
      "post": {
        "summary": "UpdatePaymentStatus",
        "description": "Updates the payment status by either capturing fully/partially the invoice amount or releasing the amount back into the customer's account. This endpoint is used for authorization and capture operations. Note: You can make only one Capture/Release operation on each invoice.",
        "operationId": "update-payment-status",
        "tags": [
          "Payments (V2)"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "Operation",
                  "Key",
                  "KeyType"
                ],
                "properties": {
                  "Operation": {
                    "type": "string",
                    "enum": [
                      "Capture",
                      "Release"
                    ],
                    "description": "Capture: Refers to capturing fully or partially the invoice value. Release: Refers to releasing fully the invoice value to the customer's account."
                  },
                  "Amount": {
                    "type": "number",
                    "description": "The amount to be captured/released. For Capture: The amount has to be less than or equal to the invoice value (Mandatory). For Release: The amount has to be equal to the invoice value (Optional)."
                  },
                  "Key": {
                    "type": "string",
                    "description": "Refers to the Invoice ID, payment ID, or Customer Reference based on the key type."
                  },
                  "KeyType": {
                    "type": "string",
                    "enum": [
                      "InvoiceId",
                      "PaymentId",
                      "CustomerReference"
                    ],
                    "description": "InvoiceId: Refers to the invoice number that MyFatoorah generates. PaymentId: The value returned upon having any update on the invoice payment (recommended). CustomerReference: The reference used to link your orders in the store."
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Payment status updated successfully",
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
                            "type": "string"
                          },
                          "Error": {
                            "type": "string"
                          }
                        }
                      },
                      "description": "List of validation errors, if any."
                    },
                    "Data": {
                      "type": "object",
                      "properties": {
                        "InvoiceId": {
                          "type": "integer",
                          "description": "The invoice ID that was used in the operation."
                        },
                        "InvoiceStatus": {
                          "type": "string",
                          "description": "Pending: The customer made the payment but no operations on it OR a release is made on the amount. Paid: A capture is made on the amount."
                        },
                        "InvoiceReference": {
                          "type": "string",
                          "description": "Invoice reference generated by MyFatoorah."
                        },
                        "CustomerReference": {
                          "type": "string",
                          "description": "The customer reference data associated with the invoice."
                        },
                        "CreatedDate": {
                          "type": "string",
                          "format": "date-time",
                          "description": "The creation date of the invoice."
                        },
                        "ExpiryDate": {
                          "type": "string",
                          "format": "date-time",
                          "description": "The expiry date for the invoice."
                        },
                        "InvoiceValue": {
                          "type": "number",
                          "description": "The value of Capture/Release."
                        },
                        "Comments": {
                          "type": "string",
                          "description": "Comments associated with the invoice."
                        },
                        "CustomerName": {
                          "type": "string",
                          "description": "The customer name saved along with the invoice."
                        },
                        "CustomerMobile": {
                          "type": "string",
                          "description": "Customer mobile number."
                        },
                        "CustomerEmail": {
                          "type": "string",
                          "description": "Customer email address."
                        },
                        "UserDefinedField": {
                          "type": "string",
                          "description": "The user-defined field stored during invoice creation."
                        },
                        "InvoiceDisplayValue": {
                          "type": "string",
                          "description": "Invoice value displayed in case of different currency from the base one."
                        },
                        "DueDeposit": {
                          "type": "number",
                          "description": "The amount that will be deposited to the vendor."
                        },
                        "DepositStatus": {
                          "type": "string",
                          "description": "The deposit status of the invoice (Deposited or Not Deposited)."
                        },
                        "InvoiceItems": {
                          "type": "array",
                          "items": {
                            "type": "object",
                            "properties": {
                              "ItemName": {
                                "type": "string",
                                "description": "Invoice item name stored with the invoice."
                              },
                              "Quantity": {
                                "type": "integer",
                                "description": "Item quantity."
                              },
                              "UnitPrice": {
                                "type": "number",
                                "description": "Item unit price."
                              },
                              "Weight": {
                                "type": "number",
                                "description": "Weight in kg (100 >= Weight > 0)."
                              },
                              "Width": {
                                "type": "number",
                                "description": "Width in cm (200 >= Width > 0)."
                              },
                              "Height": {
                                "type": "number",
                                "description": "Height in cm (160 >= Height > 0)."
                              },
                              "Depth": {
                                "type": "number",
                                "description": "Depth in cm (200 >= Depth > 0)."
                              }
                            }
                          },
                          "description": "Array of invoice items."
                        },
                        "InvoiceTransactions": {
                          "type": "array",
                          "items": {
                            "type": "object",
                            "properties": {
                              "TransactionDate": {
                                "type": "string",
                                "format": "date-time",
                                "description": "The date of the transaction related to the invoice."
                              },
                              "PaymentGateway": {
                                "type": "string",
                                "description": "The gateway the transaction was processed through."
                              },
                              "ReferenceId": {
                                "type": "string",
                                "description": "The reference generated by the payment gateway."
                              },
                              "TrackId": {
                                "type": "string",
                                "description": "The track number used to track the transaction with the gateway."
                              },
                              "TransactionId": {
                                "type": "string",
                                "description": "The transaction ID."
                              },
                              "PaymentId": {
                                "type": "string",
                                "description": "The payment ID assigned to this transaction."
                              },
                              "AuthorizationId": {
                                "type": "string",
                                "description": "The authorization ID assigned to this transaction."
                              },
                              "TransactionStatus": {
                                "type": "string",
                                "description": "The status of the transaction: InProgress (payment attempt not completed), Succss (capture successful), Failed (transaction failed), Authorize (client makes payment), Canceled (amount released)."
                              },
                              "TransationValue": {
                                "type": "string",
                                "description": "The value of the transaction."
                              },
                              "CustomerServiceCharge": {
                                "type": "string",
                                "description": "The service charges considered on the customer during the transaction."
                              },
                              "TotalServiceCharge": {
                                "type": "string",
                                "description": "Total service charge deducted from MyFatoorah side."
                              },
                              "DueValue": {
                                "type": "string",
                                "description": "The amount value of this transaction."
                              },
                              "PaidCurrency": {
                                "type": "string",
                                "description": "The currency used to pay the transaction."
                              },
                              "PaidCurrencyValue": {
                                "type": "string",
                                "description": "The currency value used to pay the transaction."
                              },
                              "VatAmount": {
                                "type": "string",
                                "description": "The value of the VAT amount for the transaction."
                              },
                              "Currency": {
                                "type": "string",
                                "description": "Transaction currency."
                              },
                              "Error": {
                                "type": "string",
                                "description": "The error message associated with the transaction from the acquirer bank/platform."
                              },
                              "ErrorCode": {
                                "type": "string",
                                "description": "The MyFatoorah error code. Refer to Error Codes table."
                              },
                              "ECI": {
                                "type": "string",
                                "description": "The ECI record of the transaction."
                              },
                              "Card": {
                                "type": "object",
                                "properties": {
                                  "NameOnCard": {
                                    "type": "string",
                                    "description": "The name of the cardholder entered by the payer."
                                  },
                                  "Number": {
                                    "type": "string",
                                    "description": "The masked card number."
                                  },
                                  "PanHash": {
                                    "type": "string",
                                    "description": "A unique identifier for the PAN."
                                  },
                                  "ExpiryMonth": {
                                    "type": "string",
                                    "description": "Expiry month entered by the customer."
                                  },
                                  "ExpiryYear": {
                                    "type": "string",
                                    "description": "Expiry year entered by the customer."
                                  },
                                  "Brand": {
                                    "type": "string",
                                    "description": "Visa/Mastercard/Mada."
                                  },
                                  "Issuer": {
                                    "type": "string",
                                    "description": "Name of the issuer bank."
                                  },
                                  "IssuerCountry": {
                                    "type": "string",
                                    "description": "The issuer country of the card."
                                  },
                                  "FundingMethod": {
                                    "type": "string",
                                    "description": "The funding method of the card (e.g., debit, credit, prepaid)."
                                  }
                                },
                                "description": "Contains details about the card used for payment."
                              }
                            }
                          },
                          "description": "Array of invoice transactions."
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "Capture Response": {
                    "summary": "Successful Capture Operation",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Payment captured successfully",
                      "ValidationErrors": null,
                      "Data": {
                        "InvoiceId": 5822983,
                        "InvoiceStatus": "Paid",
                        "InvoiceReference": "INV-00002",
                        "CustomerReference": "ORDER-67890",
                        "CreatedDate": "2026-01-09T11:00:00",
                        "ExpiryDate": "2026-01-10T11:00:00",
                        "InvoiceValue": 2,
                        "Comments": "",
                        "CustomerName": "Jane Smith",
                        "CustomerMobile": "+96512345678",
                        "CustomerEmail": "jane@example.com",
                        "UserDefinedField": "",
                        "InvoiceDisplayValue": "2.000 KWD",
                        "DueDeposit": 2,
                        "DepositStatus": "Not Deposited",
                        "InvoiceItems": [],
                        "InvoiceTransactions": [
                          {
                            "TransactionDate": "2026-01-09T11:30:00",
                            "PaymentGateway": "Visa/Master Card",
                            "ReferenceId": "987654321",
                            "TrackId": "TRK456",
                            "TransactionId": "TXN456",
                            "PaymentId": "9876543210",
                            "AuthorizationId": "AUTH456",
                            "TransactionStatus": "Succss",
                            "TransationValue": "2.000",
                            "CustomerServiceCharge": "0.000",
                            "TotalServiceCharge": "0.100",
                            "DueValue": "1.900",
                            "PaidCurrency": "KWD",
                            "PaidCurrencyValue": "2.000",
                            "VatAmount": "0",
                            "Currency": "KWD",
                            "Error": "",
                            "ErrorCode": "",
                            "ECI": "05"
                          }
                        ]
                      }
                    }
                  },
                  "Release Response": {
                    "summary": "Successful Release Operation",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Payment released successfully",
                      "ValidationErrors": null,
                      "Data": {
                        "InvoiceId": 5822987,
                        "InvoiceStatus": "Pending",
                        "InvoiceReference": "INV-00003",
                        "CustomerReference": "ORDER-11111",
                        "CreatedDate": "2026-01-09T12:00:00",
                        "ExpiryDate": "2026-01-10T12:00:00",
                        "InvoiceValue": 40,
                        "Comments": "",
                        "CustomerName": "Bob Johnson",
                        "CustomerMobile": "+96512345679",
                        "CustomerEmail": "bob@example.com",
                        "UserDefinedField": "",
                        "InvoiceDisplayValue": "40.000 KWD",
                        "DueDeposit": 0,
                        "DepositStatus": "Not Deposited",
                        "InvoiceItems": [],
                        "InvoiceTransactions": [
                          {
                            "TransactionDate": "2026-01-09T12:30:00",
                            "PaymentGateway": "Visa/Master Card",
                            "ReferenceId": "555666777",
                            "TrackId": "TRK789",
                            "TransactionId": "TXN789",
                            "PaymentId": "5556667770",
                            "AuthorizationId": "AUTH789",
                            "TransactionStatus": "Canceled",
                            "TransationValue": "40.000",
                            "CustomerServiceCharge": "0.000",
                            "TotalServiceCharge": "0.000",
                            "DueValue": "40.000",
                            "PaidCurrency": "KWD",
                            "PaidCurrencyValue": "40.000",
                            "VatAmount": "0",
                            "Currency": "KWD",
                            "Error": "",
                            "ErrorCode": "",
                            "ECI": "05"
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

## UpdateSession

*`https://docs.myfatoorah.com/reference/update-session` — updated 2026-04-21*

> Updates a payment session with payment token information. This endpoint accepts MyFatoorah tokens, and tokens for native wallet integrations.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/UpdateSession": {
      "post": {
        "summary": "UpdateSession",
        "description": "Updates a payment session with payment token information. This endpoint accepts MyFatoorah tokens, and tokens for native wallet integrations.",
        "operationId": "update-session",
        "tags": [
          "Payments (V2)"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "SessionId",
                  "Token",
                  "TokenType"
                ],
                "properties": {
                  "SessionId": {
                    "type": "string",
                    "description": "Received from InitiateSession endpoint to update the session with the token."
                  },
                  "Token": {
                    "type": "string",
                    "description": "Token based on the TokenType."
                  },
                  "TokenType": {
                    "type": "string",
                    "enum": [
                      "mftoken",
                      "googlepay",
                      "applepay"
                    ],
                    "description": "mftoken: for MyFatoorah card token. googlepay: for Google Pay token. applepay: for Apple Pay token."
                  }
                }
              },
              "examples": {
                "MyFatoorah Card Token": {
                  "summary": "MyFatoorah Card Token",
                  "value": {
                    "SessionId": "9d2678e5-ae8a-4469-9b84-97ac6d78fbed",
                    "Token": "TKN-385cbb0f-4291-4247-9906-a8eed490dd85",
                    "TokenType": "mftoken"
                  }
                },
                "Apple Pay Token": {
                  "summary": "Apple Pay Token (Native integration)",
                  "value": {
                    "SessionId": "9d2678e5-ae8a-4469-9b84-97ac6d78fbed",
                    "Token": "{\"PaymentData\":{\"version\":\"EC_v1\",\"data\":\"FY54AajfqpMCGOrXK0AePw8/kxVVRMn/hL7O0Yu1+j6nrSrJGSeUtVaDDJkvV9Nht+szczce3aWGk4CpZKWgZtFxMHWW4m8eMD2Ciq1S21ds451hQ1GhIaVJ+KZRdb0rTa39q3U5zSxb5ZyxJ6PcgAbn9UVLuy3rZvtN7WiCeh15GTMKsQA1Kky8M0Pan112xBWiOw/7R+Lus68ADkBRMbe1UG8/E8inocrk1Lym+nOuB9e44kQE6Z0c7ZjjK1fGG2ew+YHq1stk1bsAOOvIhjMmdAJLL1d0dsqjCqPIZv9MMDwUZRtfuUAFxn/92lYm4WBJ22kaIBeGk/fTx6fTFIuOxCgRA+yIdBIILF8NKXWG/9zA5BMufojlC8WBb0T8K1OWN4eswk7y5jg/6tQ=\",\"signature\":\"MIAGCSqGSIb3DQEHAqCAMIACAQExDTALBglghkgBZQMEAgEwgAYJKoZIhvcNAQcBAACggDCCA+QwggOLoAMCAQICCFnYobyq9OPNMAoGCCqGSM49BAMCMHoxLjAsBgNVBAMMJUFwcGxlIEFwcGxpY2F0aW9uIEludGVncmF0aW9uIENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9yaXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzAeFw0yMTA0MjAxOTM3MDBaFw0yNjA0MTkxOTM2NTlaMGIxKDAmBgNVBAMMH2VjYy1zbXAtYnJva2VyLXNpZ25fVUM0LVNBTkRCT1gxFDASBgNVBAsMC2lPUyBTeXN0ZW1zMRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABIIw/avDnPdeICxQ2ZtFEuY34qkB3Wyz4LHNS1JnmPjPTr3oGiWowh5MM93OjiqWwvavoZMDRcToekQmzpUbEpWjggIRMIICDTAMBgNVHRMBAf8EAjAAMB8GA1UdIwQYMBaAFCPyScRPk+TvJ+bE9ihsP6K7/S5LMEUGCCsGAQUFBwEBBDkwNzA1BggrBgEFBQcwAYYpaHR0cDovL29jc3AuYXBwbGUuY29tL29jc3AwNC1hcHBsZWFpY2EzMDIwggEdBgNVHSAEggEUMIIBEDCCAQwGCSqGSIb3Y2QFATCB/jCBwwYIKwYBBQUHAgIwgbYMgbNSZWxpYW5jZSBvbiB0aGlzIGNlcnRpZmljYXRlIGJ5IGFueSBwYXJ0eSBhc3N1bWVzIGFjY2VwdGFuY2Ugb2YgdGhlIHRoZW4gYXBwbGljYWJsZSBzdGFuZGFyZCB0ZXJtcyBhbmQgY29uZGl0aW9ucyBvZiB1c2UsIGNlcnRpZmljYXRlIHBvbGljeSBhbmQgY2VydGlmaWNhdGlvbiBwcmFjdGljZSBzdGF0ZW1lbnRzLjA2BggrBgEFBQcCARYqaHR0cDovL3d3dy5hcHBsZS5jb20vY2VydGlmaWNhdGVhdXRob3JpdHkvMDQGA1UdHwQtMCswKaAnoCWGI2h0dHA6Ly9jcmwuYXBwbGUuY29tL2FwcGxlYWljYTMuY3JsMB0GA1UdDgQWBBQCJDALmu7tRjGXpKZaKZ5CcYIcRTAOBgNVHQ8BAf8EBAMCB4AwDwYJKoZIhvdjZAYdBAIFADAKBggqhkjOPQQDAgNHADBEAiB0obMk20JJQw3TJ0xQdMSAjZofSA46hcXBNiVmMl+8owIgaTaQU6v1C1pS+fYATcWKrWxQp9YIaDeQ4Kc60B5K2YEwggLuMIICdaADAgECAghJbS+/OpjalzAKBggqhkjOPQQDAjBnMRswGQYDVQQDDBJBcHBsZSBSb290IENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9yaXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzAeFw0xNDA1MDYyMzQ2MzBaFw0yOTA1MDYyMzQ2MzBaMHoxLjAsBgNVBAMMJUFwcGxlIEFwcGxpY2F0aW9uIEludGVncmF0aW9uIENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9yaXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABPAXEYQZ12SF1RpeJYEHduiAou/ee65N4I38S5PhM1bVZls1riLQl3YNIk57ugj9dhfOiMt2u2ZwvsjoKYT/VEWjgfcwgfQwRgYIKwYBBQUHAQEEOjA4MDYGCCsGAQUFBzABhipodHRwOi8vb2NzcC5hcHBsZS5jb20vb2NzcDA0LWFwcGxlcm9vdGNhZzMwHQYDVR0OBBYEFCPyScRPk+TvJ+bE9ihsP6K7/S5LMA8GA1UdEwEB/wQFMAMBAf8wHwYDVR0jBBgwFoAUu7DeoVgziJqkipnevr3rr9rLJKswNwYDVR0fBDAwLjAsoCqgKIYmaHR0cDovL2NybC5hcHBsZS5jb20vYXBwbGVyb290Y2FnMy5jcmwwDgYDVR0PAQH/BAQDAgEGMBAGCiqGSIb3Y2QGAg4EAgUAMAoGCCqGSM49BAMCA2cAMGQCMDrPcoNRFpmxhvs1w1bKYr/0F+3ZD3VNoo6+8ZyBXkK3ifiY95tZn5jVQQ2PnenC/gIwMi3VRCGwowV3bF3zODuQZ/0XfCwhbZZPxnJpghJvVPh6fRuZy5sJiSFhBpkPCZIdAAAxggGIMIIBhAIBATCBhjB6MS4wLAYDVQQDDCVBcHBsZSBBcHBsaWNhdGlvbiBJbnRlZ3JhdGlvbiBDQSAtIEczMSYwJAYDVQQLDB1BcHBsZSBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTETMBEGA1UECgwKQXBwbGUgSW5jLjELMAkGA1UEBhMCVVMCCFnYobyq9OPNMAsGCWCGSAFlAwQCAaCBkzAYBgkqhkiG9w0BCQMxCwYJKoZIhvcNAQcBMBwGCSqGSIb3DQEJBTEPFw0yNTEyMDIwOTE3NDBaMCgGCSqGSIb3DQEJNDEbMBkwCwYJYIZIAWUDBAIBoQoGCCqGSM49BAMCMC8GCSqGSIb3DQEJBDEiBCBgEHd0qYFivIW/juzkYS9ZM/EJPtEof3BYxZF37VxYwTAKBggqhkjOPQQDAgRHMEUCIQDCTIdh626dVbaJN2iPnD4M0Zx8JM5FkEri+XNg8YzeOgIgBlqrEevxEHgwDRIWYVWgzC8+uYujiAiROWDEzt/CEPcAAAAAAAA=\",\"header\":{\"ephemeralPublicKey\":\"MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEL7bmqfLGIjCAxd9WegJaS3kZcN4oqJFC+h+MjEKNXV6mWcR+wLfE39y3p/oj33Oo1/LI+tVcDi9/mJHHCrR2YA==\",\"publicKeyHash\":\"hmOvu/gjGyJ2irwuLSHzSB2irbqeEjsc/IBnBTzfGnA=\",\"transactionId\":\"fb63b9dfd46b59420721e4a19b3e0397a9df2aa70b6337aec8a52bcf6582609f\"}},\"PaymentMethod\":{\"displayName\":\"MasterCard 2095\",\"network\":\"MasterCard\",\"type\":\"credit\"},\"TransactionIdentifier\":\"fb63b9dfd46b59420721e4a19b3e0397a9df2aa70b6337aec8a52bcf6582609f\"}",
                    "TokenType": "applepay"
                  }
                },
                "Google Pay Token": {
                  "summary": "Google Pay Token (Native integration)",
                  "value": {
                    "SessionId": "9d2678e5-ae8a-4469-9b84-97ac6d78fbed",
                    "Token": "{\r\n  \"apiVersion\": 2,\r\n  \"apiVersionMinor\": 0,\r\n  \"paymentMethodData\": {\r\n    \"description\": \"Test Card: Visa •••• 1111\",\r\n    \"info\": {\r\n      \"assuranceDetails\": {\r\n        \"accountVerified\": true,\r\n        \"cardHolderAuthenticated\": false\r\n      },\r\n      \"cardDetails\": \"1111\",\r\n      \"cardFundingSource\": \"CREDIT\",\r\n      \"cardNetwork\": \"VISA\"\r\n    },\r\n    \"tokenizationData\": {\r\n      \"token\": \"{\\\"signature\\\":\\\"MEUCIQDMywQlWFHvdQdENipi+DjitheBl3A+sNmF1qvZcoz30QIgJ3xdUtN2il/Oxx+9y+d3BECBi4zYYsobeF1TmVAPbYw\\\\u003d\\\",\\\"intermediateSigningKey\\\":{\\\"signedKey\\\":\\\"{ \\\\\\\"keyValue\\\\\\\": \\\\\\\"MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEAkii8Zoh8AOqfkRrvgCcPza/ZfYSzdXn0epS+ADmkOc7IPijjEnDg+usgxElDmKixBKf/q8Y20AK1sBM1Phzmw\\\\\\\\u003d\\\\\\\\u003d\\\\\\\", \\\\\\\"keyExpiration\\\\\\\": \\\\\\\"1768677227112\\\\\\\" }\\\",\\\"signatures\\\":[\\\"MEUCIQC6OZONZAohwua7uvyv3bUsxjPjdORz4Kkaqp9n0o77oAIgQQREpCg0YeHz5ircyXzeMlcN1oyxruiFApJ670U3JVs\\\\u003d\\\"]},\\\"protocolVersion\\\":\\\"ECv2\\\",\\\"signedMessage\\\":\\\"{ \\\\\\\"encryptedMessage\\\\\\\": \\\\\\\"ynK6JOo+W33L/rc+y61ihRs5olrszcUzK2D9VWXY2vVKVM2K9K1K3KEYSL06JCImljEXJVaz/jvBfGaA034vGLouWAFIUj+G8pWBGk19BZwlj7dokLd4AdFAwoTfsAwuOuQj6IPpngkFF0ptXh4L96DOaqA+vDkSKrsl+8aa58J8Bky9sxM8TIkHjO18CYn1W3GJEYE5xTzG7dno6Via+rZ8zhzHX2EolmisKaVzlL31qLoVMMTi63ALhQ3yye4SivlfrRODeYqfiL2+jGEgJnSeOZDU91cryKsXOg5KgGKyh7ectfAHSKAf3nnLOSeKj0cnefPKU+IGcefuxNDtLAyztVv9Tri8ruIcJqXEtcKyFoca1oYrqByDwrziuXCRnKzt5MrEpujtaCwwjBfxzZLrR4RMG4GGprlVMDrItyu9HO/lgE1C/OvCR8VANEq/iQ5siGUd+ZXspGW6tDTl5KlSdSQGhyHpch/zccxcmYsd2K0wwVFlKSgFsAsifdPsPT3LwzvPnjPM2XNBuHv9N6kWCmaBRuB5VFX6aGhOiy9BrHnsJtl+cD65/AxbtxcfpXsPkpiCcLw+gWdpIXkXmOI\\\\\\\\u003d\\\\\\\", \\\\\\\"ephemeralPublicKey\\\\\\\": \\\\\\\"BLVUz62+HGCkigFesLCxEMHw91g4MTbPW29r3KYE4eohlbmHyuzeTOXAkxPOHWdGVK5TPd33LqQgExs0A5W707c\\\\\\\\u003d\\\\\\\", \\\\\\\"tag\\\\\\\": \\\\\\\"t6Jn5xR9s3+xjSzIJRs6EdlPQbC4Z5jk04mqLSq/F9Q\\\\\\\\u003d\\\\\\\" }\\\"}\",\r\n      \"type\": \"PAYMENT_GATEWAY\"\r\n    },\r\n    \"type\": \"CARD\"\r\n  }\r\n}",
                    "TokenType": "googlepay"
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Session updated successfully",
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
                      "properties": {
                        "SessionId": {
                          "type": "string",
                          "description": "The updated session ID."
                        },
                        "CountryCode": {
                          "type": "string",
                          "description": "The country code associated with the session."
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "Success Response": {
                    "summary": "Successful Session Update",
                    "value": {
                      "IsSuccess": true,
                      "Message": null,
                      "ValidationErrors": null,
                      "Data": {
                        "SessionId": "6d493401-534a-42d1-856d-d15fed12f487",
                        "CountryCode": "KWT"
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

## CancelToken

*`https://docs.myfatoorah.com/reference/cancel-token` — updated 2026-04-21*

> Cancels a saved card token

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/CancelToken": {
      "post": {
        "summary": "CancelToken",
        "description": "Cancels a saved card token",
        "operationId": "cancel-token",
        "tags": [
          "Payments (V2)"
        ],
        "parameters": [
          {
            "name": "Token",
            "in": "query",
            "required": true,
            "schema": {
              "type": "string"
            },
            "description": "The token to be canceled."
          }
        ],
        "responses": {
          "200": {
            "description": "Token canceled successfully",
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
                            "type": "string"
                          },
                          "Error": {
                            "type": "string"
                          }
                        }
                      },
                      "description": "List of validation errors, if any."
                    },
                    "Data": {
                      "type": "boolean"
                    }
                  }
                },
                "examples": {
                  "Success Response": {
                    "summary": "Successful Token Cancellation",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Canceled successfully",
                      "ValidationErrors": null,
                      "Data": true
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

## DirectPayment

*`https://docs.myfatoorah.com/reference/direct-payment` — updated 2026-04-21*

> Facilitates the payment process by directly executing payments using card details or saved tokens. Note: This endpoint can be used if your system PCI certified

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/DirectPayment/{invoiceKey}/{paymentGatewayId}": {
      "post": {
        "summary": "DirectPayment",
        "description": "Facilitates the payment process by directly executing payments using card details or saved tokens. Note: This endpoint can be used if your system PCI certified",
        "operationId": "direct-payment",
        "tags": [
          "Payments (V2)"
        ],
        "parameters": [
          {
            "name": "invoiceKey",
            "in": "path",
            "required": true,
            "schema": {
              "type": "string"
            },
            "description": "The invoice key for the transaction."
          },
          {
            "name": "paymentGatewayId",
            "in": "path",
            "required": true,
            "schema": {
              "type": "integer"
            },
            "description": "The payment gateway ID to process the payment through."
          }
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "PaymentType"
                ],
                "properties": {
                  "PaymentType": {
                    "type": "string",
                    "enum": [
                      "card",
                      "token"
                    ],
                    "description": "Specify the payment type: 'card' for card payment, 'token' for tokenized payment."
                  },
                  "Bypass3DS": {
                    "type": "boolean",
                    "description": "Specify whether this transaction should be verified by 3DS or not."
                  },
                  "SaveToken": {
                    "type": "boolean",
                    "description": "Set to true to save card data and return a token for future use."
                  },
                  "Token": {
                    "type": "string",
                    "description": "The token value to execute the transaction against. Required if PaymentType is 'token'."
                  },
                  "Card": {
                    "type": "object",
                    "description": "Card details. Required if PaymentType is 'card'.",
                    "properties": {
                      "Number": {
                        "type": "string",
                        "description": "The 16 digits of the card that will be charged for the transaction."
                      },
                      "ExpiryMonth": {
                        "type": "string",
                        "description": "Card expiry month (MM)."
                      },
                      "ExpiryYear": {
                        "type": "string",
                        "description": "Card expiry year (YY)."
                      },
                      "SecurityCode": {
                        "type": "string",
                        "description": "Card CVV / CVC."
                      },
                      "HolderName": {
                        "type": "string",
                        "description": "Name on card."
                      }
                    }
                  }
                }
              },
              "examples": {
                "Card Payment": {
                  "summary": "Direct Payment with Card",
                  "value": {
                    "PaymentType": "card",
                    "Bypass3DS": false,
                    "SaveToken": true,
                    "Card": {
                      "Number": "5123450000000008",
                      "ExpiryMonth": "01",
                      "ExpiryYear": "39",
                      "SecurityCode": "100",
                      "HolderName": "John Doe"
                    }
                  }
                },
                "Token Payment": {
                  "summary": "Direct Payment with Token",
                  "value": {
                    "PaymentType": "token",
                    "Token": "TKN-d1b18031-78e9-4f90-8308-a975c0921dd4",
                    "Bypass3DS": false
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Payment processed successfully",
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
                            "type": "string"
                          },
                          "Error": {
                            "type": "string"
                          }
                        }
                      },
                      "description": "List of validation errors, if any."
                    },
                    "Data": {
                      "type": "object",
                      "properties": {
                        "Status": {
                          "type": "string",
                          "description": "The transaction status in non-3DS flow"
                        },
                        "ErrorMessage": {
                          "type": "string",
                          "description": "In case of error, the error message returned from the gateway."
                        },
                        "PaymentId": {
                          "type": "string",
                          "description": "The payment ID associated with the transaction."
                        },
                        "Token": {
                          "type": "string",
                          "description": "If SaveToken is requested, this returns the token of the card."
                        },
                        "PaymentURL": {
                          "type": "string",
                          "description": "The OTP link. You should redirect your customer to this page for 3DS verification."
                        },
                        "CardInfo": {
                          "type": "object",
                          "properties": {
                            "Number": {
                              "type": "string",
                              "description": "The card number that executed the payment, displays first and last 4 digits."
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
                              "description": "Card brand (VISA or Master)."
                            },
                            "Issuer": {
                              "type": "string",
                              "description": "The card issuer bank."
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

## GetRecurringPayment

*`https://docs.myfatoorah.com/reference/get-recurring-payment` — updated 2026-04-21*

> Retrieves all recurring payments created in your account. This endpoint returns a list of all recurring payments with their status, execution details, and related invoices.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/GetRecurringPayment": {
      "get": {
        "summary": "GetRecurringPayment",
        "description": "Retrieves all recurring payments created in your account. This endpoint returns a list of all recurring payments with their status, execution details, and related invoices.",
        "operationId": "get-recurring-payment",
        "tags": [
          "Payments (V2)"
        ],
        "responses": {
          "200": {
            "description": "Recurring payments retrieved successfully",
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
                            "type": "string"
                          },
                          "Error": {
                            "type": "string"
                          }
                        }
                      },
                      "description": "List of validation errors, if any."
                    },
                    "Data": {
                      "type": "object",
                      "properties": {
                        "RecurringPayment": {
                          "type": "array",
                          "items": {
                            "type": "object",
                            "properties": {
                              "RecurringId": {
                                "type": "string",
                                "description": "A unique number for each recurring payment. It's recommended to save this ID in your system with your customer profile so that, you can keep track of all payments done against that customer. Moreover, you will be able to cancel it when needed later on."
                              },
                              "RecurringStatus": {
                                "type": "string",
                                "enum": [
                                  "ACTIVE",
                                  "UNCOMPLETED",
                                  "COMPLETED",
                                  "CANCELED",
                                  "DRAFT"
                                ],
                                "description": "ACTIVE: The recurring payment is being processed normally but its iterations have not been completed yet. UNCOMPLETED: MyFatoorah tried to withdraw the recurring value but the payment has failed and also Retry Counts have been executed and produced failed payments. COMPLETED: The recurring payment has been executed successfully with all its iterations and will not be executed again. CANCELED: The recurring status is changed to canceled when you use CancelRecurringPayment endpoint to stop a recurring payment from being executed. DRAFT: The recurring status is initially set as draft. When the first payment (first invoice before recurring) is done the status changes to active."
                              },
                              "CreationDate": {
                                "type": "string",
                                "format": "date-time",
                                "description": "The date when the recurring payment was created."
                              },
                              "RecurringValue": {
                                "type": "number",
                                "description": "The value to be paid by your customer."
                              },
                              "RecurringType": {
                                "type": "string",
                                "enum": [
                                  "Custom",
                                  "Daily",
                                  "Weekly",
                                  "Monthly"
                                ],
                                "description": "Recurring type you set in the request while creating the recurring payment. Possible values: (Custom-Daily-Weekly-Monthly)"
                              },
                              "IntervalDays": {
                                "type": "integer",
                                "description": "Valid when recurring type is set to 'custom'."
                              },
                              "ExecutedTimes": {
                                "type": "integer",
                                "description": "How many times the recurring payment has been already executed."
                              },
                              "LastPayDate": {
                                "type": "string",
                                "format": "date-time",
                                "description": "Last date the recurring payment was executed."
                              },
                              "NextPayDate": {
                                "type": "string",
                                "format": "date-time",
                                "description": "Next date the recurring payment will be executed."
                              },
                              "IsActive": {
                                "type": "boolean",
                                "description": "Whether the recurring status is active or not."
                              },
                              "RecurringInvoices": {
                                "type": "array",
                                "items": {
                                  "type": "object",
                                  "properties": {
                                    "InvoiceId": {
                                      "type": "integer",
                                      "description": "The invoice ID associated with the recurring payment."
                                    },
                                    "CustomerReference": {
                                      "type": "string",
                                      "description": "The customer reference associated with the invoice."
                                    },
                                    "CustomerName": {
                                      "type": "string",
                                      "description": "The customer name associated with the invoice."
                                    },
                                    "CustomerMobile": {
                                      "type": "string",
                                      "description": "The customer mobile number associated with the invoice."
                                    },
                                    "CreatedDate": {
                                      "type": "string",
                                      "format": "date-time",
                                      "description": "The date when the invoice was created."
                                    },
                                    "InvoiceStatus": {
                                      "type": "string",
                                      "description": "The status of the invoice."
                                    }
                                  }
                                },
                                "description": "The list of invoices related to this recurring payment."
                              }
                            }
                          },
                          "description": "Array of recurring payment objects."
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "Success Response": {
                    "summary": "Successful Recurring Payments Retrieval",
                    "value": {
                      "IsSuccess": true,
                      "Message": "",
                      "ValidationErrors": null,
                      "Data": {
                        "RecurringPayment": [
                          {
                            "RecurringId": "RECUR2136",
                            "RecurringStatus": "Canceled",
                            "CreationDate": "2021-04-13T11:57:51.733",
                            "RecurringValue": 50,
                            "RecurringType": "Custom",
                            "IntervalDays": 1,
                            "ExecutedTimes": 0,
                            "LastPayDate": "2021-04-13T11:57:51.733",
                            "NextPayDate": "2021-04-14T00:00:00",
                            "IsActive": false,
                            "RecurringInvoices": null
                          },
                          {
                            "RecurringId": "RECUR2171",
                            "RecurringStatus": "Active",
                            "CreationDate": "2021-04-22T15:17:56.38",
                            "RecurringValue": 50,
                            "RecurringType": "Custom",
                            "IntervalDays": 180,
                            "ExecutedTimes": 0,
                            "LastPayDate": "2021-04-22T15:17:56.38",
                            "NextPayDate": "2021-10-19T00:00:00",
                            "IsActive": true,
                            "RecurringInvoices": null
                          },
                          {
                            "RecurringId": "RECUR2137",
                            "RecurringStatus": "Uncompleted",
                            "CreationDate": "2021-04-13T12:06:41.067",
                            "RecurringValue": 50,
                            "RecurringType": "Custom",
                            "IntervalDays": 1,
                            "ExecutedTimes": 2,
                            "LastPayDate": "2021-04-15T04:02:45.717",
                            "NextPayDate": "2021-04-16T00:00:00",
                            "IsActive": false,
                            "RecurringInvoices": [
                              {
                                "InvoiceId": 614309,
                                "CustomerReference": null,
                                "CustomerName": "fname lname",
                                "CustomerMobile": "+965",
                                "CreatedDate": "2021-04-14T04:03:31.277",
                                "InvoiceStatus": "Paid"
                              },
                              {
                                "InvoiceId": 615024,
                                "CustomerReference": null,
                                "CustomerName": "fname lname",
                                "CustomerMobile": "+965",
                                "CreatedDate": "2021-04-15T04:02:43.077",
                                "InvoiceStatus": "Paid"
                              }
                            ]
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

## CancelRecurringPayment

*`https://docs.myfatoorah.com/reference/cancel-recurring-payment` — updated 2026-04-21*

> Cancels a recurring payment by its recurring ID. Once canceled, the recurring payment will stop executing future scheduled payments.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/CancelRecurringPayment": {
      "post": {
        "summary": "CancelRecurringPayment",
        "description": "Cancels a recurring payment by its recurring ID. Once canceled, the recurring payment will stop executing future scheduled payments.",
        "operationId": "cancel-recurring-payment",
        "tags": [
          "Payments (V2)"
        ],
        "parameters": [
          {
            "name": "recurringId",
            "in": "query",
            "required": true,
            "schema": {
              "type": "string"
            },
            "description": "The unique recurring ID that you want to cancel."
          }
        ],
        "responses": {
          "200": {
            "description": "Recurring payment canceled successfully",
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
                            "type": "string"
                          },
                          "Error": {
                            "type": "string"
                          }
                        }
                      },
                      "description": "List of validation errors, if any."
                    },
                    "Data": {
                      "type": "boolean"
                    }
                  }
                },
                "examples": {
                  "Success Response": {
                    "summary": "Successful Cancellation",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Canceled successfully",
                      "ValidationErrors": null,
                      "Data": true
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

## ResumeRecurringPayment

*`https://docs.myfatoorah.com/reference/resume-recurring-payment` — updated 2026-04-21*

> Manually retries a failed recurring payment. This endpoint is used only with uncompleted recurring payments where the recurring status is inactive.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/ResumeRecurringPayment": {
      "post": {
        "summary": "ResumeRecurringPayment",
        "description": "Manually retries a failed recurring payment. This endpoint is used only with uncompleted recurring payments where the recurring status is inactive.",
        "operationId": "resume-recurring-payment",
        "tags": [
          "Payments (V2)"
        ],
        "parameters": [
          {
            "name": "recurringId",
            "in": "query",
            "required": true,
            "schema": {
              "type": "string"
            },
            "description": "The unique recurring ID that you want to resume."
          }
        ],
        "responses": {
          "200": {
            "description": "Recurring payment resumed successfully",
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
                            "type": "string"
                          },
                          "Error": {
                            "type": "string"
                          }
                        }
                      },
                      "description": "List of validation errors, if any."
                    },
                    "Data": {
                      "type": "boolean"
                    }
                  }
                },
                "examples": {
                  "Success Response": {
                    "summary": "Successful Resume",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Recurring Resumed successfully",
                      "ValidationErrors": null,
                      "Data": true
                    }
                  }
                }
              }
            }
          },
          "400": {
            "description": "Bad Request",
            "content": {
              "application/json": {
                "examples": {
                  "Failure Response": {
                    "summary": "Failed Resume",
                    "value": {
                      "IsSuccess": false,
                      "Message": "Invalid data",
                      "ValidationErrors": [
                        {
                          "Name": "RecurringId",
                          "Error": "This recurring cannot be resumed as it is active or the status is not Uncompleted"
                        }
                      ],
                      "Data": false
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

## RegisterApplePayDomain

*`https://docs.myfatoorah.com/reference/register-apple-pay-domain` — updated 2026-04-21*

> Registers your domain to use Apple Pay in embedded integration. You need to host the Apple Pay verification file on your domain before calling this endpoint.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "paths": {
    "/v2/RegisterApplePayDomain": {
      "post": {
        "summary": "RegisterApplePayDomain",
        "description": "Registers your domain to use Apple Pay in embedded integration. You need to host the Apple Pay verification file on your domain before calling this endpoint.",
        "operationId": "register-apple-pay-domain",
        "tags": [
          "Payments (V2)"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "DomainName"
                ],
                "properties": {
                  "DomainName": {
                    "type": "string",
                    "description": "The domain name to register for Apple Pay in this format \"example.com\"."
                  }
                }
              },
              "examples": {
                "Register Domain": {
                  "summary": "Register Domain for Apple Pay",
                  "value": {
                    "DomainName": "example.com"
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Domain registered successfully",
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
                            "type": "string"
                          },
                          "Error": {
                            "type": "string"
                          }
                        }
                      },
                      "description": "List of validation errors, if any."
                    },
                    "Data": {
                      "type": "object",
                      "nullable": true
                    }
                  }
                },
                "examples": {
                  "Success Response": {
                    "summary": "Successful Domain Registration",
                    "value": {
                      "IsSuccess": true,
                      "Message": "OK",
                      "ValidationErrors": null,
                      "Data": null
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

## MakeRefund

*`https://docs.myfatoorah.com/reference/make-refund` — updated 2026-04-21*

> Cancels a payment and returns the funds to the customer. This endpoint can be used for full or partial refunds. The refund can be processed using either InvoiceId or PaymentId.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "tags": [
    {
      "name": "Refund",
      "description": "Refund operations for canceling payments and returning funds to customers"
    }
  ],
  "paths": {
    "/v2/MakeRefund": {
      "post": {
        "summary": "MakeRefund",
        "description": "Cancels a payment and returns the funds to the customer. This endpoint can be used for full or partial refunds. The refund can be processed using either InvoiceId or PaymentId.",
        "operationId": "make-refund",
        "tags": [
          "Refund"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "KeyType",
                  "Key",
                  "Amount"
                ],
                "properties": {
                  "KeyType": {
                    "type": "string",
                    "enum": [
                      "InvoiceId",
                      "PaymentId"
                    ],
                    "description": "State either it's 'InvoiceId' or 'PaymentId' to identify the transaction to be refunded."
                  },
                  "Key": {
                    "type": "string",
                    "description": "Value of the key type mentioned. If KeyType is 'InvoiceId', provide the invoice ID. If KeyType is 'PaymentId', provide the payment ID."
                  },
                  "ServiceChargeOnCustomer": {
                    "type": "boolean",
                    "description": "Determine whether the customer will be charged for the service fees or not. Service fees are charged by MyFatoorah."
                  },
                  "Amount": {
                    "type": "number",
                    "format": "decimal",
                    "description": "The amount to be refunded."
                  },
                  "Comment": {
                    "type": "string",
                    "description": "Extra comments for your reference."
                  },
                  "ExternalIdentifier": {
                    "type": "string",
                    "description": "External data associated with the refund, which will be received in the webhook."
                  },
                  "AmountDeductedFromSupplier": {
                    "type": "number",
                    "format": "decimal",
                    "description": "This is the amount that will be deducted from the supplier in the refund process. It will be part of the total amount. For example: If the total amount is 100 and the AmountDeductedFromSupplier is 70, the vendor will pay 30 and the supplier will pay 70. This parameter is optional."
                  }
                }
              },
              "examples": {
                "Sample Request": {
                  "summary": "Sample Refund Request",
                  "value": {
                    "Key": "6424767",
                    "KeyType": "invoiceid",
                    "ServiceChargeOnCustomer": false,
                    "ExternalIdentifier": "refund-external-id",
                    "Amount": 1,
                    "Comment": "partial refund to the customer",
                    "AmountDeductedFromSupplier": 0
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Refund processed successfully",
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
                            "description": "Name of the field with validation error."
                          },
                          "Error": {
                            "type": "string",
                            "description": "Description of the validation error."
                          }
                        }
                      },
                      "description": "List of validation errors, if any."
                    },
                    "Data": {
                      "type": "object",
                      "nullable": true,
                      "properties": {
                        "Key": {
                          "type": "string",
                          "description": "The key value you have passed for the Request Transaction."
                        },
                        "RefundId": {
                          "type": "number",
                          "description": "The unique identifier for this refund transaction."
                        },
                        "RefundReference": {
                          "type": "string",
                          "description": "The refund reference generated by MyFatoorah for following up with the finance team."
                        },
                        "RefundInvoiceId": {
                          "type": "string",
                          "description": "The InvoiceId of the refunded amount."
                        },
                        "Amount": {
                          "type": "number",
                          "format": "decimal",
                          "description": "The amount that was refunded."
                        },
                        "Comment": {
                          "type": "string",
                          "description": "The comments that you have passed in the request."
                        },
                        "ExternalIdentifier": {
                          "type": "string",
                          "description": "The External Identifier you provided in the request."
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "Success Response": {
                    "summary": "Successful Refund",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Refund processed successfully",
                      "ValidationErrors": null,
                      "Data": {
                        "Key": "6424767",
                        "RefundId": 123456,
                        "RefundReference": "REF-2024-001",
                        "RefundInvoiceId": "INV-789",
                        "Amount": 1,
                        "Comment": "partial refund to the customer",
                        "ExternalIdentifier": "refund-external-id"
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

## GetRefundStatus

*`https://docs.myfatoorah.com/reference/get-refund-status` — updated 2026-04-21*

> Gets the status of a refund to check if it is refunded, rejected, or still pending.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "tags": [
    {
      "name": "Refund",
      "description": "Refund operations for canceling payments and returning funds to customers"
    }
  ],
  "paths": {
    "/v2/GetRefundStatus": {
      "post": {
        "summary": "GetRefundStatus",
        "description": "Gets the status of a refund to check if it is refunded, rejected, or still pending.",
        "operationId": "get-refund-status",
        "tags": [
          "Refund"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "KeyType",
                  "Key"
                ],
                "properties": {
                  "KeyType": {
                    "type": "string",
                    "enum": [
                      "InvoiceId",
                      "RefundReference",
                      "RefundId"
                    ],
                    "description": "Supported keys are 'InvoiceId', 'RefundReference', and 'RefundId'. Specifies the type of identifier to use for checking the refund status."
                  },
                  "Key": {
                    "type": "string",
                    "description": "Value of the key type mentioned. Provide the corresponding identifier value based on the KeyType selected."
                  }
                }
              },
              "examples": {
                "Sample Request": {
                  "summary": "Sample Refund Status Request",
                  "value": {
                    "Key": "6867014",
                    "KeyType": "InvoiceId"
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Refund status retrieved successfully",
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
                            "description": "Name of the field with validation error."
                          },
                          "Error": {
                            "type": "string",
                            "description": "Description of the validation error."
                          }
                        }
                      },
                      "description": "List of validation errors, if any."
                    },
                    "Data": {
                      "type": "object",
                      "nullable": true,
                      "properties": {
                        "RefundStatusResult": {
                          "type": "array",
                          "description": "An array of refund status result data.",
                          "items": {
                            "type": "object",
                            "properties": {
                              "RefundId": {
                                "type": "number",
                                "description": "The unique identifier for the refund transaction."
                              },
                              "RefundStatus": {
                                "type": "string",
                                "enum": [
                                  "Refunded",
                                  "Canceled",
                                  "Pending"
                                ],
                                "description": "The status of the refund. It takes one of the following values: 'Refunded', 'Canceled', or 'Pending'."
                              },
                              "InvoiceId": {
                                "type": "number",
                                "description": "Represents the invoice ID that was used in the inquiry call."
                              },
                              "Amount": {
                                "type": "number",
                                "format": "decimal",
                                "description": "The amount to be refunded."
                              },
                              "RefundReference": {
                                "type": "string",
                                "description": "The refund reference generated by MyFatoorah."
                              },
                              "RefundAmount": {
                                "type": "number",
                                "format": "decimal",
                                "description": "The actual amount refunded to the customer."
                              },
                              "ExternalIdentifier": {
                                "type": "string",
                                "description": "The ExternalIdentifier that you passed in the MakeRefund request."
                              },
                              "RRN": {
                                "type": "string",
                                "description": "Refund Reference Number that the customer can check with their bank."
                              },
                              "BaseCurrency": {
                                "type": "string",
                                "description": "Your account base currency"
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "Success Response": {
                    "summary": "Successful Refund Status Inquiry",
                    "value": {
                      "IsSuccess": true,
                      "Message": "",
                      "ValidationErrors": null,
                      "Data": {
                        "RefundStatusResult": [
                          {
                            "RefundId": 275988,
                            "RefundStatus": "Refunded",
                            "InvoiceId": 6867014,
                            "Amount": 1,
                            "RefundReference": "2026002024",
                            "ExternalIdentifier": "MF-222",
                            "RefundAmount": 1,
                            "RRN": "617614024566",
                            "BaseCurrency": "KWD"
                          },
                          {
                            "RefundId": 275990,
                            "RefundStatus": "Pending",
                            "InvoiceId": 6867014,
                            "Amount": 1,
                            "RefundReference": "2026002025",
                            "ExternalIdentifier": "MF-33",
                            "RefundAmount": 1,
                            "RRN": null,
                            "BaseCurrency": "KWD"
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

## GetBanks

*`https://docs.myfatoorah.com/reference/get-banks` — updated 2026-04-21*

> Retrieves a list of available banks for your region with their identifiers and names.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "tags": [
    {
      "name": "List",
      "description": "List operations for retrieving reference data"
    }
  ],
  "paths": {
    "/v2/GetBanks": {
      "get": {
        "summary": "GetBanks",
        "description": "Retrieves a list of available banks for your region with their identifiers and names.",
        "operationId": "get-banks",
        "tags": [
          "List"
        ],
        "parameters": [],
        "responses": {
          "200": {
            "description": "Successful response",
            "content": {
              "application/json": {
                "schema": {
                  "type": "array",
                  "description": "Array of bank objects",
                  "items": {
                    "type": "object",
                    "properties": {
                      "Value": {
                        "type": "integer",
                        "description": "The bank identifier"
                      },
                      "Text": {
                        "type": "string",
                        "description": "The bank name"
                      }
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": [
                      {
                        "Value": 1,
                        "Text": "Kuwait - Test NBK"
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

## GetCurrenciesExchangeList

*`https://docs.myfatoorah.com/reference/get-currencies-exchange-list` — updated 2026-04-21*

> Retrieves a list of currencies with their exchange rates against your base currency (Dashboard Currency).

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "tags": [
    {
      "name": "List",
      "description": "List operations for retrieving reference data"
    }
  ],
  "paths": {
    "/v2/GetCurrenciesExchangeList": {
      "get": {
        "summary": "GetCurrenciesExchangeList",
        "description": "Retrieves a list of currencies with their exchange rates against your base currency (Dashboard Currency).",
        "operationId": "get-currencies-exchange-list",
        "tags": [
          "List"
        ],
        "parameters": [],
        "responses": {
          "200": {
            "description": "Successful response",
            "content": {
              "application/json": {
                "schema": {
                  "type": "array",
                  "description": "Array of currency exchange objects",
                  "items": {
                    "type": "object",
                    "properties": {
                      "Value": {
                        "type": "string",
                        "description": "The exchange rate value"
                      },
                      "Text": {
                        "type": "string",
                        "description": "The currency text"
                      }
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": [
                      {
                        "Value": "1.00000000",
                        "Text": "KWD"
                      },
                      {
                        "Value": "12.35100000",
                        "Text": "SAR"
                      },
                      {
                        "Value": "1.23600000",
                        "Text": "BHD"
                      },
                      {
                        "Value": "12.03300000",
                        "Text": "AED"
                      },
                      {
                        "Value": "12.21800000",
                        "Text": "QAR"
                      },
                      {
                        "Value": "1.26100000",
                        "Text": "OMR"
                      },
                      {
                        "Value": "2.35000000",
                        "Text": "JOD"
                      },
                      {
                        "Value": "3.24000000",
                        "Text": "USD"
                      },
                      {
                        "Value": "3.08000000",
                        "Text": "EUR"
                      },
                      {
                        "Value": "85.35100000",
                        "Text": "EGP"
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

## GetDepositedInvoices

*`https://docs.myfatoorah.com/reference/get-deposited-invoices` — updated 2026-04-21*

> Retrieves the list of invoices included in a specific deposit by deposit reference.

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "tags": [
    {
      "name": "Reports",
      "description": "Reporting operations for retrieving your data in the MyFatoorah system"
    }
  ],
  "paths": {
    "/v2/GetDepositedInvoices": {
      "post": {
        "summary": "GetDepositedInvoices",
        "description": "Retrieves the list of invoices included in a specific deposit by deposit reference.",
        "operationId": "get-deposited-invoices",
        "tags": [
          "Reports"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "DepositReference"
                ],
                "properties": {
                  "DepositReference": {
                    "type": "string",
                    "description": "The Deposit Reference you would like to receive its invoices."
                  },
                  "Type": {
                    "type": "string",
                    "description": "Vendor (Default): The deposit reference belongs to the vendor.  \nSupplier: The deposit reference belongs to a supplier.",
                    "enum": [
                      "Vendor",
                      "Supplier"
                    ]
                  },
                  "SupplierCode": {
                    "type": "integer",
                    "description": "The supplier code to which the deposit reference belongs. Mandatory if Type value is Supplier."
                  }
                }
              },
              "examples": {
                "vendor": {
                  "value": {
                    "DepositReference": "2023000011",
                    "Type": "Vendor"
                  }
                },
                "supplier": {
                  "value": {
                    "DepositReference": "2023000204",
                    "Type": "Supplier",
                    "SupplierCode": 1
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Successful response",
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "IsSuccess": {
                      "type": "boolean",
                      "description": "Indicates if the request was successful"
                    },
                    "Message": {
                      "type": "string",
                      "description": "Response message"
                    },
                    "ValidationErrors": {
                      "type": "array",
                      "description": "List of validation errors if any",
                      "items": {
                        "type": "object",
                        "properties": {
                          "Name": {
                            "type": "string"
                          },
                          "Error": {
                            "type": "string"
                          }
                        }
                      }
                    },
                    "Data": {
                      "type": "array",
                      "description": "List of invoice details included in the deposit",
                      "items": {
                        "type": "object",
                        "properties": {
                          "InvoiceId": {
                            "type": "number",
                            "description": "InvoiceId"
                          },
                          "PaymentGateway": {
                            "type": "string",
                            "description": "Payment gateway used"
                          },
                          "PaymentId": {
                            "type": "string"
                          },
                          "InvoiceReference": {
                            "type": "string"
                          },
                          "CustomerReference": {
                            "type": "string"
                          },
                          "CreatedDate": {
                            "type": "string",
                            "format": "date-time",
                            "description": "Date when the invoice was created"
                          },
                          "InvoiceValue": {
                            "type": "string"
                          },
                          "PaidCurrencyValue": {
                            "type": "string",
                            "description": "Amount paid in the payment currency"
                          },
                          "PaidCurrency": {
                            "type": "string",
                            "description": "Currency code of the payment"
                          },
                          "InvoiceDisplayValue": {
                            "type": "string"
                          },
                          "DueValue": {
                            "type": "string",
                            "description": "The total amount of the invoice"
                          },
                          "PaidDate": {
                            "type": "string",
                            "format": "date-time",
                            "description": "Date when the payment was made"
                          },
                          "TotalServiceCharge": {
                            "type": "string",
                            "description": "Transaction service charge"
                          },
                          "DueDeposit": {
                            "type": "string",
                            "description": "The amount received by the vendor/supplier"
                          },
                          "DepositReference": {
                            "type": "string"
                          },
                          "DepositDate": {
                            "type": "string",
                            "format": "date-time",
                            "description": "Date when the deposit was made"
                          }
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": {
                      "IsSuccess": true,
                      "Message": null,
                      "ValidationErrors": null,
                      "Data": [
                        {
                          "InvoiceId": 2608271,
                          "PaymentGateway": "VISA/MASTER",
                          "PaymentId": "07072608271167936573",
                          "InvoiceReference": "2023004944",
                          "CustomerReference": null,
                          "CreatedDate": "2023-08-14T09:13:50.197",
                          "InvoiceValue": "232.500",
                          "PaidCurrencyValue": "232.500",
                          "PaidCurrency": "KD",
                          "InvoiceDisplayValue": "232.500",
                          "DueValue": "232.500",
                          "PaidDate": "2023-08-14T09:13:52.103",
                          "TotalServiceCharge": "6.975",
                          "DueDeposit": "224.479",
                          "DepositReference": "2023000011",
                          "DepositDate": "2023-08-14T09:26:02.237"
                        },
                        {
                          "InvoiceId": 2608272,
                          "PaymentGateway": "VISA/MASTER",
                          "PaymentId": "07072608272167936673",
                          "InvoiceReference": "2023004945",
                          "CustomerReference": null,
                          "CreatedDate": "2023-08-14T09:13:52.247",
                          "InvoiceValue": "232.500",
                          "PaidCurrencyValue": "232.500",
                          "PaidCurrency": "KD",
                          "InvoiceDisplayValue": "232.500",
                          "DueValue": "232.500",
                          "PaidDate": "2023-08-14T09:13:53.15",
                          "TotalServiceCharge": "6.975",
                          "DueDeposit": "224.479",
                          "DepositReference": "2023000011",
                          "DepositDate": "2023-08-14T09:26:02.237"
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

## GetWebhooks

*`https://docs.myfatoorah.com/reference/get-webhooks` — updated 2026-04-21*

> Retrieves all webhook events triggered by MyFatoorah to your endpoint based on webhook configuration and delivery status. Useful for retrieving missed webhook events if your server was down or slow.

<HTMLBlock>{`
<style>

  .TryItpmla7eyfx5CX {

    display: none;

  }
</style>
`}</HTMLBlock>

<br />

### OpenAPI definition

```json
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "Myfatoorah Api V2"
  },
  "servers": [
    {
      "url": "https://apitest.myfatoorah.com"
    }
  ],
  "tags": [
    {
      "name": "Webhook",
      "description": "Webhook operations for retrieving webhook event logs and delivery status"
    }
  ],
  "paths": {
    "/v2/GetWebhooks": {
      "post": {
        "summary": "GetWebhooks",
        "description": "Retrieves all webhook events triggered by MyFatoorah to your endpoint based on webhook configuration and delivery status. Useful for retrieving missed webhook events if your server was down or slow.",
        "operationId": "get-webhooks",
        "tags": [
          "Webhook"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "Start": {
                    "type": "string",
                    "format": "date-time",
                    "description": "Start date for filtering webhook events (format: ISO 8601) in UTC Time zone"
                  },
                  "End": {
                    "type": "string",
                    "format": "date-time",
                    "description": "End date for filtering webhook events (format: ISO 8601) in UTC Time zone"
                  },
                  "Page": {
                    "type": "integer",
                    "description": "Page number for pagination (1-based). The number of events in a single page is 500 events."
                  },
                  "EventType": {
                    "type": "string",
                    "description": "Webhook V1: TransactionsStatusChanged, RefundStatusChanged, RecurringStatusChanged, BalanceTransferred, SupplierStatusChanged, DisputeStatusChanged. \n\nWebhook V2: PAYMENT_STATUS_CHANGED, REFUND_STATUS_CHANGED, RECURRING_UPDATES, BALANCE_TRANSFERRED, SUPPLIER_STATUS_CHANGED, DISPUTE_STATUS_CHANGED",
                    "enum": [
                      "TransactionsStatusChanged",
                      "RefundStatusChanged",
                      "RecurringStatusChanged",
                      "BalanceTransferred",
                      "SupplierStatusChanged",
                      "DisputeStatusChanged",
                      "PAYMENT_STATUS_CHANGED",
                      "REFUND_STATUS_CHANGED",
                      "RECURRING_UPDATES",
                      "BALANCE_TRANSFERRED",
                      "SUPPLIER_STATUS_CHANGED",
                      "DISPUTE_STATUS_CHANGED"
                    ]
                  },
                  "Status": {
                    "type": "string",
                    "description": "Status of webhook attempts to filter: Waiting (not yet triggered from MyFatoorah), Running (The webhook is triggered from MyFatoorah but we are still attempting to reach your endpoint till we get success status code or all the retries are consumed.), Succeeded (successfully received), Failed (Your server failed to receive the webhook and all the retries are consumed.)",
                    "enum": [
                      "Waiting",
                      "Running",
                      "Succeeded",
                      "Failed"
                    ]
                  },
                  "Key": {
                    "type": "array",
                    "description": "Array of Keys based on the KeyType",
                    "items": {
                      "type": "string"
                    }
                  },
                  "KeyType": {
                    "type": "string",
                    "description": "InvoiceId: Filters for Payment Webhook with this InvoiceId. \nCustomerReference: Filters for Payment Webhook with this CustomerReference. \nWebhookReference: Filters for webhook events with this Webhook Reference",
                    "enum": [
                      "InvoiceId",
                      "CustomerReference",
                      "WebhookReference"
                    ]
                  }
                }
              },
              "examples": {
                "Request": {
                  "value": {
                    "Start": "2024-02-12T00:16:29.650Z",
                    "End": "2025-02-12T20:43:43.848Z",
                    "Status": "Failed"
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Successful response",
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "IsSuccess": {
                      "type": "boolean",
                      "description": "Indicates if the request was successful"
                    },
                    "Message": {
                      "type": "string",
                      "description": "Status or result message"
                    },
                    "ValidationErrors": {
                      "type": "array",
                      "description": "List of validation errors if any",
                      "items": {
                        "type": "object",
                        "properties": {
                          "Name": {
                            "type": "string"
                          },
                          "Error": {
                            "type": "string"
                          }
                        }
                      }
                    },
                    "Data": {
                      "type": "object",
                      "description": "Contains the response details",
                      "properties": {
                        "Items": {
                          "type": "array",
                          "description": "List of webhook event log items",
                          "items": {
                            "type": "object",
                            "properties": {
                              "EndPoint": {
                                "type": "string",
                                "description": "The URL where the webhook notification was sent"
                              },
                              "Signature": {
                                "type": "string",
                                "description": "Signature for verifying the webhook payload"
                              },
                              "EventCode": {
                                "type": "integer",
                                "description": "Numeric code for the event type"
                              },
                              "EventName": {
                                "type": "string",
                                "description": "Name of the event (V1: TransactionsStatusChanged, RefundStatusChanged, etc. V2: PAYMENT_STATUS_CHANGED, REFUND_STATUS_CHANGED, etc.)"
                              },
                              "EventEntityId": {
                                "type": "string",
                                "description": "Identifier for the entity associated with the event"
                              },
                              "WebhookReference": {
                                "type": "string",
                                "description": "Unique reference for the webhook event"
                              },
                              "Data": {
                                "type": "object",
                                "description": "The webhook data content"
                              },
                              "Status": {
                                "type": "string",
                                "description": "Status of the webhook event delivery (Waiting, Running, Succeeded, Failed)"
                              },
                              "Attempts": {
                                "type": "array",
                                "description": "List of delivery attempt objects",
                                "items": {
                                  "type": "object",
                                  "properties": {
                                    "Date": {
                                      "type": "string",
                                      "format": "date-time",
                                      "description": "Datetime of the attempt (format: ISO 8601) in UTC Time zone"
                                    },
                                    "Status": {
                                      "type": "integer",
                                      "description": "The status code received from your server for the attempt"
                                    },
                                    "Response Message": {
                                      "type": "string",
                                      "description": "The message corresponding to the status code received"
                                    },
                                    "Duration": {
                                      "type": "string",
                                      "description": "The number of seconds it took for your server to respond to the attempt"
                                    }
                                  }
                                }
                              }
                            }
                          }
                        },
                        "Pagination": {
                          "type": "object",
                          "description": "Pagination information",
                          "properties": {
                            "PageSize": {
                              "type": "integer",
                              "description": "The size of the page (Fixed: 500)"
                            },
                            "PageNumber": {
                              "type": "integer",
                              "description": "The page you are currently on"
                            },
                            "PagesCount": {
                              "type": "integer",
                              "description": "The number of pages with webhook events matching the filters"
                            },
                            "ItemsCount": {
                              "type": "integer",
                              "description": "The number of webhook events matching the filters"
                            }
                          }
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": {
                      "IsSuccess": true,
                      "Message": "",
                      "ValidationErrors": null,
                      "Data": {
                        "Items": [
                          {
                            "EndPoint": "https://webhook.site/2a9d88c8-1329-4ad4-a163-57cac6986547",
                            "Signature": "N+TPuke9hEn963L1zQbXF3zSR2zx7O/RgM4eCavfP8A=",
                            "EventCode": 1,
                            "EventName": "TransactionsStatusChanged",
                            "EventEntityId": "5131277",
                            "WebhookReference": "WH-24349",
                            "Data": {
                              "InvoiceId": 5131277,
                              "InvoiceReference": "2025000148",
                              "CreatedDate": "12022025154151",
                              "CustomerReference": "rLVgyA7C6v19bHwpXEIDrA5wW",
                              "CustomerName": "sdadsaas sad Ggaga",
                              "CustomerMobile": "+966",
                              "CustomerEmail": "test@test.com",
                              "TransactionStatus": "SUCCESS",
                              "PaymentMethod": "VISA/MASTER",
                              "UserDefinedField": "rLVgyA7C6v19bHwpXEIDrA5wW",
                              "ReferenceId": "504312229134",
                              "TrackId": "12-02-2025_2485915",
                              "PaymentId": "07075131277248591573",
                              "AuthorizationId": "229134",
                              "InvoiceValueInBaseCurrency": "112.185",
                              "BaseCurrency": "KWD",
                              "InvoiceValueInDisplayCurreny": "30.29",
                              "DisplayCurrency": "USD",
                              "InvoiceValueInPayCurrency": "112.19",
                              "PayCurrency": "SAR"
                            },
                            "Status": "Failed",
                            "Attempts": [
                              {
                                "Date": "2025-02-12T12:42:16.1218556Z",
                                "Status": 404,
                                "ResponseMessage": "Not Found",
                                "Duration": "0.056"
                              },
                              {
                                "Date": "2025-02-12T12:42:51.2235299Z",
                                "Status": 404,
                                "ResponseMessage": "Not Found",
                                "Duration": "0.052"
                              },
                              {
                                "Date": "2025-02-12T12:43:21.2716528Z",
                                "Status": 404,
                                "ResponseMessage": "Not Found",
                                "Duration": "0.073"
                              },
                              {
                                "Date": "2025-02-12T12:43:51.2951604Z",
                                "Status": 404,
                                "ResponseMessage": "Not Found",
                                "Duration": "0.048"
                              }
                            ]
                          },
                          {
                            "EndPoint": "https://webhook.site/2a9d88c8-1329-4ad4-a163-57cac6986547",
                            "Signature": "QcNL1eSkVJuXqsC5zF7bCzKfn0c+DuFWP/LFQlRkrjQ=",
                            "EventCode": 1,
                            "EventName": "TransactionsStatusChanged",
                            "EventEntityId": "5131253",
                            "WebhookReference": "WH-24344",
                            "Data": {
                              "InvoiceId": 5131253,
                              "InvoiceReference": "2025000147",
                              "CreatedDate": "12022025153902",
                              "CustomerReference": "rt3V3n4nBpSdkF5HQLsRs52NV",
                              "CustomerName": "sdadsaas sad Ggaga",
                              "CustomerMobile": "+966",
                              "CustomerEmail": "test@test.com",
                              "TransactionStatus": "SUCCESS",
                              "PaymentMethod": "VISA/MASTER",
                              "UserDefinedField": "rt3V3n4nBpSdkF5HQLsRs52NV",
                              "ReferenceId": "504312227034",
                              "TrackId": "12-02-2025_2485907",
                              "PaymentId": "07075131253248590772",
                              "AuthorizationId": "227034",
                              "InvoiceValueInBaseCurrency": "260.444",
                              "BaseCurrency": "KWD",
                              "InvoiceValueInDisplayCurreny": "70.32",
                              "DisplayCurrency": "USD",
                              "InvoiceValueInPayCurrency": "260.45",
                              "PayCurrency": "SAR"
                            },
                            "Status": "Failed",
                            "Attempts": [
                              {
                                "Date": "2025-02-12T12:39:16.9598573Z",
                                "Status": 400,
                                "ResponseMessage": "Bad Request",
                                "Duration": "0.066"
                              },
                              {
                                "Date": "2025-02-12T12:39:51.0342446Z",
                                "Status": 400,
                                "ResponseMessage": "Bad Request",
                                "Duration": "0.048"
                              },
                              {
                                "Date": "2025-02-12T12:40:36.0741091Z",
                                "Status": 400,
                                "ResponseMessage": "Bad Request",
                                "Duration": "0.073"
                              },
                              {
                                "Date": "2025-02-12T12:41:06.1305213Z",
                                "Status": 400,
                                "ResponseMessage": "Bad Request",
                                "Duration": "0.048"
                              }
                            ]
                          }
                        ],
                        "Pagination": {
                          "PageSize": 500,
                          "PageNumber": 1,
                          "PagesCount": 1,
                          "ItemsCount": 2
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
