<?php
/**
 * Main plugin bootstrap.
 *
 * @package Customer Subaccounts for WooCommerce
 */

defined( 'ABSPATH' ) || exit;

require_once CSFW_PATH . 'includes/class-csfw-settings.php';
require_once CSFW_PATH . 'includes/class-csfw-subaccounts.php';
require_once CSFW_PATH . 'includes/class-csfw-orders.php';
require_once CSFW_PATH . 'includes/class-csfw-switching.php';
require_once CSFW_PATH . 'includes/class-csfw-my-account.php';
require_once CSFW_PATH . 'includes/class-csfw-admin.php';

/**
 * Customer Subaccounts for WooCommerce plugin container.
 */
final class CSFW_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var CSFW_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings service.
	 *
	 * @var CSFW_Settings
	 */
	public $settings;

	/**
	 * Subaccounts service.
	 *
	 * @var CSFW_Subaccounts
	 */
	public $subaccounts;

	/**
	 * Orders service.
	 *
	 * @var CSFW_Orders
	 */
	public $orders;

	/**
	 * Switching service.
	 *
	 * @var CSFW_Switching
	 */
	public $switching;

	/**
	 * My Account integration.
	 *
	 * @var CSFW_My_Account
	 */
	public $my_account;

	/**
	 * Admin integration.
	 *
	 * @var CSFW_Admin
	 */
	public $admin;

	/**
	 * Get singleton instance.
	 *
	 * @return CSFW_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
			return;
		}

		$this->settings    = new CSFW_Settings();
		$this->subaccounts = new CSFW_Subaccounts( $this->settings );
		$this->orders      = new CSFW_Orders( $this->settings, $this->subaccounts );
		$this->switching   = new CSFW_Switching( $this->settings, $this->subaccounts );
		$this->my_account  = new CSFW_My_Account( $this->settings, $this->subaccounts, $this->orders, $this->switching );
		$this->admin       = new CSFW_Admin( $this->settings, $this->subaccounts );
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! get_option( 'customer_subaccounts_for_woocommerce_settings' ) ) {
			add_option( 'customer_subaccounts_for_woocommerce_settings', CSFW_Settings::defaults() );
		}

		if ( class_exists( 'CSFW_My_Account' ) ) {
			CSFW_My_Account::add_endpoint();
			flush_rewrite_rules();
		}
	}

	/**
	 * Check WooCommerce dependency.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Dependency notice.
	 *
	 * @return void
	 */
	public function woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		esc_html_e( 'Customer Subaccounts for WooCommerce requires WooCommerce to be installed and active.', 'customer-subaccounts-for-woocommerce' );
		echo '</p></div>';
	}
}
