<?php

defined('ABSPATH') || exit;

class X_Reset_Handler {

    private $options_map;

    public function __construct() {
        $this->options_map = apply_filters('x_reset_wp_register_options', [
            // ── WooCommerce ──────────────────────────────────────────
            'wc_orders'            => ['label' => __('WooCommerce Orders', 'x-reset-wp'),               'method' => 'process_wc_orders',            'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_order_notes'       => ['label' => __('Order Notes', 'x-reset-wp'),                      'method' => 'process_wc_order_notes',       'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_coupons'           => ['label' => __('Coupons', 'x-reset-wp'),                          'method' => 'process_wc_coupons',            'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_sessions'          => ['label' => __('Customer Sessions', 'x-reset-wp'),                 'method' => 'process_wc_sessions',           'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_logs'              => ['label' => __('System Logs', 'x-reset-wp'),                       'method' => 'process_wc_logs',               'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_analytics'         => ['label' => __('WooCommerce Analytics', 'x-reset-wp'),             'method' => 'process_wc_analytics',          'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_orders_by_country' => ['label' => __('Geographic Statistics', 'x-reset-wp'),             'method' => 'process_wc_orders_by_country',  'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_downloads'         => ['label' => __('Customer Downloads', 'x-reset-wp'),                'method' => 'process_wc_downloads',          'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_reviews'           => ['label' => __('Product Reviews', 'x-reset-wp'),                   'method' => 'process_wc_reviews',            'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_taxonomies'        => ['label' => __('Product Categories, Tags & Attributes', 'x-reset-wp'), 'method' => 'process_wc_taxonomies',    'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_shipping'          => ['label' => __('Shipping Zones & Methods', 'x-reset-wp'),          'method' => 'process_wc_shipping',           'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_payment_gateways'  => ['label' => __('Payment Gateways', 'x-reset-wp'),                  'method' => 'process_wc_payment_gateways',   'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_tax_rates'         => ['label' => __('Tax Rates', 'x-reset-wp'),                         'method' => 'process_wc_tax_rates',          'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_webhooks'          => ['label' => __('Webhooks', 'x-reset-wp'),                          'method' => 'process_wc_webhooks',           'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_api_keys'          => ['label' => __('API Keys', 'x-reset-wp'),                          'method' => 'process_wc_api_keys',           'requires_woocommerce' => true,  'group' => 'woocommerce'],
            'wc_refunds'           => ['label' => __('Refunds', 'x-reset-wp'),                           'method' => 'process_wc_refunds',            'requires_woocommerce' => true,  'group' => 'woocommerce'],

            // ── WordPress ────────────────────────────────────────────
            'users'                => ['label' => __('Users (except admins)', 'x-reset-wp'),             'method' => 'process_users',                                    'group' => 'wordpress'],
            'transients'           => ['label' => __('Transients', 'x-reset-wp'),                        'method' => 'process_transients',                                'group' => 'wordpress'],
            'posts_media'          => ['label' => __('Posts, Pages & Media', 'x-reset-wp'),              'method' => 'process_posts_media',                               'group' => 'wordpress'],
            'comments_spam'        => ['label' => __('All Comments', 'x-reset-wp'),                      'method' => 'process_comments_spam',                             'group' => 'wordpress'],

            // ── System ───────────────────────────────────────────────
            'factory_reset'        => ['label' => __('Factory Reset', 'x-reset-wp'),                     'method' => 'process_factory_reset',                             'group' => 'system'],
        ]);
    }

    public function get_options_map() {
        return $this->options_map;
    }

    public function get_audit_log() {
        return get_option('x_reset_wp_audit_log', []);
    }

    // ─── Public API ──────────────────────────────────────────────────────

    public function process($option, $batch = 100, $dry_run = false, $date_from = null, $date_to = null) {
        if (!isset($this->options_map[$option])) {
            return [
                'label'     => '',
                'total'     => 0,
                'processed' => 0,
                'done'      => true,
                'message'   => __('Invalid option.', 'x-reset-wp'),
            ];
        }

        $map    = $this->options_map[$option];
        $method = $map['method'];
        $label  = $map['label'];

        if (!empty($map['requires_woocommerce']) && !class_exists('WooCommerce')) {
            return [
                'label'     => $label,
                'total'     => 0,
                'processed' => 0,
                'done'      => true,
                'message'   => sprintf(__('WooCommerce is required for %s.', 'x-reset-wp'), $label),
            ];
        }

        do_action('x_reset_wp_before_delete', $option, $label, $dry_run);

        $result = $this->$method($batch, $dry_run, $date_from, $date_to);

        $result['label'] = $label;

        if ($result['done'] && !$dry_run) {
            $this->audit_log($option, $label, $result);
        }

        do_action('x_reset_wp_after_delete', $option, $label, $result);

        return $result;
    }

    public function get_counts() {
        $counts = [];
        $skip = ['factory_reset'];

        foreach ($this->options_map as $key => $map) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            if (!empty($map['requires_woocommerce']) && !class_exists('WooCommerce')) {
                $counts[$key] = -1;
                continue;
            }
            $counts[$key] = $this->count_option($key);
        }

        return $counts;
    }

    // ─── Count helpers ──────────────────────────────────────────────────

    private function count_option($option) {
        global $wpdb;

        switch ($option) {
            case 'wc_orders':
                if ($this->is_hpos_active()) {
                    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders");
                }
                return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'shop_order'));

            case 'wc_order_notes':
                return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = %s", 'order_note'));

            case 'wc_coupons':
                return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'shop_coupon'));

            case 'users':
                $admin_ids = $this->get_admin_user_ids();
                $args = ['exclude' => $admin_ids, 'fields' => 'ID'];
                return (int) (new WP_User_Query($args))->get_total();

            case 'wc_sessions':
                $table = $wpdb->prefix . 'woocommerce_sessions';
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
                return $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : 0;

            case 'transients':
                return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('_transient_') . '%'));

            case 'wc_logs':
                $count = 0;
                foreach (['/' . 'uploads/wc-logs/', '/' . 'debug.log'] as $rel) {
                    $path = WP_CONTENT_DIR . $rel;
                    if (is_file($path)) { $count++; continue; }
                    if (is_dir($path)) { $count += count(glob($path . '*')); }
                }
                return $count;

            case 'wc_analytics':
                $count = 0;
                foreach (['wc_order_stats', 'wc_order_product_lookup', 'wc_order_coupon_lookup', 'wc_order_tax_lookup'] as $t) {
                    $table = $wpdb->prefix . $t;
                    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
                        $count += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
                    }
                }
                return $count;

            case 'wc_orders_by_country':
                $option_name = 'woocommerce_orders_by_country';
                return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $option_name));

            case 'wc_downloads':
                $table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
                return $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : 0;

            case 'wc_reviews':
                return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = %s", 'review'));

            case 'wc_taxonomies':
                $count = 0;
                foreach (['product_cat', 'product_tag', 'product_shipping_class'] as $tax) {
                    $count += (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax));
                }
                if (function_exists('wc_get_attribute_taxonomies')) {
                    $attributes = wc_get_attribute_taxonomies();
                    foreach ($attributes as $attr) {
                        $tax = wc_attribute_taxonomy_name($attr->attribute_name);
                        $count += (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax));
                    }
                }
                return $count;

            case 'wc_shipping':
                $table = $wpdb->prefix . 'woocommerce_shipping_zones';
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
                return $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : 0;

            case 'wc_payment_gateways':
                $settings = get_option('woocommerce_payment_gateway_setting', []);
                return is_array($settings) ? count($settings) : 0;

            case 'wc_tax_rates':
                $table = $wpdb->prefix . 'woocommerce_tax_rates';
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
                return $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : 0;

            case 'wc_webhooks':
                return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'shop_webhook'));

            case 'wc_api_keys':
                $count = 0;
                $table = $wpdb->prefix . 'woocommerce_api_keys';
                if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
                    $count += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
                }
                $oauth_table = $wpdb->prefix . 'oauth_access_tokens';
                if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $oauth_table))) {
                    $count += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$oauth_table}");
                }
                return $count;

            case 'comments_spam':
                return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}");

            case 'wc_refunds':
                return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'shop_order_refund'));

            case 'posts_media':
                return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN (%s, %s, %s)", 'post', 'page', 'attachment'));

            default:
                return 0;
        }
    }

    // ─── WooCommerce Orders ────────────────────────────────────────────

    private function process_wc_orders($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $using_hpos = $this->is_hpos_active();
        $date_where = $this->build_date_where($using_hpos ? 'date_created_gmt' : 'post_date', $date_from, $date_to);

        if ($using_hpos) {
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE 1=1{$date_where}");
        } else {
            $total = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s{$date_where}", 'shop_order')
            );
        }

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No orders to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d orders.', 'x-reset-wp'), $total)];
        }

        if ($using_hpos) {
            $order_ids = $wpdb->get_col(
                "SELECT id FROM {$wpdb->prefix}wc_orders WHERE 1=1{$date_where} ORDER BY id ASC LIMIT {$batch}"
            );
        } else {
            $order_ids = $wpdb->get_col(
                $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s{$date_where} ORDER BY ID ASC LIMIT %d", 'shop_order', $batch)
            );
        }

        $processed = 0;
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->delete(true);
                $processed++;
            }
        }

        $done = $processed < $batch;

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => $done,
            'message'   => $done
                ? sprintf(__('%d orders deleted.', 'x-reset-wp'), $total)
                : sprintf(__('Deleted %1$d orders, remaining %2$d...', 'x-reset-wp'), $processed, $total - $processed),
        ];
    }

    // ─── Order Notes ──────────────────────────────────────────────────

    private function process_wc_order_notes($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $date_where = $this->build_date_where('comment_date', $date_from, $date_to);

        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = %s{$date_where}", 'order_note')
        );

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No order notes to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d order notes.', 'x-reset-wp'), $total)];
        }

        $comment_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_type = %s{$date_where} ORDER BY comment_ID ASC LIMIT %d", 'order_note', $batch)
        );

        $processed = 0;
        foreach ($comment_ids as $comment_id) {
            wp_delete_comment((int) $comment_id, true);
            $processed++;
        }

        $done = $processed < $batch;

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => $done,
            'message'   => $done
                ? sprintf(__('%d notes deleted.', 'x-reset-wp'), $total)
                : sprintf(__('Deleted %1$d notes, remaining %2$d...', 'x-reset-wp'), $processed, $total - $processed),
        ];
    }

    // ─── Coupons ──────────────────────────────────────────────────────

    private function process_wc_coupons($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $date_where = $this->build_date_where('post_date', $date_from, $date_to);

        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s{$date_where}", 'shop_coupon')
        );

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No coupons to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d coupons.', 'x-reset-wp'), $total)];
        }

        $coupon_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s{$date_where} ORDER BY ID ASC LIMIT %d", 'shop_coupon', $batch)
        );

        $processed = 0;
        foreach ($coupon_ids as $coupon_id) {
            $coupon = new WC_Coupon($coupon_id);
            $coupon->delete(true);
            $processed++;
        }

        $done = $processed < $batch;

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => $done,
            'message'   => $done
                ? sprintf(__('%d coupons deleted.', 'x-reset-wp'), $total)
                : sprintf(__('Deleted %1$d coupons, remaining %2$d...', 'x-reset-wp'), $processed, $total - $processed),
        ];
    }

    // ─── Users (except admins) ─────────────────────────────────────────

    private function process_users($batch, $dry_run, $date_from, $date_to) {
        $admin_ids = $this->get_admin_user_ids();

        $args = [
            'exclude'  => $admin_ids,
            'number'   => $batch,
            'orderby'  => 'ID',
            'order'    => 'ASC',
            'fields'   => 'ID',
        ];

        if (!empty($date_from)) {
            $args['date_query'][] = ['after' => $date_from, 'inclusive' => true];
        }
        if (!empty($date_to)) {
            $args['date_query'][] = ['before' => $date_to, 'inclusive' => true];
        }

        $total_query = new WP_User_Query(array_merge($args, ['number' => -1, 'fields' => 'ID']));
        $total = (int) $total_query->get_total();

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No users to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d users.', 'x-reset-wp'), $total)];
        }

        $user_ids = get_users($args);
        $processed = count($user_ids);

        foreach ($user_ids as $user_id) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user((int) $user_id);
        }

        $done = $processed < $batch;

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => $done,
            'message'   => $done
                ? sprintf(__('%d users deleted.', 'x-reset-wp'), $total)
                : sprintf(__('Deleted %1$d users, remaining %2$d...', 'x-reset-wp'), $processed, $total - $processed),
        ];
    }

    // ─── Customer Sessions ────────────────────────────────────────────

    private function process_wc_sessions($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $table = $wpdb->prefix . 'woocommerce_sessions';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));

        if (!$exists) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('Sessions table not found.', 'x-reset-wp')];
        }

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d sessions.', 'x-reset-wp'), $total)];
        }

        $wpdb->query("TRUNCATE TABLE {$table}");

        return [
            'total'     => 1,
            'processed' => 1,
            'done'      => true,
            'message'   => __('Customer sessions deleted.', 'x-reset-wp'),
        ];
    }

    // ─── Transients ────────────────────────────────────────────────────

    private function process_transients($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $transient_like = $wpdb->esc_like('_transient_') . '%';

        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $transient_like)
        );

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No transients to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d transients.', 'x-reset-wp'), $total)];
        }

        $transients = $wpdb->get_col(
            $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d", $transient_like, $batch)
        );

        $processed = 0;
        foreach ($transients as $transient) {
            if (strpos($transient, '_transient_timeout_') === 0) {
                $wpdb->delete($wpdb->options, ['option_name' => $transient]);
                $processed++;
            } elseif (strpos($transient, '_site_transient_') !== false || strpos($transient, '_site_transient_timeout_') !== false) {
                $wpdb->delete($wpdb->options, ['option_name' => $transient]);
                $processed++;
            } else {
                $key = str_replace('_transient_', '', $transient);
                delete_transient($key);
                $processed++;
            }
        }

        $done = $processed < $batch;

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => $done,
            'message'   => $done
                ? sprintf(__('%d transients deleted.', 'x-reset-wp'), $total)
                : sprintf(__('Deleted %1$d transients, remaining %2$d...', 'x-reset-wp'), $processed, $total - $processed),
        ];
    }

    // ─── System Logs ──────────────────────────────────────────────────

    private function process_wc_logs($batch, $dry_run, $date_from, $date_to) {
        $log_paths = [
            WP_CONTENT_DIR . '/uploads/wc-logs/',
            WP_CONTENT_DIR . '/uploads/wc-logs/logs/',
            WP_CONTENT_DIR . '/debug.log',
        ];

        $file_count = 0;
        foreach ($log_paths as $log_path) {
            if (!file_exists($log_path)) continue;
            if (is_file($log_path)) { $file_count++; continue; }
            if (is_dir($log_path)) { $file_count += count(glob($log_path . '*')); }
        }

        if ($file_count === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No log files to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $file_count, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d log files.', 'x-reset-wp'), $file_count)];
        }

        $files_deleted = 0;
        foreach ($log_paths as $log_path) {
            if (!file_exists($log_path)) continue;
            if (is_file($log_path)) {
                if (is_writable($log_path) && unlink($log_path)) $files_deleted++;
                continue;
            }
            if (is_dir($log_path)) {
                $files = glob($log_path . '*');
                foreach ($files as $file) {
                    if (is_file($file) && is_writable($file) && unlink($file)) $files_deleted++;
                }
            }
        }

        return [
            'total'     => $files_deleted,
            'processed' => $files_deleted,
            'done'      => true,
            'message'   => sprintf(__('%d log files deleted.', 'x-reset-wp'), $files_deleted),
        ];
    }

    // ─── WooCommerce Analytics ─────────────────────────────────────────

    private function process_wc_analytics($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'wc_order_stats',
            $wpdb->prefix . 'wc_order_product_lookup',
            $wpdb->prefix . 'wc_order_coupon_lookup',
            $wpdb->prefix . 'wc_order_tax_lookup',
        ];

        if ($dry_run) {
            $count = 0;
            foreach ($tables as $table) {
                if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
                    $count += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
                }
            }
            return ['total' => $count, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would clear %d analytics records.', 'x-reset-wp'), $count)];
        }

        $truncated = 0;
        foreach ($tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($exists) {
                $wpdb->query("TRUNCATE TABLE {$table}");
                $truncated++;
            }
        }

        return [
            'total'     => $truncated,
            'processed' => $truncated,
            'done'      => true,
            'message'   => sprintf(__('%d analytics tables cleared.', 'x-reset-wp'), $truncated),
        ];
    }

    // ─── Geographic Statistics ────────────────────────────────────────

    private function process_wc_orders_by_country($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $option_name = 'woocommerce_orders_by_country';

        if ($dry_run) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $option_name));
            if ($exists) {
                return ['total' => 1, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => __('Would delete geographic statistics.', 'x-reset-wp')];
            }
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No geographic statistics found.', 'x-reset-wp')];
        }

        $deleted = $wpdb->delete($wpdb->options, ['option_name' => $option_name]);
        delete_transient('woocommerce_orders_by_country');

        if ($deleted || wp_cache_delete($option_name, 'options')) {
            return [
                'total'     => 1,
                'processed' => 1,
                'done'      => true,
                'message'   => __('Geographic statistics deleted.', 'x-reset-wp'),
            ];
        }

        return [
            'total'     => 0,
            'processed' => 0,
            'done'      => true,
            'message'   => __('No geographic statistics found.', 'x-reset-wp'),
        ];
    }

    // ─── Customer Downloads ────────────────────────────────────────────

    private function process_wc_downloads($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));

        if (!$exists) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('Downloads table not found.', 'x-reset-wp')];
        }

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No download permissions to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d download permissions.', 'x-reset-wp'), $total)];
        }

        $perm_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT permission_id FROM {$table} ORDER BY permission_id ASC LIMIT %d", $batch)
        );

        $processed = 0;
        foreach ($perm_ids as $perm_id) {
            $deleted = $wpdb->delete($table, ['permission_id' => (int) $perm_id]);
            if ($deleted) {
                $processed++;
            }
        }

        $done = $processed < $batch;

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => $done,
            'message'   => $done
                ? sprintf(__('%d download permissions deleted.', 'x-reset-wp'), $total)
                : sprintf(__('Deleted %1$d permissions, remaining %2$d...', 'x-reset-wp'), $processed, $total - $processed),
        ];
    }

    // ─── NEW: Posts, Pages & Media ─────────────────────────────────────

    private function process_posts_media($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $date_where = $this->build_date_where('post_date', $date_from, $date_to);

        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN (%s, %s, %s){$date_where}", 'post', 'page', 'attachment')
        );

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No posts, pages or media to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d posts, pages & media items.', 'x-reset-wp'), $total)];
        }

        $ids = $wpdb->get_col(
            $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type IN (%s, %s, %s){$date_where} ORDER BY ID ASC LIMIT %d", 'post', 'page', 'attachment', $batch)
        );

        $processed = 0;
        foreach ($ids as $id) {
            wp_delete_post((int) $id, true);
            $processed++;
        }

        $done = $processed < $batch;

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => $done,
            'message'   => $done
                ? sprintf(__('%d posts, pages & media items deleted.', 'x-reset-wp'), $total)
                : sprintf(__('Deleted %1$d items, remaining %2$d...', 'x-reset-wp'), $processed, $total - $processed),
        ];
    }

    // ─── NEW: Product Reviews ──────────────────────────────────────────

    private function process_wc_reviews($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $date_where = $this->build_date_where('comment_date', $date_from, $date_to);

        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = %s{$date_where}", 'review')
        );

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No product reviews to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d product reviews.', 'x-reset-wp'), $total)];
        }

        $comment_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_type = %s{$date_where} ORDER BY comment_ID ASC LIMIT %d", 'review', $batch)
        );

        $processed = 0;
        foreach ($comment_ids as $comment_id) {
            wp_delete_comment((int) $comment_id, true);
            $processed++;
        }

        $done = $processed < $batch;

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => $done,
            'message'   => $done
                ? sprintf(__('%d product reviews deleted.', 'x-reset-wp'), $total)
                : sprintf(__('Deleted %1$d reviews, remaining %2$d...', 'x-reset-wp'), $processed, $total - $processed),
        ];
    }

    // ─── NEW: Product Taxonomies ───────────────────────────────────────

    private function process_wc_taxonomies($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $taxonomies = ['product_cat', 'product_tag', 'product_shipping_class'];
        if (function_exists('wc_get_attribute_taxonomies')) {
            $attributes = wc_get_attribute_taxonomies();
            foreach ($attributes as $attr) {
                $taxonomies[] = wc_attribute_taxonomy_name($attr->attribute_name);
            }
        }

        $total = 0;
        foreach ($taxonomies as $tax) {
            $total += (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax));
        }

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No product taxonomies to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d taxonomy terms.', 'x-reset-wp'), $total)];
        }

        $processed = 0;
        foreach ($taxonomies as $tax) {
            $terms = $wpdb->get_col($wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax));
            foreach ($terms as $term_id) {
                wp_delete_term((int) $term_id, $tax);
                $processed++;
            }
        }

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => true,
            'message'   => sprintf(__('%d taxonomy terms deleted.', 'x-reset-wp'), $processed),
        ];
    }

    // ─── NEW: Shipping ─────────────────────────────────────────────────

    private function process_wc_shipping($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'woocommerce_shipping_zones',
            $wpdb->prefix . 'woocommerce_shipping_zone_methods',
            $wpdb->prefix . 'woocommerce_shipping_zone_locations',
        ];

        $total = 0;
        foreach ($tables as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
                $total += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            }
        }

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No shipping data to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d shipping zones & methods.', 'x-reset-wp'), $total)];
        }

        $truncated = 0;
        foreach ($tables as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
                $wpdb->query("TRUNCATE TABLE {$table}");
                $truncated++;
            }
        }

        return [
            'total'     => $total,
            'processed' => $total,
            'done'      => true,
            'message'   => __('Shipping zones & methods deleted.', 'x-reset-wp'),
        ];
    }

    // ─── NEW: Payment Gateways ─────────────────────────────────────────

    private function process_wc_payment_gateways($batch, $dry_run, $date_from, $date_to) {
        $settings = get_option('woocommerce_payment_gateway_setting', []);
        $total = is_array($settings) ? count($settings) : 0;

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would reset %d payment gateways.', 'x-reset-wp'), $total)];
        }

        delete_option('woocommerce_payment_gateway_setting');

        foreach (wp_load_alloptions() as $key => $value) {
            if (strpos($key, 'woocommerce_') === 0 && strpos($key, '_settings') !== false) {
                delete_option($key);
            }
        }

        return [
            'total'     => $total,
            'processed' => $total,
            'done'      => true,
            'message'   => __('Payment gateway settings deleted.', 'x-reset-wp'),
        ];
    }

    // ─── NEW: Tax Rates ────────────────────────────────────────────────

    private function process_wc_tax_rates($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'woocommerce_tax_rates',
            $wpdb->prefix . 'woocommerce_tax_rate_locations',
            $wpdb->prefix . 'wc_tax_rate_classes',
        ];

        $total = 0;
        foreach ($tables as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
                $total += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            }
        }

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No tax rates to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d tax rates.', 'x-reset-wp'), $total)];
        }

        $cleared = 0;
        foreach ($tables as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
                $wpdb->query("TRUNCATE TABLE {$table}");
                $cleared++;
            }
        }

        return [
            'total'     => $total,
            'processed' => $total,
            'done'      => true,
            'message'   => __('Tax rates deleted.', 'x-reset-wp'),
        ];
    }

    // ─── NEW: Webhooks ─────────────────────────────────────────────────

    private function process_wc_webhooks($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $date_where = $this->build_date_where('post_date', $date_from, $date_to);

        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s{$date_where}", 'shop_webhook')
        );

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No webhooks to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d webhooks.', 'x-reset-wp'), $total)];
        }

        $ids = $wpdb->get_col(
            $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s{$date_where} ORDER BY ID ASC LIMIT %d", 'shop_webhook', $batch)
        );

        $processed = 0;
        foreach ($ids as $id) {
            wp_delete_post((int) $id, true);
            $processed++;
        }

        $done = $processed < $batch;

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => $done,
            'message'   => $done
                ? sprintf(__('%d webhooks deleted.', 'x-reset-wp'), $total)
                : sprintf(__('Deleted %1$d webhooks, remaining %2$d...', 'x-reset-wp'), $processed, $total - $processed),
        ];
    }

    // ─── NEW: API Keys ──────────────────────────────────────────────────

    private function process_wc_api_keys($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $wc_table = $wpdb->prefix . 'woocommerce_api_keys';
        $oauth_table = $wpdb->prefix . 'oauth_access_tokens';

        $total = 0;
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wc_table))) {
            $total += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wc_table}");
        }
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $oauth_table))) {
            $total += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$oauth_table}");
        }

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No API keys to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d API keys.', 'x-reset-wp'), $total)];
        }

        $processed = 0;
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wc_table))) {
            $processed += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wc_table}");
            $wpdb->query("TRUNCATE TABLE {$wc_table}");
        }
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $oauth_table))) {
            $processed += (int) $wpdb->get_var("SELECT COUNT(*) FROM {$oauth_table}");
            $wpdb->query("TRUNCATE TABLE {$oauth_table}");
        }

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => true,
            'message'   => __('API keys deleted.', 'x-reset-wp'),
        ];
    }

    // ─── NEW: All Comments ─────────────────────────────────────────────

    private function process_comments_spam($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $date_where = $this->build_date_where('comment_date', $date_from, $date_to);

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE 1=1{$date_where}");

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No comments to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d comments.', 'x-reset-wp'), $total)];
        }

        $comment_ids = $wpdb->get_col(
            "SELECT comment_ID FROM {$wpdb->comments} WHERE 1=1{$date_where} ORDER BY comment_ID ASC LIMIT {$batch}"
        );

        $processed = 0;
        foreach ($comment_ids as $comment_id) {
            wp_delete_comment((int) $comment_id, true);
            $processed++;
        }

        $done = $processed < $batch;

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => $done,
            'message'   => $done
                ? sprintf(__('%d comments deleted.', 'x-reset-wp'), $total)
                : sprintf(__('Deleted %1$d comments, remaining %2$d...', 'x-reset-wp'), $processed, $total - $processed),
        ];
    }

    // ─── NEW: Refunds ───────────────────────────────────────────────────

    private function process_wc_refunds($batch, $dry_run, $date_from, $date_to) {
        global $wpdb;

        $using_hpos = $this->is_hpos_active();
        $date_where = $this->build_date_where($using_hpos ? 'date_created_gmt' : 'post_date', $date_from, $date_to);

        if ($using_hpos) {
            $total = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE type = %s{$date_where}", 'shop_order_refund')
            );
        } else {
            $total = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s{$date_where}", 'shop_order_refund')
            );
        }

        if ($total === 0) {
            return ['total' => 0, 'processed' => 0, 'done' => true, 'message' => __('No refunds to delete.', 'x-reset-wp')];
        }

        if ($dry_run) {
            return ['total' => $total, 'processed' => 0, 'done' => true, 'dry_run' => true, 'message' => sprintf(__('Would delete %d refunds.', 'x-reset-wp'), $total)];
        }

        if ($using_hpos) {
            $refund_ids = $wpdb->get_col(
                $wpdb->prepare("SELECT id FROM {$wpdb->prefix}wc_orders WHERE type = %s{$date_where} ORDER BY id ASC LIMIT %d", 'shop_order_refund', $batch)
            );
        } else {
            $refund_ids = $wpdb->get_col(
                $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s{$date_where} ORDER BY ID ASC LIMIT %d", 'shop_order_refund', $batch)
            );
        }

        $processed = 0;
        foreach ($refund_ids as $refund_id) {
            $refund = wc_get_order($refund_id);
            if ($refund) {
                $refund->delete(true);
                $processed++;
            }
        }

        $done = $processed < $batch;

        return [
            'total'     => $total,
            'processed' => $processed,
            'done'      => $done,
            'message'   => $done
                ? sprintf(__('%d refunds deleted.', 'x-reset-wp'), $total)
                : sprintf(__('Deleted %1$d refunds, remaining %2$d...', 'x-reset-wp'), $processed, $total - $processed),
        ];
    }

    // ─── NEW: Factory Reset ─────────────────────────────────────────────

    private function process_factory_reset($batch, $dry_run, $date_from, $date_to) {
        return [
            'total'     => 0,
            'processed' => 0,
            'done'      => true,
            'message'   => __('Factory Reset is processed via sub-options.', 'x-reset-wp'),
        ];
    }

    // ─── Audit Log ────────────────────────────────────────────────────

    private function audit_log($option, $label, $result) {
        $audit = get_option('x_reset_wp_audit_log', []);
        $audit[] = [
            'time'    => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'option'  => $option,
            'label'   => $label,
            'total'   => (int) $result['total'],
            'deleted' => (int) $result['processed'],
            'message' => $result['message'] ?? '',
        ];

        if (count($audit) > 100) {
            $audit = array_slice($audit, -100);
        }

        update_option('x_reset_wp_audit_log', $audit);
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    private function build_date_where($column, $date_from, $date_to) {
        global $wpdb;
        $where = '';
        if (!empty($date_from)) {
            $where .= $wpdb->prepare(" AND {$column} >= %s", $date_from);
        }
        if (!empty($date_to)) {
            $where .= $wpdb->prepare(" AND {$column} <= %s", $date_to);
        }
        return $where;
    }

    private function is_hpos_active() {
        if (!class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return false;
        }
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    private function get_admin_user_ids() {
        $admins = get_users([
            'role__in' => ['administrator', 'super_admin'],
            'fields'   => 'ID',
        ]);
        return array_map('intval', $admins);
    }
}
