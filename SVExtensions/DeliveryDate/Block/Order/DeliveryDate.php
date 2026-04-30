<?php

declare(strict_types=1);

namespace SVExtensions\DeliveryDate\Block\Order;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\Data\OrderInterface;

class DeliveryDate extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?OrderInterface
    {
        $order = $this->registry->registry('current_order');
        return $order instanceof OrderInterface ? $order : null;
    }

    public function getDeliveryDate(): ?string
    {
        $order = $this->getOrder();
        if (!$order) {
            return null;
        }

        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getData('delivery_date')) {
            return (string) $shippingAddress->getData('delivery_date');
        }

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress && $billingAddress->getData('delivery_date')) {
            return (string) $billingAddress->getData('delivery_date');
        }

        return null;
    }
}
