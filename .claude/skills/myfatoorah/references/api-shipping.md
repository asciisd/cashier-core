# MyFatoorah — API reference — shipping

## GetCountries

*`https://docs.myfatoorah.com/reference/get-countries` — updated 2026-04-21*

> Retrieves a list of countries with their codes. This endpoint is used to get available countries for shipping operations.

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
      "name": "Shipping",
      "description": "Shipping operations"
    }
  ],
  "paths": {
    "/v2/GetCountries": {
      "get": {
        "summary": "GetCountries",
        "description": "Retrieves a list of countries with their codes. This endpoint is used to get available countries for shipping operations.",
        "operationId": "get-countries",
        "tags": [
          "Shipping"
        ],
        "parameters": [],
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
                      "type": "array",
                      "description": "List of countries",
                      "items": {
                        "type": "object",
                        "properties": {
                          "CountryCode": {
                            "type": "string",
                            "description": "The country code that will be used in shipping addresses"
                          },
                          "CountryName": {
                            "type": "string",
                            "description": "The country name that will be displayed for your customer"
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

## GetCities

*`https://docs.myfatoorah.com/reference/get-cities` — updated 2026-04-21*

> Retrieves a list of cities that belong to a certain country. You can also search for a specific city name in a certain country.

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
      "name": "Shipping",
      "description": "Shipping operations"
    }
  ],
  "paths": {
    "/v2/GetCities": {
      "get": {
        "summary": "GetCities",
        "description": "Retrieves a list of cities that belong to a certain country. You can also search for a specific city name in a certain country.",
        "operationId": "get-cities",
        "tags": [
          "Shipping"
        ],
        "parameters": [
          {
            "name": "shippingMethod",
            "in": "query",
            "required": true,
            "schema": {
              "type": "integer",
              "description": "1 for DHL, 2 for ARAMEX",
              "default": "",
              "enum": [
                1,
                2
              ]
            },
            "description": "The shipping method: 1 for DHL, 2 for ARAMEX"
          },
          {
            "name": "countryCode",
            "in": "query",
            "required": true,
            "schema": {
              "type": "string",
              "default": "SA"
            },
            "description": "The country code retrieved from the GetCountries endpoint"
          },
          {
            "name": "searchValue",
            "in": "query",
            "required": false,
            "schema": {
              "type": "string"
            },
            "description": "The search key that will filter the cities by names"
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
                      "type": "array",
                      "description": "List of cities for the specified country",
                      "items": {
                        "type": "object",
                        "properties": {
                          "CountryCode": {
                            "type": "string",
                            "description": "The country code of the retrieved cities"
                          },
                          "CityNames": {
                            "type": "array",
                            "description": "List of cities names provided for the specified shipping method",
                            "items": {
                              "type": "string"
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

## RequestPickup

*`https://docs.myfatoorah.com/reference/request-pickup` — updated 2026-04-21*

> Request a pickup for orders with Prepared status to be delivered via DHL or ARAMEX.

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
      "name": "Shipping",
      "description": "Shipping operations"
    }
  ],
  "paths": {
    "/v2/RequestPickup": {
      "get": {
        "summary": "RequestPickup",
        "description": "Request a pickup for orders with Prepared status to be delivered via DHL or ARAMEX.",
        "operationId": "request-pickup",
        "tags": [
          "Shipping"
        ],
        "parameters": [
          {
            "name": "ShippingMethod",
            "in": "query",
            "required": true,
            "schema": {
              "type": "integer",
              "enum": [
                1,
                2
              ],
              "description": "1 for DHL, 2 for ARAMEX"
            },
            "description": "The shipping method: 1 for DHL, 2 for ARAMEX"
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
                      "type": "array",
                      "description": "Array of shipping order status objects whose status has changed from Prepared to RequestPickup",
                      "items": {
                        "type": "object",
                        "properties": {
                          "OrderNumber": {
                            "type": "integer",
                            "description": "MyFatoorah order ID"
                          },
                          "OrderStatus": {
                            "type": "string",
                            "description": "The order status"
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
                      "FieldsErrors": null,
                      "Data": [
                        {
                          "OrderNumber": 105039,
                          "OrderStatus": "RequestPickup"
                        },
                        {
                          "OrderNumber": 301761,
                          "OrderStatus": "RequestPickup"
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

## GetShippingOrderList

*`https://docs.myfatoorah.com/reference/get-shipping-order-list` — updated 2026-04-21*

> Retrieves the shipping orders list that belongs to either DHL or ARAMEX.

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
      "name": "Shipping",
      "description": "Shipping operations"
    }
  ],
  "paths": {
    "/v2/GetShippingOrderList": {
      "get": {
        "summary": "GetShippingOrderList",
        "description": "Retrieves the shipping orders list that belongs to either DHL or ARAMEX.",
        "operationId": "get-shipping-order-list",
        "tags": [
          "Shipping"
        ],
        "parameters": [
          {
            "name": "ShippingMethod",
            "in": "query",
            "required": true,
            "schema": {
              "type": "integer",
              "enum": [
                1,
                2
              ],
              "description": "1 for DHL, 2 for ARAMEX"
            },
            "description": "The shipping method: 1 for DHL, 2 for ARAMEX"
          },
          {
            "name": "orderStatus",
            "in": "query",
            "required": true,
            "schema": {
              "type": "integer",
              "enum": [
                0,
                1,
                2,
                3,
                4
              ],
              "description": "0 for Pending Status, 1 for Prepared Status, 2 for RequestPickup Status, 3 for Picked Status, 4 for Delivered Status"
            },
            "description": "The order status to filter by (0-4), 0 for Pending Status, 1 for Prepared Status, 2 for RequestPickup Status, 3 for Picked Status, 4 for Delivered Status"
          },
          {
            "name": "start",
            "in": "query",
            "required": false,
            "schema": {
              "type": "integer"
            },
            "description": "The starting index for pagination"
          },
          {
            "name": "length",
            "in": "query",
            "required": false,
            "schema": {
              "type": "integer"
            },
            "description": "The number of records to retrieve"
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
                      "description": "Shipping orders data",
                      "properties": {
                        "ShippingMethod": {
                          "type": "string",
                          "description": "1 for DHL, 2 for ARAMEX"
                        },
                        "OrderStatus": {
                          "type": "string",
                          "description": "The order status"
                        },
                        "TotalOrders": {
                          "type": "integer",
                          "description": "The number of total shipping orders"
                        },
                        "OrderNumbers": {
                          "type": "array",
                          "description": "A list of MyFatoorah order IDs",
                          "items": {
                            "type": "integer"
                          }
                        },
                        "ShippingOrders": {
                          "type": "array",
                          "description": "Array of shipping order details",
                          "items": {
                            "type": "object",
                            "properties": {
                              "OrderNumber": {
                                "type": "integer",
                                "description": "The MyFatoorah order ID"
                              },
                              "OrderType": {
                                "type": "string",
                                "description": "The order type"
                              },
                              "OrderStatus": {
                                "type": "string",
                                "description": "The range is from 0 to 4 as follows: 0 for Pending Status, 1 for Prepared Status, 2 for RequestPickup Status, 3 for Picked Status, 4 for Delivered Status"
                              },
                              "CustomerName": {
                                "type": "string",
                                "description": "The customer name"
                              },
                              "ShippingMethod": {
                                "type": "string",
                                "description": "DHL or ARAMEX"
                              },
                              "ShippingValue": {
                                "type": "number",
                                "description": "The shipping value"
                              }
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
                      "Message": null,
                      "FieldsErrors": null,
                      "Data": {
                        "ShippingMethod": "DHL",
                        "OrderStatus": "Pending",
                        "TotalOrders": 98,
                        "OrderNumbers": [
                          299466,
                          299462,
                          286498
                        ],
                        "ShippingOrders": [
                          {
                            "OrderNumber": 299466,
                            "OrderType": "Shipping",
                            "OrderStatus": "Pending",
                            "CustomerName": "admin@hardtask.com",
                            "ShippingMethod": "DHL",
                            "ShippingValue": 3.669
                          },
                          {
                            "OrderNumber": 299462,
                            "OrderType": "Shipping",
                            "OrderStatus": "Pending",
                            "CustomerName": "Other country",
                            "ShippingMethod": "DHL",
                            "ShippingValue": 3.669
                          },
                          {
                            "OrderNumber": 286498,
                            "OrderType": "Shipping",
                            "OrderStatus": "Pending",
                            "CustomerName": "test",
                            "ShippingMethod": "DHL",
                            "ShippingValue": 27.385
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

## CalculateShippingCharge

*`https://docs.myfatoorah.com/reference/calculate-shipping-charge` — updated 2026-04-21*

> Calculates the shipping charge supported by the MyFatoorah Shipping Module for either DHL or ARAMEX. You should provide the CityName and CountryCode parameters which can be retrieved by the GetCities and GetCountries endpoints respectively.

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
      "name": "Shipping",
      "description": "Shipping operations"
    }
  ],
  "paths": {
    "/v2/CalculateShippingCharge": {
      "post": {
        "summary": "CalculateShippingCharge",
        "description": "Calculates the shipping charge supported by the MyFatoorah Shipping Module for either DHL or ARAMEX. You should provide the CityName and CountryCode parameters which can be retrieved by the GetCities and GetCountries endpoints respectively.",
        "operationId": "calculate-shipping-charge",
        "tags": [
          "Shipping"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "ShippingMethod",
                  "CityName",
                  "CountryCode",
                  "Items"
                ],
                "properties": {
                  "ShippingMethod": {
                    "type": "integer",
                    "enum": [
                      1,
                      2
                    ],
                    "description": "1 for DHL, 2 for ARAMEX"
                  },
                  "Items": {
                    "type": "array",
                    "description": "Array of shipping items",
                    "items": {
                      "type": "object",
                      "properties": {
                        "ProductName": {
                          "type": "string",
                          "description": "The product name"
                        },
                        "Description": {
                          "type": "string",
                          "description": "The product description"
                        },
                        "Weight": {
                          "type": "number",
                          "description": "Weight in kg (100 >= Weight > 0)",
                          "minimum": 0,
                          "maximum": 100,
                          "exclusiveMinimum": true
                        },
                        "Width": {
                          "type": "number",
                          "description": "Width in cm (200 >= Width > 0)",
                          "minimum": 0,
                          "maximum": 200,
                          "exclusiveMinimum": true
                        },
                        "Height": {
                          "type": "number",
                          "description": "Height in cm (160 >= Height > 0)",
                          "minimum": 0,
                          "maximum": 160,
                          "exclusiveMinimum": true
                        },
                        "Depth": {
                          "type": "number",
                          "description": "Depth in cm (200 >= Depth > 0)",
                          "minimum": 0,
                          "maximum": 200,
                          "exclusiveMinimum": true
                        },
                        "Quantity": {
                          "type": "integer",
                          "description": "The quantity of items"
                        },
                        "UnitPrice": {
                          "type": "number",
                          "description": "The unit price per item"
                        }
                      }
                    }
                  },
                  "CityName": {
                    "type": "string",
                    "description": "The city name retrieved from GetCities endpoint"
                  },
                  "PostalCode": {
                    "type": "string",
                    "description": "The postal code (optional)"
                  },
                  "CountryCode": {
                    "type": "string",
                    "description": "The country code retrieved from GetCountries endpoint"
                  }
                }
              },
              "examples": {
                "example1": {
                  "value": {
                    "ShippingMethod": 1,
                    "Items": [
                      {
                        "ProductName": "name",
                        "Description": "name",
                        "Weight": 0.5,
                        "Width": 10,
                        "Height": 15,
                        "Depth": 19,
                        "Quantity": 20,
                        "UnitPrice": 5
                      }
                    ],
                    "CityName": "DUBAI",
                    "PostalCode": "12345",
                    "CountryCode": "AE"
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
                      "description": "Shipping charge calculation result",
                      "properties": {
                        "Currency": {
                          "type": "string",
                          "description": "The currency of the shipping charge"
                        },
                        "Fees": {
                          "type": "number",
                          "description": "The calculated shipping fees"
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
                      "FieldsErrors": null,
                      "Data": {
                        "Currency": "KD",
                        "Fees": 29.993
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

## UpdateShippingStatus

*`https://docs.myfatoorah.com/reference/update-shipping-status` — updated 2026-04-21*

> Updates the status of shipping orders supported by the MyFatoorah Shipping Module for either DHL or ARAMEX. Use this endpoint to change the status of shipping invoices.

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
      "name": "Shipping",
      "description": "Shipping operations"
    }
  ],
  "paths": {
    "/v2/UpdateShippingStatus": {
      "post": {
        "summary": "UpdateShippingStatus",
        "description": "Updates the status of shipping orders supported by the MyFatoorah Shipping Module for either DHL or ARAMEX. Use this endpoint to change the status of shipping invoices.",
        "operationId": "update-shipping-status",
        "tags": [
          "Shipping"
        ],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "ShippingMethod"
                ],
                "properties": {
                  "ShippingMethod": {
                    "type": "integer",
                    "enum": [
                      1,
                      2
                    ],
                    "description": "1 for DHL, 2 for ARAMEX"
                  },
                  "InvoiceNumbers": {
                    "type": "array",
                    "description": "A list of the invoice IDs to update their status",
                    "items": {
                      "type": "integer"
                    }
                  },
                  "OrderStatusChangedTo": {
                    "type": "integer",
                    "enum": [
                      0,
                      1,
                      2,
                      3,
                      4
                    ],
                    "description": "The status to change to: 0 for Pending Status, 1 for Prepared Status, 2 for RequestPickup Status, 3 for Picked Status, 4 for Delivered Status"
                  }
                }
              },
              "examples": {
                "example1": {
                  "value": {
                    "ShippingMethod": 1,
                    "InvoiceNumbers": [
                      40481,
                      40480
                    ],
                    "OrderStatusChangedTo": 1
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
                      "description": "Shipping status update result",
                      "properties": {
                        "ShippingOrder": {
                          "type": "array",
                          "description": "Array of shipping order status updates",
                          "items": {
                            "type": "object",
                            "properties": {
                              "OrderNumber": {
                                "type": "integer",
                                "description": "The MyFatoorah order ID"
                              },
                              "OrderStatus": {
                                "type": "string",
                                "description": "The updated order status"
                              }
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
                      "Message": null,
                      "FieldsErrors": null,
                      "Data": {
                        "ShippingOrder": [
                          {
                            "OrderNumber": 40480,
                            "OrderStatus": "Prepared"
                          },
                          {
                            "OrderNumber": 40481,
                            "OrderStatus": "Prepared"
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
