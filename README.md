# Sansec Shield

## Installation

```bash
composer require sansec/magento2-module-shield
bin/magento setup:upgrade
bin/magento bin/magento config:set sansec_shield/general/license_key <your license key>
bin/magento cache:clean
```

Alternatively, you can configure your license key via System -> Configuration -> Security -> Sansec Shield.
