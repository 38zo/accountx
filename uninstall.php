<?php
/**
 * Customer Subaccounts for WooCommerce uninstall cleanup.
 *
 * @package Customer Subaccounts for WooCommerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'customer_subaccounts_for_woocommerce_settings' );
