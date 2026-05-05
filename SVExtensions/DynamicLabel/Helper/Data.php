<?php
namespace SVExtensions\DynamicLabel\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\StoreManagerInterface;

class Data extends AbstractHelper
{
    const XML_PATH_ENABLED = 'dynamiclabel/general/enabled';
    protected $resourceConnection;
    protected $storeManager;
    protected $cache = [];
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
    }

    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }


    public function getVariableValue(string $code, ?int $storeId = null): ?string
    {
        if (!$code) {
            return null;
        }

        $storeId = $storeId ?? (int)$this->storeManager->getStore()->getId();
        $cacheKey = $code . '_' . $storeId;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $connection = $this->resourceConnection->getConnection();

        $variableTable = $this->resourceConnection->getTableName('variable');
        $valueTable = $this->resourceConnection->getTableName('variable_value');

        $select = $connection->select()
            ->from(['v' => $variableTable], [])
            ->joinLeft(
                ['vv_store' => $valueTable],
                'v.variable_id = vv_store.variable_id AND vv_store.store_id = ' . (int)$storeId,
                []
            )
            ->joinLeft(
                ['vv_default' => $valueTable],
                'v.variable_id = vv_default.variable_id AND vv_default.store_id = 0',
                []
            )
            ->where('v.code = ?', $code)
            ->columns([
                'store_value'   => 'vv_store.plain_value',
                'default_value' => 'vv_default.plain_value'
            ])
            ->limit(1);

        $result = $connection->fetchRow($select);

        if (!$result) {
            return $this->cache[$cacheKey] = null;
        }

        // Proper fallback
        $value = $result['store_value'] ?? $result['default_value'];

        return $this->cache[$cacheKey] = ($value !== '' ? $value : null);
    }

    public function getHtmlValue(string $code, ?int $storeId = null): ?string
    {
        if (!$code) {
            return null;
        }

        $storeId = $storeId ?? (int)$this->storeManager->getStore()->getId();
        $cacheKey = 'html_' . $code . '_' . $storeId;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $connection = $this->resourceConnection->getConnection();

        $variableTable = $this->resourceConnection->getTableName('variable');
        $valueTable = $this->resourceConnection->getTableName('variable_value');

        $select = $connection->select()
            ->from(['v' => $variableTable], [])
            ->joinLeft(
                ['vv_store' => $valueTable],
                'v.variable_id = vv_store.variable_id AND vv_store.store_id = ' . (int)$storeId,
                []
            )
            ->joinLeft(
                ['vv_default' => $valueTable],
                'v.variable_id = vv_default.variable_id AND vv_default.store_id = 0',
                []
            )
            ->where('v.code = ?', $code)
            ->columns([
                'store_value'   => 'vv_store.html_value',
                'default_value' => 'vv_default.html_value'
            ])
            ->limit(1);

        $result = $connection->fetchRow($select);

        if (!$result) {
            return $this->cache[$cacheKey] = null;
        }

        // Correct fallback (IMPORTANT)
        $value = ($result['store_value'] !== null && $result['store_value'] !== '')
            ? $result['store_value']
            : $result['default_value'];

        return $this->cache[$cacheKey] = ($value !== '' ? $value : null);
    }

}