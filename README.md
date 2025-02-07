# Sansec Shield

Requires Magento 2.4+ and PHP 7.2+

## Installation

```bash
composer require sansec/magento2-module-shield
bin/magento setup:upgrade
bin/magento bin/magento config:set sansec_shield/general/license_key <your license key>
bin/magento cache:clean
bin/magento sansec:shield:sync-rules
```

You can also configure your license key via System -> Configuration -> Security -> Sansec Shield.

Rules are downloaded asynchronously via cron (every 5 minutes).
