<?php

/**
 * Stubs for framework interfaces missing on older Magento, so the module
 * loads on releases earlier than it natively targets. Each is declared only
 * when absent; versions that ship the real interface autoload it and skip the
 * stub. SerializerInterface arrived in 2.2.0, the Patch interfaces in 2.3.0.
 */

namespace Magento\Framework\Serialize {
    if (!interface_exists(SerializerInterface::class)) {
        interface SerializerInterface
        {
            public function serialize($data);

            public function unserialize($string);
        }
    }
}

namespace Magento\Framework\Setup\Patch {
    if (!interface_exists(PatchInterface::class)) {
        interface PatchInterface
        {
            public static function getDependencies();

            public function getAliases();
        }
    }

    if (!interface_exists(SchemaPatchInterface::class)) {
        interface SchemaPatchInterface extends PatchInterface
        {
            public function apply();
        }
    }
}
