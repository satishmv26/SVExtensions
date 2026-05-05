<?php
/**
 * SVExtensions_DynamicLabel
 *
 * Allows admin to override frontend text/labels globally via database,
 * without modifying CSV translation files or templates.
 *
 * @category  SVExtensions
 * @package   SVExtensions_DynamicLabel
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'SVExtensions_DynamicLabel',
    __DIR__
);
