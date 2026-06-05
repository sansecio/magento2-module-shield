<?php

use Magento\Framework\Component\ComponentRegistrar;

require __DIR__ . '/polyfill.php';

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Sansec_Shield', __DIR__);
