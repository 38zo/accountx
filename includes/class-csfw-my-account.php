<?php
/**
 * WooCommerce My Account integration.
 *
 * @package Customer Subaccounts for WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Customer Subaccounts for WooCommerce My Account UI.
 */
class CSFW_My_Account {
	const ENDPOINT = 'customer-subaccounts';

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
	 * Orders.
	 *
	 * @var CSFW_Orders
	 */
	private $orders;

	/**
	 * Switching.
	 *
	 * @var CSFW_Switching
	 */
	private $switching;

	/**
	 * Constructor.
	 *
	 * @param CSFW_Settings    $settings    Settings.
	 * @param CSFW_Subaccounts $subaccounts Subaccounts.
	 * @param CSFW_Orders      $orders      Orders.
	 * @param CSFW_Switching   $switching   Switching.
	 */
	public function __construct( CSFW_Settings $settings, CSFW_Subaccounts $subaccounts, CSFW_Orders $orders, CSFW_Switching $switching ) {
		$this->settings    = $settings;
		$this->subaccounts = $subaccounts;
		$this->orders      = $orders;
		$this->switching   = $switching;

		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'handle_actions' ) );
	}

	/**
	 * Add endpoint.
	 *
	 * @return void
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::ENDPOINT;

		return $vars;
	}

	/**
	 * Add My Account menu item.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public function add_menu_item( $items ) {
		if ( ! $this->settings->is_enabled() || $this->subaccounts->is_subaccount( get_current_user_id() ) ) {
			return $items;
		}

		$new_items = array();

		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$new_items[ self::ENDPOINT ] = $this->menu_label();
			}

			$new_items[ $key ] = $label;
		}

		return $new_items;
	}

	/**
	 * Process create, update, and delete actions.
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! $this->settings->is_enabled() || ! is_user_logged_in() || ! is_account_page() ) {
			return;
		}

		if ( empty( $_POST['csfw_action'] ) ) {
			return;
		}

		$action    = sanitize_key( wp_unslash( $_POST['csfw_action'] ) );
		$parent_id = get_current_user_id();

		if ( $this->subaccounts->is_subaccount( $parent_id ) ) {
			wc_add_notice( __( 'Subaccounts cannot manage other accounts.', 'customer-subaccounts-for-woocommerce' ), 'error' );
			return;
		}

		if ( ! isset( $_POST['csfw_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csfw_nonce'] ) ), 'customer_subaccounts_for_woocommerce_manage_subaccounts' ) ) {
			wc_add_notice( __( 'Security check failed. Please try again.', 'customer-subaccounts-for-woocommerce' ), 'error' );
			return;
		}

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password   = isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '';

		if ( 'create' === $action && ! $this->settings->customers_can_create() ) {
			$result = new WP_Error( 'csfw_customer_create_disabled', __( 'Customers are not allowed to create subaccounts.', 'customer-subaccounts-for-woocommerce' ) );
		} elseif ( 'create' === $action ) {
			$result = $this->subaccounts->create_subaccount(
				$parent_id,
				$first_name,
				$last_name,
				$email,
				$password
			);
		} elseif ( 'update' === $action ) {
			$result = $this->subaccounts->update_subaccount(
				$parent_id,
				isset( $_POST['subaccount_id'] ) ? absint( wp_unslash( $_POST['subaccount_id'] ) ) : 0,
				$first_name,
				$last_name,
				$email,
				$password
			);
		} elseif ( 'delete' === $action ) {
			$result = $this->subaccounts->delete_subaccount(
				$parent_id,
				isset( $_POST['subaccount_id'] ) ? absint( wp_unslash( $_POST['subaccount_id'] ) ) : 0
			);
		} else {
			return;
		}

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
		} else {
			wc_add_notice( __( 'Subaccount saved successfully.', 'customer-subaccounts-for-woocommerce' ), 'success' );
		}

		wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
		exit;
	}

	/**
	 * Render endpoint.
	 *
	 * @return void
	 */
	public function render_endpoint() {
		if ( ! $this->settings->is_enabled() ) {
			esc_html_e( 'Subaccounts are currently disabled.', 'customer-subaccounts-for-woocommerce' );
			return;
		}

		if ( $this->subaccounts->is_subaccount( get_current_user_id() ) ) {
			esc_html_e( 'Subaccounts cannot manage other accounts.', 'customer-subaccounts-for-woocommerce' );
			return;
		}

		$parent_id   = get_current_user_id();
		$subaccounts = $this->subaccounts->get_subaccounts( $parent_id );
		$label       = $this->settings->account_label();
		$limit       = $this->settings->has_unlimited_subaccounts() ? __( 'unlimited', 'customer-subaccounts-for-woocommerce' ) : (string) $this->settings->subaccount_limit();

		echo '<h2>' . esc_html( $this->menu_label() ) . '</h2>';
		echo '<p>' . esc_html( sprintf( /* translators: 1: label, 2: used count, 3: account limit. */ __( '%1$s usage: %2$d of %3$s.', 'customer-subaccounts-for-woocommerce' ), $label, count( $subaccounts ), $limit ) ) . '</p>';

		$this->render_create_form( $label );
		$this->render_subaccounts_table( $subaccounts, $label );
	}

	/**
	 * Menu label.
	 *
	 * @return string
	 */
	private function menu_label() {
		return 'sub_user' === $this->settings->mode() ? __( 'Subaccounts', 'customer-subaccounts-for-woocommerce' ) : __( 'Team Accounts', 'customer-subaccounts-for-woocommerce' );
	}

	/**
	 * Render create form.
	 *
	 * @param string $label Label.
	 * @return void
	 */
	private function render_create_form( $label ) {
		if ( ! $this->settings->customers_can_create() ) {
			echo '<p class="woocommerce-info">' . esc_html__( 'Subaccount creation is managed by the store admin.', 'customer-subaccounts-for-woocommerce' ) . '</p>';
			return;
		}

		if ( ! $this->subaccounts->can_create_subaccount( get_current_user_id() ) ) {
			echo '<p class="woocommerce-info">' . esc_html__( 'You have reached the subaccount limit.', 'customer-subaccounts-for-woocommerce' ) . '</p>';
			return;
		}

		echo '<h3>' . esc_html( sprintf( /* translators: %s: subaccount label. */ __( 'Create %s', 'customer-subaccounts-for-woocommerce' ), $label ) ) . '</h3>';
		echo '<form method="post" class="csfw-subaccount-form">';
		wp_nonce_field( 'customer_subaccounts_for_woocommerce_manage_subaccounts', 'csfw_nonce' );
		echo '<input type="hidden" name="csfw_action" value="create" />';
		$this->render_fields();
		echo '<p><button type="submit" class="woocommerce-Button button">' . esc_html__( 'Create Subaccount', 'customer-subaccounts-for-woocommerce' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * Render subaccounts table.
	 *
	 * @param WP_User[] $subaccounts Subaccounts.
	 * @param string    $label       Label.
	 * @return void
	 */
	private function render_subaccounts_table( $subaccounts, $label ) {
		echo '<h3>' . esc_html( sprintf( /* translators: %s: subaccount label. */ __( 'Existing %s Accounts', 'customer-subaccounts-for-woocommerce' ), $label ) ) . '</h3>';

		if ( empty( $subaccounts ) ) {
			echo '<p>' . esc_html__( 'No subaccounts have been created yet.', 'customer-subaccounts-for-woocommerce' ) . '</p>';
			return;
		}

		echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive customer-subaccounts-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'customer-subaccounts-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'customer-subaccounts-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Orders', 'customer-subaccounts-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'customer-subaccounts-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $subaccounts as $subaccount ) {
			$this->render_subaccount_row( $subaccount );
		}

		echo '</tbody></table>';
	}

	/**
	 * Render a subaccount row.
	 *
	 * @param WP_User $subaccount Subaccount.
	 * @return void
	 */
	private function render_subaccount_row( WP_User $subaccount ) {
		$orders = $this->orders->get_orders_for_subaccount( $subaccount->ID );

		echo '<tr>';
		echo '<td data-title="' . esc_attr__( 'Name', 'customer-subaccounts-for-woocommerce' ) . '">' . esc_html( $this->subaccounts->get_display_name( $subaccount ) ) . '</td>';
		echo '<td data-title="' . esc_attr__( 'Email', 'customer-subaccounts-for-woocommerce' ) . '">' . esc_html( $subaccount->user_email ) . '</td>';
		echo '<td data-title="' . esc_attr__( 'Orders', 'customer-subaccounts-for-woocommerce' ) . '">';

		if ( empty( $orders ) ) {
			esc_html_e( 'No orders', 'customer-subaccounts-for-woocommerce' );
		} else {
			foreach ( $orders as $order ) {
				echo '<a href="' . esc_url( $order->get_view_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a> ';
			}
		}

		echo '</td><td data-title="' . esc_attr__( 'Actions', 'customer-subaccounts-for-woocommerce' ) . '">';
		$this->render_update_form( $subaccount );

		if ( $this->settings->is_switching_enabled() ) {
			echo '<p><a class="button" href="' . esc_url( $this->switching->get_switch_to_url( $subaccount->ID ) ) . '">' . esc_html__( 'Switch to Subaccount', 'customer-subaccounts-for-woocommerce' ) . '</a></p>';
		}

		$this->render_delete_form( $subaccount );
		echo '</td></tr>';
	}

	/**
	 * Render shared fields.
	 *
	 * @param WP_User|null $user User.
	 * @return void
	 */
	private function render_fields( $user = null ) {
		$first_name = $user ? get_user_meta( $user->ID, 'first_name', true ) : '';
		$last_name  = $user ? get_user_meta( $user->ID, 'last_name', true ) : '';
		$email      = $user ? $user->user_email : '';

		echo '<p class="form-row form-row-first"><label>' . esc_html__( 'First name', 'customer-subaccounts-for-woocommerce' ) . ' <span class="required">*</span></label><input type="text" name="first_name" value="' . esc_attr( $first_name ) . '" required /></p>';
		echo '<p class="form-row form-row-last"><label>' . esc_html__( 'Last name', 'customer-subaccounts-for-woocommerce' ) . '</label><input type="text" name="last_name" value="' . esc_attr( $last_name ) . '" /></p>';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Email address', 'customer-subaccounts-for-woocommerce' ) . ' <span class="required">*</span></label><input type="email" name="email" value="' . esc_attr( $email ) . '" required /></p>';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Password', 'customer-subaccounts-for-woocommerce' ) . ( $user ? '' : ' <span class="required">*</span>' ) . '</label><input type="password" name="password" ' . ( $user ? '' : 'required' ) . ' minlength="8" autocomplete="new-password" /></p>';
		echo '<div class="clear"></div>';
	}

	/**
	 * Render update form.
	 *
	 * @param WP_User $subaccount Subaccount.
	 * @return void
	 */
	private function render_update_form( WP_User $subaccount ) {
		echo '<details><summary>' . esc_html__( 'Edit', 'customer-subaccounts-for-woocommerce' ) . '</summary>';
		echo '<form method="post" class="csfw-subaccount-form">';
		wp_nonce_field( 'customer_subaccounts_for_woocommerce_manage_subaccounts', 'csfw_nonce' );
		echo '<input type="hidden" name="csfw_action" value="update" />';
		echo '<input type="hidden" name="subaccount_id" value="' . esc_attr( $subaccount->ID ) . '" />';
		$this->render_fields( $subaccount );
		echo '<p><button type="submit" class="woocommerce-Button button">' . esc_html__( 'Save Subaccount', 'customer-subaccounts-for-woocommerce' ) . '</button></p>';
		echo '</form></details>';
	}

	/**
	 * Render delete form.
	 *
	 * @param WP_User $subaccount Subaccount.
	 * @return void
	 */
	private function render_delete_form( WP_User $subaccount ) {
		echo '<form method="post">';
		wp_nonce_field( 'customer_subaccounts_for_woocommerce_manage_subaccounts', 'csfw_nonce' );
		echo '<input type="hidden" name="csfw_action" value="delete" />';
		echo '<input type="hidden" name="subaccount_id" value="' . esc_attr( $subaccount->ID ) . '" />';
		echo '<button type="submit" class="button" onclick="return confirm(\'' . esc_js( __( 'Delete this subaccount?', 'customer-subaccounts-for-woocommerce' ) ) . '\');">' . esc_html__( 'Delete', 'customer-subaccounts-for-woocommerce' ) . '</button>';
		echo '</form>';
	}
}
