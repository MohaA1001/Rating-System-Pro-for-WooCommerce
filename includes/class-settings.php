<?php
/**
 * RSP_Settings – Manages plugin-wide options.
 *
 * @package RatingSystemPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSP_Settings {

	const OPTION_KEY = 'rsp_settings';

	private array $settings = [];

	private array $defaults = [
		'stars_only_mode'    => 'yes',
		'enable_manual_dist' => 'yes',
		'show_breakdown'     => 'yes',
		'show_average'       => 'yes',
		'badge_enabled'      => 'yes',
		'badge_threshold'    => 4.5,
		'badge_text'         => 'Top Rated',
		'color_stars'        => '#f5a623',
		'color_bars'         => '#f5a623',
		'color_badge_bg'     => '#27ae60',
		'color_badge_text'   => '#ffffff',
		'show_in_shop'       => 'yes',
		// Rating form settings
		'enable_rating_form' => 'yes',
		'allow_guest_rating' => 'no',
		'rating_form_title'  => 'Rate this product',
		'rating_success_msg' => 'Thank you for your rating!',
		'rating_login_msg'   => 'Please log in to rate this product.',
	];

	public function __construct() {
		$saved          = get_option( self::OPTION_KEY, [] );
		$this->settings = wp_parse_args( $saved, $this->defaults );
	}

	public function get( string $key, $fallback = null ) {
		return $this->settings[ $key ] ?? $fallback;
	}

	public function all(): array {
		return $this->settings;
	}

	public function defaults(): array {
		return $this->defaults;
	}

	public function save( array $data ): void {
		$clean = $this->sanitize( $data );
		update_option( self::OPTION_KEY, $clean );
		$this->settings = wp_parse_args( $clean, $this->defaults );
	}

	public function sanitize( array $data ): array {
		$toggles = [
			'stars_only_mode',
			'enable_manual_dist',
			'show_breakdown',
			'show_average',
			'badge_enabled',
			'show_in_shop',
			'enable_rating_form',
			'allow_guest_rating',
		];

		$clean = [];

		foreach ( $toggles as $key ) {
			$clean[ $key ] = isset( $data[ $key ] ) && $data[ $key ] === 'yes' ? 'yes' : 'no';
		}

		$clean['badge_threshold'] = isset( $data['badge_threshold'] )
			? max( 1, min( 5, (float) $data['badge_threshold'] ) )
			: $this->defaults['badge_threshold'];

		foreach ( [ 'badge_text', 'rating_form_title', 'rating_success_msg', 'rating_login_msg' ] as $key ) {
			$clean[ $key ] = isset( $data[ $key ] )
				? sanitize_text_field( $data[ $key ] )
				: $this->defaults[ $key ];
		}

		$color_keys = [ 'color_stars', 'color_bars', 'color_badge_bg', 'color_badge_text' ];
		foreach ( $color_keys as $key ) {
			$clean[ $key ] = isset( $data[ $key ] ) && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $data[ $key ] )
				? sanitize_hex_color( $data[ $key ] )
				: $this->defaults[ $key ];
		}

		return $clean;
	}

	public function is_enabled( string $key ): bool {
		return $this->get( $key ) === 'yes';
	}
}
