<?php

declare(strict_types=1);

namespace SVExtensions\DeliveryDate\Plugin\Checkout;

use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\QuoteRepository;

class ShippingInformationManagementPlugin
{
    public function __construct(
        private readonly QuoteRepository $quoteRepository,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Move delivery date from shipping information extension attributes
     * onto the address objects Magento saves to quote_address.
     */
    public function beforeSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
        int $cartId,
        ShippingInformationInterface $addressInformation
    ): array {
        $extensionAttributes = $addressInformation->getExtensionAttributes();
        $deliveryDate = $extensionAttributes ? $extensionAttributes->getDeliveryDate() : null;

        if (!$deliveryDate) {
            return [$cartId, $addressInformation];
        }

        $shippingAddress = $addressInformation->getShippingAddress();
        if ($shippingAddress) {
            $shippingAddress->setData('delivery_date', $deliveryDate);
            $shippingAddress->setCustomAttribute('delivery_date', $deliveryDate);
        }

        $billingAddress = $addressInformation->getBillingAddress();
        if ($billingAddress) {
            $billingAddress->setData('delivery_date', $deliveryDate);
            $billingAddress->setCustomAttribute('delivery_date', $deliveryDate);
        }

        return [$cartId, $addressInformation];
    }

    /**
     * Persist delivery date after Magento completes shipping information save.
     */
    public function aroundSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
        callable $proceed,
        int $cartId,
        ShippingInformationInterface $addressInformation
    ): PaymentDetailsInterface {
        $result = $proceed($cartId, $addressInformation);

        $extensionAttributes = $addressInformation->getExtensionAttributes();
        $deliveryDate = $extensionAttributes ? $extensionAttributes->getDeliveryDate() : null;

        if (!$deliveryDate) {
            return $result;
        }

        $quote = $this->quoteRepository->getActive($cartId);

        if ($quote->getShippingAddress()) {
            $quote->getShippingAddress()->setData('delivery_date', $deliveryDate);
            $this->persistAddressDeliveryDate(
                'quote_address',
                (int) $quote->getShippingAddress()->getAddressId(),
                $deliveryDate
            );
        }

        if ($quote->getBillingAddress()) {
            $quote->getBillingAddress()->setData('delivery_date', $deliveryDate);
            $this->persistAddressDeliveryDate(
                'quote_address',
                (int) $quote->getBillingAddress()->getAddressId(),
                $deliveryDate
            );
        }

        $this->quoteRepository->save($quote);

        return $result;
    }

    private function persistAddressDeliveryDate(string $tableName, int $addressId, string $deliveryDate): void
    {
        if ($addressId <= 0) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->update(
            $this->resourceConnection->getTableName($tableName),
            ['delivery_date' => $deliveryDate],
            ['address_id = ?' => $addressId]
        );
    }
}
