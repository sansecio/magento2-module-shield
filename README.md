# Sansec Shield

## Installation

```bash
composer require sansec/magento2-module-shield
bin/magento setup:upgrade
bin/magento bin/magento config:set sansec_shield/general/license_key <your license key>
bin/magento cache:clean
```

You can also configure your license key via System -> Configuration -> Security -> Sansec Shield.

Rules are downloaded asynchronously via cron. If you want immediate protection, execute the following command:

```bash
bin/magento sansec:shield:sync-rules
```
