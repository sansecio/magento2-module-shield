# Sansec Shield

Requires Magento 2.3+, PHP 7.2+ and an [eComscan account](https://sansec.io/pricing) (Advanced or up).

## Installation

```bash
composer require sansec/magento2-module-shield
bin/magento setup:upgrade
bin/magento config:set sansec_shield/general/license_key <your license key>
bin/magento sansec:shield:sync-rules
```

## Configuration

You can configure your license key and other settings via System → Configuration → Security → Sansec Shield.

## Live reports

You can view live reports at your [Sansec Dashboard](https://dashboard.sansec.io/d/account/shield). If you do not want reports, you can disable it via:

```bash
bin/magento config:set sansec_shield/general/report_enabled 0
```

You can always view detailed logs in `var/log/sansec_shield.log`.

See for FAQs [our Shield guide](https://sansec.io/guides/sansec-shield).

## License

Sansec Shield is published under the liberal [MIT license](./LICENSE).
