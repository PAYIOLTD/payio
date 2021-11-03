
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
    ],
    function ($, Component, quote, customerData, additionalValidators, url) {
        'use strict';
        const cartId = quote.getQuoteId();
        const config =  window.checkoutConfig.payment.payio;
        const basePath = url.build('');
        return Component.extend({
            defaults: {
                template: 'PayioLtd_Payio/payment/payio'
            },
            async initiateCheckoutSession() {
                //construct POST data
                let sessionData = [];
                sessionData                     = this.getCustomerInfo(cartId);
                sessionData["lineItems"]        = this.getCartData();
                sessionData["shippingMethods"]  = this.getShippingMethods();
                // create encrypted request path
                this.sendRequest('POST', config.apiTransactionPath, sessionData);
            },

            sendRequest(type, apiPath, sessionData) {
                const apiKey = config.apiKey;
                return $.ajax({
                    type: type,
                    url: apiPath,
                    headers: {
                        'X-API-KEY':apiKey
                    },
                    data: sessionData,
                    showLoader: true,
                    success : function (response) {
                        window.location.href = config.gatewayPath+response.queryData;
                    },
                    error: function(xhr) {
                        const responseText = JSON.parse(xhr.responseText);
                        $('#mainmessages .message-error span.message-text').text('Error ' + responseText.statusCode + ': ' + responseText.message);
                        $('#mainmessages .message-error').show();
                        $('#mainmessages .message-error').delay(8000).fadeOut();
                        return false;
                    }
                });
            },
            getCustomerInfo() {
                const addressObj = quote.shippingAddress();
                const shippingMethod = quote.shippingMethod();
                const totals = quote.totals();

                // if guest user use guest email
                const customerEmail = addressObj.email ? addressObj.email : quote.guestEmail
                const customerInfo = {
                    cartId,
                    "paymentSuccessUrl" : config.paymentSuccessUrl,
                    "checkoutUrl" : config.checkoutUrl,
                    "totalAmount" : totals.base_grand_total,
                    "customerEmail" : customerEmail,
                    "customerPhoneNumber" : addressObj.telephone,
                    "customerFirstName" : addressObj.firstname,
                    "customerLastName" : addressObj.lastname,
                    "shippingAddress" : addressObj.street[0],
                    "shippingAddress2" : addressObj.street[1],
                    "shippingCity" : addressObj.city,
                    "shippingPostcode" : addressObj.postcode,
                    "shippingType" : shippingMethod.carrier_code+'_'+shippingMethod.method_code,
                    "shippingCost" : totals.base_shipping_incl_tax,
                    "countryCode"  : addressObj.countryId,
                    "cartTax"      : config.cartTax,
                    "totalTax"     : parseFloat(config.cartTax+totals.base_shipping_tax_amount)
                };

                return customerInfo;
            },
            getCartData() {
                const cartObj = quote.getItems();
                const cart = [];
                cartObj.forEach(function(item) {
                    const cartItem = {
                        "sku"      : item.sku,
                        "id"       : item.product_id,
                        "name"     : item.name,
                        "quantity" : item.qty,
                        "price"    : item.price,
                        "imageUrl" : item.thumbnail,
                    };
                    cart.push(cartItem);
                });
                return cart;
            },
            getShippingMethods() {
                const shippingMethod = quote.shippingMethod();
                const totals = quote.totals();
                return [
                    {
                        'rateId'     : shippingMethod.carrier_code+'_'+shippingMethod.method_code,
                        "methodId"   : shippingMethod.method_code,
                        "instanceId" : shippingMethod.carrier_code,
                        "name"       : shippingMethod.carrier_title,
                        "cost"       : shippingMethod.amount,
                        "tax"        : totals.shipping_tax_amount
                    }
                ]
            }
        });
    }
);
