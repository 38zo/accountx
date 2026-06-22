<?php
/**
 * Settings service.
 *
 * @package AccountX
 */

defined( 'ABSPATH' ) || exit;

/**
 * AccountX settings wrapper.
 */
class AccountX_Settings {
	const OPTION_NAME = 'accountx_settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'              => 'yes',
			'mode'                 => 'multi_user',
			'creator_access'       => 'admins_customers',
			'subaccount_limit'     => 10,
			'user_switching'       => 'yes',
			'display_name_format'  => 'full_name_email',
			'show_order_page_info' => 'yes',
			'show_order_list_info' => 'yes',
			'show_user_list_info'  => 'yes',
		);
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function all() {
		$saved = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Get setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Is AccountX enabled?
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === $this->get( 'enabled', 'yes' );
	}

	/**
	 * Is user switching enabled?
	 *
	 * @return bool
	 */
	public function is_switching_enabled() {
		return 'yes' === $this->get( 'user_switching', 'yes' );
	}

	/**
	 * Current mode.
	 *
	 * @return string
	 */
	public function mode() {
		$mode = (string) $this->get( 'mode', 'multi_user' );

		return in_array( $mode, array( 'multi_user', 'sub_user' ), true ) ? $mode : 'multi_user';
	}

	/**
	 * User-facing subaccount label.
	 *
	 * @return string
	 */
	public function account_label() {
		return 'sub_user' === $this->mode() ? __( 'Subaccount', 'accountx' ) : __( 'Team Member', 'accountx' );
	}

	/**
	 * Subaccount limit.
	 *
	 * @return int
	 */
	public function subaccount_limit() {
		return absint( $this->get( 'subaccount_limit', 10 ) );
	}

	/**
	 * Is subaccount creation unlimited?
	 *
	 * @return bool
	 */
	public function has_unlimited_subaccounts() {
		return 0 === $this->subaccount_limit();
	}

	/**
	 * Can customers create subaccounts?
	 *
	 * @return bool
	 */
	public function customers_can_create() {
		return in_array( $this->get( 'creator_access', 'admins_customers' ), array( 'customers', 'admins_customers' ), true );
	}

	/**
	 * Can admins create subaccounts?
	 *
	 * @return bool
	 */
	public function admins_can_create() {
		return in_array( $this->get( 'creator_access', 'admins_customers' ), array( 'admins', 'admins_customers' ), true );
	}

	/**
	 * Get display name format.
	 *
	 * @return string
	 */
	public function display_name_format() {
		$format = (string) $this->get( 'display_name_format', 'full_name_email' );

		return in_array( $format, array( 'username_email', 'full_name_email', 'company_email' ), true ) ? $format : 'full_name_email';
	}

	/**
	 * Is a display location enabled?
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public function is_display_location_enabled( $key ) {
		return 'yes' === $this->get( $key, 'yes' );
	}
}
