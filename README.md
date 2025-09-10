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

## Testing & live reports

Test it by visiting your store and add `?SANSEC-SHIELD-TEST` to your URL, it should give you "permission denied". You'll see your first blocked attack appear instantly on your [Shield Dashboard](https://dashboard.sansec.io/d/account/shield). If you do not want reports, you can disable it with:

```bash
bin/magento config:set sansec_shield/general/report_enabled 0
```

You can always view detailed logs in `var/log/sansec_shield.log`.

See for FAQs [our Shield guide](https://sansec.io/shield).

## License

Sansec Shield is published under the liberal [MIT license](./LICENSE).
