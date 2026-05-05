<?php
namespace SVExtensions\DynamicLabel\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\CacheInterface;
use SVExtensions\DynamicLabel\Helper\Data as Helper;

class Index extends Action
{
    const CACHE_TAG = 'dynamic_label_json';

    protected $resultJsonFactory;
    protected $resource;
    protected $storeManager;
    protected $scopeConfig;
    protected $cache;
    protected $helper;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CacheInterface $cache,
        Helper $helper
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->cache = $cache;
        $this->helper = $helper;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->helper->isEnabled()) {
            return $result->setData([]);
        }

        $storeId = (int)$this->storeManager->getStore()->getId();

        $type = $this->scopeConfig->getValue(
            'dynamiclabel/general/value_type',
            ScopeInterface::SCOPE_STORE
        );

        $valueColumn = ($type === 'html') ? 'html_value' : 'plain_value';

        $cacheKey = self::CACHE_TAG . '_' . $storeId . '_' . $valueColumn;

        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            $data = json_decode($cached, true);

            return $result
                ->setHeader('Cache-Control', 'public, max-age=86400', true)
                ->setHeader('X-Magento-Tags', self::CACHE_TAG, true)
                ->setData($data);
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

            $key = $this->normalize($row['name']);

            if (isset($map[$key])) {
                continue;
            }

            $value = trim((string)($row[$valueColumn] ?? ''));

            if ($key && $value !== '') {
                $map[$key] = $value;
            }
        }

        $this->cache->save(
            json_encode($map),
            $cacheKey,
            [self::CACHE_TAG],
            86400
        );

        $result->setHeader('Cache-Control', 'public, max-age=86400', true);
        $result->setHeader('X-Magento-Tags', self::CACHE_TAG, true);

        return $result->setData($map);
    }

    private function normalize($text)
    {
        return strtolower(
            trim(
                preg_replace('/\s+/', ' ', (string)$text)
            )
        );
    }
}