<?php
/**
 * RSP_Product_Meta – Manages per-product manual rating distribution.
 *
 * @package RatingSystemPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSP_Product_Meta {

	/** Meta key prefix */
	const META_PREFIX = '_rsp_';

	/** Individual star count meta keys */
	const STAR_KEYS = [ 5, 4, 3, 2, 1 ];

	/** @var RSP_Settings */
	private RSP_Settings $settings;

	public function __construct( RSP_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get star counts for a product.
	 *
	 * @param int $product_id
	 * @return array<int,int>  Keys: 5,4,3,2,1 — Values: counts
	 */
	public function get_star_counts( int $product_id ): array {
		$counts = [];
		foreach ( self::STAR_KEYS as $star ) {
			$val           = get_post_meta( $product_id, self::META_PREFIX . 'stars_' . $star, true );
			$counts[ $star ] = $val !== '' ? (int) $val : 0;
		}
		return $counts;
	}

	/**
	 * Save star counts for a product.
	 *
	 * @param int          $product_id
	 * @param array<int,int> $counts
	 */
	public function save_star_counts( int $product_id, array $counts ): void {
		foreach ( self::STAR_KEYS as $star ) {
			$value = isset( $counts[ $star ] ) ? absint( $counts[ $star ] ) : 0;
			update_post_meta( $product_id, self::META_PREFIX . 'stars_' . $star, $value );
		}
		// Bust cache
		delete_transient( 'rsp_rating_data_' . $product_id );
	}

	/**
	 * Get override flag for a product.
	 *
	 * @param int $product_id
	 * @return bool
	 */
	public function has_override( int $product_id ): bool {
		return get_post_meta( $product_id, self::META_PREFIX . 'override', true ) === 'yes';
	}

	/**
	 * Save override flag.
	 *
	 * @param int  $product_id
	 * @param bool $override
	 */
	public function save_override( int $product_id, bool $override ): void {
		update_post_meta( $product_id, self::META_PREFIX . 'override', $override ? 'yes' : 'no' );
	}

	/**
	 * Compute aggregate rating data.
	 * Uses transient caching for 12 hours.
	 *
	 * @param int $product_id
	 * @return array{
	 *   counts: array<int,int>,
	 *   total: int,
	 *   average: float,
	 *   percentages: array<int,float>
	 * }
	 */
	public function get_rating_data( int $product_id ): array {
		$cache_key = 'rsp_rating_data_' . $product_id;
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		$counts  = $this->get_star_counts( $product_id );
		$total   = array_sum( $counts );
		$weighted = 0;

		foreach ( $counts as $star => $count ) {
			$weighted += $star * $count;
		}

		$average     = $total > 0 ? round( $weighted / $total, 1 ) : 0.0;
		$percentages = [];

		foreach ( $counts as $star => $count ) {
			$percentages[ $star ] = $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0.0;
		}

		$data = compact( 'counts', 'total', 'average', 'percentages' );
		set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Determine if a product should use manual distribution.
	 *
	 * @param int $product_id
	 * @return bool
	 */
	public function use_manual( int $product_id ): bool {
		if ( ! $this->settings->is_enabled( 'enable_manual_dist' ) ) {
			return false;
		}
		// If override is set, always use manual for this product
		if ( $this->has_override( $product_id ) ) {
			return true;
		}
		// Use global setting
		return $this->settings->is_enabled( 'enable_manual_dist' );
	}
}
