<?php
/**
 * Analytics and lead persistence.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Analytics {
	/**
	 * Creates analytics tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$analytics_table = $wpdb->prefix . 'cmlc_analytics';
		$leads_table     = $wpdb->prefix . 'cmlc_leads';

		$analytics_sql = "CREATE TABLE {$analytics_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			event_type varchar(30) NOT NULL,
			page_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY campaign_event (campaign_id, event_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		$leads_sql = "CREATE TABLE {$leads_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			email varchar(190) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY campaign_id (campaign_id),
			KEY email (email)
		) {$charset_collate};";

		dbDelta( $analytics_sql );
		dbDelta( $leads_sql );
	}

	/**
	 * Records an impression.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int
	 */
	public static function record_impression( $campaign_id ) {
		return self::record_event( $campaign_id, 'impression' );
	}

	/**
	 * Records a submission.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int
	 */
	public static function record_submission( $campaign_id ) {
		return self::record_event( $campaign_id, 'submission' );
	}

	/**
	 * Writes analytics row.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $event_type Event type.
	 * @return int
	 */
	private static function record_event( $campaign_id, $event_type ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cmlc_analytics';

		$wpdb->insert(
			$table,
			array(
				'campaign_id' => absint( $campaign_id ),
				'event_type'  => sanitize_key( $event_type ),
				'page_id'     => absint( get_queried_object_id() ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
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
		$table = $wpdb->prefix . 'cmlc_leads';

		$wpdb->insert(
			$table,
			array(
				'campaign_id' => absint( $campaign_id ),
				'email'       => sanitize_email( $email ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch campaign performance.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_campaign_performance() {
		global $wpdb;
		$campaigns = get_posts(
			array(
				'post_type'      => CMLC_Campaigns::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);

		$rows            = array();
		$analytics_table = $wpdb->prefix . 'cmlc_analytics';

		foreach ( $campaigns as $campaign_post ) {
			$campaign = CMLC_Campaigns::get_campaign( $campaign_post->ID );
			if ( ! $campaign ) {
				continue;
			}
			$campaign_id = (int) $campaign_post->ID;
			$counts      = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT event_type, COUNT(*) AS event_count FROM {$analytics_table} WHERE campaign_id = %d GROUP BY event_type",
					$campaign_id
				),
				ARRAY_A
			);

			$impressions = (int) $campaign['baseline_impressions'];
			$submissions = (int) $campaign['baseline_submissions'];
			foreach ( $counts as $count ) {
				if ( 'impression' === $count['event_type'] ) {
					$impressions += (int) $count['event_count'];
				}
				if ( 'submission' === $count['event_type'] ) {
					$submissions += (int) $count['event_count'];
				}
			}

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
}
