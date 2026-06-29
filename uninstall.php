<?php
/**
 * TeaMore uninstall cleanup.
 *
 * @package TeaMore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'teamore_settings' );
