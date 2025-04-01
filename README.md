# Sansec Shield

Requires Magento 2.3+, PHP 7.2+ and an [eComscan account](https://sansec.io/pricing) (Advanced or up).

## Installation

For **Composer 1.x**, the Shield repository must be added manually as [packagist is removing support](https://blog.packagist.com/shutting-down-packagist-org-support-for-composer-1-x/) for this version:

```bash
# composer1 only!!
composer config repositories.sansec-shield vcs https://github.com/sansecio/magento2-module-shield.git
```

Then, proceed with the installation:

```bash
composer require sansec/magento2-module-shield
bin/magento setup:upgrade
bin/magento config:set sansec_shield/general/license_key <your license key>
bin/magento sansec:shield:sync-rules
```

## Configuration

You can configure your license key and other settings via System -> Configuration -> Security -> Sansec Shield.

## Live reports

You can view live reports at your [Sansec Dashboard](https://dashboard.sansec.io/d/account/shield). If you do not want reports, you can disable it via:

```bash
bin/magento config:set sansec_shield/general/report_enabled 0
```

See for FAQs [our Shield guide](https://sansec.io/guides/sansec-shield).

## License

See [LICENSE](./LICENSE) file.
