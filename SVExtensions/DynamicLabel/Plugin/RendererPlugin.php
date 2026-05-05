<?php
namespace SVExtensions\DynamicLabel\Plugin;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Phrase\RendererInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use SVExtensions\DynamicLabel\Helper\Data as Helper;

class RendererPlugin
{
    const CACHE_KEY = 'dynamic_label_variable_map';

    private $cache;
    private $resource;
    private $localCache = null;
    private $storeManager;
    private $scopeConfig;
    private $helper;

    public function __construct(
        CacheInterface $cache,
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        Helper $helper
    ) {
        $this->cache        = $cache;
        $this->resource     = $resource;
        $this->storeManager = $storeManager;
        $this->scopeConfig  = $scopeConfig;
        $this->helper       = $helper;
    }

    public function aroundRender(
        RendererInterface $subject,
        callable $proceed,
        array $source,
        array $arguments
    ) {
        // Magento render first
        $result = $proceed($source, $arguments);

        // Feature OFF → zero cost
        if (! $this->helper->isEnabled()) {
            return $result;
        }

        $text = $source[0] ?? '';

        // Fast exit (performance critical)
        if (! $text || strlen($text) > 50) {
            return $result;
        }

        $normalized = $this->normalize($text);

        $map = $this->getLabelMap();

        // O(1) lookup
        return $map[$normalized] ?? $result;
    }

    private function normalize($text)
    {
        return strtolower(
            trim(
                preg_replace('/\s+/', ' ', html_entity_decode((string)$text))
            )
        );
    }

    private function getLabelMap(): array
    {
        if ($this->localCache !== null) {
            return $this->localCache;
        }

        $storeId = (int)$this->storeManager->getStore()->getId();

        $type = $this->scopeConfig->getValue(
            'dynamiclabel/general/value_type',
            ScopeInterface::SCOPE_STORE
        );

        $valueColumn = ($type === 'html') ? 'html_value' : 'plain_value';

        $cacheKey = self::CACHE_KEY . '_' . $storeId . '_' . $valueColumn;

        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return $this->localCache = json_decode($cached, true) ?: [];
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

            // store value priority
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
            ['DYNAMIC_LABEL'],
            86400
        );

        return $this->localCache = $map;
    }
}