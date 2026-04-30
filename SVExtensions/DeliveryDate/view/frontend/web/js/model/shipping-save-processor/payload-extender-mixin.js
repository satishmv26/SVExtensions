define([
    'jquery',
    'Magento_Checkout/js/model/quote'
], function ($, quote) {
    'use strict';

    function resolveDeliveryDate(shippingAddress) {
        var deliveryDate;

        if (!shippingAddress) {
            return null;
        }

        if (shippingAddress.customAttributes) {
            deliveryDate = shippingAddress.customAttributes.delivery_date;

            if (deliveryDate && typeof deliveryDate === 'object' && deliveryDate.value) {
                return deliveryDate.value;
            }

            if (typeof deliveryDate === 'string') {
                return deliveryDate;
            }
        }

        if (shippingAddress.delivery_date) {
            if (typeof shippingAddress.delivery_date === 'object' && shippingAddress.delivery_date.value) {
                return shippingAddress.delivery_date.value;
            }

            if (typeof shippingAddress.delivery_date === 'string') {
                return shippingAddress.delivery_date;
            }
        }

        if (shippingAddress.custom_attributes && shippingAddress.custom_attributes.delivery_date) {
            deliveryDate = shippingAddress.custom_attributes.delivery_date;

            if (deliveryDate && typeof deliveryDate === 'object' && deliveryDate.value) {
                return deliveryDate.value;
            }

            if (typeof deliveryDate === 'string') {
                return deliveryDate;
            }
        }

        deliveryDate = $('[name="delivery_date"]').val() || $('#delivery_date').val();

        return deliveryDate || null;
    }

    return function (payloadExtender) {
        return function (payload) {
            var deliveryDate = resolveDeliveryDate(quote.shippingAddress());

            payloadExtender(payload);
            payload.addressInformation.extension_attributes =
                payload.addressInformation.extension_attributes || {};

            if (deliveryDate) {
                payload.addressInformation.extension_attributes.delivery_date = deliveryDate;
            }

            return payload;
        };
    };
});
