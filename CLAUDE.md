# Munafa – COD Profit & Return Tracker for WooCommerce

WooCommerce plugin that shows the real net profit of each order for COD sellers
(Bangladesh / South Asia): product cost, courier charge, COD/gateway fee, return loss.
Free version on WordPress.org; Pro (via Freemius) adds ad spend import and courier
settlement reconciliation. See ROADMAP.md for phases and current progress.

The owner communicates in Banglish (Bangla in Latin script). Reply in the same style.

## Stack

- PHP 7.4+ (OOP, namespaces, PSR-4 via Composer), WordPress 6.5+, WooCommerce 9.0+
- Admin UI: React via `@wordpress/scripts`, `@wordpress/components`, `@wordpress/api-fetch`;
  charts with Recharts. One bundle serves WooCommerce → Munafa (dashboard) and
  WooCommerce → Munafa Settings (`data-view` on the mount div picks the screen)
- Small UI pieces (product cost field, order meta box) are plain PHP, not React
- Data: custom tables `{$wpdb->prefix}munafa_order_profit` and `munafa_order_items`,
  REST namespace `munafa/v1`; every setting lives in `Settings\Settings`
- Node.js lives in Herd's nvm (`C:\Users\rlora\.config\herd\bin\nvm\v24.22.1`); put it on PATH
  before `npm` commands if `node` is not found
- Background jobs: Action Scheduler (bundled with WooCommerce)

## Naming

- Text domain / slug: `munafa`
- PHP namespace: `Munafa\` → `src/`
- Global functions, hooks, options, meta keys: prefix `munafa_` / `_munafa_`
- Constants: `MUNAFA_` (e.g. `MUNAFA_VERSION`, `MUNAFA_FILE`)
- JS handles and CSS classes: `munafa-`

## Layout

```
munafa.php            Plugin header, constants, bootstrap
uninstall.php
src/                  PHP classes (Admin/, Rest/, Profit/, Db/, Settings/)
assets/src/           React source (dashboard, settings)
assets/build/         Built JS/CSS (generated, do not edit)
languages/            .pot and bn_BD translation
tests/                PHPUnit tests
readme.txt            WordPress.org readme
```

## Commands

- `npm run start` – watch and build JS
- `npm run build` – production JS build
- `composer phpcs` – WordPress Coding Standards check
- `composer test` – PHPUnit
- `npm run make-pot` – regenerate languages/munafa.pot

## Rules

- Follow WordPress Coding Standards; keep `composer phpcs` clean
- Declare HPOS (`custom_order_tables`) and `cart_checkout_blocks` compatibility.
  Never read order data with `get_post_meta` – use `wc_get_order()` and order getters
- Prefer WooCommerce's native COGS field when available; fall back to `_munafa_cost` meta
- Escape all output (`esc_html`, `esc_attr`, `wp_kses_post`), sanitize all input,
  verify nonces, check `current_user_can( 'manage_woocommerce' )` on admin actions and REST routes
- All user-facing strings translatable with the `munafa` text domain
- Money: `Profit\Calculator` rounds every amount to 2 decimals before adding it up; the table
  stores `DECIMAL(19,4)`; product costs are saved with `wc_format_decimal()`
- Keep `Profit\Calculator` and `Profit\ZoneMatcher` free of WordPress/WooCommerce calls so they stay unit-testable
- Nothing country-specific in code: courier zones, payment method fees, return reasons and
  report statuses are seller settings. Sellers are anywhere in South Asia (BD, IN, PK, NP…),
  so no hardcoded state codes, city names, gateways or currency
- A schema change bumps `Installer::DB_VERSION`; a settings shape change bumps `Settings::VERSION`
  and adds a step to `Settings::maybe_migrate()`
- Heavy work (recalculating many orders) goes through Action Scheduler in batches
- No external CSS frameworks (Tailwind/Bootstrap) in wp-admin
- Pro-only code stays behind Freemius checks and out of the WordPress.org build
- Don't phone home or send data anywhere without an explicit opt-in setting
