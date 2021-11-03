define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/additional-validators',
        'PayioLtd_Payio/js/model/payio-validator'
    ],
    function (Component, additionalValidators, payioValidator) {
        'use strict';
        additionalValidators.registerValidator(payioValidator);
        return Component.extend({});
    }
);
