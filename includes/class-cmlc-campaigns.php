<?php
/**
 * Campaign entity and resolution helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Campaigns {
	/**
	 * Campaign post type.
	 */
	const POST_TYPE = 'cmlc_campaign';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register CPT immediately if init already fired (bootstrapped during init).
		if ( did_action( 'init' ) ) {
			$this->register_post_type();
		} else {
			add_action( 'init', array( $this, 'register_post_type' ) );
		}
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_campaign_meta' ) );
	}

	/**
	 * Registers campaign CPT.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Lead Campaigns', 'coppermont-lead-capture' ),
					'singular_name'      => __( 'Lead Campaign', 'coppermont-lead-capture' ),
					'add_new'            => __( 'Add New', 'coppermont-lead-capture' ),
					'add_new_item'       => __( 'Add New Campaign', 'coppermont-lead-capture' ),
					'edit_item'          => __( 'Edit Campaign', 'coppermont-lead-capture' ),
					'new_item'           => __( 'New Campaign', 'coppermont-lead-capture' ),
					'view_item'          => __( 'View Campaign', 'coppermont-lead-capture' ),
					'search_items'       => __( 'Search Campaigns', 'coppermont-lead-capture' ),
					'not_found'          => __( 'No campaigns found.', 'coppermont-lead-capture' ),
					'not_found_in_trash' => __( 'No campaigns found in Trash.', 'coppermont-lead-capture' ),
					'all_items'          => __( 'All Campaigns', 'coppermont-lead-capture' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'cmlc-dashboard',
				'supports'        => array( 'title' ),
				'menu_icon'       => 'dashicons-megaphone',
				'capability_type' => 'post',
			)
		);
	}

	/**
	 * Default campaign values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'status'                     => 'active',
			'priority'                   => 100,
			'display_mode'               => 'bottom_bar',
			'headline'                   => 'Get weekly growth tips',
			'body'                       => 'Join our email list for practical lead generation insights.',
			'button_text'                => 'Subscribe',
			'bg_color'                   => '#1f2937',
			'text_color'                 => '#ffffff',
			'button_color'               => '#f59e0b',
			'button_text_color'          => '#111827',
			'dimensions'                 => '1200xauto',
			'opacity'                    => 98,
			'scroll_trigger_percent'     => 40,
			'time_delay_seconds'         => 8,
			'repetition_cooldown_hours'  => 24,
			'max_views'                  => 3,
			'enable_exit_intent'         => 1,
			'enable_mobile'              => 1,
			'allowed_referrers'          => '',
			'page_target_mode'           => 'all',
			'page_ids'                   => '',
			'schedule_start'             => '',
			'schedule_end'               => '',
			'baseline_impressions'       => 0,
			'baseline_submissions'       => 0,
		);
	}

	/**
	 * Returns campaign meta.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_campaign( $campaign_id ) {
		$post = get_post( $campaign_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		$campaign = self::defaults();
		foreach ( array_keys( $campaign ) as $key ) {
			$campaign[ $key ] = get_post_meta( $campaign_id, 'cmlc_' . $key, true );
		}

		$campaign = wp_parse_args( $campaign, self::defaults() );
		$campaign = self::sanitize_campaign( $campaign );
		$campaign['id'] = (int) $campaign_id;

		return $campaign;
	}

	/**
	 * Resolves an eligible campaign.
	 *
	 * @param int $campaign_id Preferred campaign ID.
	 * @return array<string,mixed>|null
	 */
	public static function resolve_campaign( $campaign_id = 0 ) {
		$campaign_id = absint( $campaign_id );
		if ( $campaign_id > 0 ) {
			$campaign = self::get_campaign( $campaign_id );
			if ( $campaign && self::is_active_campaign( $campaign ) ) {
				return $campaign;
			}
		}

		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'meta_key'       => 'cmlc_priority',
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
			)
		);

		if ( ! $query->have_posts() ) {
			return null;
		}

		foreach ( $query->posts as $post ) {
			$campaign = self::get_campaign( $post->ID );
			if ( $campaign && self::is_active_campaign( $campaign ) ) {
				return $campaign;
			}
		}

		return null;
	}

	/**
	 * Check if campaign is eligible to render.
	 *
	 * @param array<string,mixed> $campaign Campaign data.
	 * @return bool
	 */
	public static function is_active_campaign( $campaign ) {
		if ( is_admin() || 'active' !== $campaign['status'] ) {
			return false;
		}

		$timezone = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $timezone );
		if ( ! empty( $campaign['schedule_start'] ) ) {
			$start = date_create_immutable( (string) $campaign['schedule_start'], $timezone );
			if ( $start && $now < $start ) {
				return false;
			}
		}
		if ( ! empty( $campaign['schedule_end'] ) ) {
			$end = date_create_immutable( (string) $campaign['schedule_end'], $timezone );
			if ( $end && $now > $end ) {
				return false;
			}
		}

		$allowed_rules = CMLC_Renderer::normalize_allowed_referrer_domains( (string) $campaign['allowed_referrers'] );
		if ( ! empty( $allowed_rules ) ) {
			$referrer = wp_get_referer();
			$host     = $referrer ? CMLC_Renderer::normalize_domain( (string) wp_parse_url( $referrer, PHP_URL_HOST ) ) : '';
			$matched  = false;
			foreach ( $allowed_rules as $rule ) {
				if ( '' !== $host && CMLC_Renderer::host_matches_referrer_rule( $host, $rule ) ) {
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) {
				return false;
			}
		}

		$post_id = get_queried_object_id();
		$ids     = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) $campaign['page_ids'] ) ) ) );
		if ( 'include' === $campaign['page_target_mode'] ) {
			return in_array( $post_id, $ids, true );
		}
		if ( 'exclude' === $campaign['page_target_mode'] ) {
			return ! in_array( $post_id, $ids, true );
		}

		return true;
	}

	/**
	 * Registers campaign meta box.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box( 'cmlc_campaign', __( 'Campaign Settings', 'coppermont-lead-capture' ), array( $this, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Renders campaign meta box.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'cmlc_campaign_save', 'cmlc_campaign_nonce' );
		$campaign = self::get_campaign( $post->ID );
		$campaign = $campaign ?: self::defaults();
		?>
		<table class="form-table" role="presentation">
			<tr><th>Status</th><td><select name="cmlc_campaign[status]"><option value="active" <?php selected( 'active', $campaign['status'] ); ?>>Active</option><option value="inactive" <?php selected( 'inactive', $campaign['status'] ); ?>>Inactive</option></select></td></tr>
			<tr><th>Priority</th><td><input type="number" name="cmlc_campaign[priority]" value="<?php echo esc_attr( $campaign['priority'] ); ?>"></td></tr>
			<tr><th>Display Mode</th><td><select name="cmlc_campaign[display_mode]"><option value="bottom_bar" <?php selected( 'bottom_bar', $campaign['display_mode'] ); ?>>Bottom Bar</option><option value="top_bar" <?php selected( 'top_bar', $campaign['display_mode'] ); ?>>Top Bar</option><option value="lightbox" <?php selected( 'lightbox', $campaign['display_mode'] ); ?>>Lightbox Popup</option></select><p class="description">Bottom/top bar slides in from the edge. Lightbox shows a centered modal with overlay.</p></td></tr>
			<tr><th>Headline</th><td><input class="regular-text" name="cmlc_campaign[headline]" value="<?php echo esc_attr( $campaign['headline'] ); ?>"></td></tr>
			<tr><th>Body</th><td><input class="regular-text" name="cmlc_campaign[body]" value="<?php echo esc_attr( $campaign['body'] ); ?>"></td></tr>
			<tr><th>Button Text</th><td><input name="cmlc_campaign[button_text]" value="<?php echo esc_attr( $campaign['button_text'] ); ?>"></td></tr>
			<tr><th>Dimensions</th><td><input name="cmlc_campaign[dimensions]" value="<?php echo esc_attr( $campaign['dimensions'] ); ?>"><p class="description">Example: 1200xauto.</p></td></tr>
			<tr><th>Opacity (%)</th><td><input type="number" min="10" max="100" name="cmlc_campaign[opacity]" value="<?php echo esc_attr( $campaign['opacity'] ); ?>"></td></tr>
			<tr><th>Background Color</th><td><input type="color" name="cmlc_campaign[bg_color]" value="<?php echo esc_attr( $campaign['bg_color'] ); ?>"></td></tr>
			<tr><th>Text Color</th><td><input type="color" name="cmlc_campaign[text_color]" value="<?php echo esc_attr( $campaign['text_color'] ); ?>"></td></tr>
			<tr><th>Button Color</th><td><input type="color" name="cmlc_campaign[button_color]" value="<?php echo esc_attr( $campaign['button_color'] ); ?>"></td></tr>
			<tr><th>Button Text Color</th><td><input type="color" name="cmlc_campaign[button_text_color]" value="<?php echo esc_attr( $campaign['button_text_color'] ); ?>"></td></tr>
			<tr><th>Scroll Trigger %</th><td><input type="number" min="0" max="100" name="cmlc_campaign[scroll_trigger_percent]" value="<?php echo esc_attr( $campaign['scroll_trigger_percent'] ); ?>"></td></tr>
			<tr><th>Delay Seconds</th><td><input type="number" min="0" name="cmlc_campaign[time_delay_seconds]" value="<?php echo esc_attr( $campaign['time_delay_seconds'] ); ?>"></td></tr>
			<tr><th>Cooldown Hours</th><td><input type="number" min="1" name="cmlc_campaign[repetition_cooldown_hours]" value="<?php echo esc_attr( $campaign['repetition_cooldown_hours'] ); ?>"></td></tr>
			<tr><th>Max Views</th><td><input type="number" min="1" name="cmlc_campaign[max_views]" value="<?php echo esc_attr( $campaign['max_views'] ); ?>"></td></tr>
			<tr><th>Exit Intent</th><td><input type="checkbox" name="cmlc_campaign[enable_exit_intent]" value="1" <?php checked( 1, $campaign['enable_exit_intent'] ); ?>></td></tr>
			<tr><th>Enable on Mobile</th><td><input type="checkbox" name="cmlc_campaign[enable_mobile]" value="1" <?php checked( 1, $campaign['enable_mobile'] ); ?>></td></tr>
			<tr><th>Allowed Referrers</th><td><input class="regular-text" name="cmlc_campaign[allowed_referrers]" value="<?php echo esc_attr( $campaign['allowed_referrers'] ); ?>"></td></tr>
			<tr><th>Page Targeting Mode</th><td><select name="cmlc_campaign[page_target_mode]"><option value="all" <?php selected( 'all', $campaign['page_target_mode'] ); ?>>All pages</option><option value="include" <?php selected( 'include', $campaign['page_target_mode'] ); ?>>Include listed IDs</option><option value="exclude" <?php selected( 'exclude', $campaign['page_target_mode'] ); ?>>Exclude listed IDs</option></select></td></tr>
			<tr><th>Page IDs</th><td><input class="regular-text" name="cmlc_campaign[page_ids]" value="<?php echo esc_attr( $campaign['page_ids'] ); ?>"></td></tr>
			<tr><th>Schedule Start</th><td><input type="datetime-local" name="cmlc_campaign[schedule_start]" value="<?php echo esc_attr( $campaign['schedule_start'] ); ?>"></td></tr>
			<tr><th>Schedule End</th><td><input type="datetime-local" name="cmlc_campaign[schedule_end]" value="<?php echo esc_attr( $campaign['schedule_end'] ); ?>"></td></tr>
		</table>
		<?php
	}

	/**
	 * Saves campaign meta.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_campaign_meta( $post_id ) {
		if ( ! isset( $_POST['cmlc_campaign_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cmlc_campaign_nonce'] ) ), 'cmlc_campaign_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw      = isset( $_POST['cmlc_campaign'] ) && is_array( $_POST['cmlc_campaign'] ) ? wp_unslash( $_POST['cmlc_campaign'] ) : array();
		$campaign = self::sanitize_campaign( $raw );
		foreach ( $campaign as $key => $value ) {
			update_post_meta( $post_id, 'cmlc_' . $key, $value );
		}
	}

	/**
	 * Sanitizes campaign payload.
	 *
	 * @param array<string,mixed> $input Campaign data.
	 * @return array<string,mixed>
	 */
	public static function sanitize_campaign( $input ) {
		$defaults = self::defaults();
		$output   = wp_parse_args( is_array( $input ) ? $input : array(), $defaults );

		$output['status']                    = in_array( $output['status'], array( 'active', 'inactive' ), true ) ? $output['status'] : 'inactive';
		$output['priority']                  = absint( $output['priority'] );
		$output['display_mode']              = in_array( $output['display_mode'], array( 'bottom_bar', 'top_bar', 'lightbox' ), true ) ? $output['display_mode'] : 'bottom_bar';
		$output['headline']                  = sanitize_text_field( $output['headline'] );
		$output['body']                      = sanitize_text_field( $output['body'] );
		$output['button_text']               = sanitize_text_field( $output['button_text'] );
		$output['bg_color']                  = sanitize_hex_color( $output['bg_color'] ) ?: $defaults['bg_color'];
		$output['text_color']                = sanitize_hex_color( $output['text_color'] ) ?: $defaults['text_color'];
		$output['button_color']              = sanitize_hex_color( $output['button_color'] ) ?: $defaults['button_color'];
		$output['button_text_color']         = sanitize_hex_color( $output['button_text_color'] ) ?: $defaults['button_text_color'];
		$output['dimensions']                = sanitize_text_field( $output['dimensions'] );
		$output['opacity']                   = max( 10, min( 100, absint( $output['opacity'] ) ) );
		$output['scroll_trigger_percent']    = max( 0, min( 100, absint( $output['scroll_trigger_percent'] ) ) );
		$output['time_delay_seconds']        = max( 0, absint( $output['time_delay_seconds'] ) );
		$output['repetition_cooldown_hours'] = max( 1, absint( $output['repetition_cooldown_hours'] ) );
		$output['max_views']                 = max( 1, absint( $output['max_views'] ) );
		$output['enable_exit_intent']        = empty( $output['enable_exit_intent'] ) ? 0 : 1;
		$output['enable_mobile']             = empty( $output['enable_mobile'] ) ? 0 : 1;
		$output['allowed_referrers']         = sanitize_text_field( $output['allowed_referrers'] );
		$output['page_target_mode']          = in_array( $output['page_target_mode'], array( 'all', 'include', 'exclude' ), true ) ? $output['page_target_mode'] : 'all';
		$output['page_ids']                  = sanitize_text_field( $output['page_ids'] );
		$output['schedule_start']            = sanitize_text_field( $output['schedule_start'] );
		$output['schedule_end']              = sanitize_text_field( $output['schedule_end'] );
		$output['baseline_impressions']      = absint( $output['baseline_impressions'] );
		$output['baseline_submissions']      = absint( $output['baseline_submissions'] );

		return $output;
	}
}
