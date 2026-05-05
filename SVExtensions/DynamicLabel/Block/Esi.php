<?php
namespace SVExtensions\DynamicLabel\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\CacheInterface;
use SVExtensions\DynamicLabel\Helper\Data as Helper;

class Esi extends Template implements IdentityInterface
{
    const CACHE_TAG = 'dynamic_label_json';

    protected $resource;
    protected $storeManager;
    protected $helper;
    protected $scopeConfig;
    protected $cache;

    public function __construct(
        Template\Context $context,
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CacheInterface $cache,
        Helper $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->cache = $cache;
        $this->helper = $helper;
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG];
    }

    protected function _toHtml()
    {
        if (!$this->helper->isEnabled()) {
            return '';
        }

        $storeId = (int)$this->storeManager->getStore()->getId();

        $type = $this->scopeConfig->getValue(
            'dynamiclabel/general/value_type',
            ScopeInterface::SCOPE_STORE
        );

        $valueColumn = ($type === 'html') ? 'html_value' : 'plain_value';

        $cacheKey = self::CACHE_TAG . '_esi_' . $storeId . '_' . $valueColumn;

        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return $cached;
        }

        $connection = $this->resource->getConnection();

        $select = $connection->select()
            ->from(['v' => $this->resource->getTableName('variable')], ['name'])
            ->join(
                ['vv' => $this->resource->getTableName('variable_value')],
                'v.variable_id = vv.variable_id',
                ['store_id', $valueColumn]
            )
            ->where('vv.store_id IN (?)', [0, $storeId])
            ->order('vv.store_id DESC');

        $rows = $connection->fetchAll($select);

        $map = [];

        foreach ($rows as $row) {

            $key = strtolower(trim((string)($row['name'] ?? '')));

            if (isset($map[$key])) {
                continue;
            }

            $value = trim((string)($row[$valueColumn] ?? ''));

            if ($key && $value !== '') {
                $map[$key] = $value;
            }
        }

        $html = '<script>window.dynamicLabelMap = ' . json_encode($map) . ';</script>';

        $this->cache->save(
            $html,
            $cacheKey,
            [self::CACHE_TAG],
            86400
        );

        return $html;
    }
}