<?php
/**
 * RSP_Init – Central bootstrap class.
 *
 * @package RatingSystemPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RSP_Init {

	/** @var RSP_Init|null Singleton instance */
	private static ?RSP_Init $instance = null;

	/** @var RSP_Settings */
	public RSP_Settings $settings;

	/** @var RSP_Product_Meta */
	public RSP_Product_Meta $product_meta;

	/** @var RSP_Admin */
	public RSP_Admin $admin;

	/** @var RSP_Frontend */
	public RSP_Frontend $frontend;

	/**
	 * Get or create the singleton.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor – wire everything up.
	 */
	private function __construct() {
		$this->settings     = new RSP_Settings();
		$this->product_meta = new RSP_Product_Meta( $this->settings );
		$this->admin        = new RSP_Admin( $this->settings, $this->product_meta );
		$this->frontend     = new RSP_Frontend( $this->settings, $this->product_meta );
	}

	/** Prevent cloning */
	public function __clone() {}

	/** Prevent unserialization */
	public function __wakeup() {}
}
