<?php
/**
 * Analytics service, event storage, and lead persistence.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Analytics {
	/**
	 * Cleanup hook name.
	 *
	 * @var string
	 */
	const CLEANUP_HOOK = 'cmlc_cleanup_events';

	/**
	 * Seed migration option key.
	 *
	 * @var string
	 */
	const SEEDED_OPTION_KEY = 'cmlc_analytics_seeded';

	/**
	 * Legacy offset option key.
	 *
	 * @var string
	 */
	const LEGACY_OFFSET_OPTION_KEY = 'cmlc_analytics_legacy_offsets';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup_old_events' ) );
		self::schedule_cleanup();
	}

	/**
	 * Retrieves events table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'cmlc_events';
	}

	/**
	 * Retrieves leads table name.
	 *
	 * @return string
	 */
	public static function leads_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'cmlc_leads';
	}

	/**
	 * Creates/updates analytics schema (events + leads tables).
	 *
	 * @return void
	 */
	public static function install_schema() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$events_table = self::table_name();
		$sql = "CREATE TABLE {$events_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			event_type VARCHAR(20) NOT NULL,
			page_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			referrer_host VARCHAR(191) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY campaign_event (campaign_id, event_type),
			KEY created_at (created_at),
			KEY page_id (page_id),
			KEY referrer_host (referrer_host)
		) {$charset_collate};";

		dbDelta( $sql );

		$leads_table = self::leads_table_name();
		$leads_sql = "CREATE TABLE {$leads_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			source varchar(100) NOT NULL DEFAULT '',
			campaign_id varchar(100) NOT NULL DEFAULT '',
			metadata longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $leads_sql );

		self::maybe_seed_legacy_totals();
	}

	/**
	 * Alias for install_schema for backward compatibility with PR migration code.
	 *
	 * @return void
	 */
	public static function create_tables() {
		self::install_schema();
	}

	/**
	 * Ensures cleanup cron is scheduled.
	 *
	 * @return void
	 */
	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Records an analytics event row.
	 *
	 * @param string              $event_type Event type.
	 * @param array<string,mixed> $args       Event context.
	 * @return bool
	 */
	public static function record_event( $event_type, $args = array() ) {
		global $wpdb;

		$event_type = sanitize_key( (string) $event_type );
		if ( ! in_array( $event_type, array( 'impression', 'submission' ), true ) ) {
			return false;
		}

		$campaign_id   = isset( $args['campaign_id'] ) ? absint( $args['campaign_id'] ) : 1;
		$page_id       = isset( $args['page_id'] ) ? absint( $args['page_id'] ) : 0;
		$referrer_host = isset( $args['referrer_host'] ) ? sanitize_text_field( (string) $args['referrer_host'] ) : self::detect_referrer_host();
		$created_at    = gmdate( 'Y-m-d H:i:s' );

		$result = $wpdb->insert(
			self::table_name(),
			array(
				'campaign_id'   => $campaign_id,
				'event_type'    => $event_type,
				'page_id'       => $page_id,
				'referrer_host' => $referrer_host,
				'created_at'    => $created_at,
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Records an impression (convenience wrapper).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public static function record_impression( $campaign_id ) {
		return self::record_event( 'impression', array( 'campaign_id' => $campaign_id ) );
	}

	/**
	 * Records a submission (convenience wrapper).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool
	 */
	public static function record_submission( $campaign_id ) {
		return self::record_event( 'submission', array( 'campaign_id' => $campaign_id ) );
	}

	/**
	 * Writes lead record.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $email Lead email.
	 * @return int
	 */
	public static function record_lead( $campaign_id, $email ) {
		global $wpdb;

		$wpdb->insert(
			self::leads_table_name(),
			array(
				'campaign_id' => sanitize_text_field( (string) $campaign_id ),
				'email'       => sanitize_email( $email ),
				'source'      => 'analytics',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Retrieves totals and conversion rate.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<string,mixed>
	 */
	public static function get_totals( $args = array() ) {
		global $wpdb;

		$where = self::build_where_clause( $args );
		$rows  = $wpdb->get_results(
			"SELECT event_type, COUNT(*) AS total FROM " . self::table_name() . " {$where} GROUP BY event_type",
			ARRAY_A
		);

		$totals = array(
			'impressions' => 0,
			'submissions' => 0,
		);

		foreach ( $rows as $row ) {
			if ( 'impression' === $row['event_type'] ) {
				$totals['impressions'] = absint( $row['total'] );
			}

			if ( 'submission' === $row['event_type'] ) {
				$totals['submissions'] = absint( $row['total'] );
			}
		}

		$legacy = self::legacy_totals();
		$offset = self::legacy_offsets();

		if ( ! self::is_seeded() ) {
			$totals['impressions'] += $legacy['impressions'];
			$totals['submissions'] += $legacy['submissions'];
		} else {
			$totals['impressions'] += $offset['impressions'];
			$totals['submissions'] += $offset['submissions'];
		}

		$totals['conversion_rate'] = $totals['impressions'] > 0 ? round( ( $totals['submissions'] / $totals['impressions'] ) * 100, 2 ) : 0.0;

		return $totals;
	}

	/**
	 * Returns daily or weekly rollups.
	 *
	 * @param string $interval daily|weekly.
	 * @param int    $lookback_days Number of days in lookback window.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_rollups( $interval = 'daily', $lookback_days = 30 ) {
		global $wpdb;

		$lookback_days = max( 1, absint( $lookback_days ) );
		$start         = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $lookback_days ) );

		$bucket_select = 'DATE(created_at)';
		if ( 'weekly' === $interval ) {
			$bucket_select = "DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY)";
		}

		$sql = $wpdb->prepare(
			"SELECT {$bucket_select} AS bucket,
			SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) AS impressions,
			SUM(CASE WHEN event_type = 'submission' THEN 1 ELSE 0 END) AS submissions
			FROM " . self::table_name() . '
			WHERE created_at >= %s
			GROUP BY bucket
			ORDER BY bucket ASC',
			$start
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $rows as &$row ) {
			$row['impressions']     = absint( $row['impressions'] );
			$row['submissions']     = absint( $row['submissions'] );
			$row['conversion_rate'] = $row['impressions'] > 0 ? round( ( $row['submissions'] / $row['impressions'] ) * 100, 2 ) : 0.0;
		}

		return $rows;
	}

	/**
	 * Returns top pages by event count.
	 *
	 * @param int    $limit Limit.
	 * @param string $event_type Event type filter.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_top_pages( $limit = 10, $event_type = '' ) {
		return self::get_top_dimension( 'page_id', $limit, $event_type );
	}

	/**
	 * Returns top referrer hosts by event count.
	 *
	 * @param int    $limit Limit.
	 * @param string $event_type Event type filter.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_top_referrers( $limit = 10, $event_type = '' ) {
		return self::get_top_dimension( 'referrer_host', $limit, $event_type );
	}

	/**
	 * Returns top campaign IDs by event count.
	 *
	 * @param int    $limit Limit.
	 * @param string $event_type Event type filter.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_top_campaigns( $limit = 10, $event_type = '' ) {
		return self::get_top_dimension( 'campaign_id', $limit, $event_type );
	}

	/**
	 * Fetch campaign performance.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_campaign_performance() {
		global $wpdb;

		if ( ! class_exists( 'CMLC_Campaigns' ) ) {
			return array();
		}

		$campaigns = get_posts(
			array(
				'post_type'      => CMLC_Campaigns::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);

		$rows         = array();
		$events_table = self::table_name();
		$campaign_ids = wp_list_pluck( $campaigns, 'ID' );
		$event_counts = array();

		if ( ! empty( $campaign_ids ) ) {
			$campaign_ids = array_map( 'absint', $campaign_ids );
			$placeholders = implode( ',', array_fill( 0, count( $campaign_ids ), '%d' ) );
			$sql          = $wpdb->prepare(
				"SELECT campaign_id, event_type, COUNT(*) AS event_count FROM {$events_table} WHERE campaign_id IN ({$placeholders}) GROUP BY campaign_id, event_type",
				$campaign_ids
			);

			$counts = $wpdb->get_results( $sql, ARRAY_A );
			foreach ( $counts as $count ) {
				$campaign_id = isset( $count['campaign_id'] ) ? absint( $count['campaign_id'] ) : 0;
				$event_type  = isset( $count['event_type'] ) ? sanitize_key( (string) $count['event_type'] ) : '';
				if ( empty( $campaign_id ) || empty( $event_type ) ) {
					continue;
				}

				if ( empty( $event_counts[ $campaign_id ] ) ) {
					$event_counts[ $campaign_id ] = array();
				}

				$event_counts[ $campaign_id ][ $event_type ] = isset( $count['event_count'] ) ? absint( $count['event_count'] ) : 0;
			}
		}

		foreach ( $campaigns as $campaign_post ) {
			$campaign = CMLC_Campaigns::get_campaign( $campaign_post->ID );
			if ( ! $campaign ) {
				continue;
			}
			$campaign_id = (int) $campaign_post->ID;

			$impressions = (int) $campaign['baseline_impressions'];
			$submissions = (int) $campaign['baseline_submissions'];
			$impressions += isset( $event_counts[ $campaign_id ]['impression'] ) ? absint( $event_counts[ $campaign_id ]['impression'] ) : 0;
			$submissions += isset( $event_counts[ $campaign_id ]['submission'] ) ? absint( $event_counts[ $campaign_id ]['submission'] ) : 0;

			$rows[] = array(
				'campaign_id' => $campaign_id,
				'title'       => get_the_title( $campaign_id ),
				'status'      => $campaign['status'],
				'impressions' => $impressions,
				'submissions' => $submissions,
				'conversion'  => $impressions > 0 ? round( ( $submissions / $impressions ) * 100, 2 ) : 0,
			);
		}

		usort(
			$rows,
			function ( $left, $right ) {
				return $right['conversion'] <=> $left['conversion'];
			}
		);

		return $rows;
	}

	/**
	 * Deletes old events based on retention settings.
	 *
	 * @return void
	 */
	public static function cleanup_old_events() {
		global $wpdb;

		$settings       = CMLC_Settings::get();
		$retention_days = isset( $settings['analytics_retention_days'] ) ? absint( $settings['analytics_retention_days'] ) : 180;
		$retention_days = in_array( $retention_days, array( 90, 180, 365 ), true ) ? $retention_days : 180;
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $retention_days ) );

		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table_name() . ' WHERE created_at < %s', $cutoff ) );
	}

	/**
	 * Retrieves legacy counters from settings.
	 *
	 * @return array<string,int>
	 */
	public static function legacy_totals() {
		$settings = CMLC_Settings::get();

		return array(
			'impressions' => isset( $settings['analytics_impressions'] ) ? absint( $settings['analytics_impressions'] ) : 0,
			'submissions' => isset( $settings['analytics_submissions'] ) ? absint( $settings['analytics_submissions'] ) : 0,
		);
	}

	/**
	 * Seeds event table from legacy total counters one-time.
	 *
	 * @return void
	 */
	private static function maybe_seed_legacy_totals() {
		if ( self::is_seeded() ) {
			return;
		}

		update_option( self::LEGACY_OFFSET_OPTION_KEY, self::legacy_totals(), false );
		update_option( self::SEEDED_OPTION_KEY, 1, false );
	}

	/**
	 * Returns one-time legacy offsets.
	 *
	 * @return array<string,int>
	 */
	private static function legacy_offsets() {
		$offset = get_option( self::LEGACY_OFFSET_OPTION_KEY, array() );

		return array(
			'impressions' => isset( $offset['impressions'] ) ? absint( $offset['impressions'] ) : 0,
			'submissions' => isset( $offset['submissions'] ) ? absint( $offset['submissions'] ) : 0,
		);
	}

	/**
	 * Returns whether migration seed has completed.
	 *
	 * @return bool
	 */
	private static function is_seeded() {
		return (bool) get_option( self::SEEDED_OPTION_KEY, false );
	}

	/**
	 * Builds where clause for event queries.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return string
	 */
	private static function build_where_clause( $args ) {
		global $wpdb;

		$clauses = array( '1=1' );

		if ( ! empty( $args['campaign_id'] ) ) {
			$clauses[] = $wpdb->prepare( 'campaign_id = %d', absint( $args['campaign_id'] ) );
		}

		if ( ! empty( $args['start_date'] ) ) {
			$clauses[] = $wpdb->prepare( 'created_at >= %s', sanitize_text_field( (string) $args['start_date'] ) );
		}

		if ( ! empty( $args['end_date'] ) ) {
			$clauses[] = $wpdb->prepare( 'created_at <= %s', sanitize_text_field( (string) $args['end_date'] ) );
		}

		return 'WHERE ' . implode( ' AND ', $clauses );
	}

	/**
	 * Gets top values for a given dimension.
	 *
	 * @param string $dimension Column.
	 * @param int    $limit Limit.
	 * @param string $event_type Event type filter.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_top_dimension( $dimension, $limit, $event_type ) {
		global $wpdb;

		$allowed_dimensions = array( 'page_id', 'referrer_host', 'campaign_id' );
		if ( ! in_array( $dimension, $allowed_dimensions, true ) ) {
			return array();
		}

		$limit  = max( 1, absint( $limit ) );
		$where  = 'WHERE ' . $dimension . " <> ''";
		$params = array();

		if ( in_array( $dimension, array( 'page_id', 'campaign_id' ), true ) ) {
			$where = 'WHERE ' . $dimension . ' > 0';
		}

		$event_type = sanitize_key( (string) $event_type );
		if ( in_array( $event_type, array( 'impression', 'submission' ), true ) ) {
			$where   .= ' AND event_type = %s';
			$params[] = $event_type;
		}

		$params[] = $limit;

		$sql = "SELECT {$dimension} AS label, COUNT(*) AS total FROM " . self::table_name() . " {$where} GROUP BY {$dimension} ORDER BY total DESC LIMIT %d";
		$sql = $wpdb->prepare( $sql, $params );

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Detects HTTP referrer host.
	 *
	 * @return string
	 */
	private static function detect_referrer_host() {
		$referrer = wp_get_referer();
		if ( empty( $referrer ) ) {
			return '';
		}

		$host = wp_parse_url( $referrer, PHP_URL_HOST );

		return is_string( $host ) ? sanitize_text_field( $host ) : '';
	}
}
