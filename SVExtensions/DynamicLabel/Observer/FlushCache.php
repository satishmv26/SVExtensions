<?php
namespace SVExtensions\DynamicLabel\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use SVExtensions\DynamicLabel\Helper\Data as Helper;

class FlushCache implements ObserverInterface
{
    const CACHE_TAG = 'dynamic_label_json';

    private $curl;
    private $storeManager;
    private $cache;
    private $logger;
    private $helper;

    public function __construct(
        Curl $curl,
        StoreManagerInterface $storeManager,
        CacheInterface $cache,
        LoggerInterface $logger,
        Helper $helper
    ) {
        $this->curl = $curl;
        $this->storeManager = $storeManager;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->helper = $helper;
    }

    public function execute(Observer $observer)
    {
        try {
            if (!$this->helper->isEnabled()) {
                return;
            }

            $baseUrl = $this->storeManager->getStore()->getBaseUrl();
            $url = rtrim($baseUrl, '/') . '/dynamiclabel/index/index';

            try {
                $this->curl->setTimeout(2); // prevent blocking

                $this->curl->addHeader('X-Magento-Tags-Pattern', self::CACHE_TAG);

                $this->curl->addHeader('Fastly-Soft-Purge', '1');

                // Optional generic header (some CDNs)
                $this->curl->addHeader('X-Soft-Purge', '1');

                $this->curl->request('PURGE', $url);

                $this->logger->info('[DynamicLabel] CDN soft purge attempted');
            } catch (\Throwable $e) {
                // CDN not present or blocked → safe fallback
                $this->logger->info('[DynamicLabel] CDN not available, fallback used');
            }

            $this->cache->clean(
                \Zend_Cache::CLEANING_MODE_MATCHING_TAG,
                [self::CACHE_TAG]
            );

        } catch (\Throwable $e) {
            $this->logger->error('[DynamicLabel] ERROR: ' . $e->getMessage());
        }
    }
}