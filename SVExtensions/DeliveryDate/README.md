# CheckoutCustomField
To add a custom field in Magento checkout, I use LayoutProcessor to inject the UI component, a JavaScript mixin to include the field in the checkout payload, extension attributes to extend the API, a plugin on ShippingInformationManagement to persist the value into quote_address, and then map it to sales_order_address during order placement. 
