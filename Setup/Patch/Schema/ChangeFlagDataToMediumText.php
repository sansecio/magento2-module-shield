<?php

namespace Sansec\Shield\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class ChangeFlagDataToMediumText implements SchemaPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }

    public function apply()
    {
        $this->moduleDataSetup->startSetup();
        $this->moduleDataSetup->getConnection()->changeColumn(
            $this->moduleDataSetup->getTable('flag'),
            'flag_data',
            'flag_data',
            [
                'type' => Table::TYPE_TEXT,
                'length' => '16m'
            ]
        );
        $this->moduleDataSetup->endSetup();
    }
}
