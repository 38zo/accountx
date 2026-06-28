<?php
/**
 * Subaccount service.
 *
 * @package Customer Subaccounts for WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage parent/subaccount relationships.
 */
class CSFW_Subaccounts {
	const META_PARENT_ID = '_csfw_parent_user_id';

	/**
	 * Settings.
	 *
	 * @var CSFW_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param CSFW_Settings $settings Settings service.
	 */
	public function __construct( CSFW_Settings $settings ) {
		$this->settings = $settings;

		add_action( 'admin_init', array( $this, 'block_subaccount_admin' ) );
	}

	/**
	 * Get parent ID for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_parent_id( $user_id ) {
		return absint( get_user_meta( $user_id, self::META_PARENT_ID, true ) );
	}

	/**
	 * Check if user is a subaccount.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_subaccount( $user_id ) {
		return $this->get_parent_id( $user_id ) > 0;
	}

	/**
	 * Check ownership.
	 *
	 * @param int $parent_id     Parent user ID.
	 * @param int $subaccount_id Subaccount user ID.
	 * @return bool
	 */
	public function parent_owns_subaccount( $parent_id, $subaccount_id ) {
		return absint( $parent_id ) > 0 && absint( $parent_id ) === $this->get_parent_id( $subaccount_id );
	}

	/**
	 * Get subaccounts for parent.
	 *
	 * @param int $parent_id Parent user ID.
	 * @return WP_User[]
	 */
	public function get_subaccounts( $parent_id ) {
		return get_users(
			array(
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Parent/subaccount ownership is stored as user meta.
				'meta_key'   => self::META_PARENT_ID,
				'meta_value' => absint( $parent_id ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Query is scoped to one parent account and capped by the configured subaccount limit.
				'orderby'    => 'registered',
				'order'      => 'ASC',
			)
		);
	}

	/**
	 * Count subaccounts.
	 *
	 * @param int $parent_id Parent user ID.
	 * @return int
	 */
	public function count_subaccounts( $parent_id ) {
		return count( $this->get_subaccounts( $parent_id ) );
	}

	/**
	 * Can parent create more subaccounts?
	 *
	 * @param int $parent_id Parent user ID.
	 * @return bool
	 */
	public function can_create_subaccount( $parent_id ) {
		if ( $this->settings->has_unlimited_subaccounts() ) {
			return true;
		}

		return $this->count_subaccounts( $parent_id ) < $this->settings->subaccount_limit();
	}

	/**
	 * Create a subaccount.
	 *
	 * @param int    $parent_id  Parent user ID.
	 * @param string $first_name First name.
	 * @param string $last_name  Last name.
	 * @param string $email      Email.
	 * @param string $password   Password.
	 * @return int|WP_Error
	 */
	public function create_subaccount( $parent_id, $first_name, $last_name, $email, $password ) {
		$parent_id  = absint( $parent_id );
		$first_name = sanitize_text_field( $first_name );
		$last_name  = sanitize_text_field( $last_name );
		$email      = sanitize_email( $email );

		if ( ! $this->can_create_subaccount( $parent_id ) ) {
			return new WP_Error( 'csfw_limit_reached', __( 'Subaccount limit reached.', 'customer-subaccounts-for-woocommerce' ) );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'csfw_invalid_email', __( 'Please enter a valid email address.', 'customer-subaccounts-for-woocommerce' ) );
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( 'csfw_email_exists', __( 'A user with this email address already exists.', 'customer-subaccounts-for-woocommerce' ) );
		}

		if ( empty( $password ) || strlen( $password ) < 8 ) {
			return new WP_Error( 'csfw_short_password', __( 'Password must be at least 8 characters long.', 'customer-subaccounts-for-woocommerce' ) );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $this->format_display_name_from_values( $email, $email, $first_name, $last_name, '' ),
				'role'         => 'customer',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, self::META_PARENT_ID, $parent_id );

		return $user_id;
	}

	/**
	 * Update subaccount.
	 *
	 * @param int    $parent_id     Parent user ID.
	 * @param int    $subaccount_id Subaccount user ID.
	 * @param string $first_name    First name.
	 * @param string $last_name     Last name.
	 * @param string $email         Email.
	 * @param string $password      Password.
	 * @return true|WP_Error
	 */
	public function update_subaccount( $parent_id, $subaccount_id, $first_name, $last_name, $email, $password = '' ) {
		if ( ! $this->parent_owns_subaccount( $parent_id, $subaccount_id ) ) {
			return new WP_Error( 'csfw_forbidden', __( 'You cannot edit this subaccount.', 'customer-subaccounts-for-woocommerce' ) );
		}

		$email         = sanitize_email( $email );
		$existing_user = get_user_by( 'email', $email );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'csfw_invalid_email', __( 'Please enter a valid email address.', 'customer-subaccounts-for-woocommerce' ) );
		}

		if ( $existing_user && absint( $existing_user->ID ) !== absint( $subaccount_id ) ) {
			return new WP_Error( 'csfw_email_exists', __( 'A user with this email address already exists.', 'customer-subaccounts-for-woocommerce' ) );
		}

		$data = array(
			'ID'           => absint( $subaccount_id ),
			'user_email'   => $email,
			'first_name'   => sanitize_text_field( $first_name ),
			'last_name'    => sanitize_text_field( $last_name ),
			'role'         => 'customer',
		);

		$user = get_userdata( $subaccount_id );

		if ( $user ) {
			$data['display_name'] = $this->format_display_name_from_values( $user->user_login, $email, $first_name, $last_name, get_user_meta( $subaccount_id, 'billing_company', true ) );
		}

		if ( '' !== $password ) {
			if ( strlen( $password ) < 8 ) {
				return new WP_Error( 'csfw_short_password', __( 'Password must be at least 8 characters long.', 'customer-subaccounts-for-woocommerce' ) );
			}

			$data['user_pass'] = $password;
		}

		$result = wp_update_user( $data );

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Delete a subaccount.
	 *
	 * @param int $parent_id     Parent user ID.
	 * @param int $subaccount_id Subaccount user ID.
	 * @return true|WP_Error
	 */
	public function delete_subaccount( $parent_id, $subaccount_id ) {
		if ( ! $this->parent_owns_subaccount( $parent_id, $subaccount_id ) ) {
			return new WP_Error( 'csfw_forbidden', __( 'You cannot delete this subaccount.', 'customer-subaccounts-for-woocommerce' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( absint( $subaccount_id ) );

		return true;
	}

	/**
	 * Format a user name for Customer Subaccounts for WooCommerce displays.
	 *
	 * @param int|WP_User $user User ID or user object.
	 * @return string
	 */
	public function get_display_name( $user ) {
		$user = $user instanceof WP_User ? $user : get_user_by( 'id', absint( $user ) );

		if ( ! $user ) {
			return __( 'Unknown', 'customer-subaccounts-for-woocommerce' );
		}

		$company = get_user_meta( $user->ID, 'billing_company', true );

		if ( '' === $company ) {
			$company = get_user_meta( $user->ID, 'shipping_company', true );
		}

		return $this->format_display_name_from_values(
			$user->user_login,
			$user->user_email,
			get_user_meta( $user->ID, 'first_name', true ),
			get_user_meta( $user->ID, 'last_name', true ),
			$company
		);
	}

	/**
	 * Format display name from raw values.
	 *
	 * @param string $username   Username.
	 * @param string $email      Email address.
	 * @param string $first_name First name.
	 * @param string $last_name  Last name.
	 * @param string $company    Company.
	 * @return string
	 */
	private function format_display_name_from_values( $username, $email, $first_name, $last_name, $company ) {
		$username  = sanitize_user( $username, true );
		$email     = sanitize_email( $email );
		$full_name = trim( sanitize_text_field( $first_name ) . ' ' . sanitize_text_field( $last_name ) );
		$company   = sanitize_text_field( $company );

		if ( 'username_email' === $this->settings->display_name_format() ) {
			$name = $username;
		} elseif ( 'company_email' === $this->settings->display_name_format() ) {
			$name = '' !== $company ? $company : $username;
		} else {
			$name = '' !== $full_name ? $full_name : $username;
		}

		if ( '' !== $email ) {
			return sprintf( '%1$s (%2$s)', $name, $email );
		}

		return $name;
	}

	/**
	 * Prevent subaccounts from using wp-admin.
	 *
	 * @return void
	 */
	public function block_subaccount_admin() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( $this->is_subaccount( $user_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( wc_get_account_endpoint_url( 'dashboard' ) );
			exit;
		}
	}
}
