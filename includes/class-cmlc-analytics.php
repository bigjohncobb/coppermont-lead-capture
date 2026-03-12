<?php
/**
 * Analytics storage and reporting service.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Analytics {
	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const CLEANUP_HOOK = 'cmlc_cleanup_events';
	const SCHEMA_VERSION = '1.0.0';

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Registers analytics hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup_old_events' ) );
	}

	/**
	 * Returns full analytics event table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'cmlc_events';
	}

	/**
	 * Activation routine.
	 *
	 * @return void
	 */
	public static function activate() {
		self::maybe_upgrade();
		self::maybe_migrate_legacy_counters();
		self::ensure_cleanup_schedule();
	}

	/**
	 * Deactivation routine.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$next = wp_next_scheduled( self::CLEANUP_HOOK );
		if ( $next ) {
			wp_unschedule_event( $next, self::CLEANUP_HOOK );
		}
	}


	/**
	 * Runs schema upgrades when needed.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = (string) get_option( 'cmlc_analytics_schema_version', '' );
		if ( self::SCHEMA_VERSION !== $installed ) {
			self::create_tables();
			update_option( 'cmlc_analytics_schema_version', self::SCHEMA_VERSION, false );
		}
	}

	/**
	 * Ensures analytics table exists.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event_type varchar(32) NOT NULL,
			page_id bigint(20) unsigned NOT NULL DEFAULT 0,
			referrer_host varchar(191) NOT NULL DEFAULT '',
			event_count int(10) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type_created_at (event_type, created_at),
			KEY campaign_id (campaign_id),
			KEY page_id (page_id),
			KEY referrer_host (referrer_host)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Schedules daily cleanup.
	 *
	 * @return void
	 */
	public static function ensure_cleanup_schedule() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Writes an analytics event.
	 *
	 * @param string $event_type Event type.
	 * @param array<string,mixed> $context Event context.
	 * @return bool
	 */
	public function record_event( $event_type, $context = array() ) {
		global $wpdb;

		$allowed_types = array( 'impression', 'submission' );
		if ( ! in_array( $event_type, $allowed_types, true ) ) {
			return false;
		}

		$campaign_id   = isset( $context['campaign_id'] ) ? absint( $context['campaign_id'] ) : 0;
		$page_id       = isset( $context['page_id'] ) ? absint( $context['page_id'] ) : 0;
		$referrer_host = isset( $context['referrer_host'] ) ? sanitize_text_field( (string) $context['referrer_host'] ) : '';
		$event_count   = isset( $context['event_count'] ) ? max( 1, absint( $context['event_count'] ) ) : 1;

		$result = $wpdb->insert(
			self::table_name(),
			array(
				'campaign_id'   => $campaign_id,
				'event_type'    => $event_type,
				'page_id'       => $page_id,
				'referrer_host' => substr( $referrer_host, 0, 191 ),
				'event_count'   => $event_count,
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Returns totals and conversion.
	 *
	 * @param int $days Optional day lookback.
	 * @return array<string,float|int>
	 */
	public function get_totals( $days = 0 ) {
		global $wpdb;

		$where_sql = '';
		$params    = array();
		if ( $days > 0 ) {
			$where_sql = 'WHERE created_at >= %s';
			$params[]  = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * absint( $days ) ) );
		}

		$query = "SELECT event_type, COALESCE(SUM(event_count), 0) AS total FROM " . self::table_name() . " {$where_sql} GROUP BY event_type";
		$rows  = empty( $params ) ? $wpdb->get_results( $query, ARRAY_A ) : $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );

		$impressions = 0;
		$submissions = 0;
		foreach ( $rows as $row ) {
			if ( 'impression' === $row['event_type'] ) {
				$impressions = (int) $row['total'];
			}
			if ( 'submission' === $row['event_type'] ) {
				$submissions = (int) $row['total'];
			}
		}

		$conversion = $impressions > 0 ? round( ( $submissions / $impressions ) * 100, 2 ) : 0;

		return array(
			'impressions'     => $impressions,
			'submissions'     => $submissions,
			'conversion_rate' => $conversion,
		);
	}

	/**
	 * Returns date-based rollups.
	 *
	 * @param string $interval daily|weekly.
	 * @param int    $days Lookback in days.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_rollups( $interval = 'daily', $days = 30 ) {
		global $wpdb;

		$days = max( 1, absint( $days ) );
		$from = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $days ) );
		$bucket_sql = ( 'weekly' === $interval ) ? "YEARWEEK(created_at, 1)" : "DATE(created_at)";

		$query = "SELECT {$bucket_sql} AS bucket, event_type, COALESCE(SUM(event_count), 0) AS total
			FROM " . self::table_name() . '
			WHERE created_at >= %s
			GROUP BY bucket, event_type
			ORDER BY bucket ASC';

		$rows = $wpdb->get_results( $wpdb->prepare( $query, $from ), ARRAY_A );

		$data = array();
		foreach ( $rows as $row ) {
			$bucket = (string) $row['bucket'];
			if ( ! isset( $data[ $bucket ] ) ) {
				$data[ $bucket ] = array(
					'bucket'      => $bucket,
					'impressions' => 0,
					'submissions' => 0,
				);
			}
			if ( 'impression' === $row['event_type'] ) {
				$data[ $bucket ]['impressions'] = (int) $row['total'];
			}
			if ( 'submission' === $row['event_type'] ) {
				$data[ $bucket ]['submissions'] = (int) $row['total'];
			}
		}

		return array_values( $data );
	}

	/**
	 * Returns top dimension values.
	 *
	 * @param string $dimension page_id|referrer_host|campaign_id.
	 * @param int    $limit Number of rows.
	 * @param int    $days Lookback in days.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_top_dimensions( $dimension, $limit = 10, $days = 30 ) {
		global $wpdb;

		$allowed = array( 'page_id', 'referrer_host', 'campaign_id' );
		if ( ! in_array( $dimension, $allowed, true ) ) {
			return array();
		}

		$limit = max( 1, min( 100, absint( $limit ) ) );
		$days  = max( 1, absint( $days ) );
		$from  = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $days ) );

		$query = "SELECT {$dimension} AS label, COALESCE(SUM(event_count), 0) AS total
			FROM " . self::table_name() . '
			WHERE created_at >= %s
			GROUP BY label
			ORDER BY total DESC
			LIMIT %d';

		return $wpdb->get_results( $wpdb->prepare( $query, $from, $limit ), ARRAY_A );
	}

	/**
	 * Returns a full analytics report payload.
	 *
	 * @return array<string,mixed>
	 */
	public function get_report() {
		return array(
			'totals'        => $this->get_totals(),
			'daily_rollups' => $this->get_rollups( 'daily', 30 ),
			'weekly_rollups'=> $this->get_rollups( 'weekly', 90 ),
			'top_pages'     => $this->get_top_dimensions( 'page_id' ),
			'top_referrers' => $this->get_top_dimensions( 'referrer_host' ),
			'top_campaigns' => $this->get_top_dimensions( 'campaign_id' ),
		);
	}

	/**
	 * Deletes analytics data older than retention policy.
	 *
	 * @return int Number of deleted rows.
	 */
	public static function cleanup_old_events() {
		global $wpdb;

		$settings       = CMLC_Settings::get();
		$retention_days = isset( $settings['analytics_retention_days'] ) ? absint( $settings['analytics_retention_days'] ) : 180;
		if ( ! in_array( $retention_days, array( 90, 180, 365 ), true ) ) {
			$retention_days = 180;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $retention_days ) );
		$query  = $wpdb->prepare( 'DELETE FROM ' . self::table_name() . ' WHERE created_at < %s', $cutoff );
		$result = $wpdb->query( $query );

		return is_numeric( $result ) ? (int) $result : 0;
	}

	/**
	 * Seeds analytics table from legacy counters once.
	 *
	 * @return void
	 */
	public static function maybe_migrate_legacy_counters() {
		if ( get_option( 'cmlc_analytics_migrated', false ) ) {
			return;
		}

		$settings    = CMLC_Settings::get();
		$analytics   = new self();
		$impressions = isset( $settings['analytics_impressions'] ) ? absint( $settings['analytics_impressions'] ) : 0;
		$submissions = isset( $settings['analytics_submissions'] ) ? absint( $settings['analytics_submissions'] ) : 0;

		if ( $impressions > 0 ) {
			$analytics->record_event(
				'impression',
				array(
					'referrer_host' => 'legacy-migrated',
					'event_count'   => $impressions,
				)
			);
		}

		if ( $submissions > 0 ) {
			$analytics->record_event(
				'submission',
				array(
					'referrer_host' => 'legacy-migrated',
					'event_count'   => $submissions,
				)
			);
		}

		update_option( 'cmlc_analytics_migrated', 1, false );
	}
}
