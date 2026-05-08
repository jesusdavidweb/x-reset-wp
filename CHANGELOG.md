# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2025-05-08

### Changed
- Brand colors updated to jesusdavid.net palette: accent changed from cyan (`#00d4ff`) to brand yellow (`#fece00`)
- Header icon replaced with `Logo-XReset-White.png` full horizontal logo
- Added `Logo-XReset-icon.png` as favicon and in modals

## [0.2.0] - 2025-05-08

### Added
- WP-CLI commands: `wp x-reset-wp {run,list,audit-log,factory-reset}`
- PHPUnit test suite (`tests/bootstrap.php` + `tests/test-handler.php`)
- Audit log download as CSV from the admin toolbar
- Progress persistence via sessionStorage (resume after refresh)
- `x_reset_wp_before_delete` and `x_reset_wp_after_delete` hooks
- `get_audit_log()` public method on handler

### Changed
- Dry-run messages now display `[DRY-RUN]` prefix in audit log
- Cleanup modal clears persisted progress on close/finish

## [0.1.0] - 2025-05-08

### Added
- **11 new cleanup options:**
  - Posts, Pages & Media
  - Product Reviews
  - Product Categories, Tags & Attributes
  - Shipping Zones & Methods
  - Payment Gateways
  - Tax Rates
  - Webhooks
  - API Keys (WooCommerce + WP OAuth)
  - All Comments (approved, pending, spam, trash)
  - Refunds (HPOS compatible)
  - Factory Reset (nuke all data)
- Dry-run mode (simulate without deleting)
- Date range filter (from/to on orders, comments, posts, users)
- Configurable batch size (slider: 10–1000)
- Collapsible categories (WooCommerce / WordPress / System)
- Select All / Deselect All per category
- Live item counts via AJAX stats on page load
- Factory Reset with double confirmation (type "DELETE")
- HPOS-aware date columns (`date_created_gmt` vs `post_date`)
- `x_reset_wp_register_options` filter for extensibility

### Changed
- Grid layout updated to support dynamic categories
- All existing methods updated for dry-run and date filter support

### Security
- WordPress Nonce validation on all AJAX endpoints including stats

## [0.0.2] - 2025-05-08

### Security
- Added WooCommerce active check to prevent fatal errors when WC is not installed
- Added audit logging for all cleanup operations (stored in `wp_options` as `x_reset_wp_audit_log`, max 100 entries)
- Fixed SQL LIKE wildcard in transient queries to prevent unintended matches (OWASP A06)
- Removed external Google Fonts dependency for GDPR compliance and availability
- Replaced error-suppressed file deletions (`@unlink`) with proper `is_writable()` checks
- Removed dead code (unused `$upload_dir` variable)

## [0.0.1] - 2025-05-08

### Added
- Initial X Reset WP plugin to reset WordPress and WooCommerce data
- Administration interface under Tools > X Reset WP
- WooCommerce orders deletion (compatible with HPOS and legacy storage)
- Order notes deletion
- WooCommerce coupons deletion
- Users deletion (except administrators)
- Customer sessions deletion
- WordPress and WooCommerce transients deletion
- System log files deletion
- WooCommerce analytics reset
- Geographic statistics (orders by country/region)
- Download permissions deletion for downloadable products
- Confirmation modal with irreversible action warning
- Batch processing support via AJAX
- Translation system (i18n) with .pot files
- Administration panel with custom styles
- Permissions and Nonce validation for security

### Security
- User capabilities verification (manage_options)
- WordPress Nonces for all AJAX requests