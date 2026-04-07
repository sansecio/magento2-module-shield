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

## Troubleshooting

### "Please enable the module and configure the license key"

If you get this error when running `bin/magento sansec:shield:sync-rules`, even though the license key is already configured, flush the Magento cache:

```bash
bin/magento cache:flush
```

Then retry the sync command.

### "There are no commands defined in the sansec:shield namespace"

Run the Magento dependency injection compiler:

```bash
bin/magento setup:di:compile
```

### Composer upgrades unrelated packages during installation

Shield's only dependency is `magento/framework`, so it will not pull in or force any additional upgrades. If you see many packages being upgraded, your `vendor/` directory was out of sync with `composer.lock`. Running `composer require` synced your vendor directory to match.

To avoid this, revert `composer.lock` to a version that matches your current vendor directory before installing Shield:

```bash
git checkout composer.lock
composer require sansec/magento2-module-shield
```

If installing via Composer is not an option, you can copy the source files directly into `app/code/Sansec/Shield`, though you will need to handle updates manually from that point on.

## License

Sansec Shield is published under the liberal [MIT license](./LICENSE).
