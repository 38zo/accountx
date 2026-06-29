<?php
/**
 * Plugin Name: TeaMore
 * Plugin URI:  https://github.com/Frenziecodes/teamore
 * Description: Turn WooCommerce customer accounts into team accounts with lightweight subaccount management.
 * Version:     1.0.1
 * Author:      lewisushindi
 * Author URI:  https://github.com/frenziecodes
 * Text Domain: teamore
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP: 7.4
 * Requires at least: 6.2
 * WC requires at least: 7.0
 * WC tested up to: 9.9
 *
 * @package TeaMore
 */

defined( 'ABSPATH' ) || exit;

define( 'TEAMORE_VERSION', '1.0.1' );
define( 'TEAMORE_FILE', __FILE__ );
define( 'TEAMORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TEAMORE_URL', plugin_dir_url( __FILE__ ) );

require_once TEAMORE_PATH . 'includes/class-teamore-plugin.php';

register_activation_hook( __FILE__, array( 'Teamore_Plugin', 'activate' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'Teamore_Plugin', 'instance' ) );
