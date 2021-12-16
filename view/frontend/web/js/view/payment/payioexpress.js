define(
    [
        'jquery',
        'uiComponent',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
    ],
    function ($, Component, customerData, additionalValidators, url) {
        'use strict';
        const basePath = url.build('');
        var sections = ['cart'];
        var addressObj         = '';
        var totals             = '';
        var cartObj            = '';
        var cartId             = '';
        var email              = '';
        var currencyCode       = '';
        var cartTax            = '';
        customerData.invalidate(sections);
        customerData.reload(sections, true);
        return Component.extend({

            defaults: {
                id: null,
                quoteId: 0,
                apiKey: null,
                gatewayPath: null,
                apiTransactionPath: null,
                checkoutUrl: null,
                paymentSuccessUrl: null,
            },

            /**
             * @returns {Object}
             */
            initialize: function () {
                this._super();
                var cartId = $('#initiateCheckoutSession').attr("data-id");
                $.ajax({
                    url: '/payio/index/getquote',
                    type: 'POST',
                    data: {maskid:cartId},
                    showLoader: true,
                    success: function (response) {
                        totals         = response.totalsData;
                        cartObj        = response.quoteItemData;
                        addressObj     = response.isCustomerLoggedIn ? response.customerData : response.shippingAddressFromData;
                        currencyCode   = response.currencyCode;
                        cartTax        = response.cartTax;
                    }
                });

                const apiKey             = this.apiKey;
                const apiTransactionPath = this.apiTransactionPath;
                const apigatewayPath     = this.gatewayPath;
                const checkoutUrl        = this.checkoutUrl;
                const paymentSuccessUrl  = this.paymentSuccessUrl;

                $('#initiateCheckoutSession').on('click', function(){
                    // get latest card data incase it has changed
                    customerData.invalidate(['cart']);
                    //construct POST data
                    let sessionData = [];
                    const customerInfo = {
                        cartId,
                        "paymentSuccessUrl"     : paymentSuccessUrl,
                        "checkoutUrl"           : checkoutUrl,
                        "totalAmount"           : totals.base_grand_total,
                        "customerEmail"         : addressObj.email,
                        "currency"              : currencyCode,
                        "customerFirstName"     : addressObj.firstname ? addressObj.firstname : '',
                        "customerLastName"      : addressObj.lastname ? addressObj.lastname : '',
                        "shippingAddress"       : addressObj.street ? addressObj.street[0] : '',
                        "shippingAddress2"      : addressObj.street ? addressObj.street[1] : '',
                        "shippingCity"          : addressObj.city ? addressObj.city : '',
                        "shippingPostcode"      : addressObj.postcode ? addressObj.postcode : '',
                        "shippingType"          : '',
                        "shippingCost"          : totals.base_shipping_incl_tax,
                        "countryCode"           : 'GB',
                        "cartTax"               : cartTax
                    };

                    sessionData  = customerInfo;
                    const cart   = [];
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

                    sessionData["lineItems"]  = cart;

                    const getShippingMethods   = [];
                        // shippingMethod.forEach(function(shippingitem) {
                        //     const getShippingItem = {
                        //         "rateId"      : shippingitem.value,
                        //         "methodId"    : shippingitem.value,
                        //         "instanceId"  : shippingitem.value,
                        //         "name"        : shippingitem.label,
                        //         "cost"        : shippingitem.cost,
                        //         "tax"         : totals.shipping_tax_amount,
                        //         "countryCode" : shippingitem.countryCode
                        //     };
                        //     getShippingMethods.push(getShippingItem);
                        // });

                    sessionData["shippingMethods"] = getShippingMethods;
                    $.ajax({
                        type: 'POST',
                        url: apiTransactionPath,
                        headers: {
                            'X-API-KEY':apiKey
                        },
                        data: sessionData,
                        showLoader: true,
                        success : function (response) {
                            window.location.href = apigatewayPath+response.queryData;
                        },
                        error: function(xhr) {
                            const responseText = JSON.parse(xhr.responseText);
                            $('#minimessages .message-error span.message-text').text('Error ' + responseText.statusCode + ': ' + responseText.message);
                            $('#minimessages .message-error').show();
                            $('#minimessages .message-error').delay(8000).fadeOut();
                            return false;
                        }
                    });
                });
                return this;
            }
        });
    }
);
