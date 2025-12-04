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

## Cron

Shield rules update automatically through the standard Magento cron mechanism. If you are running a standard cron setup (`bin/magento cron:run`), no further action is required.

If you only run specific cron groups (`bin/magento cron:run --group <group name>`), make sure to include a cron for the `sansec` group as well.

You can verify Shield rules sync every 5 minutes in `var/log/sansec_shield.log`.

## Upgrading

The Sansec Shield module is deliberately kept stable and there is no need to monitor for updates. If an essential new version is released, we will notify you via email.

To check your current version:

```bash
composer show sansec/magento2-module-shield
```

To upgrade to the latest version:

```bash
composer require sansec/magento2-module-shield:^1.0
bin/magento setup:upgrade
```

## License

Sansec Shield is published under the liberal [MIT license](./LICENSE).
