# X Reset WP

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="assets/images/Logo-XReset-White.png">
  <source media="(prefers-color-scheme: light)" srcset="assets/images/Logo-XReset-Black.png">
  <img alt="X Reset WP" src="assets/images/Logo-XReset-Black.png" style="max-height: 75px;">
</picture>

**Reset your WordPress safely.** A WordPress admin tool for selectively deleting WooCommerce and WordPress data in bulk — orders, users, coupons, analytics, logs, transients, and more.

[![Plugin Version](https://img.shields.io/badge/version-1.0.0-blue.svg)]()
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)]()
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)]()
[![License](https://img.shields.io/badge/license-GPL%202.0%2B-green.svg)]()

---

## Features

| Option | Clears |
|---|---|
| **WooCommerce Orders** | `wp_posts` (legacy) or `wp_wc_orders` (HPOS) — auto-detected |
| **Order Notes** | `wp_comments` where `comment_type = 'order_note'` |
| **Coupons** | `wp_posts` where `post_type = 'shop_coupon'` |
| **Users** | All users except `administrator` and `super_admin` roles |
| **Customer Sessions** | `wp_woocommerce_sessions` (truncated) |
| **Transients** | `_transient_%` entries in `wp_options` |
| **System Logs** | `wp-content/uploads/wc-logs/` and `wp-content/debug.log` |
| **WooCommerce Analytics** | Order stats, product lookup, coupon lookup, tax lookup tables |
| **Geographic Statistics** | Orders by country option + its transient |
| **Customer Downloads** | `wp_woocommerce_downloadable_product_permissions` |

### Key capabilities

- **HPOS compatible** — detects WooCommerce High-Performance Order Storage automatically.
- **Batch processing** — operations are chunked (default 100 per batch) and executed via sequential AJAX requests. No timeouts on large datasets.
- **Confirmation modal** — displays exactly what will be deleted before execution. Irreversible action warning.
- **Progress UI** — real-time progress bar, step labels, and scrollable log (green for success, red for errors).
- **Keyboard support** — dismiss the confirmation modal with `Escape`.
- **i18n ready** — fully translatable via `.po`/`.mo` files. Ships with Spanish (Spain) and Portuguese (Portugal).
- **Vanilla JS** — no jQuery dependency. No build step.

## Requirements

- WordPress 5.0+
- WooCommerce 4.0+ (for WooCommerce-specific features)
- PHP 7.4+

## Installation

### From WordPress admin

1. Download the plugin ZIP.
2. Go to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Activate the plugin.

### Manual (FTP)

1. Extract the ZIP archive.
2. Upload the `x-reset-wp` folder to `/wp-content/plugins/`.
3. Go to **Plugins** and activate **X Reset WP**.

## Usage

1. Go to **Tools > X Reset WP** in the WordPress admin.
2. Check the data types you want to delete.
3. Click **Execute Reset**.
4. Review the confirmation modal — this action is **irreversible**.
5. Confirm to start the batch process. A progress bar shows real-time status.

> **Warning:** Deleted data cannot be recovered. Always back up your database before using this tool.

## Project structure

```
x-reset-wp/
├── x-reset-wp.php                    # Plugin bootstrap, admin page, AJAX handler
├── includes/
│   └── class-reset-handler.php       # Core reset logic (10 cleanup methods)
├── assets/
│   ├── css/admin.css                 # Admin UI styles
│   └── js/admin.js                   # Batch processing, modals, progress UI
├── languages/
│   ├── x-reset-wp-es_ES.po           # Spanish (Spain) translation
│   ├── x-reset-wp-es_ES.mo
│   ├── x-reset-wp-pt_PT.po           # Portuguese (Portugal) translation
│   └── x-reset-wp-pt_PT.mo
└── CHANGELOG.md
```

## Changelog

See [CHANGELOG.md](./CHANGELOG.md) for version history.

## Security

- All AJAX requests are protected with WordPress Nonces.
- Only users with `manage_options` capability can access the tool.
- All inputs are sanitized before processing.

## Contributing

1. Fork the repository.
2. Create a branch for your change.
3. Submit a pull request with a clear description of the change.

## License

GPL-2.0+ — © 2025 Jesús David

## Author

**Jesús David** — [@jesusdavidweb](https://github.com/jesusdavidweb) — [jesusdavid.net](https://jesusdavid.net)
