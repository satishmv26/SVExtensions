<?php

declare(strict_types=1);

namespace SVExtensions\DeliveryDate\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class BackfillSalesOrderGridDeliveryDate implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly ResourceConnection $resourceConnection
    ) {
    }
    // this is for backfilling delivery_date in sales_order_grid for existing old orders. New orders will be handled by observer and plugin.
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');
        $salesOrderGridTable = $this->resourceConnection->getTableName('sales_order_grid');
        $salesOrderAddressTable = $this->resourceConnection->getTableName('sales_order_address');

        $this->moduleDataSetup->startSetup();

        $select = $connection->select()
            ->join(
                ['so' => $salesOrderTable],
                'sog.entity_id = so.entity_id',
                []
            )
            ->joinLeft(
                ['ship' => $salesOrderAddressTable],
                'so.shipping_address_id = ship.entity_id',
                []
            )
            ->joinLeft(
                ['bill' => $salesOrderAddressTable],
                'so.billing_address_id = bill.entity_id',
                []
            )
            ->columns([
                'delivery_date' => new \Zend_Db_Expr('COALESCE(ship.delivery_date, bill.delivery_date)')
            ]);

        $query = $connection->updateFromSelect(
            $select,
            ['sog' => $salesOrderGridTable]
        );

        $connection->query($query);

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
