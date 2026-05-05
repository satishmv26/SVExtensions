<?php
namespace SVExtensions\DynamicLabel\Plugin;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Variable\Model\Variable;

class VariableSavePlugin
{
    const CACHE_KEY = 'dynamic_label_variable_map';

    protected $cache;
    protected $typeList;
    protected $cacheFrontendPool;

    public function __construct(
        CacheInterface $cache,
        TypeListInterface $typeList,
        Pool $cacheFrontendPool
    ) {
        $this->cache = $cache;
        $this->typeList = $typeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
    }

    public function afterSave(Variable $subject, $result)
    {
        // Remove only your cache
        $this->cache->clean(['DYNAMIC_LABEL']);

        // Light FPC invalidation
        $this->typeList->invalidate('full_page');

        return $result;
    }
}