<?php
/**
 * Plugin Name:     X Reset WP
 * Plugin URI:      https://jesusdavid.net/
 * Description:     Resets your WordPress by deleting orders, users, statistics, logs and more. Compatible with WooCommerce HPOS.
 * Version:         0.3.0
 * Author:          @jesusdavidweb
 * Author URI:      https://jesusdavid.net/
 * License:         GPL-2.0+
 * Text Domain:     x-reset-wp
 * Domain Path:     /languages
 */

defined("ABSPATH") || exit();

define("X_RESET_WP_VERSION", "0.3.0");
define("X_RESET_WP_PATH", plugin_dir_path(__FILE__));
define("X_RESET_WP_URL", plugin_dir_url(__FILE__));

require_once X_RESET_WP_PATH . "includes/class-reset-handler.php";

if (defined("WP_CLI") && WP_CLI) {
    require_once X_RESET_WP_PATH . "includes/class-wp-cli.php";
}

add_action("init", function () {
    load_plugin_textdomain(
        "x-reset-wp",
        false,
        dirname(plugin_basename(__FILE__)) . "/languages",
    );
});

add_action("admin_menu", function () {
    add_management_page(
        "X Reset WP",
        "X Reset WP",
        "manage_options",
        "x-reset-wp",
        "x_reset_wp_render_page",
    );
});

add_filter(
    "plugin_action_links",
    function ($links, $plugin_file) {
        if (plugin_basename(__FILE__) === $plugin_file) {
            $settings_link =
                '<a href="' .
                admin_url("tools.php?page=x-reset-wp") .
                '">' .
                __("Settings", "x-reset-wp") .
                "</a>";
            array_unshift($links, $settings_link);
        }
        return $links;
    },
    10,
    2,
);

add_action("admin_head", function () {
    $screen = get_current_screen();
    if ($screen && $screen->id === "tools_page_x-reset-wp") {
        echo '<link rel="icon" href="' . esc_url(X_RESET_WP_URL . 'assets/images/Logo-XReset-icon.png') . '" type="image/png">';
    }
});

add_action("admin_enqueue_scripts", function ($hook) {
    if ($hook !== "tools_page_x-reset-wp") {
        return;
    }

    $handler = new X_Reset_Handler();
    $options_map = $handler->get_options_map();

    $optionLabels = [];
    $optionGroups = [];
    foreach ($options_map as $key => $map) {
        $optionLabels[$key] = $map['label'];
        $optionGroups[$key] = $map['group'];
    }

    wp_enqueue_style(
        "x-reset-wp-admin",
        X_RESET_WP_URL . "assets/css/admin.css",
        [],
        X_RESET_WP_VERSION,
    );
    wp_enqueue_script(
        "x-reset-wp-admin",
        X_RESET_WP_URL . "assets/js/admin.js",
        [],
        X_RESET_WP_VERSION,
        true,
    );
    wp_localize_script("x-reset-wp-admin", "xResetWP", [
        "ajaxUrl" => admin_url("admin-ajax.php"),
        "nonce" => wp_create_nonce("x_reset_wp_nonce"),
        "confirm" => __(
            "Are you sure? This action is irreversible and will permanently delete the selected data.",
            "x-reset-wp",
        ),
        "optionLabels" => $optionLabels,
        "optionGroups" => $optionGroups,
        "groupLabels" => [
            "woocommerce" => __("WooCommerce", "x-reset-wp"),
            "wordpress" => __("WordPress", "x-reset-wp"),
            "system" => __("System", "x-reset-wp"),
        ],
        "strings" => [
            "selectAll" => __("Select All", "x-reset-wp"),
            "deselectAll" => __("Deselect All", "x-reset-wp"),
            "runCleanup" => __("Run Cleanup", "x-reset-wp"),
            "cleaning" => __("Cleaning:", "x-reset-wp"),
            "processing" => __("Processing...", "x-reset-wp"),
            "cancel" => __("Cancel", "x-reset-wp"),
            "yesDelete" => __("Yes, delete data", "x-reset-wp"),
            "close" => __("Close", "x-reset-wp"),
            "confirmCleanup" => __("Confirm Cleanup", "x-reset-wp"),
            "cleanupDone" => __("Cleanup completed", "x-reset-wp"),
            "allFinished" => __("All processes have finished.", "x-reset-wp"),
            "connectionErr" => __("Connection error", "x-reset-wp"),
            "invalidResp" => __("Invalid response", "x-reset-wp"),
            "networkErr" => __("Network error", "x-reset-wp"),
            "unknownErr" => __("Unknown error", "x-reset-wp"),
            "dryRun" => __("Dry run (simulate only)", "x-reset-wp"),
            "dateFrom" => __("From date", "x-reset-wp"),
            "dateTo" => __("To date", "x-reset-wp"),
            "batchSize" => __("Batch size:", "x-reset-wp"),
            "factoryResetConfirm" => __("Type DELETE to confirm factory reset:", "x-reset-wp"),
            "factoryResetInvalid" => __("Please type DELETE to confirm.", "x-reset-wp"),
            "factoryResetDesc" => __("Deletes ALL data: orders, users, posts, comments, media, terms, and WooCommerce settings. Irreversible.", "x-reset-wp"),
            "items" => __("items", "x-reset-wp"),
            "noItems" => __("empty", "x-reset-wp"),
            "requiresWc" => __("Requires WooCommerce", "x-reset-wp"),
            "expandAll" => __("Expand All", "x-reset-wp"),
            "collapseAll" => __("Collapse All", "x-reset-wp"),
            "dryRunNotice" => __("Dry-run mode enabled. No data will be deleted.", "x-reset-wp"),
            "downloadAudit" => __("Download Audit Log", "x-reset-wp"),
            "auditEmpty" => __("No audit log entries yet.", "x-reset-wp"),
            "resumePrompt" => __("Resume previous cleanup?", "x-reset-wp"),
        ],
    ]);
});

function x_reset_wp_render_page()
{
    $handler = new X_Reset_Handler();
    $options_map = $handler->get_options_map();

    $groups = ['woocommerce', 'wordpress', 'system'];
    $group_labels = [
        'woocommerce' => __('WooCommerce', 'x-reset-wp'),
        'wordpress'   => __('WordPress', 'x-reset-wp'),
        'system'      => __('System', 'x-reset-wp'),
    ];
    $group_icons = [
        'woocommerce' => 'W',
        'wordpress'   => 'W',
        'system'      => 'S',
    ];

    $grouped = [];
    foreach ($options_map as $key => $map) {
        $g = $map['group'] ?? 'wordpress';
        $grouped[$g][] = ['key' => $key, 'map' => $map];
    }
    ?>
    <div class="xrp-wrap">
        <div class="xrp-header">
            <div class="xrp-brand">
                <img class="xrp-brand-icon" src="<?php echo esc_url(X_RESET_WP_URL . 'assets/images/Logo-XReset-White.png'); ?>" alt="X Reset WP">
            </div>
            <div class="xrp-meta">
                <span class="xrp-version">v<?php echo esc_html(
                    X_RESET_WP_VERSION,
                ); ?></span>
                <span class="xrp-author"><?php echo sprintf(
                    esc_html__("by %s", "x-reset-wp"),
                    '<a href="https://jesusdavid.net/" target="_blank" rel="noopener">@jesusdavidweb</a>',
                ); ?></span>
            </div>
        </div>

        <div class="xrp-notice">
            <span class="xrp-notice-icon">!</span>
            <div>
                <strong><?php esc_html_e(
                    "Caution:",
                    "x-reset-wp",
                ); ?></strong> <?php echo esc_html__(
    "The actions of this plugin are",
    "x-reset-wp",
); ?> <strong><?php esc_html_e(
     "irreversible",
     "x-reset-wp",
 ); ?></strong>. <?php echo esc_html__(
    "Make sure you have a",
    "x-reset-wp",
); ?> <strong><?php esc_html_e(
     "backup",
     "x-reset-wp",
 ); ?></strong> <?php echo esc_html__(
    "of your database before proceeding.",
    "x-reset-wp",
); ?>
            </div>
        </div>

        <div id="xrp-dry-run-notice" class="xrp-notice xrp-notice-dry" style="display:none;">
            <span class="xrp-notice-icon">&#9881;</span>
            <div><?php esc_html_e(
                "Dry-run mode enabled. No data will be deleted.",
                "x-reset-wp",
            ); ?></div>
        </div>

        <div class="xrp-toolbar">
            <div class="xrp-toolbar-row">
                <label class="xrp-toggle-label">
                    <input type="checkbox" id="xrp-dry-run" class="xrp-toggle-input">
                    <span class="xrp-toggle-track">
                        <span class="xrp-toggle-thumb"></span>
                    </span>
                    <span><?php esc_html_e("Dry run (simulate only)", "x-reset-wp"); ?></span>
                </label>
                <div class="xrp-batch-control">
                    <label for="xrp-batch"><?php esc_html_e("Batch size:", "x-reset-wp"); ?></label>
                    <input type="range" id="xrp-batch" class="xrp-range" min="10" max="1000" value="100" step="10">
                    <span id="xrp-batch-value">100</span>
                </div>
            </div>
            <div class="xrp-toolbar-row">
                <div class="xrp-date-control">
                    <label for="xrp-date-from"><?php esc_html_e("From date:", "x-reset-wp"); ?></label>
                    <input type="date" id="xrp-date-from" class="xrp-date-input">
                    <label for="xrp-date-to"><?php esc_html_e("To date:", "x-reset-wp"); ?></label>
                    <input type="date" id="xrp-date-to" class="xrp-date-input">
                </div>
            </div>
            <div class="xrp-toolbar-row">
                <button type="button" id="xrp-export-audit" class="xrp-btn xrp-btn-ghost" style="font-size:12px;padding:6px 16px;">
                    <?php esc_html_e("Download Audit Log", "x-reset-wp"); ?>
                </button>
            </div>
        </div>

        <form id="xrp-form" class="xrp-form">

            <?php foreach ($groups as $group): ?>
            <?php if (empty($grouped[$group])) continue; ?>
            <div class="xrp-category" data-group="<?php echo esc_attr($group); ?>">
                <div class="xrp-category-header">
                    <button type="button" class="xrp-category-toggle" aria-expanded="true">
                        <span class="xrp-category-icon"><?php echo esc_html($group_icons[$group]); ?></span>
                        <span class="xrp-category-title"><?php echo esc_html($group_labels[$group]); ?></span>
                        <span class="xrp-category-count"><?php echo count($grouped[$group]); ?></span>
                        <span class="xrp-category-arrow"></span>
                    </button>
                    <div class="xrp-category-actions">
                        <button type="button" class="xrp-btn xrp-btn-tiny xrp-category-select"><?php esc_html_e("Select All", "x-reset-wp"); ?></button>
                    </div>
                </div>
                <div class="xrp-category-body">
                    <div class="xrp-grid">
                        <?php foreach ($grouped[$group] as $item):
                            $key = $item['key'];
                            $map = $item['map'];
                            $requires_wc = !empty($map['requires_woocommerce']);
                            $is_factory = ($key === 'factory_reset');
                        ?>
                        <div class="xrp-card<?php echo $is_factory ? ' xrp-card-danger' : ''; ?>" data-option="<?php echo esc_attr($key); ?>">
                            <label class="xrp-option">
                                <div class="xrp-checkbox-wrap">
                                    <input type="checkbox" name="options[]" value="<?php echo esc_attr($key); ?>">
                                    <span class="xrp-checkmark"></span>
                                </div>
                                <div class="xrp-option-content">
                                    <span class="xrp-option-title">
                                        <?php echo esc_html($map['label']); ?>
                                        <?php if ($requires_wc): ?>
                                        <span class="xrp-badge-wc">WC</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="xrp-option-desc">
                                        <?php if ($is_factory): ?>
                                        <?php esc_html_e("Deletes ALL data: orders, users, posts, comments, media, terms, and WooCommerce settings. Irreversible.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_orders'): ?>
                                        <?php esc_html_e("Delete all orders. Compatible with HPOS and legacy storage.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_order_notes'): ?>
                                        <?php esc_html_e("Delete all customer and internal order notes.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_coupons'): ?>
                                        <?php esc_html_e("Delete all WooCommerce coupons.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'users'): ?>
                                        <?php esc_html_e("Delete all non-admin users.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_sessions'): ?>
                                        <?php esc_html_e("Clear all active WooCommerce customer sessions.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'transients'): ?>
                                        <?php esc_html_e("Delete all WordPress and WooCommerce transients.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_logs'): ?>
                                        <?php esc_html_e("Delete WooCommerce and WordPress log files.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_analytics'): ?>
                                        <?php esc_html_e("Reset WooCommerce analytics data and reports.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_orders_by_country'): ?>
                                        <?php esc_html_e("Clear order data by country and region.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_downloads'): ?>
                                        <?php esc_html_e("Delete download permissions for downloadable products.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_reviews'): ?>
                                        <?php esc_html_e("Delete all product reviews and ratings.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_taxonomies'): ?>
                                        <?php esc_html_e("Delete product categories, tags, shipping classes, and attributes.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_shipping'): ?>
                                        <?php esc_html_e("Reset all shipping zones, methods, and locations.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_payment_gateways'): ?>
                                        <?php esc_html_e("Reset all payment gateway instance settings.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_tax_rates'): ?>
                                        <?php esc_html_e("Delete all tax rates and rate classes.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_webhooks'): ?>
                                        <?php esc_html_e("Delete all WooCommerce webhooks.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_api_keys'): ?>
                                        <?php esc_html_e("Delete WooCommerce API keys and WP Application Passwords.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'comments_spam'): ?>
                                        <?php esc_html_e("Delete all comments (approved, pending, spam, trash).", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'wc_refunds'): ?>
                                        <?php esc_html_e("Delete all refunds. Compatible with HPOS.", "x-reset-wp"); ?>
                                        <?php elseif ($key === 'posts_media'): ?>
                                        <?php esc_html_e("Delete all posts, pages, and media attachments.", "x-reset-wp"); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="xrp-option-count" id="xrp-count-<?php echo esc_attr($key); ?>"></span>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="xrp-actions">
                <div class="xrp-actions-left">
                    <button type="button" id="xrp-select-all" class="xrp-btn xrp-btn-ghost"><?php esc_html_e(
                        "Select All",
                        "x-reset-wp",
                    ); ?></button>
                </div>
                <button type="submit" id="xrp-submit" class="xrp-btn xrp-btn-primary" disabled><?php esc_html_e(
                    "Run Cleanup",
                    "x-reset-wp",
                ); ?></button>
            </div>
        </form>

        <div id="xrp-modal" class="xrp-modal" style="display:none;">
            <div class="xrp-modal-inner">
                <div id="xrp-modal-confirm" class="xrp-modal-body">
                    <h2 style="display:flex;align-items:center;gap:10px;">
                        <img src="<?php echo esc_url(X_RESET_WP_URL . 'assets/images/Logo-XReset-icon.png'); ?>" alt="" style="height:22px;width:auto;opacity:0.7;flex-shrink:0;">
                        <?php esc_html_e("Confirm Cleanup", "x-reset-wp"); ?>
                    </h2>
                    <p><?php esc_html_e(
                        "You are about to delete the following data:",
                        "x-reset-wp",
                    ); ?></p>
                    <ul id="xrp-modal-list"></ul>
                    <p class="xrp-warning"><?php esc_html_e(
                        "This action is irreversible. Make sure you have a backup.",
                        "x-reset-wp",
                    ); ?></p>
                    <div id="xrp-factory-confirm" style="display:none;margin-bottom:16px;">
                        <label style="display:block;font-size:13px;color:var(--xrp-danger);font-weight:600;margin-bottom:6px;"><?php esc_html_e(
                            "Type DELETE to confirm factory reset:",
                            "x-reset-wp",
                        ); ?></label>
                        <input type="text" id="xrp-factory-input" class="xrp-factory-input" placeholder="DELETE" autocomplete="off">
                    </div>
                    <div class="xrp-modal-buttons">
                        <button type="button" id="xrp-modal-cancel" class="xrp-btn xrp-btn-ghost"><?php esc_html_e(
                            "Cancel",
                            "x-reset-wp",
                        ); ?></button>
                        <button type="button" id="xrp-modal-start" class="xrp-btn xrp-btn-danger"><?php esc_html_e(
                            "Yes, delete data",
                            "x-reset-wp",
                        ); ?></button>
                    </div>
                </div>
                <div id="xrp-modal-progress" class="xrp-modal-body" style="display:none;">
                    <h2 id="xrp-progress-title" style="display:flex;align-items:center;gap:10px;">
                        <img src="<?php echo esc_url(X_RESET_WP_URL . 'assets/images/Logo-XReset-icon.png'); ?>" alt="" style="height:22px;width:auto;opacity:0.7;flex-shrink:0;">
                        <?php esc_html_e("Cleaning...", "x-reset-wp"); ?>
                    </h2>
                    <div class="xrp-progress-bar">
                        <div class="xrp-progress-fill"></div>
                    </div>
                    <p id="xrp-progress-step" class="xrp-progress-step"></p>
                    <ul id="xrp-log-list" class="xrp-log-list"></ul>
                    <div class="xrp-modal-buttons" style="display:none;" id="xrp-modal-done-buttons">
                        <button type="button" id="xrp-modal-close" class="xrp-btn xrp-btn-primary"><?php esc_html_e(
                            "Close",
                            "x-reset-wp",
                        ); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

add_action("wp_ajax_x_reset_wp_get_audit", function () {
    check_ajax_referer("x_reset_wp_nonce", "nonce");

    if (!current_user_can("manage_options")) {
        wp_send_json_error([
            "message" => __("Insufficient permissions.", "x-reset-wp"),
        ]);
    }

    $handler = new X_Reset_Handler();
    wp_send_json_success($handler->get_audit_log());
});

add_action("wp_ajax_x_reset_wp_process", function () {
    check_ajax_referer("x_reset_wp_nonce", "nonce");

    if (!current_user_can("manage_options")) {
        wp_send_json_error([
            "message" => __("Insufficient permissions.", "x-reset-wp"),
        ]);
    }

    $option = sanitize_text_field($_POST["option"] ?? "");
    $batch = intval($_POST["batch"] ?? 100);
    $dry_run = isset($_POST["dry_run"]) && $_POST["dry_run"] === "1";
    $date_from = sanitize_text_field($_POST["date_from"] ?? "");
    $date_to = sanitize_text_field($_POST["date_to"] ?? "");

    $handler = new X_Reset_Handler();
    $result = $handler->process($option, $batch, $dry_run, $date_from ?: null, $date_to ?: null);

    wp_send_json_success($result);
});

add_action("wp_ajax_x_reset_wp_stats", function () {
    check_ajax_referer("x_reset_wp_nonce", "nonce");

    if (!current_user_can("manage_options")) {
        wp_send_json_error([
            "message" => __("Insufficient permissions.", "x-reset-wp"),
        ]);
    }

    $handler = new X_Reset_Handler();
    $stats = $handler->get_counts();

    wp_send_json_success($stats);
});
