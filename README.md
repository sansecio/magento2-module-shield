# Sansec Shield

Requires Magento 2.3+, PHP 7.2+ and an [eComscan account](https://sansec.io/pricing) (Advanced or up).

## Installation

```bash
composer require sansec/magento2-module-shield
bin/magento setup:upgrade
bin/magento config:set sansec_shield/general/license_key <your license key>
bin/magento cache:clean
bin/magento sansec:shield:sync-rules
```

You can also configure your license key via System -> Configuration -> Security -> Sansec Shield.

## Live reports

You can view live reports at your [Sansec Dashboard](https://dashboard.sansec.io/d/account/shield). If you do not want reports, you can disable it via:

```bash
bin/magento config:set sansec_shield/general/enabled 0
```

See for FAQs [our Shield guide](https://sansec.io/guides/sansec-shield).
