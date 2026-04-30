<?php

declare(strict_types=1);

namespace SVExtensions\DeliveryDate\Plugin\Checkout;

class LayoutProcessor
{
    /**
     * Add delivery date field to checkout shipping address form.
     *
     * @param \Magento\Checkout\Block\Checkout\LayoutProcessor $subject
     * @param array<string, mixed> $jsLayout
     * @return array<string, mixed>
     */
    public function afterProcess(
        \Magento\Checkout\Block\Checkout\LayoutProcessor $subject,
        array $jsLayout
    ): array {
        if (!isset(
            $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']
                ['children']['shippingAddress']['children']['before-form']['children']
        ) || !is_array(
            $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']
                ['children']['shippingAddress']['children']['before-form']['children']
        )) {
            return $jsLayout;
        }

        $path = &$jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']
            ['children']['shippingAddress']['children']['before-form']['children'];

        $path['delivery_date'] = [
            'component' => 'Magento_Ui/js/form/element/date',
            'config' => [
                'customScope' => 'shippingAddress',
                'template' => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/date',
                'id' => 'delivery_date'
            ],
            'dataScope' => 'shippingAddress.delivery_date',
            'label' => __('Delivery Date'),
            'provider' => 'checkoutProvider',
            'visible' => true,
            'value' => '',
            'validation' => [],
            'sortOrder' => 250,
            'options' => [
                'dateFormat' => 'yyyy-MM-dd',
                'showsTime' => false,
                'minDate' => 0
            ]
        ];

        return $jsLayout;
    }
}
