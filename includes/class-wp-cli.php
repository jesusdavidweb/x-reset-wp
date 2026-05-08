<?php

defined('ABSPATH') || exit;

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Reset WordPress and WooCommerce data via WP-CLI.
 *
 * ## EXAMPLES
 *
 *     wp x-reset-wp list
 *     wp x-reset-wp run wc_orders --dry-run
 *     wp x-reset-wp run users --from=2024-01-01 --to=2024-12-31
 *     wp x-reset-wp factory-reset --confirm=DELETE
 *     wp x-reset-wp audit-log --format=csv
 */
class X_Reset_WP_CLI_Command extends WP_CLI_Command {

    /**
     * List all available cleanup options.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Default: table. Options: table, csv, json, yaml.
     *
     * ## EXAMPLES
     *
     *     wp x-reset-wp list
     *     wp x-reset-wp list --format=json
     */
    public function list_( $args, $assoc_args ) {
        $handler = new X_Reset_Handler();
        $map     = $handler->get_options_map();

        $items = [];
        foreach ( $map as $key => $config ) {
            $items[] = [
                'Option'   => $key,
                'Label'    => $config['label'],
                'Group'    => $config['group'] ?? '',
                'Requires WooCommerce' => ! empty( $config['requires_woocommerce'] ) ? 'Yes' : 'No',
            ];
        }

        $format = $assoc_args['format'] ?? 'table';
        WP_CLI\Utils\format_items( $format, $items, [ 'Option', 'Label', 'Group', 'Requires WooCommerce' ] );
    }

    /**
     * Run a single cleanup operation.
     *
     * ## OPTIONS
     *
     * <option>
     * : The option key to clean (e.g., wc_orders, users, transients).
     *
     * [--dry-run]
     * : Simulate the operation without deleting any data.
     *
     * [--from=<date>]
     * : Start date in Y-m-d format (inclusive).
     *
     * [--to=<date>]
     * : End date in Y-m-d format (inclusive).
     *
     * [--batch=<number>]
     * : Items per batch. Default: 100.
     *
     * ## EXAMPLES
     *
     *     wp x-reset-wp run wc_orders
     *     wp x-reset-wp run users --dry-run
     *     wp x-reset-wp run transients --batch=500
     */
    public function run( $args, $assoc_args ) {
        $option = $args[0] ?? '';
        if ( empty( $option ) ) {
            WP_CLI::error( 'Please specify an option. Use `wp x-reset-wp list` to see available options.' );
        }

        $dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
        $from    = $assoc_args['from'] ?? null;
        $to      = $assoc_args['to'] ?? null;
        $batch   = (int) ( $assoc_args['batch'] ?? 100 );

        $handler = new X_Reset_Handler();

        $done = false;
        while ( ! $done ) {
            $result = $handler->process( $option, $batch, $dry_run, $from, $to );

            $prefix = ! empty( $result['dry_run'] ) ? '[DRY-RUN] ' : '';
            WP_CLI::line( $prefix . ( $result['message'] ?? '' ) );

            $done = ! empty( $result['done'] );
        }
    }

    /**
     * Perform a complete factory reset (deletes ALL data).
     *
     * ## OPTIONS
     *
     * --confirm=<string>
     * : Must be "DELETE" to confirm.
     *
     * ## EXAMPLES
     *
     *     wp x-reset-wp factory-reset --confirm=DELETE
     */
    public function factory_reset( $args, $assoc_args ) {
        $confirm = $assoc_args['confirm'] ?? '';
        if ( $confirm !== 'DELETE' ) {
            WP_CLI::error( 'Use --confirm=DELETE to confirm the factory reset.' );
        }

        WP_CLI::warning( 'Starting factory reset. This will delete ALL data.' );

        $handler   = new X_Reset_Handler();
        $options_map = $handler->get_options_map();

        foreach ( $options_map as $key => $map ) {
            if ( $key === 'factory_reset' ) {
                continue;
            }
            if ( ! empty( $map['requires_woocommerce'] ) && ! class_exists( 'WooCommerce' ) ) {
                continue;
            }

            WP_CLI::line( "Processing: {$map['label']}..." );

            $done = false;
            while ( ! $done ) {
                $result = $handler->process( $key, 100, false, null, null );
                WP_CLI::line( '  ' . ( $result['message'] ?? '' ) );
                $done = ! empty( $result['done'] );
            }
        }

        WP_CLI::success( 'Factory reset completed.' );
    }

    /**
     * View or export the audit log.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Default: table. Options: table, csv, json, yaml.
     *
     * ## EXAMPLES
     *
     *     wp x-reset-wp audit-log
     *     wp x-reset-wp audit-log --format=csv
     *     wp x-reset-wp audit-log --format=json
     */
    public function audit_log( $args, $assoc_args ) {
        $handler = new X_Reset_Handler();
        $log     = $handler->get_audit_log();

        if ( empty( $log ) ) {
            WP_CLI::warning( 'No audit log entries found.' );
            return;
        }

        $items = [];
        foreach ( $log as $entry ) {
            $items[] = [
                'Time'    => $entry['time'] ?? '',
                'User ID' => $entry['user_id'] ?? '',
                'Option'  => $entry['option'] ?? '',
                'Label'   => $entry['label'] ?? '',
                'Total'   => $entry['total'] ?? 0,
                'Deleted' => $entry['deleted'] ?? 0,
                'Message' => $entry['message'] ?? '',
            ];
        }

        $format = $assoc_args['format'] ?? 'table';
        WP_CLI\Utils\format_items( $format, $items, [ 'Time', 'User ID', 'Option', 'Label', 'Total', 'Deleted', 'Message' ] );
    }
}

WP_CLI::add_command( 'x-reset-wp', 'X_Reset_WP_CLI_Command' );
