<?php
/**
 * Campaign registry and analytics storage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Campaigns {
	/**
	 * Option key for default campaign id.
	 *
	 * @var string
	 */
	const DEFAULT_CAMPAIGN_OPTION = 'cmlc_default_campaign_id';

	/**
	 * Campaign post type.
	 *
	 * @var string
	 */
	const POST_TYPE = 'cmlc_campaign';

	/**
	 * Meta keys used by campaigns.
	 *
	 * @var array<string,string>
	 */
	const META_KEYS = array(
		'headline'                  => '_cmlc_headline',
		'body'                      => '_cmlc_body',
		'button_text'               => '_cmlc_button_text',
		'bg_color'                  => '_cmlc_bg_color',
		'text_color'                => '_cmlc_text_color',
		'button_color'              => '_cmlc_button_color',
		'button_text_color'         => '_cmlc_button_text_color',
		'scroll_trigger_percent'    => '_cmlc_scroll_trigger_percent',
		'time_delay_seconds'        => '_cmlc_time_delay_seconds',
		'repetition_cooldown_hours' => '_cmlc_repetition_cooldown_hours',
		'max_views'                 => '_cmlc_max_views',
		'enable_exit_intent'        => '_cmlc_enable_exit_intent',
		'enable_mobile'             => '_cmlc_enable_mobile',
		'allowed_referrers'         => '_cmlc_allowed_referrers',
		'page_target_mode'          => '_cmlc_page_target_mode',
		'page_ids'                  => '_cmlc_page_ids',
		'schedule_start'            => '_cmlc_schedule_start',
		'schedule_end'              => '_cmlc_schedule_end',
		'priority'                  => '_cmlc_priority',
		'opacity'                   => '_cmlc_opacity',
		'width'                     => '_cmlc_width',
		'height'                    => '_cmlc_height',
		'status'                    => '_cmlc_status',
	);

	/**
	 * Registers runtime hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_campaign_meta' ) );
	}

	/**
	 * Registers campaign post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Lead Campaigns', 'coppermont-lead-capture' ),
					'singular_name' => __( 'Lead Campaign', 'coppermont-lead-capture' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'options-general.php',
				'supports'     => array( 'title' ),
				'menu_icon'    => 'dashicons-megaphone',
			)
		);
	}


	/**
	 * Registers campaign meta box.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'cmlc_campaign_settings',
			__( 'Campaign Settings', 'coppermont-lead-capture' ),
			array( $this, 'render_campaign_meta_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Renders campaign settings form.
	 *
	 * @param WP_Post $post Campaign post.
	 * @return void
	 */
	public function render_campaign_meta_box( $post ) {
		$campaign = self::resolve_campaign( (int) $post->ID );
		wp_nonce_field( 'cmlc_campaign_meta', 'cmlc_campaign_meta_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tr><th><label for="cmlc_headline">Headline</label></th><td><input id="cmlc_headline" class="regular-text" name="cmlc_campaign[headline]" value="<?php echo esc_attr( $campaign['headline'] ); ?>"></td></tr>
			<tr><th><label for="cmlc_body">Body</label></th><td><input id="cmlc_body" class="regular-text" name="cmlc_campaign[body]" value="<?php echo esc_attr( $campaign['body'] ); ?>"></td></tr>
			<tr><th><label for="cmlc_button_text">Button Text</label></th><td><input id="cmlc_button_text" name="cmlc_campaign[button_text]" value="<?php echo esc_attr( $campaign['button_text'] ); ?>"></td></tr>
			<tr><th>Colors</th><td><input type="color" name="cmlc_campaign[bg_color]" value="<?php echo esc_attr( $campaign['bg_color'] ); ?>"> <input type="color" name="cmlc_campaign[text_color]" value="<?php echo esc_attr( $campaign['text_color'] ); ?>"> <input type="color" name="cmlc_campaign[button_color]" value="<?php echo esc_attr( $campaign['button_color'] ); ?>"> <input type="color" name="cmlc_campaign[button_text_color]" value="<?php echo esc_attr( $campaign['button_text_color'] ); ?>"></td></tr>
			<tr><th>Trigger Settings</th><td>Scroll % <input type="number" min="0" max="100" name="cmlc_campaign[scroll_trigger_percent]" value="<?php echo esc_attr( (string) $campaign['scroll_trigger_percent'] ); ?>"> Delay <input type="number" min="0" name="cmlc_campaign[time_delay_seconds]" value="<?php echo esc_attr( (string) $campaign['time_delay_seconds'] ); ?>"> Cooldown <input type="number" min="1" name="cmlc_campaign[repetition_cooldown_hours]" value="<?php echo esc_attr( (string) $campaign['repetition_cooldown_hours'] ); ?>"> Max Views <input type="number" min="1" name="cmlc_campaign[max_views]" value="<?php echo esc_attr( (string) $campaign['max_views'] ); ?>"> <label><input type="checkbox" name="cmlc_campaign[enable_exit_intent]" value="1" <?php checked( 1, $campaign['enable_exit_intent'] ); ?>> Exit intent</label> <label><input type="checkbox" name="cmlc_campaign[enable_mobile]" value="1" <?php checked( 1, $campaign['enable_mobile'] ); ?>> Mobile</label></td></tr>
			<tr><th>Targeting</th><td>Referrers <input class="regular-text" name="cmlc_campaign[allowed_referrers]" value="<?php echo esc_attr( $campaign['allowed_referrers'] ); ?>"> Mode <select name="cmlc_campaign[page_target_mode]"><option value="all" <?php selected( 'all', $campaign['page_target_mode'] ); ?>>All</option><option value="include" <?php selected( 'include', $campaign['page_target_mode'] ); ?>>Include</option><option value="exclude" <?php selected( 'exclude', $campaign['page_target_mode'] ); ?>>Exclude</option></select> Page IDs <input name="cmlc_campaign[page_ids]" value="<?php echo esc_attr( $campaign['page_ids'] ); ?>"></td></tr>
			<tr><th>Schedule</th><td>Start <input type="datetime-local" name="cmlc_campaign[schedule_start]" value="<?php echo esc_attr( $campaign['schedule_start'] ); ?>"> End <input type="datetime-local" name="cmlc_campaign[schedule_end]" value="<?php echo esc_attr( $campaign['schedule_end'] ); ?>"></td></tr>
			<tr><th>Display</th><td>Width <input type="number" min="300" name="cmlc_campaign[width]" value="<?php echo esc_attr( (string) $campaign['width'] ); ?>"> Height <input type="number" min="80" name="cmlc_campaign[height]" value="<?php echo esc_attr( (string) $campaign['height'] ); ?>"> Opacity <input type="number" min="0" max="100" name="cmlc_campaign[opacity]" value="<?php echo esc_attr( (string) $campaign['opacity'] ); ?>"> Priority <input type="number" min="0" name="cmlc_campaign[priority]" value="<?php echo esc_attr( (string) $campaign['priority'] ); ?>"> Status <select name="cmlc_campaign[status]"><option value="active" <?php selected( 'active', $campaign['status'] ); ?>>Active</option><option value="inactive" <?php selected( 'inactive', $campaign['status'] ); ?>>Inactive</option></select></td></tr>
		</table>
		<?php
	}

	/**
	 * Saves campaign meta.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function save_campaign_meta( $post_id ) {
		if ( ! isset( $_POST['cmlc_campaign_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cmlc_campaign_meta_nonce'] ) ), 'cmlc_campaign_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST['cmlc_campaign'] ) && is_array( $_POST['cmlc_campaign'] ) ? wp_unslash( $_POST['cmlc_campaign'] ) : array();
		$raw['enable_exit_intent'] = isset( $raw['enable_exit_intent'] ) ? 1 : 0;
		$raw['enable_mobile']      = isset( $raw['enable_mobile'] ) ? 1 : 0;

		$campaign = self::normalize_campaign( $raw );
		foreach ( self::META_KEYS as $field => $meta_key ) {
			if ( isset( $campaign[ $field ] ) ) {
				update_post_meta( $post_id, $meta_key, $campaign[ $field ] );
			}
		}
	}

	/**
	 * Ensures custom tables and default campaign exist.
	 *
	 * @return void
	 */
	public static function install() {
		self::create_tables();
		self::migrate_global_settings_to_default_campaign();
	}

	/**
	 * Creates plugin data tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$analytics_table = self::analytics_table_name();
		$leads_table     = self::leads_table_name();

		$analytics_sql = "CREATE TABLE {$analytics_table} (
			campaign_id BIGINT(20) UNSIGNED NOT NULL,
			impressions BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			submissions BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (campaign_id)
		) {$charset_collate};";

		$leads_sql = "CREATE TABLE {$leads_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(190) NOT NULL,
			campaign_id BIGINT(20) UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY campaign_id (campaign_id)
		) {$charset_collate};";

		dbDelta( $analytics_sql );
		dbDelta( $leads_sql );
	}

	/**
	 * Migrates existing single settings to a default campaign.
	 *
	 * @return void
	 */
	public static function migrate_global_settings_to_default_campaign() {
		$campaign_id = absint( get_option( self::DEFAULT_CAMPAIGN_OPTION, 0 ) );
		if ( $campaign_id > 0 && get_post( $campaign_id ) ) {
			return;
		}

		$settings = CMLC_Settings::get();
		$post_id  = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => __( 'Default Campaign', 'coppermont-lead-capture' ),
			)
		);

		if ( is_wp_error( $post_id ) || empty( $post_id ) ) {
			return;
		}

		$campaign_data = self::settings_to_campaign_data( $settings );
		foreach ( $campaign_data as $key => $value ) {
			update_post_meta( $post_id, self::meta_key( $key ), $value );
		}

		$impressions = absint( $settings['analytics_impressions'] );
		$submissions = absint( $settings['analytics_submissions'] );
		if ( $impressions || $submissions ) {
			self::upsert_analytics( $post_id, $impressions, $submissions );
		}

		update_option( self::DEFAULT_CAMPAIGN_OPTION, (int) $post_id );
	}

	/**
	 * Gets a campaign meta key by logical setting key.
	 *
	 * @param string $key Logical key.
	 * @return string
	 */
	public static function meta_key( $key ) {
		if ( isset( self::META_KEYS[ $key ] ) ) {
			return self::META_KEYS[ $key ];
		}

		return '_cmlc_' . sanitize_key( $key );
	}

	/**
	 * Converts plugin settings into campaign shape.
	 *
	 * @param array<string,mixed> $settings Settings payload.
	 * @return array<string,mixed>
	 */
	public static function settings_to_campaign_data( $settings ) {
		return array(
			'headline'                  => $settings['headline'],
			'body'                      => $settings['body'],
			'button_text'               => $settings['button_text'],
			'bg_color'                  => $settings['bg_color'],
			'text_color'                => $settings['text_color'],
			'button_color'              => $settings['button_color'],
			'button_text_color'         => $settings['button_text_color'],
			'scroll_trigger_percent'    => (int) $settings['scroll_trigger_percent'],
			'time_delay_seconds'        => (int) $settings['time_delay_seconds'],
			'repetition_cooldown_hours' => (int) $settings['repetition_cooldown_hours'],
			'max_views'                 => (int) $settings['max_views'],
			'enable_exit_intent'        => empty( $settings['enable_exit_intent'] ) ? 0 : 1,
			'enable_mobile'             => empty( $settings['enable_mobile'] ) ? 0 : 1,
			'allowed_referrers'         => (string) $settings['allowed_referrers'],
			'page_target_mode'          => (string) $settings['page_target_mode'],
			'page_ids'                  => (string) $settings['page_ids'],
			'schedule_start'            => (string) $settings['schedule_start'],
			'schedule_end'              => (string) $settings['schedule_end'],
			'priority'                  => 10,
			'opacity'                   => 100,
			'width'                     => 1000,
			'height'                    => 140,
			'status'                    => empty( $settings['enabled'] ) ? 'inactive' : 'active',
		);
	}

	/**
	 * Resolves campaign for request by id or priority.
	 *
	 * @param int $campaign_id Optional campaign ID override.
	 * @return array<string,mixed>
	 */
	public static function resolve_campaign( $campaign_id = 0 ) {
		$campaign_post = null;

		if ( $campaign_id > 0 ) {
			$maybe = get_post( $campaign_id );
			if ( $maybe && self::POST_TYPE === $maybe->post_type ) {
				$campaign_post = $maybe;
			}
		}

		if ( ! $campaign_post ) {
			$query = new WP_Query(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 10,
					'meta_key'       => self::meta_key( 'priority' ),
					'orderby'        => 'meta_value_num',
					'order'          => 'DESC',
				)
			);

			foreach ( $query->posts as $post ) {
				$status = get_post_meta( $post->ID, self::meta_key( 'status' ), true );
				if ( ! $status ) {
					$status = 'active';
				}
				if ( 'active' !== $status ) {
					continue;
				}
				$campaign_post = $post;
				break;
			}
		}

		if ( ! $campaign_post ) {
			$default_id = absint( get_option( self::DEFAULT_CAMPAIGN_OPTION, 0 ) );
			$default    = $default_id ? get_post( $default_id ) : null;
			if ( $default && self::POST_TYPE === $default->post_type ) {
				$campaign_post = $default;
			}
		}

		if ( ! $campaign_post ) {
			return self::campaign_from_settings( CMLC_Settings::get(), 0 );
		}

		$data = self::campaign_defaults();
		foreach ( self::META_KEYS as $field => $meta_key ) {
			$value = get_post_meta( $campaign_post->ID, $meta_key, true );
			if ( '' !== $value && null !== $value ) {
				$data[ $field ] = $value;
			}
		}

		$data['campaign_id'] = (int) $campaign_post->ID;
		$data['title']       = $campaign_post->post_title;
		$data['enabled']     = ( 'active' === $data['status'] ) ? 1 : 0;

		return self::normalize_campaign( $data );
	}

	/**
	 * Provides campaign defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function campaign_defaults() {
		$settings = CMLC_Settings::defaults();

		return array_merge(
			$settings,
			array(
				'campaign_id' => 0,
				'title'       => '',
				'priority'    => 10,
				'opacity'     => 100,
				'width'       => 1000,
				'height'      => 140,
				'status'      => 'active',
			)
		);
	}

	/**
	 * Normalizes campaign scalar and boolean values.
	 *
	 * @param array<string,mixed> $campaign Campaign payload.
	 * @return array<string,mixed>
	 */
	public static function normalize_campaign( $campaign ) {
		$defaults = self::campaign_defaults();
		$data     = wp_parse_args( $campaign, $defaults );

		$data['campaign_id']              = absint( $data['campaign_id'] );
		$data['scroll_trigger_percent']   = max( 0, min( 100, absint( $data['scroll_trigger_percent'] ) ) );
		$data['time_delay_seconds']       = max( 0, absint( $data['time_delay_seconds'] ) );
		$data['repetition_cooldown_hours']= max( 1, absint( $data['repetition_cooldown_hours'] ) );
		$data['max_views']                = max( 1, absint( $data['max_views'] ) );
		$data['enable_exit_intent']       = empty( $data['enable_exit_intent'] ) ? 0 : 1;
		$data['enable_mobile']            = empty( $data['enable_mobile'] ) ? 0 : 1;
		$data['priority']                 = absint( $data['priority'] );
		$data['opacity']                  = max( 0, min( 100, absint( $data['opacity'] ) ) );
		$data['width']                    = max( 300, absint( $data['width'] ) );
		$data['height']                   = max( 80, absint( $data['height'] ) );
		$data['status']                   = in_array( $data['status'], array( 'active', 'inactive' ), true ) ? $data['status'] : 'inactive';
		$data['enabled']                  = empty( $data['enabled'] ) ? 0 : 1;

		$data['headline']         = sanitize_text_field( (string) $data['headline'] );
		$data['body']             = sanitize_text_field( (string) $data['body'] );
		$data['button_text']      = sanitize_text_field( (string) $data['button_text'] );
		$data['bg_color']         = sanitize_hex_color( (string) $data['bg_color'] ) ?: $defaults['bg_color'];
		$data['text_color']       = sanitize_hex_color( (string) $data['text_color'] ) ?: $defaults['text_color'];
		$data['button_color']     = sanitize_hex_color( (string) $data['button_color'] ) ?: $defaults['button_color'];
		$data['button_text_color']= sanitize_hex_color( (string) $data['button_text_color'] ) ?: $defaults['button_text_color'];
		$data['allowed_referrers']= sanitize_text_field( (string) $data['allowed_referrers'] );
		$data['page_target_mode'] = in_array( $data['page_target_mode'], array( 'all', 'include', 'exclude' ), true ) ? $data['page_target_mode'] : 'all';
		$data['page_ids']         = sanitize_text_field( (string) $data['page_ids'] );
		$data['schedule_start']   = sanitize_text_field( (string) $data['schedule_start'] );
		$data['schedule_end']     = sanitize_text_field( (string) $data['schedule_end'] );

		return $data;
	}

	/**
	 * Creates campaign data from settings fallback.
	 *
	 * @param array<string,mixed> $settings Settings data.
	 * @param int                 $campaign_id Campaign id.
	 * @return array<string,mixed>
	 */
	private static function campaign_from_settings( $settings, $campaign_id ) {
		$data                = self::normalize_campaign( self::settings_to_campaign_data( $settings ) );
		$data['campaign_id'] = absint( $campaign_id );
		$data['enabled']     = empty( $settings['enabled'] ) ? 0 : 1;

		return $data;
	}

	/**
	 * Tracks aggregate analytics counters.
	 *
	 * @param int $campaign_id Campaign id.
	 * @param int $impression_delta Impression increment.
	 * @param int $submission_delta Submission increment.
	 * @return void
	 */
	public static function upsert_analytics( $campaign_id, $impression_delta = 0, $submission_delta = 0 ) {
		global $wpdb;

		$campaign_id = absint( $campaign_id );
		if ( $campaign_id <= 0 ) {
			return;
		}

		$table   = self::analytics_table_name();
		$current = $wpdb->get_row( $wpdb->prepare( "SELECT impressions, submissions FROM {$table} WHERE campaign_id = %d", $campaign_id ), ARRAY_A );

		$impressions = absint( $impression_delta );
		$submissions = absint( $submission_delta );
		if ( is_array( $current ) ) {
			$impressions += absint( $current['impressions'] );
			$submissions += absint( $current['submissions'] );
		}

		$wpdb->replace(
			$table,
			array(
				'campaign_id' => $campaign_id,
				'impressions' => $impressions,
				'submissions' => $submissions,
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Saves lead record.
	 *
	 * @param string $email Lead email.
	 * @param int    $campaign_id Campaign id.
	 * @return void
	 */
	public static function insert_lead( $email, $campaign_id ) {
		global $wpdb;

		$campaign_id = absint( $campaign_id );
		if ( ! is_email( $email ) || $campaign_id <= 0 ) {
			return;
		}

		$wpdb->insert(
			self::leads_table_name(),
			array(
				'email'       => $email,
				'campaign_id' => $campaign_id,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s' )
		);
	}

	/**
	 * Fetches dashboard campaign rows with analytics.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function campaign_performance_rows() {
		$campaigns = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$stats = self::analytics_map();
		$rows  = array();
		foreach ( $campaigns as $campaign ) {
			$campaign_id  = (int) $campaign->ID;
			$impressions  = isset( $stats[ $campaign_id ] ) ? absint( $stats[ $campaign_id ]['impressions'] ) : 0;
			$submissions  = isset( $stats[ $campaign_id ] ) ? absint( $stats[ $campaign_id ]['submissions'] ) : 0;
			$conversion   = $impressions > 0 ? round( ( $submissions / $impressions ) * 100, 2 ) : 0;
			$status       = get_post_meta( $campaign_id, self::meta_key( 'status' ), true );
			$status       = 'inactive' === $status ? 'inactive' : 'active';

			$rows[] = array(
				'campaign_id' => $campaign_id,
				'title'       => $campaign->post_title,
				'impressions' => $impressions,
				'submissions' => $submissions,
				'conversion'  => $conversion,
				'status'      => $status,
			);
		}

		usort(
			$rows,
			static function( $a, $b ) {
				if ( $a['conversion'] === $b['conversion'] ) {
					return $b['impressions'] <=> $a['impressions'];
				}
				return $b['conversion'] <=> $a['conversion'];
			}
		);

		return $rows;
	}

	/**
	 * Analytics table map keyed by campaign id.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function analytics_map() {
		global $wpdb;

		$table = self::analytics_table_name();
		$rows  = $wpdb->get_results( "SELECT campaign_id, impressions, submissions FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$map   = array();
		foreach ( (array) $rows as $row ) {
			$map[ absint( $row['campaign_id'] ) ] = array(
				'impressions' => absint( $row['impressions'] ),
				'submissions' => absint( $row['submissions'] ),
			);
		}

		return $map;
	}

	/**
	 * Analytics table name.
	 *
	 * @return string
	 */
	public static function analytics_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'cmlc_analytics';
	}

	/**
	 * Leads table name.
	 *
	 * @return string
	 */
	public static function leads_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'cmlc_leads';
	}
}
