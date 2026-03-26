<?php
/**
 * RSP_Admin – Admin settings page and product meta box.
 *
 * @package RatingSystemPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSP_Admin {

	/** @var RSP_Settings */
	private RSP_Settings $settings;

	/** @var RSP_Product_Meta */
	private RSP_Product_Meta $product_meta;

	public function __construct( RSP_Settings $settings, RSP_Product_Meta $product_meta ) {
		$this->settings     = $settings;
		$this->product_meta = $product_meta;

		add_action( 'admin_menu',                        [ $this, 'register_menu' ] );
		add_action( 'admin_init',                        [ $this, 'handle_settings_save' ] );
		add_action( 'add_meta_boxes',                    [ $this, 'add_product_meta_box' ] );
		add_action( 'save_post_product',                 [ $this, 'save_product_meta' ] );
		add_action( 'admin_enqueue_scripts',             [ $this, 'enqueue_admin_assets' ] );
		add_filter( 'plugin_action_links_' . RSP_PLUGIN_BASE, [ $this, 'plugin_action_links' ] );
	}

	/**
	 * Register WooCommerce → Rating System menu item.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Rating System Pro', 'rating-system-pro' ),
			__( 'Rating System', 'rating-system-pro' ),
			'manage_woocommerce',
			'rsp-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Handle settings form submission.
	 */
	public function handle_settings_save(): void {
		if (
			! isset( $_POST['rsp_save_settings'], $_POST['rsp_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rsp_nonce'] ) ), 'rsp_save_settings' ) ||
			! current_user_can( 'manage_woocommerce' )
		) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
		$raw = array_map( 'wp_unslash', $_POST );
		$this->settings->save( $raw );

		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Settings saved.', 'rating-system-pro' )
				. '</p></div>';
		} );
	}

	/**
	 * Enqueue admin CSS/JS.
	 *
	 * @param string $hook
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$allowed_hooks = [ 'woocommerce_page_rsp-settings', 'post.php', 'post-new.php' ];
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'rsp-admin',
			RSP_PLUGIN_URL . 'assets/css/admin.css',
			[],
			RSP_VERSION
		);

		// Colour picker
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_script(
			'rsp-admin',
			RSP_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'wp-color-picker' ],
			RSP_VERSION,
			true
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'rating-system-pro' ) );
		}

		$s = $this->settings->all();
		?>
		<div class="wrap rsp-settings-wrap">
			<h1 class="rsp-settings-title">
				<span class="rsp-logo">⭐</span>
				<?php esc_html_e( 'Rating System Pro', 'rating-system-pro' ); ?>
				<span class="rsp-version">v<?php echo esc_html( RSP_VERSION ); ?></span>
			</h1>

			<form method="post" action="" id="rsp-settings-form">
				<?php wp_nonce_field( 'rsp_save_settings', 'rsp_nonce' ); ?>
				<input type="hidden" name="rsp_save_settings" value="1">

				<div class="rsp-grid">

					<!-- General Settings -->
					<div class="rsp-card">
						<h2><?php esc_html_e( 'General Settings', 'rating-system-pro' ); ?></h2>

						<?php $this->render_toggle( 'stars_only_mode', __( 'Stars Only Mode', 'rating-system-pro' ), __( 'Remove review textarea; accept star ratings only.', 'rating-system-pro' ), $s['stars_only_mode'] ); ?>
						<?php $this->render_toggle( 'enable_manual_dist', __( 'Enable Manual Distribution', 'rating-system-pro' ), __( 'Use manually entered star counts for display.', 'rating-system-pro' ), $s['enable_manual_dist'] ); ?>
						<?php $this->render_toggle( 'show_breakdown', __( 'Show Rating Breakdown', 'rating-system-pro' ), __( 'Display Amazon-style progress bars on product pages.', 'rating-system-pro' ), $s['show_breakdown'] ); ?>
						<?php $this->render_toggle( 'show_in_shop', __( 'Show on Shop Page', 'rating-system-pro' ), __( 'Display ratings after product names on the shop page.', 'rating-system-pro' ), $s['show_in_shop'] ); ?>
						<?php $this->render_toggle( 'show_average', __( 'Show Average Rating', 'rating-system-pro' ), __( 'Display the computed average and total count.', 'rating-system-pro' ), $s['show_average'] ); ?>
					</div>

					<!-- Badge Settings -->
					<div class="rsp-card">
						<h2><?php esc_html_e( 'Top Rated Badge', 'rating-system-pro' ); ?></h2>

						<?php $this->render_toggle( 'badge_enabled', __( 'Enable Badge', 'rating-system-pro' ), __( 'Show a badge on products that exceed the threshold.', 'rating-system-pro' ), $s['badge_enabled'] ); ?>

						<div class="rsp-field">
							<label for="rsp_badge_threshold"><?php esc_html_e( 'Badge Threshold', 'rating-system-pro' ); ?></label>
							<input type="number" id="rsp_badge_threshold" name="badge_threshold"
								   value="<?php echo esc_attr( $s['badge_threshold'] ); ?>"
								   min="1" max="5" step="0.1" class="small-text">
							<p class="description"><?php esc_html_e( 'Show badge when average rating ≥ this value (1–5).', 'rating-system-pro' ); ?></p>
						</div>

						<div class="rsp-field">
							<label for="rsp_badge_text"><?php esc_html_e( 'Badge Text', 'rating-system-pro' ); ?></label>
							<input type="text" id="rsp_badge_text" name="badge_text"
								   value="<?php echo esc_attr( $s['badge_text'] ); ?>" class="regular-text">
						</div>
					</div>

					<!-- Rating Form Settings -->
					<div class="rsp-card">
						<h2><?php esc_html_e( 'Rating Form', 'rating-system-pro' ); ?></h2>

						<?php $this->render_toggle( 'enable_rating_form', __( 'Enable Rating Form', 'rating-system-pro' ), __( 'Show a star rating form inside the Ratings tab.', 'rating-system-pro' ), $s['enable_rating_form'] ); ?>
						<?php $this->render_toggle( 'allow_guest_rating', __( 'Allow Guest Ratings', 'rating-system-pro' ), __( 'Let visitors rate without logging in (tracked by session).', 'rating-system-pro' ), $s['allow_guest_rating'] ); ?>

						<div class="rsp-field">
							<label for="rsp_rating_form_title"><?php esc_html_e( 'Form Title', 'rating-system-pro' ); ?></label>
							<input type="text" id="rsp_rating_form_title" name="rating_form_title"
								   value="<?php echo esc_attr( $s['rating_form_title'] ); ?>" class="regular-text">
						</div>

						<div class="rsp-field">
							<label for="rsp_rating_success_msg"><?php esc_html_e( 'Success Message', 'rating-system-pro' ); ?></label>
							<input type="text" id="rsp_rating_success_msg" name="rating_success_msg"
								   value="<?php echo esc_attr( $s['rating_success_msg'] ); ?>" class="regular-text">
						</div>

						<div class="rsp-field">
							<label for="rsp_rating_login_msg"><?php esc_html_e( 'Login Required Message', 'rating-system-pro' ); ?></label>
							<input type="text" id="rsp_rating_login_msg" name="rating_login_msg"
								   value="<?php echo esc_attr( $s['rating_login_msg'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Shown to guests when Allow Guest Ratings is OFF.', 'rating-system-pro' ); ?></p>
						</div>
					</div>

					<!-- Color Settings -->
					<div class="rsp-card">
						<h2><?php esc_html_e( 'Colors', 'rating-system-pro' ); ?></h2>

						<?php $this->render_color( 'color_stars', __( 'Star Color', 'rating-system-pro' ), $s['color_stars'] ); ?>
						<?php $this->render_color( 'color_bars', __( 'Progress Bar Color', 'rating-system-pro' ), $s['color_bars'] ); ?>
						<?php $this->render_color( 'color_badge_bg', __( 'Badge Background', 'rating-system-pro' ), $s['color_badge_bg'] ); ?>
						<?php $this->render_color( 'color_badge_text', __( 'Badge Text Color', 'rating-system-pro' ), $s['color_badge_text'] ); ?>
					</div>

				</div><!-- /.rsp-grid -->

				<p class="submit">
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Save Settings', 'rating-system-pro' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Helper: render a toggle row.
	 */
	private function render_toggle( string $key, string $label, string $desc, string $value ): void {
		?>
		<div class="rsp-field rsp-toggle-field">
			<label class="rsp-toggle">
				<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="yes"
					<?php checked( $value, 'yes' ); ?>>
				<span class="rsp-slider"></span>
				<span class="rsp-toggle-label"><?php echo esc_html( $label ); ?></span>
			</label>
			<p class="description"><?php echo esc_html( $desc ); ?></p>
		</div>
		<?php
	}

	/**
	 * Helper: render a color picker row.
	 */
	private function render_color( string $key, string $label, string $value ): void {
		?>
		<div class="rsp-field">
			<label for="rsp_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="text" id="rsp_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
				   value="<?php echo esc_attr( $value ); ?>" class="rsp-color-picker">
		</div>
		<?php
	}

	/* -----------------------------------------------------------------------
	 * Product meta box
	 * -------------------------------------------------------------------- */

	/**
	 * Register meta box on product edit screen.
	 */
	public function add_product_meta_box(): void {
		add_meta_box(
			'rsp-product-ratings',
			__( '⭐ Manual Rating Distribution', 'rating-system-pro' ),
			[ $this, 'render_product_meta_box' ],
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Render the product meta box.
	 *
	 * @param WP_Post $post
	 */
	public function render_product_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'rsp_product_meta', 'rsp_product_nonce' );

		$counts   = $this->product_meta->get_star_counts( $post->ID );
		$override = $this->product_meta->has_override( $post->ID );
		$data     = $this->product_meta->get_rating_data( $post->ID );
		?>
		<div class="rsp-meta-box">

			<div class="rsp-meta-override">
				<label>
					<input type="checkbox" name="rsp_override" value="yes" <?php checked( $override, true ); ?>>
					<?php esc_html_e( 'Override global settings for this product', 'rating-system-pro' ); ?>
				</label>
			</div>

			<div class="rsp-meta-stars">
				<?php foreach ( RSP_Product_Meta::STAR_KEYS as $star ) : ?>
					<div class="rsp-meta-row">
						<label for="rsp_stars_<?php echo esc_attr( $star ); ?>">
							<?php echo esc_html( sprintf( _n( '%d Star', '%d Stars', $star, 'rating-system-pro' ), $star ) ); ?>
							<span class="rsp-meta-stars-icon">
								<?php echo str_repeat( '★', $star ); ?>
							</span>
						</label>
						<input type="number" id="rsp_stars_<?php echo esc_attr( $star ); ?>"
							   name="rsp_stars[<?php echo esc_attr( $star ); ?>]"
							   value="<?php echo esc_attr( $counts[ $star ] ); ?>"
							   min="0" step="1" class="small-text rsp-star-input">
					</div>
				<?php endforeach; ?>
			</div>

			<div class="rsp-meta-preview">
				<strong><?php esc_html_e( 'Computed Preview:', 'rating-system-pro' ); ?></strong>
				<span class="rsp-meta-average"><?php echo esc_html( $data['average'] ); ?></span>
				<?php esc_html_e( 'out of 5 —', 'rating-system-pro' ); ?>
				<span class="rsp-meta-total"><?php echo esc_html( number_format_i18n( $data['total'] ) ); ?></span>
				<?php esc_html_e( 'ratings', 'rating-system-pro' ); ?>
			</div>

		</div>
		<?php
	}

	/**
	 * Save product meta box data.
	 *
	 * @param int $post_id
	 */
	public function save_product_meta( int $post_id ): void {
		if (
			! isset( $_POST['rsp_product_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rsp_product_nonce'] ) ), 'rsp_product_meta' ) ||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		// Override flag
		$override = isset( $_POST['rsp_override'] ) && $_POST['rsp_override'] === 'yes';
		$this->product_meta->save_override( $post_id, $override );

		// Star counts
		$raw_stars = isset( $_POST['rsp_stars'] ) && is_array( $_POST['rsp_stars'] )
			? array_map( 'absint', wp_unslash( $_POST['rsp_stars'] ) )
			: [];

		$counts = [];
		foreach ( RSP_Product_Meta::STAR_KEYS as $star ) {
			$counts[ $star ] = $raw_stars[ $star ] ?? 0;
		}

		$this->product_meta->save_star_counts( $post_id, $counts );
	}

	/**
	 * Add Settings link to plugin list.
	 *
	 * @param array $links
	 * @return array
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=rsp-settings' ) ),
			esc_html__( 'Settings', 'rating-system-pro' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}
