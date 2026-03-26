<?php
/**
 * RSP_Frontend – All front-end hooks and rendering.
 *
 * @package RatingSystemPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSP_Frontend {

	/** @var RSP_Settings */
	private RSP_Settings $settings;

	/** @var RSP_Product_Meta */
	private RSP_Product_Meta $product_meta;

	public function __construct( RSP_Settings $settings, RSP_Product_Meta $product_meta ) {
		$this->settings     = $settings;
		$this->product_meta = $product_meta;

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Stars-only mode
		if ( $this->settings->is_enabled( 'stars_only_mode' ) ) {
			add_filter( 'comment_form_defaults',                       [ $this, 'strip_review_textarea' ] );
			add_filter( 'woocommerce_product_review_comment_form_args',[ $this, 'strip_review_textarea' ] );
			add_filter( 'preprocess_comment',                          [ $this, 'allow_empty_comment' ] );
			add_filter( 'woocommerce_reviews_title',                   '__return_false' );
		}

		// Remove WooCommerce reviews tab completely
		add_filter( 'woocommerce_product_tabs', [ $this, 'manage_tabs' ], 98 );

		// Rating HTML override (loops)
		add_filter( 'woocommerce_product_get_rating_html', [ $this, 'override_rating_html' ], 10, 3 );

		// Mini clickable strip below product title
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_mini_summary' ], 6 );

		// Shop page ratings
		add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'render_shop_rating' ], 5 );

		// Shop page badge (at top of image)
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'render_shop_badge_on_image' ], 10 );

		// AJAX handler for star rating submission
		add_action( 'wp_ajax_rsp_submit_rating',        [ $this, 'ajax_submit_rating' ] );
		add_action( 'wp_ajax_nopriv_rsp_submit_rating', [ $this, 'ajax_submit_rating' ] );

		// Inline CSS vars
		add_action( 'wp_head', [ $this, 'output_css_variables' ] );
	}

	/* -----------------------------------------------------------------------
	 * Assets
	 * -------------------------------------------------------------------- */

	public function enqueue_assets(): void {
		if ( ! is_singular( 'product' ) && ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
			return;
		}

		wp_enqueue_style( 'rsp-frontend', RSP_PLUGIN_URL . 'assets/css/frontend.css', [], RSP_VERSION );
		wp_enqueue_script( 'rsp-frontend', RSP_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery' ], RSP_VERSION, true );

		wp_localize_script( 'rsp-frontend', 'rsp_cfg', [
			'tab_key'     => 'rsp-ratings',
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'rsp_submit_rating' ),
			'is_logged_in'=> is_user_logged_in() ? '1' : '0',
			'allow_guest' => $this->settings->is_enabled( 'allow_guest_rating' ) ? '1' : '0',
			'login_msg'   => esc_html( $this->settings->get( 'rating_login_msg', __( 'Please log in to rate this product.', 'rating-system-pro-for-woocommerce' ) ) ),
			'success_msg' => esc_html( $this->settings->get( 'rating_success_msg', __( 'Thank you for your rating!', 'rating-system-pro-for-woocommerce' ) ) ),
			'login_url'   => esc_url( wc_get_page_permalink( 'myaccount' ) ),
		] );
	}

	/* -----------------------------------------------------------------------
	 * Stars-only helpers
	 * -------------------------------------------------------------------- */

	public function strip_review_textarea( array $args ): array {
		if ( isset( $args['fields']['comment'] ) ) {
			unset( $args['fields']['comment'] );
		}
		if ( isset( $args['comment_field'] ) ) {
			$args['comment_field'] = '';
		}
		return $args;
	}

	public function allow_empty_comment( array $comment_data ): array {
		if ( isset( $comment_data['comment_type'] ) && $comment_data['comment_type'] === 'review' ) {
			if ( empty( $comment_data['comment_content'] ) ) {
				$comment_data['comment_content'] = ' ';
			}
		}
		return $comment_data;
	}

	/* -----------------------------------------------------------------------
	 * Tab management – remove Reviews, add our Ratings tab
	 * -------------------------------------------------------------------- */

	public function manage_tabs( array $tabs ): array {
		// Always remove the default WooCommerce reviews tab
		unset( $tabs['reviews'] );

		// Add our Ratings tab if product has manual data
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return $tabs;
		}
		if ( ! $this->product_meta->use_manual( $product->get_id() ) ) {
			return $tabs;
		}

		$data = $this->product_meta->get_rating_data( $product->get_id() );

		$tabs['rsp-ratings'] = [
			'title'    => $data['total'] > 0
				/* translators: %s: average rating */
				? sprintf( __( 'Ratings (%s★)', 'rating-system-pro-for-woocommerce' ), $data['average'] )
				: __( 'Ratings', 'rating-system-pro-for-woocommerce' ),
			'priority' => 25,
			'callback' => [ $this, 'render_ratings_tab_content' ],
		];

		return $tabs;
	}

	/* -----------------------------------------------------------------------
	 * Rating HTML override (loops)
	 * -------------------------------------------------------------------- */

	public function override_rating_html( string $html, float $rating, int $count ): string {
		global $product;
		if ( ! $product instanceof WC_Product ) { return $html; }
		if ( ! $this->product_meta->use_manual( $product->get_id() ) ) { return $html; }

		$data = $this->product_meta->get_rating_data( $product->get_id() );
		if ( $data['total'] === 0 ) { return $html; }

		return sprintf(
			'<div class="rsp-inline-rating" title="%s">%s<span class="rsp-inline-count">(%s)</span></div>',
			/* translators: 1: average rating, 2: total ratings count */
			esc_attr( sprintf( __( 'Rated %1$s out of 5 — %2$s ratings', 'rating-system-pro-for-woocommerce' ), $data['average'], $data['total'] ) ),
			$this->stars_html( $data['average'] ),
			esc_html( number_format_i18n( $data['total'] ) )
		);
	}

	/* -----------------------------------------------------------------------
	 * Mini summary strip – under product title
	 * -------------------------------------------------------------------- */

	/**
	 * Shared logic to determine if the "Top Rated" badge should be shown.
	 */
	private function should_show_badge( WC_Product $product ): bool {
		if ( ! $this->settings->is_enabled( 'badge_enabled' ) ) {
			$show = false;
		} else {
			$data = $this->product_meta->get_rating_data( $product->get_id() );
			$average = (float) ( $data['average'] > 0 ? $data['average'] : $product->get_average_rating() );
			$show = ( $average > 0 && $average >= (float) $this->settings->get( 'badge_threshold' ) );
		}

		/**
		 * Filter to allow forcing or hiding the badge externally.
		 * 
		 * @param bool $show Current decision.
		 * @param int $product_id The product ID.
		 */
		return apply_filters( 'rsp_show_badge', $show, $product->get_id() );
	}

	public function render_mini_summary(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) { return; }

		$data = $this->product_meta->get_rating_data( $product->get_id() );
		
		// If no manual data AND no native ratings, hide the summary
		$total_ratings = $data['total'] > 0 ? $data['total'] : $product->get_rating_count();
		if ( $total_ratings === 0 ) { return; }

		// Sync with central badge logic
		$show_badge = $this->should_show_badge( $product );

		// For the rest of the summary data, use manual if available, fallback to native
		$average = $data['average'] > 0 ? $data['average'] : $product->get_average_rating();
		?>
		<div class="rsp-mini-summary" id="rsp-mini-summary"
			 role="button" tabindex="0" data-rsp-tab="rsp-ratings"
			 data-product-url="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"
			 <?php /* translators: %s: average rating */ ?>
			 aria-label="<?php echo esc_attr( sprintf( __( 'See all ratings — %s out of 5', 'rating-system-pro-for-woocommerce' ), $average ) ); ?>">

			<span class="rsp-mini-average"><?php echo esc_html( $average ); ?></span>
			<span class="rsp-mini-stars"><?php echo $this->stars_html( $average ); // phpcs:ignore ?></span>
			<span class="rsp-mini-count">
				<?php echo esc_html( number_format_i18n( $total_ratings ) ); ?>
				<?php esc_html_e( 'ratings', 'rating-system-pro-for-woocommerce' ); ?>
			</span>
			<span class="rsp-mini-arrow">›</span>

			<?php if ( $show_badge && is_singular( 'product' ) ) : ?>
				<span class="rsp-badge rsp-badge--inline">
					<span class="rsp-badge-icon">🏆</span>
					<?php echo esc_html( $this->settings->get( 'badge_text', __( 'Top Rated', 'rating-system-pro-for-woocommerce' ) ) ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_shop_rating(): void {
		if ( $this->settings->is_enabled( 'show_in_shop' ) ) {
			$this->render_mini_summary();
		}
	}

	/**
	 * Render "Top Rated" badge overlay on shop loop thumbnails.
	 */
	public function render_shop_badge_on_image(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		// Check global setting
		if ( ! $this->settings->is_enabled( 'show_in_shop' ) ) {
			return;
		}

		// Use centralized badge logic
		if ( ! $this->should_show_badge( $product ) ) {
			return;
		}

		$badge_text = $this->settings->get( 'badge_text', __( 'Top Rated', 'rating-system-pro-for-woocommerce' ) );
		?>
		<div class="rsp-shop-badge-overlay">
			<span class="rsp-badge">
				<span class="rsp-badge-icon">🏆</span>
				<?php echo esc_html( $badge_text ); ?>
			</span>
		</div>
		<?php
	}

	/* -----------------------------------------------------------------------
	 * Ratings Tab Content
	 * -------------------------------------------------------------------- */

	public function render_ratings_tab_content(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) { return; }

		$data           = $this->product_meta->get_rating_data( $product->get_id() );
		$show_breakdown = $this->settings->is_enabled( 'show_breakdown' );
		$show_average   = $this->settings->is_enabled( 'show_average' );
		$show_badge     = $this->should_show_badge( $product );
		$show_form      = $this->settings->is_enabled( 'enable_rating_form' );
		$allow_guest    = $this->settings->is_enabled( 'allow_guest_rating' );
		$is_logged_in   = is_user_logged_in();
		?>
		<div class="rsp-tab-wrap">

			<?php if ( $data['total'] > 0 || $product->get_rating_count() > 0 ) : ?>

				<!-- ── Rating breakdown widget ── -->
				<div class="rsp-widget" id="rsp-widget">

					<?php if ( $show_badge ) : ?>
						<div class="rsp-badge rsp-badge--tab">
							<span class="rsp-badge-icon">🏆</span>
							<?php echo esc_html( $this->settings->get( 'badge_text', __( 'Top Rated', 'rating-system-pro-for-woocommerce' ) ) ); ?>
						</div>
					<?php endif; ?>

					<div class="rsp-widget-inner">

						<?php if ( $show_average ) : ?>
							<div class="rsp-average-block">
								<div class="rsp-average-number"><?php echo esc_html( $data['average'] ); ?></div>
								<div class="rsp-average-stars"><?php echo $this->stars_html( $data['average'] ); // phpcs:ignore ?></div>
								<div class="rsp-average-label">
									<?php echo esc_html( number_format_i18n( $data['total'] ) . ' ' . __( 'ratings', 'rating-system-pro-for-woocommerce' ) ); ?>
								</div>
								<div class="rsp-average-outof"><?php esc_html_e( 'out of 5', 'rating-system-pro-for-woocommerce' ); ?></div>
							</div>
						<?php endif; ?>

						<?php if ( $show_breakdown ) : ?>
							<div class="rsp-breakdown">
								<?php foreach ( RSP_Product_Meta::STAR_KEYS as $star ) :
									$pct   = $data['percentages'][ $star ];
									$count = $data['counts'][ $star ];
								?>
									<div class="rsp-breakdown-row">
										<span class="rsp-breakdown-label"><?php echo esc_html( $star ); ?>&nbsp;★</span>
										<div class="rsp-bar-track">
											<div class="rsp-bar-fill" data-width="<?php echo esc_attr( $pct ); ?>" style="width:0%"></div>
										</div>
										<span class="rsp-breakdown-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

					</div>
				</div>

			<?php endif; ?>

			<?php if ( $show_form ) : ?>

				<!-- ── Rate this product form ── -->
				<div class="rsp-rate-form-wrap">

					<h3 class="rsp-rate-form-title">
						<?php echo esc_html( $this->settings->get( 'rating_form_title', __( 'Rate this product', 'rating-system-pro-for-woocommerce' ) ) ); ?>
					</h3>

					<?php if ( $is_logged_in || $allow_guest ) : ?>

						<form class="rsp-rate-form" id="rsp-rate-form"
							  data-product="<?php echo esc_attr( $product->get_id() ); ?>">

							<div class="rsp-star-picker" id="rsp-star-picker" role="radiogroup" aria-label="<?php esc_attr_e( 'Star rating', 'rating-system-pro-for-woocommerce' ); ?>">
								<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
									<input type="radio" name="rsp_star" id="rsp_star_<?php echo esc_attr( $i ); ?>"
										   value="<?php echo esc_attr( $i ); ?>" class="rsp-star-radio">
									<label for="rsp_star_<?php echo esc_attr( $i ); ?>"
										   <?php /* translators: %d: number of stars */ ?>
										   title="<?php echo esc_attr( sprintf( _n( '%d star', '%d stars', $i, 'rating-system-pro-for-woocommerce' ), $i ) ); ?>">★</label>
								<?php endfor; ?>
							</div>

							<p class="rsp-selected-label" id="rsp-selected-label" aria-live="polite"></p>

							<button type="submit" class="rsp-submit-btn" id="rsp-submit-btn" disabled>
								<?php esc_html_e( 'Submit Rating', 'rating-system-pro-for-woocommerce' ); ?>
							</button>

							<p class="rsp-form-notice" id="rsp-form-notice" aria-live="polite"></p>

						</form>

					<?php else : ?>

						<!-- Not logged in + guest not allowed -->
						<div class="rsp-login-prompt">
							<p><?php echo esc_html( $this->settings->get( 'rating_login_msg', __( 'Please log in to rate this product.', 'rating-system-pro-for-woocommerce' ) ) ); ?></p>
							<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="rsp-login-btn button">
								<?php esc_html_e( 'Log In', 'rating-system-pro-for-woocommerce' ); ?>
							</a>
						</div>

					<?php endif; ?>

				</div><!-- /.rsp-rate-form-wrap -->

			<?php endif; ?>

		</div><!-- /.rsp-tab-wrap -->
		<?php
	}

	/* -----------------------------------------------------------------------
	 * AJAX: submit star rating
	 * -------------------------------------------------------------------- */

	public function ajax_submit_rating(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rsp_submit_rating' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'rating-system-pro-for-woocommerce' ) ] );
		}

		$allow_guest = $this->settings->is_enabled( 'allow_guest_rating' );

		// Check auth
		if ( ! is_user_logged_in() && ! $allow_guest ) {
			wp_send_json_error( [
				'message' => esc_html( $this->settings->get( 'rating_login_msg', __( 'Please log in to rate this product.', 'rating-system-pro-for-woocommerce' ) ) ),
			] );
		}

		// Validate star value
		$star = isset( $_POST['star'] ) ? (int) $_POST['star'] : 0;
		if ( $star < 1 || $star > 5 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid star rating.', 'rating-system-pro-for-woocommerce' ) ] );
		}

		// Validate product
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product.', 'rating-system-pro-for-woocommerce' ) ] );
		}

		// Duplicate-vote guard (session or user meta)
		$voted_key  = 'rsp_voted_' . $product_id;
		$user_id    = get_current_user_id();

		if ( $user_id > 0 ) {
			if ( get_user_meta( $user_id, $voted_key, true ) ) {
				wp_send_json_error( [ 'message' => __( 'You have already rated this product.', 'rating-system-pro-for-woocommerce' ) ] );
			}
		} else {
			// Guest: use session cookie
			if ( ! session_id() ) { @session_start(); }
			if ( isset( $_SESSION[ $voted_key ] ) ) {
				wp_send_json_error( [ 'message' => __( 'You have already rated this product.', 'rating-system-pro-for-woocommerce' ) ] );
			}
		}

		// Increment the star count
		$counts          = $this->product_meta->get_star_counts( $product_id );
		$counts[ $star ] = ( $counts[ $star ] ?? 0 ) + 1;
		$this->product_meta->save_star_counts( $product_id, $counts );

		// Mark as voted
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, $voted_key, true );
		} else {
			if ( ! session_id() ) { @session_start(); }
			$_SESSION[ $voted_key ] = true;
		}

		// Return updated data
		$new_data = $this->product_meta->get_rating_data( $product_id );

		wp_send_json_success( [
			'message'  => esc_html( $this->settings->get( 'rating_success_msg', __( 'Thank you for your rating!', 'rating-system-pro-for-woocommerce' ) ) ),
			'average'  => $new_data['average'],
			'total'    => $new_data['total'],
			'counts'   => $new_data['counts'],
			'percents' => $new_data['percentages'],
		] );
	}

	/* -----------------------------------------------------------------------
	 * Helpers
	 * -------------------------------------------------------------------- */

	private function stars_html( float $rating ): string {
		$output = '<span class="rsp-stars" aria-hidden="true">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$diff = $rating - $i + 1;
			if ( $diff >= 1 ) {
				$output .= '<span class="rsp-star rsp-star--full">★</span>';
			} elseif ( $diff > 0 ) {
				$pct     = round( $diff * 100 );
				$output .= '<span class="rsp-star rsp-star--partial" style="--fill:' . $pct . '%">★</span>';
			} else {
				$output .= '<span class="rsp-star rsp-star--empty">★</span>';
			}
		}
		$output .= '</span>';
		return $output;
	}

	public function output_css_variables(): void {
		if ( ! is_singular( 'product' ) && ! is_shop() && ! is_product_category() && ! is_product_tag() ) { return; }

		$star_color = sanitize_hex_color( $this->settings->get( 'color_stars',      '#f5a623' ) );
		$bar_color  = sanitize_hex_color( $this->settings->get( 'color_bars',       '#f5a623' ) );
		$badge_bg   = sanitize_hex_color( $this->settings->get( 'color_badge_bg',   '#27ae60' ) );
		$badge_text = sanitize_hex_color( $this->settings->get( 'color_badge_text', '#ffffff' ) );

		echo '<style id="rsp-css-vars">:root{'
			. '--rsp-star:'       . esc_attr( $star_color ) . ';'
			. '--rsp-bar:'        . esc_attr( $bar_color )  . ';'
			. '--rsp-badge-bg:'   . esc_attr( $badge_bg )   . ';'
			. '--rsp-badge-text:' . esc_attr( $badge_text ) . ';'
			. '}</style>' . "\n";
	}
}
