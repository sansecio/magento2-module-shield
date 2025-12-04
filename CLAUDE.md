# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sansec Shield is a Magento 2 Web Application Firewall (WAF) module that protects against malicious requests. It fetches signed security rules from Sansec's servers and blocks requests matching those rules.

## Development Commands

```bash
# Run tests (requires rules file path via environment variable)
SANSEC_SHIELD_RULES_PATH=/path/to/rules.json vendor/bin/phpunit Test/

# Run a single test file
SANSEC_SHIELD_RULES_PATH=/path/to/rules.json vendor/bin/phpunit Test/Model/WafTest.php

# Install dependencies
composer install
```

## Architecture

### Request Interception Flow

1. **Plugin/Shield.php** - Entry point that intercepts all frontend requests via Magento's `FrontControllerInterface` plugin (`aroundDispatch`). If Shield is enabled, it passes requests to the WAF for evaluation.

2. **Model/Waf.php** - Loads rules from `Rules` and evaluates them against incoming requests using `matchRequest()`.

3. **Model/Rule.php** - Represents a single rule with conditions. The `matches()` method:
   - Extracts target values from requests (uri, body, headers, params, cookies, IP, etc.)
   - Applies preprocessing transforms (urldecode, strtolower, hex2bin, etc.)
   - Matches against condition types: `regex`, `contains`, `equals`, `network`

4. **Model/Condition.php** - Simple value object holding: target, type, value, and preprocess array.

### Rule Management

- **Model/Rules.php** - Fetches rules from Sansec API, verifies cryptographic signature using `etc/public_key.pem`, and stores in Magento's flag system (`sansec_shield_rules` flag).
- **Console/Command/SyncRules.php** - CLI command `sansec:shield:sync-rules` for manual rule sync.
- **Cron/SyncRules.php** - Automatic rule sync every 5 minutes via `sansec` cron group.

### Configuration

- **Model/Config.php** - Reads config from `sansec_shield/general/*` paths:
  - `enabled`, `license_key`, `rules_url`, `report_enabled`, `report_url`
- **etc/config.xml** - Default config values (API URLs)
- **etc/adminhtml/system.xml** - Admin panel configuration UI

### Reporting

- **Model/Report.php** - Sends blocked request details to Sansec dashboard. Filters sensitive headers (Cookie, Authorization).

### IP Handling

- **Model/IP.php** - Collects client IPs from various headers (REMOTE_ADDR, CF-Connecting-IP, X-Real-IP, X-Forwarded-For). Supports CIDR matching for network-based rules.

## Testing

Tests use PHPUnit with mock Rules and request fixtures in `Test/fixture/request/`. The `RequestStub` class simulates Magento request objects. Tests require an actual rules JSON file specified via `SANSEC_SHIELD_RULES_PATH` environment variable.

## Key Files

- `etc/di.xml` - Dependency injection config including virtual logger to `var/log/sansec_shield.log`
- `etc/crontab.xml` - Defines `sansec` cron group with 5-minute schedule
- `view/base/templates/access_denied.phtml` - 403 response template
