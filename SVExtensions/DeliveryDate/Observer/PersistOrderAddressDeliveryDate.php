<?php

declare(strict_types=1);

namespace SVExtensions\DeliveryDate\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class PersistOrderAddressDeliveryDate implements ObserverInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();

        if (!$quote || !$order) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $orderAddressTable = $this->resourceConnection->getTableName('sales_order_address');
        $orderGridTable = $this->resourceConnection->getTableName('sales_order_grid');
        $gridDeliveryDate = '';

        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress && $order->getShippingAddress()) {
            $deliveryDate = (string) $shippingAddress->getData('delivery_date');
            if ($deliveryDate !== '') {
                $order->getShippingAddress()->setData('delivery_date', $deliveryDate);
                $gridDeliveryDate = $deliveryDate;
                $connection->update(
                    $orderAddressTable,
                    ['delivery_date' => $deliveryDate],
                    ['entity_id = ?' => (int) $order->getShippingAddress()->getEntityId()]
                );
            }
        }

        $billingAddress = $quote->getBillingAddress();
        if ($billingAddress && $order->getBillingAddress()) {
            $deliveryDate = (string) $billingAddress->getData('delivery_date');
            if ($deliveryDate !== '') {
                $order->getBillingAddress()->setData('delivery_date', $deliveryDate);
                if ($gridDeliveryDate === '') {
                    $gridDeliveryDate = $deliveryDate;
                }
                $connection->update(
                    $orderAddressTable,
                    ['delivery_date' => $deliveryDate],
                    ['entity_id = ?' => (int) $order->getBillingAddress()->getEntityId()]
                );
            }
        }

        if ($gridDeliveryDate !== '') {
            $connection->update(
                $orderGridTable,
                ['delivery_date' => $gridDeliveryDate],
                ['entity_id = ?' => (int) $order->getEntityId()]
            );
        }
    }
}
