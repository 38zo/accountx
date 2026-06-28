<?php
/**
 * Simple user switching.
 *
 * @package Customer Subaccounts for WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Customer Subaccounts for WooCommerce switching service.
 */
class CSFW_Switching {
	const SESSION_PARENT_ID = 'csfw_parent_user_id';

	/**
	 * Settings.
	 *
	 * @var CSFW_Settings
	 */
	private $settings;

	/**
	 * Subaccounts.
	 *
	 * @var CSFW_Subaccounts
	 */
	private $subaccounts;

	/**
	 * Constructor.
	 *
	 * @param CSFW_Settings    $settings    Settings service.
	 * @param CSFW_Subaccounts $subaccounts Subaccounts service.
	 */
	public function __construct( CSFW_Settings $settings, CSFW_Subaccounts $subaccounts ) {
		$this->settings    = $settings;
		$this->subaccounts = $subaccounts;

		add_action( 'template_redirect', array( $this, 'maybe_switch' ) );
		add_action( 'woocommerce_before_account_navigation', array( $this, 'render_switch_back_notice' ) );
	}

	/**
	 * Switch into or back from a subaccount.
	 *
	 * @return void
	 */
	public function maybe_switch() {
		if ( ! $this->settings->is_enabled() || ! $this->settings->is_switching_enabled() || ! is_user_logged_in() ) {
			return;
		}

		if ( isset( $_GET['csfw_switch_to'], $_GET['_wpnonce'] ) ) {
			$subaccount_id = absint( wp_unslash( $_GET['csfw_switch_to'] ) );

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'customer_subaccounts_for_woocommerce_switch_to_' . $subaccount_id ) ) {
				wc_add_notice( __( 'Switch request could not be verified.', 'customer-subaccounts-for-woocommerce' ), 'error' );
				return;
			}

			$parent_id = get_current_user_id();

			if ( ! $this->subaccounts->parent_owns_subaccount( $parent_id, $subaccount_id ) ) {
				wc_add_notice( __( 'You cannot switch to this subaccount.', 'customer-subaccounts-for-woocommerce' ), 'error' );
				return;
			}

			$this->set_parent_session( $parent_id );
			wp_set_current_user( $subaccount_id );
			wp_set_auth_cookie( $subaccount_id );
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}

		if ( isset( $_GET['customer_subaccounts_for_woocommerce_switch_back'], $_GET['_wpnonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'customer_subaccounts_for_woocommerce_switch_back' ) ) {
				wc_add_notice( __( 'Switch back request could not be verified.', 'customer-subaccounts-for-woocommerce' ), 'error' );
				return;
			}

			$parent_id = $this->get_parent_session();

			if ( $parent_id < 1 ) {
				wc_add_notice( __( 'No parent session was found.', 'customer-subaccounts-for-woocommerce' ), 'error' );
				return;
			}

			$this->clear_parent_session();
			wp_set_current_user( $parent_id );
			wp_set_auth_cookie( $parent_id );
			wp_safe_redirect( wc_get_account_endpoint_url( 'customer-subaccounts' ) );
			exit;
		}
	}

	/**
	 * Get switch-to URL.
	 *
	 * @param int $subaccount_id Subaccount ID.
	 * @return string
	 */
	public function get_switch_to_url( $subaccount_id ) {
		return wp_nonce_url(
			add_query_arg( 'csfw_switch_to', absint( $subaccount_id ), wc_get_account_endpoint_url( 'customer-subaccounts' ) ),
			'customer_subaccounts_for_woocommerce_switch_to_' . absint( $subaccount_id )
		);
	}

	/**
	 * Get switch-back URL.
	 *
	 * @return string
	 */
	public function get_switch_back_url() {
		return wp_nonce_url( add_query_arg( 'customer_subaccounts_for_woocommerce_switch_back', '1', wc_get_page_permalink( 'myaccount' ) ), 'customer_subaccounts_for_woocommerce_switch_back' );
	}

	/**
	 * Is current session switched?
	 *
	 * @return bool
	 */
	public function is_switched() {
		return $this->get_parent_session() > 0;
	}

	/**
	 * Render switch-back notice.
	 *
	 * @return void
	 */
	public function render_switch_back_notice() {
		if ( ! $this->is_switched() ) {
			return;
		}

		echo '<p class="woocommerce-info csfw-switch-back">';
		echo esc_html__( 'You are viewing this store as a subaccount.', 'customer-subaccounts-for-woocommerce' ) . ' ';
		echo '<a class="button" href="' . esc_url( $this->get_switch_back_url() ) . '">' . esc_html__( 'Switch Back to Parent', 'customer-subaccounts-for-woocommerce' ) . '</a>';
		echo '</p>';
	}

	/**
	 * Store parent session.
	 *
	 * @param int $parent_id Parent ID.
	 * @return void
	 */
	private function set_parent_session( $parent_id ) {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_PARENT_ID, absint( $parent_id ) );
		}
	}

	/**
	 * Get parent session.
	 *
	 * @return int
	 */
	private function get_parent_session() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return absint( WC()->session->get( self::SESSION_PARENT_ID ) );
		}

		return 0;
	}

	/**
	 * Clear parent session.
	 *
	 * @return void
	 */
	private function clear_parent_session() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( self::SESSION_PARENT_ID );
		}
	}
}
