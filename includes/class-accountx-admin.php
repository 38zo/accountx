<?php
/**
 * Admin settings page.
 *
 * @package AccountX
 */

defined( 'ABSPATH' ) || exit;

/**
 * AccountX admin UI.
 */
class AccountX_Admin {
	/**
	 * Settings service.
	 *
	 * @var AccountX_Settings
	 */
	private $settings;

	/**
	 * Subaccounts service.
	 *
	 * @var AccountX_Subaccounts
	 */
	private $subaccounts;

	/**
	 * Constructor.
	 *
	 * @param AccountX_Settings    $settings    Settings service.
	 * @param AccountX_Subaccounts $subaccounts Subaccounts service.
	 */
	public function __construct( AccountX_Settings $settings, AccountX_Subaccounts $subaccounts ) {
		$this->settings    = $settings;
		$this->subaccounts = $subaccounts;

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'show_user_profile', array( $this, 'render_user_profile_panel' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_profile_panel' ) );
		add_action( 'personal_options_update', array( $this, 'handle_admin_profile_create_subaccount' ) );
		add_action( 'edit_user_profile_update', array( $this, 'handle_admin_profile_create_subaccount' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
		add_filter( 'manage_users_columns', array( $this, 'add_users_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_users_column' ), 10, 3 );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'AccountX', 'accountx' ),
			__( 'AccountX', 'accountx' ),
			'manage_woocommerce',
			'accountx',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'accountx_settings',
			AccountX_Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => AccountX_Settings::defaults(),
			)
		);

		add_settings_section( 'accountx_main', __( 'AccountX Settings', 'accountx' ), '__return_false', 'accountx' );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'enabled'              => isset( $input['enabled'] ) ? 'yes' : 'no',
			'mode'                 => isset( $input['mode'] ) && in_array( $input['mode'], array( 'multi_user', 'sub_user' ), true ) ? $input['mode'] : 'multi_user',
			'creator_access'       => isset( $input['creator_access'] ) && in_array( $input['creator_access'], array( 'admins', 'customers', 'admins_customers' ), true ) ? $input['creator_access'] : 'admins_customers',
			'subaccount_limit'     => min( 100, absint( isset( $input['subaccount_limit'] ) ? $input['subaccount_limit'] : 10 ) ),
			'user_switching'       => isset( $input['user_switching'] ) ? 'yes' : 'no',
			'display_name_format'  => isset( $input['display_name_format'] ) && in_array( $input['display_name_format'], array( 'username_email', 'full_name_email', 'company_email' ), true ) ? $input['display_name_format'] : 'full_name_email',
			'show_order_page_info' => isset( $input['show_order_page_info'] ) ? 'yes' : 'no',
			'show_order_list_info' => isset( $input['show_order_list_info'] ) ? 'yes' : 'no',
			'show_user_list_info'  => isset( $input['show_user_list_info'] ) ? 'yes' : 'no',
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = $this->settings->all();
		?>
		<div class="wrap">
			<p><?php esc_html_e( 'On this page, you can configure the settings for the AccountX plugin.', 'accountx' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'accountx_settings' ); ?>
				<?php do_settings_sections( 'accountx' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Features', 'accountx' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[enabled]" value="yes" <?php checked( $settings['enabled'], 'yes' ); ?> />
								<?php esc_html_e( 'Enable subaccount features', 'accountx' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Show the AccountX menu in WooCommerce My Account and allow parent customers to manage subaccounts.', 'accountx' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="accountx-mode"><?php esc_html_e( 'Mode', 'accountx' ); ?></label></th>
						<td>
							<select id="accountx-mode" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[mode]">
								<option value="multi_user" <?php selected( $settings['mode'], 'multi_user' ); ?>><?php esc_html_e( 'Multi-User Mode', 'accountx' ); ?></option>
								<option value="sub_user" <?php selected( $settings['mode'], 'sub_user' ); ?>><?php esc_html_e( 'Sub-User Mode', 'accountx' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Choose how subaccounts are presented to customers: team members for company accounts, or controlled subaccounts under one parent customer.', 'accountx' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="accountx-creator-access"><?php esc_html_e( 'Who can create subaccounts', 'accountx' ); ?></label></th>
						<td>
							<select id="accountx-creator-access" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[creator_access]">
								<option value="admins_customers" <?php selected( $settings['creator_access'], 'admins_customers' ); ?>><?php esc_html_e( 'Admins and customers', 'accountx' ); ?></option>
								<option value="admins" <?php selected( $settings['creator_access'], 'admins' ); ?>><?php esc_html_e( 'Admins only', 'accountx' ); ?></option>
								<option value="customers" <?php selected( $settings['creator_access'], 'customers' ); ?>><?php esc_html_e( 'Customers only', 'accountx' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Choose whether store admins, parent customers, or both can create new subaccounts.', 'accountx' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="accountx-limit"><?php esc_html_e( 'Subaccount limit', 'accountx' ); ?></label></th>
						<td>
							<input id="accountx-limit" type="number" min="0" max="100" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[subaccount_limit]" value="<?php echo esc_attr( $settings['subaccount_limit'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Set the maximum number of subaccounts each parent customer can create. Enter 0 for unlimited subaccounts.', 'accountx' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'User switching', 'accountx' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[user_switching]" value="yes" <?php checked( $settings['user_switching'], 'yes' ); ?> />
								<?php esc_html_e( 'Allow parents to switch into subaccount sessions', 'accountx' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Adds a My Account button that lets a parent customer temporarily view the store as one of their subaccounts.', 'accountx' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="accountx-display-name-format"><?php esc_html_e( 'Subaccount display name', 'accountx' ); ?></label></th>
						<td>
							<select id="accountx-display-name-format" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[display_name_format]">
								<option value="full_name_email" <?php selected( $settings['display_name_format'], 'full_name_email' ); ?>><?php esc_html_e( 'Full name + email', 'accountx' ); ?></option>
								<option value="username_email" <?php selected( $settings['display_name_format'], 'username_email' ); ?>><?php esc_html_e( 'Username + email', 'accountx' ); ?></option>
								<option value="company_email" <?php selected( $settings['display_name_format'], 'company_email' ); ?>><?php esc_html_e( 'Company + email', 'accountx' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Controls how parent and subaccount names appear in AccountX order and user screens. Username is used when the selected name source is empty.', 'accountx' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Subaccount information display', 'accountx' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[show_order_page_info]" value="yes" <?php checked( $settings['show_order_page_info'], 'yes' ); ?> />
								<?php esc_html_e( 'Show subaccount information on WooCommerce order pages', 'accountx' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[show_order_list_info]" value="yes" <?php checked( $settings['show_order_list_info'], 'yes' ); ?> />
								<?php esc_html_e( 'Show subaccount information in WooCommerce order lists', 'accountx' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="<?php echo esc_attr( AccountX_Settings::OPTION_NAME ); ?>[show_user_list_info]" value="yes" <?php checked( $settings['show_user_list_info'], 'yes' ); ?> />
								<?php esc_html_e( 'Show parent/subaccount information in the WordPress user list', 'accountx' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Choose where AccountX should add parent and subaccount context for admins.', 'accountx' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render AccountX panel on user profile screens.
	 *
	 * @param WP_User $user User being edited.
	 * @return void
	 */
	public function render_user_profile_panel( $user ) {
		if ( ! $this->settings->is_enabled() || ! $this->settings->admins_can_create() || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		if ( $this->subaccounts->is_subaccount( $user->ID ) ) {
			$parent = get_user_by( 'id', $this->subaccounts->get_parent_id( $user->ID ) );
			?>
			<h2><?php esc_html_e( 'AccountX', 'accountx' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Subaccount of', 'accountx' ); ?></th>
					<td><?php echo $parent ? esc_html( $this->subaccounts->get_display_name( $parent ) ) : esc_html__( 'Unknown', 'accountx' ); ?></td>
				</tr>
			</table>
			<?php
			return;
		}

		$subaccounts = $this->subaccounts->get_subaccounts( $user->ID );
		?>
		<h2><?php esc_html_e( 'AccountX Subaccounts', 'accountx' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Existing subaccounts', 'accountx' ); ?></th>
				<td>
					<?php
					if ( empty( $subaccounts ) ) {
						esc_html_e( 'No subaccounts have been created for this customer.', 'accountx' );
					} else {
						echo '<ul>';
						foreach ( $subaccounts as $subaccount ) {
							echo '<li><a href="' . esc_url( get_edit_user_link( $subaccount->ID ) ) . '">' . esc_html( $this->subaccounts->get_display_name( $subaccount ) ) . '</a></li>';
						}
						echo '</ul>';
					}
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Create subaccount', 'accountx' ); ?></th>
				<td>
					<?php if ( ! $this->subaccounts->can_create_subaccount( $user->ID ) ) : ?>
						<p><?php esc_html_e( 'This customer has reached the subaccount limit.', 'accountx' ); ?></p>
					<?php else : ?>
						<?php wp_nonce_field( 'accountx_admin_create_subaccount_' . $user->ID, 'accountx_nonce' ); ?>
						<p>
							<label><?php esc_html_e( 'First name', 'accountx' ); ?><br />
								<input type="text" name="accountx_first_name" class="regular-text" />
							</label>
						</p>
						<p>
							<label><?php esc_html_e( 'Last name', 'accountx' ); ?><br />
								<input type="text" name="accountx_last_name" class="regular-text" />
							</label>
						</p>
						<p>
							<label><?php esc_html_e( 'Email address', 'accountx' ); ?><br />
								<input type="email" name="accountx_email" class="regular-text" />
							</label>
						</p>
						<p>
							<label><?php esc_html_e( 'Password', 'accountx' ); ?><br />
								<input type="password" name="accountx_password" class="regular-text" minlength="8" autocomplete="new-password" />
							</label>
						</p>
						<p class="description"><?php esc_html_e( 'Enter all required fields, then click Update User to create the subaccount.', 'accountx' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Handle admin subaccount creation.
	 *
	 * @param int $parent_id Parent user ID.
	 * @return void
	 */
	public function handle_admin_profile_create_subaccount( $parent_id ) {
		$has_submission = ! empty( $_POST['accountx_first_name'] ) || ! empty( $_POST['accountx_email'] ) || ! empty( $_POST['accountx_password'] );

		if ( ! $has_submission ) {
			return;
		}

		if ( ! $this->settings->admins_can_create() || ! current_user_can( 'edit_user', $parent_id ) ) {
			$this->set_admin_notice( __( 'You are not allowed to create subaccounts.', 'accountx' ), 'error' );
			return;
		}

		if ( ! isset( $_POST['accountx_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['accountx_nonce'] ) ), 'accountx_admin_create_subaccount_' . $parent_id ) ) {
			$this->set_admin_notice( __( 'Security check failed. Please try again.', 'accountx' ), 'error' );
			return;
		}

		$result = $this->subaccounts->create_subaccount(
			$parent_id,
			isset( $_POST['accountx_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['accountx_first_name'] ) ) : '',
			isset( $_POST['accountx_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['accountx_last_name'] ) ) : '',
			isset( $_POST['accountx_email'] ) ? sanitize_email( wp_unslash( $_POST['accountx_email'] ) ) : '',
			isset( $_POST['accountx_password'] ) ? sanitize_text_field( wp_unslash( $_POST['accountx_password'] ) ) : ''
		);

		if ( is_wp_error( $result ) ) {
			$this->set_admin_notice( $result->get_error_message(), 'error' );
			return;
		}

		$this->set_admin_notice( __( 'Subaccount created successfully.', 'accountx' ), 'success' );
	}

	/**
	 * Add WordPress users list column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_users_column( $columns ) {
		if ( $this->settings->is_display_location_enabled( 'show_user_list_info' ) ) {
			$columns['accountx_relationship'] = __( 'AccountX', 'accountx' );
		}

		return $columns;
	}

	/**
	 * Render WordPress users list column.
	 *
	 * @param string $output  Output.
	 * @param string $column  Column key.
	 * @param int    $user_id User ID.
	 * @return string
	 */
	public function render_users_column( $output, $column, $user_id ) {
		if ( 'accountx_relationship' !== $column || ! $this->settings->is_display_location_enabled( 'show_user_list_info' ) ) {
			return $output;
		}

		if ( $this->subaccounts->is_subaccount( $user_id ) ) {
			$parent = get_user_by( 'id', $this->subaccounts->get_parent_id( $user_id ) );
			return $parent ? esc_html( sprintf( /* translators: %s: parent display name. */ __( 'Subaccount of %s', 'accountx' ), $this->subaccounts->get_display_name( $parent ) ) ) : esc_html__( 'Subaccount', 'accountx' );
		}

		$count = $this->subaccounts->count_subaccounts( $user_id );

		if ( $count > 0 ) {
			return esc_html( sprintf( /* translators: %d: number of subaccounts. */ _n( '%d subaccount', '%d subaccounts', $count, 'accountx' ), $count ) );
		}

		return '&mdash;';
	}

	/**
	 * Store an admin notice for the next page load.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type.
	 * @return void
	 */
	private function set_admin_notice( $message, $type ) {
		set_transient(
			'accountx_admin_notice_' . get_current_user_id(),
			array(
				'message' => sanitize_text_field( $message ),
				'type'    => 'success' === $type ? 'success' : 'error',
			),
			60
		);
	}

	/**
	 * Render stored admin notice.
	 *
	 * @return void
	 */
	public function render_admin_notice() {
		$notice = get_transient( 'accountx_admin_notice_' . get_current_user_id() );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( 'accountx_admin_notice_' . get_current_user_id() );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notice['type'] ),
			esc_html( $notice['message'] )
		);
	}
}
