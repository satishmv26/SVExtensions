<?php
namespace SVExtensions\DynamicLabel\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\CacheInterface;
use SVExtensions\DynamicLabel\Helper\Data as Helper;
class Js extends Template
{
    const CACHE_KEY = 'dynamic_label_variable_map';

    protected $cache;
    protected $helper;
    public function __construct(
        Template\Context $context,
        CacheInterface $cache,
        Helper $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->cache = $cache;
        $this->helper = $helper;
    }

    public function getLabelMapJson()
    {
        $cached = $this->cache->load(self::CACHE_KEY);

        if (!$cached) {
            return '{}';
        }

        return $cached; // already JSON
    }

    public function isEnabled()
    {
        return $this->helper->isEnabled();
    }
}