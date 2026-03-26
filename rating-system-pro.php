<?php
/**
 * Plugin Name:       Rating System Pro for WooCommerce
 * Plugin URI:        https://github.com/MohaA1001/Rating-System-Pro-for-WooCommerce
 * Description:       Replace WooCommerce reviews with a stars system featuring professional-style breakdowns, manual star counts, and Top Rated badges.
 * Version:           1.0.0
 * Author:            moha1001
 * Author URI:        https://github.com/MohaA1001
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rating-system-pro
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.0
 * WC tested up to:   8.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constants
 */
define( 'RSP_VERSION',     '1.0.0' );
define( 'RSP_PLUGIN_FILE', __FILE__ );
define( 'RSP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'RSP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'RSP_PLUGIN_BASE', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function rsp_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Show admin notice with Install / Activate button
 */
function rsp_admin_notice_missing_wc() {

	if ( rsp_is_woocommerce_active() ) {
		return;
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$install_url = wp_nonce_url(
		self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ),
		'install-plugin_woocommerce'
	);

	$activate_url = wp_nonce_url(
		self_admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php' ),
		'activate-plugin_woocommerce/woocommerce.php'
	);

	echo '<div class="notice notice-error"><p>';
	echo '<strong>Rating System Pro requires WooCommerce to be installed and activated.</strong><br>';

	if ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
		echo '<a href="' . esc_url( $activate_url ) . '" class="button button-primary">Activate WooCommerce</a>';
	} else {
		echo '<a href="' . esc_url( $install_url ) . '" class="button button-primary">Install WooCommerce</a>';
	}

	echo '</p></div>';
}
add_action( 'admin_notices', 'rsp_admin_notice_missing_wc' );

/**
 * Deactivate plugin if WooCommerce is not active
 */
function rsp_maybe_deactivate() {

	if ( rsp_is_woocommerce_active() ) {
		return;
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	deactivate_plugins( RSP_PLUGIN_BASE );

	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Rating System Pro has been deactivated because WooCommerce is required.', 'rating-system-pro' );
		echo '</p></div>';
	});
}
add_action( 'admin_init', 'rsp_maybe_deactivate' );

/**
 * Init plugin
 */
function rsp_init() {

	if ( ! rsp_is_woocommerce_active() ) {
		return;
	}

	// Load translations
	load_plugin_textdomain(
		'rating-system-pro',
		false,
		dirname( RSP_PLUGIN_BASE ) . '/languages'
	);

	// Includes
	require_once RSP_PLUGIN_DIR . 'includes/class-settings.php';
	require_once RSP_PLUGIN_DIR . 'includes/class-product-meta.php';
	require_once RSP_PLUGIN_DIR . 'includes/class-admin.php';
	require_once RSP_PLUGIN_DIR . 'includes/class-frontend.php';
	require_once RSP_PLUGIN_DIR . 'includes/class-init.php';

	// Boot plugin
	if ( class_exists( 'RSP_Init' ) ) {
		RSP_Init::instance();
	}
}
add_action( 'plugins_loaded', 'rsp_init' );

/**
 * Declare HPOS compatibility
 */
add_action( 'before_woocommerce_init', function () {

	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
});

/**
 * Activation hook
 */
function rsp_activate() {
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'rsp_activate' );

/**
 * Deactivation hook
 */
function rsp_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'rsp_deactivate' );