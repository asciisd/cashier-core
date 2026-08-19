# MyFatoorah — API reference — suppliers

## CreateSupplier

*`https://docs.myfatoorah.com/reference/create-supplier` — updated 2026-04-21*

> Adds a new supplier to your MyFatoorah account. This endpoint is used to create suppliers for multi-vendor functionality, and determine supplier's basic settings.

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
      "name": "Supplier",
      "description": "Supplier management operations for multi-vendor functionality"
    }
  ],
  "paths": {
    "/v2/CreateSupplier": {
      "post": {
        "summary": "CreateSupplier",
        "description": "Adds a new supplier to your MyFatoorah account. This endpoint is used to create suppliers for multi-vendor functionality, and determine supplier's basic settings.",
        "operationId": "create-supplier",
        "tags": [
          "Supplier"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "SupplierName",
                  "Mobile",
                  "Email"
                ],
                "properties": {
                  "SupplierName": {
                    "type": "string",
                    "description": "The name of the supplier"
                  },
                  "Mobile": {
                    "type": "string",
                    "description": "The mobile number of the supplier"
                  },
                  "Email": {
                    "type": "string",
                    "format": "email",
                    "description": "The email address of the supplier"
                  },
                  "CommissionValue": {
                    "type": "number",
                    "description": "A fixed value which will be deducted from each transaction"
                  },
                  "CommissionPercentage": {
                    "type": "number",
                    "description": "A percentage value which will be deducted from each transaction"
                  },
                  "IsPercentageOfNetValue": {
                    "type": "boolean",
                    "description": "true: deduct the percentage from the (total amount - transaction fees), false: deduct the percentage from the total amount. Affects only in case of one supplier in the request"
                  },
                  "DepositTerms": {
                    "type": "string",
                    "enum": [
                      "Daily",
                      "Weekly",
                      "Monthly",
                      "OnDemand"
                    ],
                    "description": "Daily for daily deposits, Weekly for weekly deposits, Monthly for monthly deposits, OnDemand for on hold deposits"
                  },
                  "DepositDay": {
                    "type": "string",
                    "description": "Specifies on which day the deposit should take place. Effective only for weekly and monthly. Weekly: values between 1 and 5 (Sunday to Thursday), can enter multiple days separated by comma. Monthly: values between 1 and 30, accepts only a single value"
                  },
                  "BankId": {
                    "type": "integer",
                    "description": "Must be from the bank list received from GetBanks"
                  },
                  "BankAccountHolderName": {
                    "type": "string",
                    "description": "Should be string without any special characters or numbers"
                  },
                  "BankAccount": {
                    "type": "string",
                    "description": "Bank account number, only numbers"
                  },
                  "Iban": {
                    "type": "string",
                    "description": "Should be valid IBAN"
                  },
                  "IsActive": {
                    "type": "boolean",
                    "description": "Indicates if the supplier is active"
                  },
                  "LogoFile": {
                    "type": "object",
                    "description": "Logo file for the supplier",
                    "properties": {
                      "FileName": {
                        "type": "string",
                        "description": "The file name"
                      },
                      "MediaType": {
                        "type": "string",
                        "description": "The media type"
                      },
                      "Buffer": {
                        "type": "string",
                        "description": "The file buffer"
                      }
                    }
                  },
                  "DisplaySupplierDetails": {
                    "type": "boolean",
                    "description": "true: The details of the suppliers will be displayed on the invoice page instead of the vendor. false: The vendor details will be displayed on the invoice. This is effective only if there is one supplier in the invoice"
                  },
                  "BusinessName": {
                    "type": "string",
                    "description": "The name of the business that will be displayed on the invoice"
                  },
                  "BusinessType": {
                    "type": "number",
                    "enum": [
                      1,
                      2
                    ],
                    "description": "1 for Home Business, 2 for Company"
                  }
                }
              },
              "examples": {
                "example1": {
                  "value": {
                    "SupplierName": "Lonny Williamson",
                    "Mobile": "374-415-4939",
                    "Email": "Carter.Hammes43@hotmail.com",
                    "CommissionValue": 0.5,
                    "IsPercentageOfNetValue": "true",
                    "CommissionPercentage": 2,
                    "DepositTerms": "Daily",
                    "BankId": "1",
                    "BankAccountHolderName": "Margarita Beer",
                    "BankAccount": "12345",
                    "Iban": "KW76KSFM1197681842334764641317",
                    "IsActive": "false",
                    "BusinessType": 1
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
                    "FieldsErrors": {
                      "type": "array",
                      "description": "List of validation errors if any"
                    },
                    "Data": {
                      "type": "object",
                      "description": "Supplier creation result",
                      "properties": {
                        "SupplierCode": {
                          "type": "integer",
                          "description": "The unique code assigned to the supplier"
                        },
                        "SupplierEmail": {
                          "type": "string",
                          "description": "The email address of the supplier"
                        },
                        "Date": {
                          "type": "string",
                          "description": "The date when the supplier was created"
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": {
                      "IsSuccess": true,
                      "Message": "The Supplier Created Successfully!",
                      "FieldsErrors": null,
                      "Data": {
                        "SupplierCode": 175,
                        "SupplierEmail": "Carter.Hammes43@hotmail.com",
                        "Date": "2024-03-20T12:04:49.3812998+03:00"
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

## EditSupplier

*`https://docs.myfatoorah.com/reference/edit-supplier` — updated 2026-04-21*

> Edits information about a certain supplier. If the supplier is approved, the request to update the supplier will be reviewed first by MyFatoorah team before approving or rejecting it. While a request is under review, you cannot create another request. Upon approval or rejection of the changes, you will receive a webhook.

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
      "name": "Supplier",
      "description": "Supplier management operations for multi-vendor functionality"
    }
  ],
  "paths": {
    "/v2/EditSupplier": {
      "post": {
        "summary": "EditSupplier",
        "description": "Edits information about a certain supplier. If the supplier is approved, the request to update the supplier will be reviewed first by MyFatoorah team before approving or rejecting it. While a request is under review, you cannot create another request. Upon approval or rejection of the changes, you will receive a webhook.",
        "operationId": "edit-supplier",
        "tags": [
          "Supplier"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "SupplierCode",
                  "SupplierName",
                  "Mobile",
                  "Email"
                ],
                "properties": {
                  "SupplierCode": {
                    "type": "integer",
                    "description": "The unique code of the supplier to edit"
                  },
                  "SupplierName": {
                    "type": "string",
                    "description": "The name of the supplier"
                  },
                  "Mobile": {
                    "type": "string",
                    "description": "The mobile number of the supplier"
                  },
                  "Email": {
                    "type": "string",
                    "format": "email",
                    "description": "The email address of the supplier"
                  },
                  "CommissionValue": {
                    "type": "number",
                    "description": "A fixed value that will be deducted from each transaction"
                  },
                  "CommissionPercentage": {
                    "type": "number",
                    "description": "A percentage value that will be deducted from each transaction"
                  },
                  "IsPercentageOfNetValue": {
                    "type": "boolean",
                    "description": "true: deduct the percentage from the (total amount - transaction fees), false: deduct the percentage from the total amount. Affects only in case of one supplier in the request"
                  },
                  "DepositTerms": {
                    "type": "string",
                    "enum": [
                      "Daily",
                      "Weekly",
                      "Monthly",
                      "OnDemand"
                    ],
                    "description": "Daily for daily deposits, Weekly for weekly deposits, Monthly for monthly deposits, OnDemand for on hold deposits"
                  },
                  "DepositDay": {
                    "type": "string",
                    "description": "It is accepted in case of Weekly and Monthly. Weekly: You can pass values between 1 and 5. You can add multiple values. Monthly: You can pass values between 1 and 30. It accepts only a single value"
                  },
                  "BankId": {
                    "type": "integer",
                    "description": "Must be from the bank list received from GetBanks"
                  },
                  "BankAccountHolderName": {
                    "type": "string",
                    "description": "Should be string without any special characters or numbers"
                  },
                  "BankAccount": {
                    "type": "string",
                    "description": "Bank account number, only numbers"
                  },
                  "Iban": {
                    "type": "string",
                    "description": "Should be valid IBAN"
                  },
                  "LogoFile": {
                    "type": "object",
                    "description": "Logo file for the supplier",
                    "properties": {
                      "FileName": {
                        "type": "string",
                        "description": "The file name"
                      },
                      "MediaType": {
                        "type": "string",
                        "description": "The media type"
                      },
                      "Buffer": {
                        "type": "string",
                        "description": "The file buffer"
                      }
                    }
                  },
                  "BusinessName": {
                    "type": "string",
                    "description": "The name of the business"
                  },
                  "DisplaySupplierDetails": {
                    "type": "boolean",
                    "description": "Indicates if supplier details should be displayed on the invoice"
                  }
                }
              },
              "examples": {
                "example1": {
                  "value": {
                    "SupplierCode": 115,
                    "SupplierName": "supplier_name",
                    "Mobile": "string",
                    "Email": "a@b.xyz",
                    "CommissionValue": 0,
                    "CommissionPercentage": 0,
                    "DepositTerms": "Daily"
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
                    "FieldsErrors": {
                      "type": "array",
                      "description": "List of validation errors if any"
                    },
                    "Data": {
                      "type": "object",
                      "description": "Supplier edit result",
                      "properties": {
                        "SupplierCode": {
                          "type": "integer",
                          "description": "The unique code of the supplier"
                        },
                        "SupplierEmail": {
                          "type": "string",
                          "description": "The email address of the supplier"
                        },
                        "Date": {
                          "type": "string",
                          "description": "The date when the supplier was updated"
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": {
                      "IsSuccess": true,
                      "Message": "The Supplier Updated Successfully!",
                      "FieldsErrors": null,
                      "Data": {
                        "SupplierCode": 115,
                        "SupplierEmail": "a@b.xyz",
                        "Date": "2020-11-24T11:08:00.2220936+03:00"
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

## CustomizeSupplierCommissions

*`https://docs.myfatoorah.com/reference/customize-supplier-commissions` — updated 2026-04-21*

> Sets a customized commission for the supplier based on the payment method that will be used to make the payment. This allows different commission structures for different payment methods.

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
      "name": "Supplier",
      "description": "Supplier management operations for multi-vendor functionality"
    }
  ],
  "paths": {
    "/v2/CustomizeSupplierCommissions": {
      "post": {
        "summary": "CustomizeSupplierCommissions",
        "description": "Sets a customized commission for the supplier based on the payment method that will be used to make the payment. This allows different commission structures for different payment methods.",
        "operationId": "customize-supplier-commissions",
        "tags": [
          "Supplier"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "SupplierCode"
                ],
                "properties": {
                  "SupplierCode": {
                    "type": "integer",
                    "description": "The unique code of the supplier"
                  },
                  "SupplierCommissions": {
                    "type": "array",
                    "description": "Array of supplier commission configurations for different payment methods",
                    "items": {
                      "type": "object",
                      "properties": {
                        "PaymentMethodId": {
                          "type": "integer",
                          "description": "The payment method ID"
                        },
                        "CommissionValue": {
                          "type": "number",
                          "description": "A fixed value that will be deducted from each transaction"
                        },
                        "CommissionPercentage": {
                          "type": "number",
                          "description": "A percentage value that will be deducted from each transaction"
                        },
                        "IsPercentageOfNetValue": {
                          "type": "boolean",
                          "description": "true: deduct the percentage from the (total amount - transaction fees), false: deduct the percentage from the total amount. Affects only in case of one supplier in the request"
                        }
                      }
                    }
                  }
                }
              },
              "examples": {
                "example1": {
                  "value": {
                    "SupplierCode": 11,
                    "SupplierCommissions": [
                      {
                        "PaymentMethodId": 1,
                        "CommissionValue": 1,
                        "CommissionPercentage": 1,
                        "IsPercentageOfNetValue": true
                      },
                      {
                        "PaymentMethodId": 11,
                        "CommissionValue": 0.1,
                        "CommissionPercentage": 10,
                        "IsPercentageOfNetValue": false
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
                    "FieldsErrors": {
                      "type": "array",
                      "description": "List of validation errors if any"
                    },
                    "Data": {
                      "type": "object",
                      "description": "Supplier commission customization result",
                      "properties": {
                        "SupplierCode": {
                          "type": "integer",
                          "description": "The unique code of the supplier"
                        },
                        "SupplierEmail": {
                          "type": "string",
                          "description": "The email address of the supplier"
                        },
                        "Date": {
                          "type": "string",
                          "description": "The date when the commission was customized"
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": {
                      "IsSuccess": true,
                      "Message": "The Supplier Updated Successfully!",
                      "FieldsErrors": null,
                      "Data": {
                        "SupplierCode": 11,
                        "SupplierEmail": "test@test.com",
                        "Date": "2022-06-21T12:25:00.94"
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

## TransferBalance

*`https://docs.myfatoorah.com/reference/transfer-balance` — updated 2026-04-21*

> Transfers a balance from or to the available balance of a supplier. This endpoint works with a single supplier at each request and is specifically designed for the Multi-Vendors feature.

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
      "name": "Supplier",
      "description": "Supplier management operations for multi-vendor functionality"
    }
  ],
  "paths": {
    "/v2/TransferBalance": {
      "post": {
        "summary": "TransferBalance",
        "description": "Transfers a balance from or to the available balance of a supplier. This endpoint works with a single supplier at each request and is specifically designed for the Multi-Vendors feature.",
        "operationId": "transfer-balance",
        "tags": [
          "Supplier"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "SupplierCode",
                  "TransferAmount",
                  "TransferType"
                ],
                "properties": {
                  "SupplierCode": {
                    "type": "integer",
                    "description": "The supplier code you need to associate the invoice with"
                  },
                  "TransferAmount": {
                    "type": "number",
                    "description": "The amount that will be transferred to or from the supplier"
                  },
                  "TransferType": {
                    "type": "string",
                    "enum": [
                      "pull",
                      "push"
                    ],
                    "description": "pull: The balance transfers from supplier to vendor. push: The balance transfers from vendor to supplier"
                  },
                  "InternalNotes": {
                    "type": "string",
                    "description": "Extra comments for your reference (optional)"
                  }
                }
              },
              "examples": {
                "pull": {
                  "summary": "Transfer from supplier to vendor",
                  "value": {
                    "SupplierCode": 33,
                    "TransferAmount": 100,
                    "TransferType": "pull",
                    "InternalNotes": "withdraw from the supplier 33 to the vendor."
                  }
                },
                "push": {
                  "summary": "Transfer from vendor to supplier",
                  "value": {
                    "SupplierCode": 33,
                    "TransferAmount": 100,
                    "TransferType": "push",
                    "InternalNotes": "withdraw from the vendor to the supplier 33."
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
                    "FieldsErrors": {
                      "type": "array",
                      "description": "List of validation errors if any"
                    },
                    "Data": {
                      "type": "object",
                      "description": "Balance transfer result",
                      "properties": {
                        "InvoiceId": {
                          "type": "integer",
                          "description": "The invoice ID associated with the balance transfer"
                        },
                        "Date": {
                          "type": "string",
                          "description": "The date when the balance transfer was completed"
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": {
                      "IsSuccess": true,
                      "Message": "The balance transferred successfully",
                      "FieldsErrors": null,
                      "Data": {
                        "InvoiceId": 685852,
                        "Date": "2021-06-30T11:43:13.2533227+03:00"
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

## UploadSupplierDocument

*`https://docs.myfatoorah.com/reference/upload-supplier-document` — updated 2026-04-21*

> Uploads supplier documents. Files must be maximum 5 MB and in the following formats: .jpg, .jpeg, .png, .bmp, .gif, .xls, .xlsx, .pdf, .doc, .docx

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
      "name": "Supplier",
      "description": "Supplier management operations for multi-vendor functionality"
    }
  ],
  "paths": {
    "/v2/UploadSupplierDocument": {
      "put": {
        "summary": "UploadSupplierDocument",
        "description": "Uploads supplier documents. Files must be maximum 5 MB and in the following formats: .jpg, .jpeg, .png, .bmp, .gif, .xls, .xlsx, .pdf, .doc, .docx",
        "operationId": "upload-supplier-document",
        "tags": [
          "Supplier"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "FileUpload",
                  "FileType",
                  "SupplierCode"
                ],
                "properties": {
                  "FileUpload": {
                    "type": "object",
                    "description": "The file to upload",
                    "required": [
                      "FileName",
                      "MediaType",
                      "Buffer"
                    ],
                    "properties": {
                      "FileName": {
                        "type": "string",
                        "description": "The file name"
                      },
                      "MediaType": {
                        "type": "string",
                        "description": "The media type"
                      },
                      "Buffer": {
                        "type": "string",
                        "description": "The file buffer encoded in base64"
                      }
                    }
                  },
                  "FileType": {
                    "type": "integer",
                    "enum": [
                      1,
                      2,
                      3,
                      4,
                      5,
                      6,
                      7,
                      16,
                      17,
                      20,
                      21,
                      25,
                      26,
                      27,
                      28,
                      30
                    ],
                    "description": "1: Civil Id, 2: Commercial License, 3: Articles of Association, 4: Signature Authorization, 5: Others, 6: Civil Id Back, 7: Instagram, 16: Civil Ids Of All Owners, 17: Civil Id Of Manager, 20: Commercial Register, 21: Bank Account Letter, 25: Website, 26: 3rd Parties, 27: Basic regulations list (For charities only), 28: Board of Directors Agreement (For charities only), 30: National address"
                  },
                  "ExpireDate": {
                    "type": "string",
                    "format": "date-time",
                    "description": "The expiration date for the document"
                  },
                  "SupplierCode": {
                    "type": "integer",
                    "description": "The supplier code"
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
                      "description": "the status of your request"
                    },
                    "Message": {
                      "type": "string",
                      "description": "The message response associated with the request done. Returns the URL of the uploaded document"
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": {
                      "IsSuccess": true,
                      "Message": "https://mfstoragemedia.blob.core.windows.net/mfdmfiles/Files/Suppliers/63427/6f6c0a38-8446-483d-ae72-b2c01d970821.png"
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

## GetSuppliers

*`https://docs.myfatoorah.com/reference/get-suppliers` — updated 2026-04-21*

> Retrieves a list that contains full information about your suppliers.

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
      "name": "Supplier",
      "description": "Supplier management operations for multi-vendor functionality"
    }
  ],
  "paths": {
    "/v2/GetSuppliers": {
      "get": {
        "summary": "GetSuppliers",
        "description": "Retrieves a list that contains full information about your suppliers.",
        "operationId": "get-suppliers",
        "tags": [
          "Supplier"
        ],
        "parameters": [],
        "responses": {
          "200": {
            "description": "Successful response",
            "content": {
              "application/json": {
                "schema": {
                  "type": "array",
                  "description": "Array of supplier information objects",
                  "items": {
                    "type": "object",
                    "properties": {
                      "SupplierCode": {
                        "type": "integer",
                        "description": "The unique supplier code"
                      },
                      "SupplierName": {
                        "type": "string",
                        "description": "The supplier name"
                      },
                      "Mobile": {
                        "type": "string",
                        "description": "The supplier mobile number"
                      },
                      "Email": {
                        "type": "string",
                        "description": "The supplier email address"
                      },
                      "CommissionValue": {
                        "type": "number",
                        "description": "Fixed commission value deducted from each transaction"
                      },
                      "CommissionPercentage": {
                        "type": "number",
                        "description": "Percentage commission deducted from each transaction"
                      },
                      "DepositTerms": {
                        "type": "string",
                        "description": "Deposit frequency: Daily, Weekly, Monthly, or OnDemand"
                      },
                      "DepositDay": {
                        "type": "string",
                        "description": "The day on which the deposit takes place for Weekly or Monthly deposit terms"
                      },
                      "SupplierStatus": {
                        "type": "string",
                        "description": "Supplier status: Active, Pending, Rejected, Closed, or Dormant"
                      },
                      "Comment": {
                        "type": "string",
                        "description": "The reason for rejection (if the supplier is rejected)"
                      },
                      "IsPercentageOfNetValue": {
                        "type": "boolean",
                        "description": "Whether the CommissionPercentage is taken from the total amount paid or from the net value"
                      },
                      "BusinessName": {
                        "type": "string",
                        "description": "The name displayed on MyFatoorah invoices if DisplaySupplierDetails is true"
                      },
                      "DisplaySupplierDetails": {
                        "type": "boolean",
                        "description": "Show the supplier details on invoices instead of vendor details"
                      },
                      "SupplierCommissions": {
                        "type": "array",
                        "description": "Customized commissions made for payment methods for the supplier",
                        "items": {
                          "type": "object",
                          "properties": {
                            "PaymentMethodName": {
                              "type": "string",
                              "description": "Name of the payment method"
                            },
                            "CommissionValue": {
                              "type": "number",
                              "description": "Fixed commission value for this payment method"
                            },
                            "CommissionPercentage": {
                              "type": "number",
                              "description": "Percentage commission for this payment method"
                            },
                            "IsPercentageOfNetValue": {
                              "type": "string",
                              "description": "Whether percentage is from net value"
                            }
                          }
                        }
                      },
                      "BusinessCategory": {
                        "type": "object",
                        "description": "MCC details of the supplier",
                        "properties": {
                          "Code": {
                            "type": "string",
                            "description": "MCC code for the supplier"
                          },
                          "Name": {
                            "type": "string",
                            "description": "Name of the MCC"
                          }
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": [
                      {
                        "SupplierCode": 27,
                        "SupplierName": "Jada Williamson",
                        "Mobile": "863-322-8615",
                        "Email": "Sigrid_Hudson6@gmail.com",
                        "CommissionValue": 0.5,
                        "CommissionPercentage": 1,
                        "DepositTerms": "Daily",
                        "DepositDay": null,
                        "SupplierStatus": "Rejected",
                        "Comment": "سيشسيشيشس",
                        "IsPercentageOfNetValue": false,
                        "BusinessName": null,
                        "DisplaySupplierDetails": false,
                        "SupplierCommissions": [],
                        "BusinessCategory": {
                          "Code": "5941",
                          "Name": "Camping products"
                        }
                      },
                      {
                        "SupplierCode": 53,
                        "SupplierName": "dasd sads",
                        "Mobile": "0555555555",
                        "Email": "test@test.cas",
                        "CommissionValue": 1,
                        "CommissionPercentage": 1,
                        "DepositTerms": "Daily",
                        "DepositDay": null,
                        "SupplierStatus": "Pending",
                        "Comment": "",
                        "IsPercentageOfNetValue": false,
                        "BusinessName": null,
                        "DisplaySupplierDetails": false,
                        "SupplierCommissions": [
                          {
                            "PaymentMethodName": "Apple Pay",
                            "CommissionValue": 1,
                            "CommissionPercentage": 3,
                            "IsPercentageOfNetValue": "False"
                          }
                        ],
                        "BusinessCategory": {
                          "Code": null,
                          "Name": null
                        }
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

## GetSupplierDetails

*`https://docs.myfatoorah.com/reference/get-supplier-details` — updated 2026-04-21*

> Retrieves full information about a specific supplier.

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
      "name": "Supplier",
      "description": "Supplier management operations for multi-vendor functionality"
    }
  ],
  "paths": {
    "/v2/GetSupplierDetails": {
      "get": {
        "summary": "GetSupplierDetails",
        "description": "Retrieves full information about a specific supplier.",
        "operationId": "get-supplier-details",
        "tags": [
          "Supplier"
        ],
        "parameters": [
          {
            "name": "SupplierCode",
            "in": "query",
            "required": true,
            "schema": {
              "type": "integer"
            },
            "description": "The unique code of the supplier"
          }
        ],
        "responses": {
          "200": {
            "description": "Successful response",
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "SupplierCode": {
                      "type": "integer",
                      "description": "The unique supplier code"
                    },
                    "SupplierName": {
                      "type": "string",
                      "description": "The supplier name"
                    },
                    "Mobile": {
                      "type": "string",
                      "description": "The supplier mobile number"
                    },
                    "Email": {
                      "type": "string",
                      "description": "The supplier email address"
                    },
                    "CommissionValue": {
                      "type": "number",
                      "description": "Fixed commission value deducted from each transaction"
                    },
                    "CommissionPercentage": {
                      "type": "number",
                      "description": "Percentage commission deducted from each transaction"
                    },
                    "DepositTerms": {
                      "type": "string",
                      "description": "Deposit frequency: Daily, Weekly, Monthly, or OnDemand"
                    },
                    "DepositDay": {
                      "type": "string",
                      "description": "The day on which the deposit takes place for Weekly or Monthly deposit terms"
                    },
                    "SupplierStatus": {
                      "type": "string",
                      "description": "Supplier status: Active, Pending, Rejected, Closed, or Dormant"
                    },
                    "Comment": {
                      "type": "string",
                      "description": "The reason for rejection (if the supplier is rejected)"
                    },
                    "IsPercentageOfNetValue": {
                      "type": "boolean",
                      "description": "Whether the CommissionPercentage is taken from the total amount paid or from the net value"
                    },
                    "BusinessName": {
                      "type": "string",
                      "description": "The name displayed on MyFatoorah invoices if DisplaySupplierDetails is true"
                    },
                    "DisplaySupplierDetails": {
                      "type": "boolean",
                      "description": "Show the supplier details on invoices instead of vendor details"
                    },
                    "SupplierCommissions": {
                      "type": "array",
                      "description": "Customized commissions made for payment methods for the supplier",
                      "items": {
                        "type": "object",
                        "properties": {
                          "PaymentMethodName": {
                            "type": "string",
                            "description": "Name of the payment method"
                          },
                          "CommissionValue": {
                            "type": "number",
                            "description": "Fixed commission value for this payment method"
                          },
                          "CommissionPercentage": {
                            "type": "number",
                            "description": "Percentage commission for this payment method"
                          },
                          "IsPercentageOfNetValue": {
                            "type": "string",
                            "description": "Whether percentage is from net value"
                          }
                        }
                      }
                    },
                    "BusinessCategory": {
                      "type": "object",
                      "description": "MCC details of the supplier",
                      "properties": {
                        "Code": {
                          "type": "string",
                          "description": "MCC code for the supplier"
                        },
                        "Name": {
                          "type": "string",
                          "description": "Name of the MCC"
                        }
                      }
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": {
                      "SupplierCode": 1,
                      "SupplierName": "    Leuschke LLC",
                      "Mobile": "3582726778",
                      "Email": "Max_Hettinger@gmail.com",
                      "CommissionValue": 0,
                      "CommissionPercentage": 0,
                      "DepositTerms": "Daily",
                      "DepositDay": null,
                      "SupplierStatus": "Active",
                      "Comment": "",
                      "IsPercentageOfNetValue": false,
                      "BusinessName": null,
                      "DisplaySupplierDetails": false,
                      "SupplierCommissions": [
                        {
                          "PaymentMethodName": "Apple Pay",
                          "CommissionValue": 0.5,
                          "CommissionPercentage": 0,
                          "IsPercentageOfNetValue": "False"
                        }
                      ],
                      "BusinessCategory": {
                        "Code": "5941",
                        "Name": "Camping products"
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

## GetSupplierDeposits

*`https://docs.myfatoorah.com/reference/get-supplier-deposits` — updated 2026-04-21*

> Gets the deposit records of a supplier.

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
      "name": "Supplier",
      "description": "Supplier management operations for multi-vendor functionality"
    }
  ],
  "paths": {
    "/v2/GetSupplierDeposits": {
      "get": {
        "summary": "GetSupplierDeposits",
        "description": "Gets the deposit records of a supplier.",
        "operationId": "get-supplier-deposits",
        "tags": [
          "Supplier"
        ],
        "parameters": [
          {
            "name": "SupplierCode",
            "in": "query",
            "required": true,
            "schema": {
              "type": "integer"
            },
            "description": "The unique code of the supplier"
          },
          {
            "name": "search",
            "in": "query",
            "required": false,
            "schema": {
              "type": "string"
            }
          },
          {
            "name": "start",
            "in": "query",
            "required": false,
            "schema": {
              "type": "integer"
            }
          },
          {
            "name": "length",
            "in": "query",
            "required": false,
            "schema": {
              "type": "integer"
            }
          },
          {
            "name": "sortColumn",
            "in": "query",
            "required": false,
            "schema": {
              "type": "string"
            }
          },
          {
            "name": "sortDirection",
            "in": "query",
            "required": false,
            "schema": {
              "type": "string"
            }
          }
        ],
        "responses": {
          "200": {
            "description": "Successful response",
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "description": "Supplier deposit data",
                  "properties": {
                    "Records": {
                      "type": "integer",
                      "description": "Total number of deposit records"
                    },
                    "Data": {
                      "type": "array",
                      "description": "Array of deposit records",
                      "items": {
                        "type": "object"
                      }
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": {
                      "Records": 1,
                      "Data": [
                        {
                          "Iban": "AL35202111090000000001234567",
                          "DepositId": 183,
                          "VendorId": 123,
                          "DepositReference": "2022000183",
                          "BankName": "NBK",
                          "TotalValue": 542.575,
                          "DepositDate": "16/10/02022"
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

## GetSupplierDocuments

*`https://docs.myfatoorah.com/reference/get-supplier-documents` — updated 2026-04-21*

> Gets the supplier documents.

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
      "name": "Supplier",
      "description": "Supplier management operations for multi-vendor functionality"
    }
  ],
  "paths": {
    "/v2/GetSupplierDocuments": {
      "get": {
        "summary": "GetSupplierDocuments",
        "description": "Gets the supplier documents.",
        "operationId": "get-supplier-documents",
        "tags": [
          "Supplier"
        ],
        "parameters": [
          {
            "name": "SupplierCode",
            "in": "query",
            "required": true,
            "schema": {
              "type": "integer"
            },
            "description": "The unique code of the supplier"
          }
        ],
        "responses": {
          "200": {
            "description": "Successful response",
            "content": {
              "application/json": {
                "schema": {
                  "type": "array",
                  "description": "Array of supplier document objects",
                  "items": {
                    "type": "object",
                    "properties": {
                      "FileUrl": {
                        "type": "string",
                        "description": "The document URL"
                      },
                      "FileType": {
                        "type": "integer",
                        "enum": [
                          1,
                          2,
                          3,
                          4,
                          5,
                          6,
                          7,
                          16,
                          17,
                          20,
                          21,
                          25,
                          26,
                          27,
                          28
                        ],
                        "description": "1: Civil Id, 2: Commercial License, 3: Articles of Association, 4: Signature Authorization, 5: Others, 6: Civil ID Back, 7: Instagram, 16: Civil IDs of All Owners, 17: Civil ID of Manager, 20: Commercial Register, 21: Bank Account Letter, 25: Website, 26: 3rd Parties, 27: Basic regulations list (For charities only), 28: Board of Directors Agreement (For charities only)"
                      },
                      "FileTypeName": {
                        "type": "string",
                        "description": "The name of the file type. Can be: Commercial License, Signature Authorisation, Articles of Association, Civil ID, Civil ID back, 3-parties contract/agreement, and Others"
                      },
                      "ExpireDate": {
                        "type": "string",
                        "description": "The expiration date of the document"
                      }
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": [
                      {
                        "FileUrl": null,
                        "FileType": 2,
                        "FileTypeName": "Commercial License",
                        "ExpireDate": null
                      },
                      {
                        "FileUrl": null,
                        "FileType": 4,
                        "FileTypeName": "Signature Authorization",
                        "ExpireDate": null
                      },
                      {
                        "FileUrl": null,
                        "FileType": 3,
                        "FileTypeName": "Articles of Association",
                        "ExpireDate": null
                      },
                      {
                        "FileUrl": null,
                        "FileType": 20,
                        "FileTypeName": "Commercial Register",
                        "ExpireDate": null
                      },
                      {
                        "FileUrl": null,
                        "FileType": 16,
                        "FileTypeName": "Civil Ids Of All Owners",
                        "ExpireDate": null
                      },
                      {
                        "FileUrl": null,
                        "FileType": 17,
                        "FileTypeName": "Civil Id Of Manager",
                        "ExpireDate": null
                      },
                      {
                        "FileUrl": null,
                        "FileType": 21,
                        "FileTypeName": "Bank Account Letter",
                        "ExpireDate": null
                      },
                      {
                        "FileUrl": null,
                        "FileType": 25,
                        "FileTypeName": "Website",
                        "ExpireDate": null
                      },
                      {
                        "FileUrl": null,
                        "FileType": 26,
                        "FileTypeName": "3-parties contract/ agreement",
                        "ExpireDate": null
                      },
                      {
                        "FileUrl": null,
                        "FileType": 27,
                        "FileTypeName": "Basic regulations list < (For charities only) ",
                        "ExpireDate": null
                      },
                      {
                        "FileUrl": null,
                        "FileType": 28,
                        "FileTypeName": "Board of Directors Agreement  (For charities only) ",
                        "ExpireDate": null
                      },
                      {
                        "FileUrl": null,
                        "FileType": 5,
                        "FileTypeName": "Others",
                        "ExpireDate": null
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

## GetSupplierDashboard

*`https://docs.myfatoorah.com/reference/get-supplier-dashboard` — updated 2026-04-21*

> Gets the supplier dashboard information, including transaction statistics, balance details, and approval status.

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
      "name": "Supplier",
      "description": "Supplier management operations for multi-vendor functionality"
    }
  ],
  "paths": {
    "/v2/GetSupplierDashboard": {
      "get": {
        "summary": "GetSupplierDashboard",
        "description": "Gets the supplier dashboard information, including transaction statistics, balance details, and approval status.",
        "operationId": "get-supplier-dashboard",
        "tags": [
          "Supplier"
        ],
        "parameters": [
          {
            "name": "SupplierCode",
            "in": "query",
            "required": true,
            "schema": {
              "type": "integer"
            },
            "description": "The unique code of the supplier"
          }
        ],
        "responses": {
          "200": {
            "description": "Successful response",
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "TotalNumberOfTransactions": {
                      "type": "integer",
                      "description": "The count of transactions made for this supplier"
                    },
                    "TotalValueOfTransactions": {
                      "type": "number",
                      "description": "The sum of the InvoiceValue parameter of transactions made for this supplier. It indicates the total sum of invoices of transactions before deducting MyFatoorah fees and vendor fees. This is calculated from the perspective of the vendor"
                    },
                    "TotalSupplierInvoiceShare": {
                      "type": "number",
                      "description": "The sum of the InvoiceShare parameter of transactions made for this supplier. It indicates the sum of all supplier share values of transactions made for this supplier"
                    },
                    "TotalDepositedAmount": {
                      "type": "number",
                      "description": "The actual amounts deposited into the supplier's bank account"
                    },
                    "TotalAwaitingBalance": {
                      "type": "number",
                      "description": "The current awaiting balance for a specific supplier. It is the sum of supplier share values after deducting the MyFatoorah fees and the vendor fees. This amount is used in refunding or transferring the balance between the vendor and supplier. This amount will be available until the deposit terms interval is up"
                    },
                    "TotalAwaitingToTransfer": {
                      "type": "number",
                      "description": "After the deposit terms interval ends, the TotalAwaitingBalance becomes the TotalAwaitingToTransfer amount to be deposited in the supplier bank account"
                    },
                    "TotalBalance": {
                      "type": "number",
                      "description": "This represents the total amount in the supplier's MyFatoorah wallet. This amount includes amounts of invoices that are not approved yet"
                    },
                    "IsApproved": {
                      "type": "boolean",
                      "description": "The supplier approval status. It will be true if MyFatoorah approves the provided supplier"
                    },
                    "IsActive": {
                      "type": "boolean",
                      "description": "The supplier activity status"
                    }
                  }
                },
                "examples": {
                  "success": {
                    "value": {
                      "TotalAwaitingBalance": 44.758,
                      "TotalNumberOfTransactions": 28,
                      "TotalValueOfTransactions": 1766.736,
                      "TotalSupplierInvoiceShare": 1239.456,
                      "TotalDepositedAmount": 542.575,
                      "TotalAwaitingToTransfer": 0,
                      "TotalBalance": 44.758,
                      "IsApproved": true,
                      "IsActive": true
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

## MakeSupplierRefund

*`https://docs.myfatoorah.com/reference/make-supplier-refund` — updated 2026-04-21*

> Cancels a payment and returns the funds to the customer for invoices that have supplier information. This endpoint is specifically designed for the Multiple Suppliers feature and accepts invoices that contain one or more suppliers.

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
    "/v2/MakeSupplierRefund": {
      "post": {
        "summary": "MakeSupplierRefund",
        "description": "Cancels a payment and returns the funds to the customer for invoices that have supplier information. This endpoint is specifically designed for the Multiple Suppliers feature and accepts invoices that contain one or more suppliers.",
        "operationId": "make-supplier-refund",
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
                      "PaymentId"
                    ],
                    "description": "State either it's 'InvoiceId' or 'PaymentId' to identify the transaction to be refunded."
                  },
                  "Key": {
                    "type": "string",
                    "description": "Value of the key type mentioned. If KeyType is 'InvoiceId', provide the invoice ID. If KeyType is 'PaymentId', provide the payment ID."
                  },
                  "VendorDeductAmount": {
                    "type": "number",
                    "format": "decimal",
                    "description": "The amount that the vendor will refund from their balance."
                  },
                  "Comment": {
                    "type": "string",
                    "description": "Extra comments for your reference."
                  },
                  "ExternalIdentifier": {
                    "type": "string",
                    "description": "External data associated with the refund, which will be received in the webhook."
                  },
                  "Suppliers": {
                    "type": "array",
                    "description": "Array of RefundSupplier objects specifying the refund amounts for each supplier.",
                    "items": {
                      "type": "object",
                      "properties": {
                        "SupplierCode": {
                          "type": "integer",
                          "description": "The supplier code you need to associate the invoice with."
                        },
                        "SupplierDeductedAmount": {
                          "type": "number",
                          "format": "decimal",
                          "description": "The amount that the supplier will send back to the customer."
                        }
                      }
                    }
                  }
                }
              },
              "examples": {
                "Supplier Refund": {
                  "summary": "Refund with Supplier Information",
                  "value": {
                    "Key": "6424985",
                    "KeyType": "InvoiceId",
                    "VendorDeductAmount": 0,
                    "ExternalIdentifier": "refund-external-id",
                    "Comment": "refund-comment",
                    "Suppliers": [
                      {
                        "SupplierCode": 2,
                        "SupplierDeductedAmount": 5
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
            "description": "Supplier refund processed successfully",
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
                      "description": "Response message associated with the refund request."
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
                          "type": "number",
                          "description": "The InvoiceId of the refunded amount."
                        },
                        "Amount": {
                          "type": "string",
                          "description": "The amount needed to be refunded."
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
                    "summary": "Successful Supplier Refund",
                    "value": {
                      "IsSuccess": true,
                      "Message": "Refund Created Successfully!",
                      "ValidationErrors": null,
                      "Data": {
                        "Key": "6424985",
                        "RefundId": 246274,
                        "RefundReference": "2026000011",
                        "RefundInvoiceId": 6426234,
                        "ExternalIdentifier": "refund-external-id",
                        "Amount": 5,
                        "Comment": "refund-comment"
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
